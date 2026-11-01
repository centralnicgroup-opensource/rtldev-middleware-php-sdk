<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponse;
use CNIC\CNR\Response as CNRResponse;
use CNIC\Exception\DuplicateColumnException;
use CNIC\IBS\Response as IBSResponse;
use CNIC\RecordInterface;
use CNIC\ResponseInterface;
use CNICTEST\Support\ColumnRegistrar;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Locks the "a Response is sealed once constructed" decision (RSRMID-2939).
 *
 * **Directive.** A Response is fully assembled by its constructor and read-only
 * afterwards. `ResponseInterface` declares no mutator and no record cursor; rows
 * are walked with `foreach` via {@see \IteratorAggregate}. Six methods came off
 * the interface in v31 — `addColumn()`, `addRecord()`, `getCurrentRecord()`,
 * `getNextRecord()`, `getPreviousRecord()`, `rewindRecordList()` — and must not
 * come back.
 *
 * **Failure mode prevented.** Every one of them was a rule a caller had to know
 * that no type expressed:
 *
 * - A column added after construction was absent from every record, because
 *   records are assembled from the columns once, at the end of `populate()`. It
 *   showed up in `getColumns()`/`getColumnKeys()` and nowhere else.
 * - A record added after construction changed `getRecordsCount()` and, through
 *   it, the four pagination getters IBS derives from it — so a caller could
 *   silently repaginate a finished response.
 * - The cursor was hidden mutable state shared by every holder of the object:
 *   `getNextRecord()` advanced it, the predicates that would have let a caller
 *   test it without moving it were `protected`, two consumers iterating one
 *   response interfered, and nothing stated that re-iteration needed a
 *   `rewindRecordList()` first.
 *
 * **Why the guard must be structural.** Re-adding any of the six is *additive*:
 * it breaks no existing call, changes no existing assertion, and every
 * functional test in the suite passes on the day it lands — the same argument as
 * {@see ResponsePaginationSeamTest} and {@see ResponseParserSeamTest}. Only
 * reflection over the interface and the implementations can refuse it. The
 * behavioural half below (independent, repeatable iteration; the duplicate-column
 * refusal) pins what a consumer actually gets, but it cannot see a *new* mutator
 * arrive — hence both halves.
 *
 * Non-vacuity was proven during development by applying the mutations this
 * refuses: re-declaring `addColumn()` on `ResponseInterface` (caught by
 * {@see self::testTheInterfaceDeclaresNoMutatorAndNoRecordCursor()}), making
 * `AbstractResponse::addRecord()` public again
 * ({@see self::testNoResponseExposesAPublicMutator()}), dropping
 * `extends \IteratorAggregate`
 * ({@see self::testTheInterfaceIsIterableOverRecords()}), restoring the `??=` in
 * `registerColumn()` ({@see self::testADuplicateColumnNameIsRefused()}) and
 * restoring the append in `assembleRecords()`
 * ({@see self::testAssemblingTwiceDoesNotDoubleTheRecordList()}).
 *
 * **Revisit condition.** Only a concrete, stated need to build a response
 * incrementally *from outside* the brand's `populate()` hook — which today does
 * not exist: responses are born from `AbstractClient::newResponse()` and
 * `AbstractResponseTemplateManager::createResponseFromTemplateId()`, each handing the whole raw
 * payload to a constructor. A substitute {@see ResponseParserInterface} is the
 * supported way to control what a response contains, and it needs no mutator. If
 * that changes, the replacement must make the assembly order part of a type, not
 * a comment — do not simply re-open the setters.
 */
final class ResponseSealSeamTest extends TestCase
{
    /**
     * The mutators and cursor steps removed in v31. Named as data, not prose, so
     * a re-add is a failure rather than a docs discrepancy.
     * @var string[]
     */
    private const array SEALED_AWAY = [
        "addColumn",
        "addRecord",
        "getCurrentRecord",
        "getNextRecord",
        "getPreviousRecord",
        "rewindRecordList",
    ];

    public function testTheInterfaceDeclaresNoMutatorAndNoRecordCursor(): void
    {
        $rc = new ReflectionClass(ResponseInterface::class);

        foreach (self::SEALED_AWAY as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "ResponseInterface must not declare {$method}() — a response is assembled by its "
                . "constructor and read-only afterwards (RSRMID-2939). Read this file's class "
                . "docblock before re-adding it."
            );
        }
    }

    /**
     * Neither the base nor a brand may expose one publicly either.
     *
     * Removing a method from the interface while leaving it public on the
     * implementation would satisfy the test above and change nothing in practice:
     * `CNR\Client::request()` returns the concrete type, so a consumer holding
     * that still reaches a public `addColumn()`. It would also be reported by
     * {@see InterfaceCoverageSeamTest} as a stray method — but only as long as
     * that sweep keeps `ResponseInterface` among its total contracts, so this
     * does not lean on it.
     */
    public function testNoResponseExposesAPublicMutator(): void
    {
        foreach ([AbstractResponse::class, CNRResponse::class, IBSResponse::class] as $class) {
            $rc = new ReflectionClass($class);
            foreach (self::SEALED_AWAY as $method) {
                if (!$rc->hasMethod($method)) {
                    continue;
                }
                $this->assertFalse(
                    $rc->getMethod($method)->isPublic(),
                    "{$class}::{$method}() must not be public. addColumn() is legitimately a protected "
                    . "brand hook called from populate(); a public one re-opens the response after "
                    . "construction (RSRMID-2939)."
                );
            }
        }
    }

    /**
     * `registerColumn()` stays non-public and stays shared.
     *
     * The column bookkeeping is three lists that must agree
     * ($columns/$columnKeys/$columnIndex), so it has exactly one implementation
     * and no consumer-reachable entry point. Pinned here rather than in
     * {@see RecordColumnSeamTest}, which guards the factory *shape* — this is
     * about visibility and ownership.
     *
     * Deliberately **not** claiming to prove sole writership: nothing here can
     * see a *second* writer arrive (a brand assigning `$this->columns[]`
     * directly would pass this untouched). That would need a source scan, which
     * would be a weaker instrument than it looks — the properties are
     * `protected`, so the honest guard is the duplicate-refusal and
     * consistency assertions below, which fail on the *effect* of a second
     * writer regardless of how it was written.
     */
    public function testRegisterColumnStaysNonPublicAndShared(): void
    {
        $m = new ReflectionMethod(AbstractResponse::class, "registerColumn");
        $this->assertFalse($m->isPublic(), "registerColumn() is internal bookkeeping, not consumer surface");
        $this->assertFalse($m->isAbstract(), "the bookkeeping is shared — a brand must not reimplement it");
    }

    public function testTheInterfaceIsIterableOverRecords(): void
    {
        $rc = new ReflectionClass(ResponseInterface::class);

        // Read off getInterfaceNames() rather than asserting
        // isSubclassOf(IteratorAggregate::class): Psalm knows the relationship
        // statically and reports that form as a RedundantCondition, which would
        // have to be suppressed — and a suppression is not a guard. A list of
        // strings is opaque to it, so this assertion stays live.
        $this->assertContains(
            IteratorAggregate::class,
            $rc->getInterfaceNames(),
            "ResponseInterface must extend IteratorAggregate — foreach is what replaced the record "
            . "cursor, and a consumer typed against the interface can only rely on what it declares"
        );

        // Declared on the interface (not merely inherited from IteratorAggregate)
        // so the element type is stated where consumers read it.
        $this->assertSame(
            ResponseInterface::class,
            $rc->getMethod("getIterator")->getDeclaringClass()->getName(),
            "getIterator() must be redeclared on ResponseInterface so its @return carries the "
            . "Traversable<int, RecordInterface> element type"
        );

        $type = (new ReflectionMethod(AbstractResponse::class, "getIterator"))->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame("Traversable", $type->getName());
    }

    /**
     * A consumer holding only the interface can iterate, and what it gets is
     * usable as a RecordInterface.
     *
     * The closure's `RecordInterface` parameter type is the assertion that
     * matters, and it is a static one: both analysers cover tests/, so if the
     * declared element type ever stopped being RecordInterface this would fail
     * `composer lint` rather than the suite. Asserting `instanceof` at runtime
     * instead would be reported as already-narrowed and prove nothing.
     *
     * The row *shapes* are asserted alongside, because they used to be uneven:
     * this fixture reported `status,domain` for row 0 and `domain` for row 1,
     * since the response's status was registered as a one-cell column and
     * therefore landed on the first row only. Metadata is not column data since
     * RSRMID-2965, so every row of one response now carries the same keys.
     */
    public function testAConsumerIteratesRecordsThroughTheInterface(): void
    {
        $keyOf = static fn(RecordInterface $rec): string => implode(",", array_keys($rec->getData()));

        $seen = [];
        foreach (new IBSResponse('{"status":"SUCCESS","domain":["a.com","b.com"]}') as $rec) {
            $seen[] = $keyOf($rec);
        }

        $this->assertSame(["domain", "domain"], $seen);
    }

    /**
     * Iterating does not consume: a second walk starts from the top, and one
     * iteration is invisible to another over the same response.
     */
    public function testIterationIsIndependentAndRepeatable(): void
    {
        $r = new IBSResponse('{"status":"SUCCESS","domain":["a.com","b.com","c.com"]}');

        $first = $this->domainsOf($r);
        $this->assertSame(["a.com", "b.com", "c.com"], $first);
        $this->assertSame($first, $this->domainsOf($r), "a second walk must not need a rewind");

        // Interleaved: an inner walk must not move an outer one along.
        $seen = [];
        foreach ($r as $outer) {
            $this->domainsOf($r);
            /** @psalm-suppress MixedAssignment getDataByKey() returns mixed by design — IBS records hold arbitrary JSON values */
            $seen[] = $outer->getDataByKey("domain");
        }
        $this->assertSame(["a.com", "b.com", "c.com"], $seen);
    }

    /**
     * A repeated column name is refused, not half-registered.
     *
     * Exercised through a subclass, because a *substitute parser* cannot reach it:
     * both brands derive their column names from `array_keys()` of the parsed
     * hash, and two distinct PHP array keys can never `strval()` to the same
     * string ("1" is normalised to int 1 on the way in). So the reachable route is
     * a brand — real or substituted — whose `populate()` registers a name twice,
     * which is what this stands in for.
     *
     * The defect it replaces: `$columns`/`$columnKeys` appended unconditionally
     * while `$columnIndex` kept only the first position via `??=`, so
     * `getColumns()` held a column `getColumn()` could never return and
     * `getColumnKeys()` listed the name twice.
     */
    public function testADuplicateColumnNameIsRefused(): void
    {
        $r = $this->registrar();

        $this->expectException(DuplicateColumnException::class);
        $this->expectExceptionMessage('Column "domain" is already registered');

        $r->register("domain", ["a.com"]);
        $r->register("domain", ["b.com"]);
    }

    /**
     * The refusal is what keeps the three column lists in step: after it, the
     * response still describes exactly the one column that did register.
     */
    public function testTheRefusalLeavesTheColumnListsConsistent(): void
    {
        $r = $this->registrar();
        $r->register("domain", ["a.com"]);

        try {
            $r->register("domain", ["b.com"]);
            $this->fail("a duplicate column name must be refused");
        } catch (DuplicateColumnException) {
            // expected — assert the state it protected below
        }

        $this->assertSame(["item", "domain"], $r->getColumnKeys(), "no name may be listed twice");
        $this->assertCount(2, $r->getColumns(), "no column may be present that getColumn() cannot return");
        foreach ($r->getColumnKeys() as $key) {
            $this->assertNotNull($r->getColumn($key), "every listed key must resolve to a column");
        }
    }

    /**
     * `assembleRecords()` replaces rather than appends, so it is idempotent.
     *
     * Exercised through a subclass because no production caller invokes it twice
     * — which is precisely the point: the old append-only version made
     * "assembles the records" conditional on a call count nothing enforced.
     */
    public function testAssemblingTwiceDoesNotDoubleTheRecordList(): void
    {
        $r = $this->registrar();

        $this->assertSame(1, $r->getRecordsCount(), "the one-data-column fixture assembles one row");
        $r->assembleAgain();
        $this->assertSame(1, $r->getRecordsCount(), "a second assembly must replace the rows, not append them");
    }

    /**
     * The "domain" cell of every record, in iteration order.
     * @return mixed[]
     */
    private function domainsOf(ResponseInterface $resp): array
    {
        $out = [];
        foreach ($resp as $rec) {
            /** @psalm-suppress MixedAssignment getDataByKey() returns mixed by design — IBS records hold arbitrary JSON values */
            $out[] = $rec->getDataByKey("domain");
        }
        return $out;
    }

    /**
     * An IBS response exposing the two protected hooks this file needs to drive.
     *
     * A subclass rather than reflection: these are the seams a brand legitimately
     * uses, so calling them the way a brand does is the honest exercise.
     *
     * The fixture carries exactly one data column ("item") and one record. It
     * used to be status-only and rely on the status becoming that column; since
     * RSRMID-2965 response metadata is not column data, so a status-only payload
     * would assemble no columns and no records at all — and "domain" is avoided
     * as the data key so the duplicate-name tests below can register it
     * themselves.
     */
    private function registrar(): IBSResponse&ColumnRegistrar
    {
        return new class ('{"status":"SUCCESS","item":["a"]}') extends IBSResponse implements ColumnRegistrar {
            /**
             * @param array<array-key, mixed> $data
             */
            #[\Override]
            public function register(string $columnName, array $data): void
            {
                $this->addColumn($columnName, $data);
            }

            #[\Override]
            public function assembleAgain(): void
            {
                $this->assembleRecords();
            }
        };
    }
}
