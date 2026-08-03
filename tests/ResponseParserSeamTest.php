<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponse;
use CNIC\AbstractResponseTemplateManager;
use CNIC\CNR\Response as CNRResponse;
use CNIC\CNR\ResponseParser as CNRParser;
use CNIC\CNR\ResponseTemplateManager as CNRTemplates;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\IBS\Response as IBSResponse;
use CNIC\IBS\ResponseParser as IBSParser;
use CNIC\IBS\ResponseTemplateManager as IBSTemplates;
use CNIC\ResponseParserInterface;
use CNICTEST\Support\SpyResponseParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Locks the response-parser injection seam (RSRMID-2924).
 *
 * Two halves, deliberately kept in one file so the trade is visible:
 *
 *  - **Behavioural** — a substitute parser reaches the hash through the public
 *    constructor, with neither reflection nor a subclass. That is the property
 *    the ticket asked for, and it is testable directly.
 *  - **Structural** — the parsers stay instance methods behind the contract.
 *    Making `parse()` static again and hard-wiring `RP::parse()` back into
 *    populate() is behaviour-preserving the day it lands (the production parsers
 *    are pure), so only reflection can refuse it. Same argument as the session,
 *    configuration, IDN and record/column seams.
 */
final class ResponseParserSeamTest extends TestCase
{
    public function testCNRResponseAcceptsASubstituteParser(): void
    {
        $fake = new SpyResponseParser();
        $r = new CNRResponse("[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n", [], [], [], $fake);

        $this->assertSame(999, $r->getCode());
        $this->assertSame("from the substitute", $r->getDescription());
        // the substituted PROPERTY block drives the real column/record assembly
        $this->assertNotNull($r->getColumn("SUBSTITUTE"));
        $this->assertCount(2, $r->getRecords());
    }

    public function testIBSResponseAcceptsASubstituteParser(): void
    {
        $fake = new SpyResponseParser();
        $r = new IBSResponse('{"status":"SUCCESS","transactid":"xyz"}', ["ResponseFormat" => "JSON"], [], [], $fake);

        $this->assertTrue($r->isError());
        $this->assertSame("from the substitute", $r->getDescription());
    }

    public function testTheParserIsHandedTheTranslatedResponseAndTheSanitizedCommand(): void
    {
        // What reaches the parser is the *translated* raw plus the command with
        // sensitive values already masked — parse() sits behind both steps, and a
        // substitute must not be able to read back a password.
        $fake = new SpyResponseParser();
        new IBSResponse('{"status":"SUCCESS"}', ["ResponseFormat" => "JSON", "password" => "secret"], [], [], $fake);

        $this->assertSame('{"status":"SUCCESS"}', $fake->seenRaw);
        $this->assertSame(["ResponseFormat" => "JSON", "password" => "***"], $fake->seenCmd);
    }

    public function testCNRAlsoReceivesTheCommandEvenThoughItsParserIgnoresIt(): void
    {
        // The uniform signature is the whole reason a shared contract is
        // possible; the seam must therefore feed both brands the same way.
        $fake = new SpyResponseParser();
        new CNRResponse("CODE=200\r\nDESCRIPTION=ok\r\n", ["COMMAND" => "StatusAccount", "PASSWORD" => "secret"], [], [], $fake);

        $this->assertSame(["COMMAND" => "StatusAccount", "PASSWORD" => "***"], $fake->seenCmd);
    }

    public function testCNRRejectsANonStringColumnCellFromASubstituteParser(): void
    {
        // CNR columns bind their value type to string, so a parser handing one a
        // number is contradicting the brand. Loud, not skipped or coerced — the
        // policy settled in RSRMID-2919/RSRMID-2920.
        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage("PROPERTY[TOTAL] carries a int");
        new CNRResponse("CODE=200\r\n", [], [], [], self::rogueParser(["TOTAL" => [42]]));
    }

    public function testCNRRejectsAColumnThatIsNotAListAtAll(): void
    {
        // The container is held to the same standard as the cells. Casting it
        // instead — (array)"example.com" — would quietly build a one-cell column
        // out of a bare string while a bare int still threw, i.e. coerce a bad
        // container while refusing a bad cell.
        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage("PROPERTY[DOMAIN] is a string");
        new CNRResponse("CODE=200\r\n", [], [], [], self::rogueParser(["DOMAIN" => "example.com"]));
    }

    public function testCNRStillAcceptsAResponseWithoutAPropertyBlock(): void
    {
        // The strictness above is about the contents of a PROPERTY block that
        // exists. A missing one is ordinary — most CNR responses have none — and
        // must stay a zero-column, zero-record response, not an exception.
        $r = new CNRResponse("CODE=200\r\n", [], [], [], self::rogueParser(null));

        $this->assertSame(200, $r->getCode());
        $this->assertSame([], $r->getColumns());
        $this->assertSame(0, $r->getRecordsCount());
    }

    /**
     * A parser returning a canned CNR hash with the given PROPERTY block
     * (omitted entirely when null).
     * @param array<string, mixed>|null $property
     */
    private static function rogueParser(?array $property): ResponseParserInterface
    {
        return new class ($property) implements ResponseParserInterface {
            /**
             * @param array<string, mixed>|null $property
             */
            public function __construct(private readonly ?array $property)
            {
            }

            /**
             * @param array<string, string> $cmd
             * @return array<string, mixed>
             */
            #[\Override]
            public function parse(string $raw, array $cmd = []): array
            {
                $hash = ["CODE" => "200", "DESCRIPTION" => "ok"];
                if ($this->property !== null) {
                    $hash["PROPERTY"] = $this->property;
                }
                return $hash;
            }
        };
    }

    public function testBothBrandParsersImplementTheContract(): void
    {
        $this->assertInstanceOf(ResponseParserInterface::class, new CNRParser());
        $this->assertInstanceOf(ResponseParserInterface::class, new IBSParser());
    }

    public function testParseIsAnInstanceMethodWithTheUnifiedSignature(): void
    {
        foreach ([CNRParser::class, IBSParser::class, ResponseParserInterface::class] as $class) {
            $m = new ReflectionMethod($class, "parse");
            $this->assertFalse($m->isStatic(), "{$class}::parse() must not be static — a static call cannot be substituted");
            $this->assertTrue($m->isPublic(), "{$class}::parse() must be public");
            $this->assertSame(2, $m->getNumberOfParameters(), "{$class}::parse() must take (raw, cmd)");
            $this->assertTrue(
                $m->getParameters()[1]->isOptional(),
                "{$class}::parse(): the command must stay optional — CNR does not need it"
            );
        }
    }

    public function testEveryResponseNamesItsParserThroughTheFactoryHook(): void
    {
        $hook = new ReflectionMethod(AbstractResponse::class, "newResponseParser");
        $this->assertTrue($hook->isAbstract(), "newResponseParser() must stay an abstract per-brand hook");

        foreach ([CNRResponse::class, IBSResponse::class] as $class) {
            $m = new ReflectionMethod($class, "newResponseParser");
            $this->assertSame($class, $m->getDeclaringClass()->getName(), "{$class} must name its own parser");
        }
    }

    public function testTheParserIsTheOptionalLastConstructorArgument(): void
    {
        $ctor = (new ReflectionClass(AbstractResponse::class))->getConstructor();
        $this->assertNotNull($ctor);
        $this->assertSame(5, $ctor->getNumberOfParameters());
        $this->assertSame(
            1,
            $ctor->getNumberOfRequiredParameters(),
            "injection must not become mandatory — it would break every caller"
        );

        // A named-argument call pins the parameter's *name* and its type without
        // reaching into ReflectionParameter: renaming or retyping it makes this
        // an Error, and skipping $cmd/$placeholders/$context proves it stays last.
        $r = new IBSResponse(raw: '{"status":"SUCCESS"}', parser: new SpyResponseParser());
        $this->assertSame("from the substitute", $r->getDescription());
    }

    public function testTemplateManagersGoThroughTheSameHookAndNotAParseHelper(): void
    {
        $this->assertFalse(
            method_exists(AbstractResponseTemplateManager::class, "parseResponse"),
            "parseResponse() was replaced by the newResponseParser() hook — a second route would drift again"
        );
        foreach ([CNRTemplates::class, IBSTemplates::class] as $class) {
            $m = new ReflectionMethod($class, "newResponseParser");
            $this->assertSame($class, $m->getDeclaringClass()->getName());
            $this->assertInstanceOf(ResponseParserInterface::class, $m->invoke(null));
        }
    }

    public function testResetTemplatesRestoresTheBuiltInsAndDropsRegisteredOnes(): void
    {
        // The container is public static state with process lifetime, so a
        // template registered by one test class was visible to every later one.
        $builtin = IBSTemplates::$templates;
        IBSTemplates::addTemplate("seamLeak", "FAILURE", "leaked");
        $this->assertTrue(IBSTemplates::hasTemplate("seamLeak"));

        IBSTemplates::resetTemplates();

        $this->assertFalse(IBSTemplates::hasTemplate("seamLeak"));
        $this->assertSame($builtin, IBSTemplates::$templates);
        $this->assertTrue(IBSTemplates::hasTemplate("empty"), "the brand's own templates must survive a reset");
    }

    public function testResetTemplatesIsPerBrand(): void
    {
        CNRTemplates::addTemplate("seamOnlyCNR", "200", "cnr only");
        IBSTemplates::addTemplate("seamOnlyIBS", "SUCCESS", "ibs only");

        IBSTemplates::resetTemplates();

        $this->assertTrue(CNRTemplates::hasTemplate("seamOnlyCNR"), "one brand's reset must not clear another's");
        $this->assertFalse(IBSTemplates::hasTemplate("seamOnlyIBS"));

        CNRTemplates::resetTemplates();
        $this->assertFalse(CNRTemplates::hasTemplate("seamOnlyCNR"));
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        // Same rule this file documents (RSRMID-2924).
        CNRTemplates::resetTemplates();
        IBSTemplates::resetTemplates();
    }
}
