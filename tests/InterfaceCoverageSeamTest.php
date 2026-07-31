<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\CNR\Column as CNRColumn;
use CNIC\CNR\Logger as CNRLogger;
use CNIC\CNR\Response as CNRResponse;
use CNIC\CNR\ResponseParser as CNRResponseParser;
use CNIC\Column;
use CNIC\ColumnInterface;
use CNIC\EchoSink;
use CNIC\HttpTransport;
use CNIC\IBS\Logger as IBSLogger;
use CNIC\IBS\Response as IBSResponse;
use CNIC\IBS\ResponseParser as IBSResponseParser;
use CNIC\LoggerInterface;
use CNIC\LogSinkInterface;
use CNIC\Record;
use CNIC\RecordInterface;
use CNIC\ResponseInterface;
use CNIC\ResponseParserInterface;
use CNIC\TransportInterface;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use SplFileInfo;

/**
 * Sweeps every concrete class in src/ for a public method that CLAUDE.md's
 * "type-hint against interfaces" directive cannot actually deliver on
 * (RSRMID-2927).
 *
 * Two defect shapes, both silent to an interface-typed consumer:
 *
 * - **Stray** — a public method the class declares that is not part of any
 *   CNIC interface it implements. An interface-typed caller can never reach
 *   it; only a caller narrowed (or downcast) to the concrete class can, which
 *   is exactly what this project tells consumers not to do. `IBS\Response`
 *   carried one of these, `getStatus()`, until RSRMID-2927 removed it.
 * - **Widening** — the class declares MORE parameters for a method than the
 *   interface that names it, e.g. `getColumnKeys(bool $filterPaginationKeys)`
 *   against an interface `getColumnKeys()` with none (the RSRMID-2918 defect,
 *   fixed in commit 4b3ff7b by widening the interface to match). PHP allows
 *   this silently — an added *optional* parameter is legal widening under
 *   LSP — so nothing short of comparing both signatures catches it.
 *
 * Both are behaviour-preserving on the day they land: every existing test
 * calls these methods on the *concrete* class and passes either way, so no
 * functional test can see them arrive — the same reasoning as
 * {@see ResponsePaginationSeamTest}. Reflection comparing the implementation
 * against the interface is therefore the only instrument that can.
 *
 * Only 7 interfaces are swept as **total contracts** — ones meant to fully
 * describe their implementors: {@see \CNIC\ResponseInterface},
 * {@see \CNIC\RecordInterface}, {@see \CNIC\ColumnInterface},
 * {@see \CNIC\TransportInterface}, {@see \CNIC\ResponseParserInterface},
 * {@see \CNIC\LoggerInterface}, {@see \CNIC\LogSinkInterface}.
 * {@see \CNIC\ExtendedResponseInterface} and
 * {@see \CNIC\RoleCredentialsInterface} are deliberately excluded from that
 * role — they are additive capability interfaces (CLAUDE.md, "Core vs.
 * extended Response contract"), not meant to describe everything their
 * implementor exposes. Treating either as total would flood this test with
 * false positives: `CNR\Client`/`CNR\SessionClient` implement
 * `RoleCredentialsInterface` and legitimately expose ~20 further public
 * methods that interface was never meant to cover. Both still count on the
 * right-hand side, as *declaring* interfaces contributing method signatures
 * to compare against — otherwise `CNR\Response`'s 5 `ExtendedResponseInterface`
 * methods would be reported as stray, which is wrong the other way.
 *
 * Classes are discovered by deriving a FQCN from each PSR-4 file path under
 * src/ (`src/IBS/Response.php` -> `CNIC\IBS\Response`), never by regexing
 * source text for the word "class" — docblock prose contains that word too
 * and a text search over it produces garbage symbols. Abstract classes and
 * interfaces are skipped (an abstract class legitimately leaves capabilities
 * unimplemented), and so is `__construct` — already guarded, for
 * `ResponseInterface`, by
 * {@see ResponseInterfaceConsumerTest::testTheInterfaceDeclaresNoConstructor()},
 * and none of the other 6 total interfaces declares one either, so there is
 * nothing left for this sweep to add there.
 *
 * The allow-list below is intentionally empty. After RSRMID-2927 removed
 * `IBS\Response::getStatus()` the sweep is green across all 7 contracts with
 * zero exceptions — keep it that way rather than adding an exception "just in
 * case"; a stray or widened method found here is a defect to fix, not a
 * reason to grow the list.
 *
 * Revisit this guard only if a genuinely new "total" interface is introduced
 * (add it to {@see self::TOTAL_INTERFACES}) or if one of the current 7 stops
 * being meant to fully describe its implementors (move it to the excluded
 * set alongside `ExtendedResponseInterface`/`RoleCredentialsInterface`, with
 * the same justification these carry).
 *
 * **A second, narrower guard protects the sweep's own discovery.** The single
 * assertion above lives inside a loop over
 * {@see self::concreteClassesImplementingATotalInterface()}; if that ever
 * returned an empty array — an autoload/Composer regression, a moved `src/`,
 * a changed PSR-4 prefix, a botched directory rename — `$failures` would
 * stay `[]` and the sweep would pass having examined nothing. This is the
 * "vacuous branch pin" trap CONTRIBUTING.md warns about, and it is exactly
 * what the two source mutations proved during development could NOT catch:
 * both changed the *subject* under sweep, not the sweep's own ability to
 * find a subject at all.
 * {@see self::testTheSweepActuallyExaminesTheKnownImplementors()} closes it
 * by pinning that discovery still finds a fixed, independently-verified set
 * of 11 real implementors (see {@see self::KNOWN_TOTAL_IMPLEMENTORS}) and
 * still walks a plausible number of files under `src/`. It asserts
 * **containment**, not equality — {@see self::KNOWN_TOTAL_IMPLEMENTORS} is a
 * floor, not a snapshot — so a newly added brand class is swept
 * automatically without failing this test the day it lands; only *discovery
 * finding fewer than it should* is a failure. Do not "tighten" either
 * assertion to exact equality: that would make this guard fail on every
 * ordinary new class instead of only on real discovery breakage, which is
 * the opposite of what it exists to catch.
 *
 * The autoload swallowing in {@see self::concreteClassesImplementingATotalInterface()}
 * is narrowed for the same reason: a PSR-4 path under `src/` that resolves to
 * neither a class nor an interface/trait/enum is not "not a class to skip
 * past" — it is discovery failing on a file that should have been
 * autoloadable, and is now a hard failure (`RuntimeException`) rather than a
 * silent `continue`.
 */
final class InterfaceCoverageSeamTest extends TestCase
{
    /**
     * "Total contract" interfaces: each is meant to fully describe every
     * public capability of every class implementing it. Deliberately does
     * NOT include {@see \CNIC\ExtendedResponseInterface} or
     * {@see \CNIC\RoleCredentialsInterface} — see the class docblock.
     * @var class-string[]
     */
    private const array TOTAL_INTERFACES = [
        ResponseInterface::class,
        RecordInterface::class,
        ColumnInterface::class,
        TransportInterface::class,
        ResponseParserInterface::class,
        LoggerInterface::class,
        LogSinkInterface::class,
    ];

    /**
     * Methods excluded from the sweep on grounds stated in the class
     * docblock: construction is not part of what an interface describes, and
     * is separately guarded where a total interface is at risk of declaring
     * one.
     * @var string[]
     */
    private const array EXCLUDED_METHODS = ["__construct"];

    /**
     * A floor, not a snapshot, of what discovery must find — see the class
     * docblock's non-vacuity note. Measured directly off `src/` at the time
     * this guard was written (RSRMID-2927): every concrete class in the tree
     * implementing one of {@see self::TOTAL_INTERFACES}. A future class
     * being added to this set does NOT need to be added here; this list is
     * a "must still contain at least these" pin, checked with containment,
     * never with strict equality.
     * @var class-string[]
     */
    private const array KNOWN_TOTAL_IMPLEMENTORS = [
        CNRColumn::class,
        CNRLogger::class,
        CNRResponse::class,
        CNRResponseParser::class,
        Column::class,
        EchoSink::class,
        HttpTransport::class,
        IBSLogger::class,
        IBSResponse::class,
        IBSResponseParser::class,
        Record::class,
    ];

    /**
     * Every public method of every concrete class implementing a "total"
     * interface must be reachable through some CNIC interface that class
     * implements, with no more parameters than that interface declares.
     */
    public function testNoConcreteClassExposesAPublicMethodUnreachableThroughItsInterfaces(): void
    {
        $failures = [];

        foreach (self::concreteClassesImplementingATotalInterface() as $class) {
            $rc = new ReflectionClass($class);

            // Every method declared on any CNIC interface this class
            // implements (including additive ones — see class docblock).
            $declaredByInterface = [];
            foreach ($rc->getInterfaceNames() as $interfaceName) {
                if (!str_starts_with($interfaceName, "CNIC\\")) {
                    continue;
                }
                foreach ((new ReflectionClass($interfaceName))->getMethods() as $im) {
                    $declaredByInterface[$im->getName()] = $im;
                }
            }

            foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if (in_array($m->getName(), self::EXCLUDED_METHODS, true)) {
                    continue;
                }

                if (!isset($declaredByInterface[$m->getName()])) {
                    $failures[] = sprintf(
                        "%s::%s() is public but declared on none of the CNIC interfaces %s implements. "
                        . "CLAUDE.md mandates typing against interfaces, not concrete classes, so this method "
                        . "is unreachable to exactly the consumers the project tells to depend on it. Either "
                        . "declare it on the relevant interface, or make it non-public/remove it.",
                        $class,
                        $m->getName(),
                        $class
                    );
                    continue;
                }

                $im = $declaredByInterface[$m->getName()];
                if ($m->getNumberOfParameters() !== $im->getNumberOfParameters()) {
                    $failures[] = sprintf(
                        "%s::%s() declares %d parameter(s) but %s::%s() declares %d. An implementation "
                        . "widening its parameter count silently outruns its interface (PHP permits an extra "
                        . "optional parameter as legal widening), so an interface-typed consumer can never "
                        . "reach the extra parameter(s) that %s exposes.",
                        $class,
                        $m->getName(),
                        $m->getNumberOfParameters(),
                        $im->getDeclaringClass()->getName(),
                        $m->getName(),
                        $im->getNumberOfParameters(),
                        $class
                    );
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Pins that discovery is actually finding something, closing the
     * vacuity hole described in the class docblock: the sweep above proves
     * nothing if {@see self::concreteClassesImplementingATotalInterface()}
     * ever returns an empty (or merely too-small) list, because an empty
     * `foreach` leaves `$failures` at `[]` and the sweep passes having
     * examined zero classes.
     *
     * Both assertions are deliberately **containment/floor** checks, not
     * exact equality — see {@see self::KNOWN_TOTAL_IMPLEMENTORS} and the
     * class docblock for why: a newly added brand class must be swept
     * automatically, not make this test start failing.
     */
    public function testTheSweepActuallyExaminesTheKnownImplementors(): void
    {
        $found = self::concreteClassesImplementingATotalInterface();

        $missing = array_diff(self::KNOWN_TOTAL_IMPLEMENTORS, $found);
        $this->assertEmpty(
            $missing,
            "Discovery no longer finds: " . implode(", ", $missing) . ". If this is not a real "
            . "discovery regression (autoload/PSR-4/moved src/), it means one of these classes "
            . "genuinely stopped implementing a total interface — update "
            . "self::KNOWN_TOTAL_IMPLEMENTORS deliberately rather than letting this fail silently."
        );

        // A floor, not the exact current count (48): a new file added under
        // src/ must not fail this test, only a discovery walk that finds
        // implausibly few (or none) should.
        $this->assertGreaterThanOrEqual(
            40,
            count(self::classesUnderSrc()),
            "classesUnderSrc() found implausibly few files under src/ — discovery is likely broken "
            . "(wrong directory, empty iterator, ...) rather than src/ having genuinely shrunk this far."
        );
    }

    /**
     * Every non-abstract class under src/ that implements at least one of
     * {@see self::TOTAL_INTERFACES}, as fully-qualified class names.
     * @return class-string[]
     */
    private static function concreteClassesImplementingATotalInterface(): array
    {
        $classes = [];
        foreach (self::classesUnderSrc() as $fqcn) {
            if (!class_exists($fqcn)) {
                if (interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn)) {
                    // Legitimately not a class — e.g. ResponseInterface,
                    // CNR\SessionCapable (a trait), System (an enum).
                    continue;
                }
                // A PSR-4 path under src/ that resolves to none of
                // class/interface/trait/enum is discovery breaking — an
                // autoload/Composer regression, a moved src/, a changed
                // PSR-4 prefix — not a shape to skip past. Swallowing this
                // as "just skip it" is exactly how the sweep degrades to
                // silently green; see the class docblock's non-vacuity note
                // and testTheSweepActuallyExaminesTheKnownImplementors().
                throw new RuntimeException(
                    "{$fqcn}, derived from a PSR-4 path under src/, does not exist as a class, interface, "
                    . "trait, or enum. Discovery is broken (autoload regression, moved src/, changed PSR-4 "
                    . "prefix, ...) rather than this genuinely being \"not a class\"."
                );
            }
            $rc = new ReflectionClass($fqcn);
            if ($rc->isAbstract() || $rc->isInterface()) {
                continue;
            }
            if (array_intersect($rc->getInterfaceNames(), self::TOTAL_INTERFACES) === []) {
                continue;
            }
            $classes[] = $fqcn;
        }
        return $classes;
    }

    /**
     * Derive a FQCN from every PHP file's path under src/, per PSR-4
     * (`src/IBS/Response.php` -> `CNIC\IBS\Response`).
     *
     * Deliberately not a regex over source text for the word "class": this
     * project's own docblocks contain that word in prose (see this file),
     * so a text search produces false symbols. Reading the PSR-4 mapping off
     * the file path is exact.
     * @return class-string[]
     */
    private static function classesUnderSrc(): array
    {
        $srcDir = dirname(__DIR__) . "/src";
        $fqcns = [];
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            assert($file instanceof SplFileInfo);
            if ($file->getExtension() !== "php") {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($srcDir) + 1, -4);
            /** @var class-string $fqcn */
            $fqcn = "CNIC\\" . str_replace(DIRECTORY_SEPARATOR, "\\", $relative);
            $fqcns[] = $fqcn;
        }
        return $fqcns;
    }
}
