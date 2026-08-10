<?php

declare(strict_types=1);

namespace CNICTEST;

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
 * Locks the record/column seam collapsed in RSRMID-2923 and, on top of it, the
 * value-typing seam moved onto the interface in RSRMID-2942.
 *
 * There is exactly one Record (CNIC\Record) and one Column (CNIC\Column); no
 * brand may declare its own. CNR used to own a `CNR\Column extends Column<string>`
 * solely to narrow `getDataByIndex()` to `?string` via a generic template
 * parameter — but `ColumnInterface` is not generic, so that narrowing was
 * erased on every reachable path (getColumn()/getColumns(), declared on
 * ResponseInterface) before a consumer holding the interface ever saw it.
 * RSRMID-2942 replaced it with a native return type declared directly on
 * ColumnInterface/RecordInterface: getStringByIndex()/getStringByKey(). The
 * brand subclass is gone.
 *
 * This is a structural test by necessity, not by preference, for two distinct
 * failure modes, both invisible to any behavioural test:
 *
 * - A brand re-growing its own Record/Column marker (even an empty
 *   pass-through) is behaviour-preserving on the day it lands — the same
 *   reasoning as tests/ResponsePaginationSeamTest.php.
 * - The narrowing sliding back into a `@template`/`@var Column<string>`
 *   docblock generic is *also* behaviour-preserving: every existing runtime
 *   call still returns the same value, because Psalm/PHPStan generics are
 *   erased at runtime. Only reflecting the interface's declared return type
 *   can tell a native `?string` apart from a docblock-only one that a
 *   consumer holding `ColumnInterface`/`RecordInterface` can never see.
 *
 * Deleting or weakening this test is a deliberate act: it re-opens the
 * decision recorded in docs/agents/architecture.md. Revisit it only if a
 * brand genuinely needs record/column behaviour the shared classes cannot
 * express (at which point that brand implements RecordInterface/ColumnInterface
 * directly, per their class docblocks), or if the string-narrowing accessor
 * itself is replaced by some other mechanism.
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
     * No brand may own a Column class either. CNR's used to exist solely to
     * narrow getDataByIndex() to ?string via a generic template parameter that
     * ColumnInterface (not generic) erased before any consumer saw it
     * (RSRMID-2942) — the narrowing now lives as a native return type on
     * ColumnInterface::getStringByIndex(), so the brand subclass has nothing
     * left to exist for.
     */
    public function testNoBrandDeclaresItsOwnColumn(): void
    {
        foreach (self::BRAND_NAMESPACES as $ns) {
            $this->assertClassAbsent(
                $ns . "\\Column",
                "columns are shared as CNIC\\Column (RSRMID-2942); the value type is expressed via "
                . "ColumnInterface::getStringByIndex(), not a per-brand subclass"
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
     * The shared Column must stay instantiable and must own the whole
     * key/data/length/bounds body — both brands build it directly.
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
     * The value-typing seam (RSRMID-2942): ColumnInterface/RecordInterface must
     * each declare a native `?string` return type on their string-narrowing
     * accessor. This is the mechanism that replaced the erased generic —
     * asserting it via reflection is the only way to tell a native return type
     * (visible to every interface-typed consumer) apart from a docblock-only
     * `@return TValue|null`/`@var Column<string>` generic (invisible to them).
     */
    public function testStringNarrowingIsDeclaredNativelyOnTheInterfaces(): void
    {
        $type = (new ReflectionMethod(ColumnInterface::class, "getStringByIndex"))->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame("string", $type->getName());
        $this->assertTrue($type->allowsNull());

        $type = (new ReflectionMethod(RecordInterface::class, "getStringByKey"))->getReturnType();
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
