<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponseTemplateManager;
use CNIC\AbstractResponseTranslator;
use CNIC\CNR\Response as CNRResponse;
use CNIC\CNR\ResponseTemplateManager as CNRTemplates;
use CNIC\IBS\Response as IBSResponse;
use CNIC\IBS\ResponseTemplateManager as IBSTemplates;
use CNIC\ResponseTemplateManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Locks the response-template registry as instance state (RSRMID-2941).
 *
 * **Directive.** A template registry is an object. Its contents belong to that
 * object and reach a Response only by being handed to one — through
 * `AbstractResponse::__construct()`'s `$templates` argument, which the brand
 * `translate()` hook forwards to `AbstractResponseTranslator::translate()`. No
 * static container, no singleton, no registry a caller can reach without being
 * given it.
 *
 * **Failure mode prevented.** The container used to be
 * `public static array $templates`, redeclared per brand and read live by the
 * translator at translate time. So `addTemplate()` in one test class silently
 * changed response translation in every later one, making the suite
 * order-dependent, and `resetTemplates()` could not reliably undo it: it was a
 * no-op unless `addTemplate()` had run first, and a direct write to the public
 * property escaped it entirely. Re-adding a static container — even "just as a
 * default", even `private` with a static accessor — brings all of that back.
 *
 * **Why the guard must be structural.** Restoring a shared container is
 * behaviour-preserving on the day it lands: every existing test still passes,
 * because a single-writer suite cannot tell a per-instance registry from a
 * shared one. The defect only appears later, as an ordering coupling between
 * two test classes that never mention each other. A behavioural test cannot
 * refuse a change whose symptom is the absence of isolation, so the shape is
 * asserted directly. The scoping half below *is* behavioural and kept alongside
 * it: together they say what the seam is and that it works.
 *
 * **Revisit condition.** A concrete need for templates that outlive the object
 * that registered them — for example a consumer configuring brand-wide
 * overrides once at bootstrap and expecting every later `request()` to see
 * them. That is a real requirement this design does not serve, and the answer
 * would be to thread a registry through `AbstractClient` (additive, and
 * deliberately deferred: every call site in this repo constructs its Response
 * directly), **not** to put the static container back.
 */
final class ResponseTemplateRegistrySeamTest extends TestCase
{
    public function testACNRRegisteredTemplateIsVisibleOnlyToTheRegistryThatReceivedIt(): void
    {
        // Same template id, two responses: the one given the registry resolves
        // it, the one built with the brand default does not and falls through
        // to treating the id as an unmatched raw payload.
        $mine = (new CNRTemplates())->addTemplate("seamScoped", "200", "scoped to mine");

        $this->assertSame("scoped to mine", (new CNRResponse("seamScoped", templates: $mine))->getDescription());
        $this->assertNotSame("scoped to mine", (new CNRResponse("seamScoped"))->getDescription());
    }

    public function testAnIBSRegisteredTemplateIsVisibleOnlyToTheRegistryThatReceivedIt(): void
    {
        $mine = (new IBSTemplates())->addTemplate("seamScoped", "SUCCESS", "scoped to mine");

        $this->assertSame("scoped to mine", (new IBSResponse("seamScoped", templates: $mine))->getDescription());
        $this->assertNotSame("scoped to mine", (new IBSResponse("seamScoped"))->getDescription());
    }

    public function testTwoRegistriesOfTheSameBrandDoNotSeeEachOther(): void
    {
        $a = (new IBSTemplates())->addTemplate("seamA", "FAILURE", "a");
        $b = new IBSTemplates();

        $this->assertTrue($a->hasTemplate("seamA"));
        $this->assertFalse($b->hasTemplate("seamA"), "a registry must not observe another's registrations");
        $this->assertTrue($b->hasTemplate("empty"), "the brand's built-ins are seeded into every instance");
    }

    public function testTheBuiltInsCannotBeReachedOrRewrittenThroughAnInstance(): void
    {
        // Mutating one instance to exhaustion must leave the next one pristine.
        // This is what a `static` container would fail: there, overwriting a
        // built-in id would change what every later instance starts from.
        $vandal = new IBSTemplates();
        foreach (array_keys($vandal->getRawTemplates()) as $id) {
            $vandal->addTemplate((string)$id, "FAILURE", "vandalised");
        }

        $fresh = new IBSTemplates();
        $this->assertSame(
            (new IBSTemplates())->getRawTemplates(),
            $fresh->getRawTemplates(),
            "the built-ins a new registry starts from must not be reachable for writing"
        );
        $this->assertStringNotContainsString("vandalised", $fresh->getTemplate("empty")->getDescription());
    }

    public function testNoBrandRegistryHoldsAStaticTemplateContainer(): void
    {
        // The literal shape RSRMID-2941 removed. Any static property here — of
        // any visibility, under any name — is process-lifetime state shared by
        // every instance, which is the defect regardless of what it is called.
        foreach ([AbstractResponseTemplateManager::class, CNRTemplates::class, IBSTemplates::class] as $class) {
            foreach ((new ReflectionClass($class))->getProperties() as $property) {
                $this->assertFalse(
                    $property->isStatic(),
                    "{$class}::\${$property->getName()} is static — the registry must be instance state"
                );
            }
        }
    }

    public function testTheRegistryReachesTheTranslatorAsAnArgumentAndNotThroughAHook(): void
    {
        // translate() must *take* the registry. A brand that reads one from
        // anywhere else has reopened the global route, so the parameter is
        // pinned by position, name, type and optionality: dropping it, renaming
        // it, or making it required all fail here. (Named-argument callers bind
        // to the implementation's parameter name, so the name is contract.)
        foreach ([AbstractResponseTranslator::class, CNRResponse::class, IBSResponse::class] as $class) {
            $parameters = (new ReflectionMethod($class, "translate"))->getParameters();
            $last = end($parameters);

            $this->assertNotFalse($last);
            $this->assertSame("templates", $last->getName(), "{$class}::translate() must take a \$templates argument");
            $this->assertTrue($last->isOptional(), "the registry must stay optional — brand built-ins are the default");

            $type = $last->getType();
            $this->assertInstanceOf(ReflectionNamedType::class, $type);
            $this->assertSame(ResponseTemplateManagerInterface::class, $type->getName());
            $this->assertTrue($type->allowsNull());
        }
    }

    public function testTheResponseConstructorForwardsTheRegistryItWasGiven(): void
    {
        // Behavioural counterpart to the reflection above: it is not enough for
        // the parameter to exist, the constructor has to actually pass it down.
        // A `translate()` that ignores its $templates argument would satisfy
        // every structural assertion in this file and fail this one.
        $registry = (new CNRTemplates())->addTemplate("seamForwarded", "421", "forwarded to the translator");

        $this->assertSame(
            "forwarded to the translator",
            (new CNRResponse("seamForwarded", templates: $registry))->getDescription()
        );
        $this->assertSame(421, (new CNRResponse("seamForwarded", templates: $registry))->getCode());
    }

    public function testMatchingAnIncompleteHashAnswersFalseWithoutReadingAMissingKey(): void
    {
        // The latent bug RSRMID-2941 names: matches() indexed both hashes with
        // no existence check, so an incomplete response hash emitted "Undefined
        // array key" and then compared null.
        //
        // Asserting only the false return would be vacuous — the unguarded
        // version *also* returns false, because null !== "423". The defect is
        // the diagnostic, and .github/phpunit.xml sets no failOnWarning, so a
        // plain assertion would let the whole thing scroll past at exit code 0.
        // Hence the handler: the notice has to become the failure.
        foreach ([new CNRTemplates(), new IBSTemplates()] as $registry) {
            /** @var string[] $raised */
            $raised = [];
            set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
                $raised[] = $message;
                return true;
            });
            try {
                $matched = $registry->isTemplateMatchHash(["only" => "one key"], "empty");
            } finally {
                restore_error_handler();
            }

            $this->assertFalse($matched);
            $this->assertSame([], $raised, $registry::class . " read a key the response hash does not carry");
        }
    }

    public function testMatchingAnIncompletePlainResponseTakesTheSameGuardedPath(): void
    {
        // isTemplateMatchPlain() reaches the same comparison through the brand
        // parser, so a payload that parses short of a match key must answer the
        // same way. Covered separately because the hash case cannot reach the
        // parser, and a future fix applied to only one entry point would leave
        // this one emitting the notice again.
        foreach ([new CNRTemplates(), new IBSTemplates()] as $registry) {
            /** @var string[] $raised */
            $raised = [];
            set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
                $raised[] = $message;
                return true;
            });
            try {
                $matched = $registry->isTemplateMatchPlain("nothing=here\r\n", "empty");
            } finally {
                restore_error_handler();
            }

            $this->assertFalse($matched);
            $this->assertSame([], $raised, $registry::class . " read a key the parsed response does not carry");
        }
    }

    public function testResetTemplatesIsGoneAndMustNotComeBack(): void
    {
        // Its only reason to exist was state outliving its user. A registry
        // that needs resetting is a registry someone else can see.
        $this->assertFalse(
            method_exists(AbstractResponseTemplateManager::class, "resetTemplates"),
            "resetTemplates() undid a leak that no longer exists — reinstating it means the leak is back"
        );
    }
}
