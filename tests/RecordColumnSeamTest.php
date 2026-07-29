<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CNR\Column as CNRColumn;
use CNIC\CNR\Response as CNRResponse;
use CNIC\Column;
use CNIC\ColumnInterface;
use CNIC\IBS\Response as IBSResponse;
use CNIC\Record;
use CNIC\RecordInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Locks the record/column seam collapsed in RSRMID-2923.
 *
 * There is exactly one Record (CNIC\Record) and one Column (CNIC\Column). The
 * brand `Record` classes were byte-identical empty subclasses and are gone; the
 * brand `Column` classes duplicated ~35 lines of the same field, constructor and
 * accessors, and only CNR\Column survives — solely to bind the value type to
 * string and narrow getDataByIndex() to ?string.
 *
 * This is a structural test by necessity, not by preference: re-adding an empty
 * `CNR\Record`/`IBS\Record`/`IBS\Column` marker, or re-inlining the shared
 * column body into a brand, is behaviour-preserving on the day it lands, so no
 * behavioural test can detect the erosion — only reflection can. The same
 * reasoning as tests/ResponsePaginationSeamTest.php.
 *
 * Deleting or weakening this test is a deliberate act: it re-opens the decision
 * recorded in docs/agents/architecture.md.
 */
final class RecordColumnSeamTest extends TestCase
{
    /**
     * Brand namespaces that must not re-grow a Record or Column of their own.
     * MONIKER is included because it reuses IBS's Response/Record wholesale.
     * @var string[]
     */
    private const array BRAND_NAMESPACES = ["CNIC\\CNR", "CNIC\\IBS", "CNIC\\MONIKER"];

    /**
     * No brand may own a Record class: record data has one shape across brands,
     * so a brand Record could only ever be an empty pass-through again.
     */
    public function testNoBrandDeclaresItsOwnRecord(): void
    {
        foreach (self::BRAND_NAMESPACES as $ns) {
            $this->assertClassAbsent(
                $ns . "\\Record",
                "records are shared as CNIC\\Record (RSRMID-2923); an empty brand marker carries no behaviour"
            );
        }
    }

    /**
     * The shared Record must stay instantiable — the point of the collapse was
     * that no abstract base plus empty leaf is needed to build a row.
     */
    public function testSharedRecordIsConcrete(): void
    {
        $this->assertFalse((new ReflectionClass(Record::class))->isAbstract());
        $this->assertClassAbsent("CNIC\\AbstractRecord", "it was folded into the concrete CNIC\\Record");
    }

    /**
     * Both brands' newRecord() hooks must resolve to the shared Record. The hook
     * itself deliberately stays a per-brand declaration (it is the seam a future
     * brand with genuinely different row behaviour would use), so what is
     * asserted is the return type, not the absence of the hook.
     */
    public function testBothBrandsBuildTheSharedRecord(): void
    {
        foreach ([CNRResponse::class, IBSResponse::class] as $brand) {
            $method = new ReflectionMethod($brand, "newRecord");
            $this->assertSame(
                $brand,
                $method->getDeclaringClass()->getName(),
                "{$brand}::newRecord() must stay the brand's own hook"
            );
            $type = $method->getReturnType();
            $this->assertInstanceOf(ReflectionNamedType::class, $type);
            $this->assertSame(
                Record::class,
                $type->getName(),
                "{$brand}::newRecord() must return the shared CNIC\\Record"
            );
        }
    }

    /**
     * IBS/Moniker carry arbitrary JSON values and need no narrowing, so they use
     * the shared Column directly; a brand Column there would be a pass-through.
     */
    public function testOnlyCnrDeclaresAColumn(): void
    {
        $this->assertClassAbsent("CNIC\\IBS\\Column", "IBS uses CNIC\\Column as-is (RSRMID-2923)");
        $this->assertClassAbsent(
            "CNIC\\MONIKER\\Column",
            "MONIKER reuses IBS's response layer and must not own a Column"
        );
        $this->assertTrue(class_exists(CNRColumn::class));
    }

    /**
     * The shared Column must stay instantiable (IBS builds it directly) and must
     * own the whole key/data/length/bounds body.
     */
    public function testSharedColumnOwnsTheSharedBody(): void
    {
        $this->assertFalse((new ReflectionClass(Column::class))->isAbstract());
        foreach (["getKey", "getData", "hasDataIndex"] as $method) {
            $declaring = (new ReflectionMethod(Column::class, $method))->getDeclaringClass()->getName();
            $this->assertSame(
                Column::class,
                $declaring,
                "CNIC\\Column::{$method}() must be owned by the shared column"
            );
        }
    }

    /**
     * CNR\Column earns its existence with exactly one thing: the narrowed
     * return type. Anything else declared there is duplication creeping back.
     */
    public function testCnrColumnNarrowsNothingButGetDataByIndex(): void
    {
        $declared = array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            array_filter(
                (new ReflectionClass(CNRColumn::class))->getMethods(),
                static fn(ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === CNRColumn::class
            )
        );
        $this->assertSame(
            ["getDataByIndex"],
            array_values($declared),
            "CNR\\Column exists only to narrow getDataByIndex() to ?string; everything else is shared"
        );

        $type = (new ReflectionMethod(CNRColumn::class, "getDataByIndex"))->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame("string", $type->getName());
        $this->assertTrue($type->allowsNull());
    }

    /**
     * Neither interface in this layer may declare a constructor.
     *
     * Same call as ResponseInterface in RSRMID-2918, extended to Column/Record
     * in RSRMID-2923: nothing constructs through these types (columns come from
     * a brand's addColumn(), records from its newRecord(), both naming concrete
     * classes), so the declaration constrained every implementer for no caller's
     * benefit — and PHP *does* enforce a constructor declared on an interface,
     * unlike one inherited from a parent class, so the constraint was real. It
     * ruled out any column or record not born from a plain (key, array) pair.
     *
     * Reflection is the only possible guard: re-adding the declaration would be
     * legal PHP, since both built-in implementations happen to match it, so
     * nothing would fail. Note hasMethod() is the right instrument here (these
     * are interfaces, so there is no inherited-constructor confusion to avoid).
     */
    public function testNeitherInterfaceDeclaresAConstructor(): void
    {
        foreach ([ColumnInterface::class, RecordInterface::class] as $interface) {
            $this->assertFalse(
                (new ReflectionClass($interface))->hasMethod("__construct"),
                "{$interface} must not declare __construct() — it describes what the thing can "
                . "be asked, not how it is built (RSRMID-2923)"
            );
        }
    }

    /**
     * Assert a class does not exist, via the autoloader.
     *
     * Routed through a helper on purpose: PHPStan constant-folds
     * class_exists() on a literal name it cannot resolve and fails the build
     * with `function.impossibleType` — "will always evaluate to false" — which
     * is exactly the state this test asserts. Behind a `string` parameter the
     * name is opaque to that folding, so the assertion survives `composer lint`
     * without an ignore. Do not inline these calls back.
     */
    private function assertClassAbsent(string $fqcn, string $because): void
    {
        $this->assertFalse(class_exists($fqcn), "{$fqcn} must not exist — {$because}");
    }
}
