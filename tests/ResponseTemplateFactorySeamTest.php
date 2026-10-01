<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractResponseTemplateManager;
use CNIC\CNR\ResponseTemplateManager as CNRTemplates;
use CNIC\IBS\ResponseTemplateManager as IBSTemplates;
use CNIC\ResponseInterface;
use CNIC\ResponseTemplateFactoryInterface;
use CNIC\ResponseTemplateManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Keeps the template registry's two faces apart (RSRMID-2968).
 *
 * **Directive.** A template registry publishes two disjoint contracts.
 * {@see \CNIC\ResponseTemplateManagerInterface} is the *pipeline contract* —
 * what `AbstractResponseTranslator::translate()` and `AbstractResponse`'s
 * `$templates` argument consume — and may declare nothing that produces a
 * `ResponseInterface`. {@see \CNIC\ResponseTemplateFactoryInterface} is the
 * *opt-in* half that turns stored templates into Responses, and the pipeline
 * never types against it. Everything the factory produces is addressed by
 * **template id**; wire text is never a way in.
 *
 * **Failure mode prevented.** Two, both of which the pre-RSRMID-2968 shape had.
 *
 * - *The pipeline could re-enter itself.* `translate()` holds a registry. While
 *   `getTemplate()` was declared on the type it holds, one call from inside
 *   `translate()` would build a Response, whose constructor calls `translate()`
 *   again, which builds another. The recursion is real; what prevents it today
 *   is a hand-written comment at the call site
 *   (`AbstractResponseTranslator::translate()`, "don't use getTemplate as it
 *   leads to endless loop"). A comment is not a mechanism. With the method off
 *   the type, the call is a static-analysis error instead of a runtime hang.
 * - *One parameter carried two meanings.* The Response-building hook was
 *   `createResponse(string $raw)`, and its two callers disagreed about what
 *   `$raw` was: `getTemplate()` passed a template **id**, `getTemplates()`
 *   passed **wire text**. Both routes happened to work only because
 *   `translate()` substitutes a raw payload equal to a known id for that
 *   template's text — the sanctioned mocking rule that needs a 25-line comment
 *   in `resolveTemplateId()` to defend. Register a template whose wire text
 *   *is* another template's id and the two routes returned different Responses:
 *   `getTemplate()` honoured the id you asked for, `getTemplates()` re-resolved
 *   the payload and handed back the other template. The hook now takes only an
 *   id ({@see AbstractResponseTemplateManager::createResponseFromTemplateId()})
 *   and `getTemplates()` builds from its own keys, so the two cannot disagree.
 *
 * **Why the guard must be structural.** Both defects are behaviour-preserving
 * to reintroduce. Nothing in `src/` calls the factory at all — `getRawTemplates()`
 * is the single production call site on either contract — so merging the two
 * interfaces back leaves every test green; the recursion only appears the first
 * time someone inside the pipeline takes the route the type now forbids. And
 * every template shipped by either brand has wire text that is not also a
 * registered id, so pointing `getTemplates()` back at its values is green too;
 * the divergence only appears for a template a consumer registers later. A
 * behavioural test cannot refuse a widened interface, so the contract's shape
 * is asserted directly. The divergence half below *is* behavioural and is kept
 * alongside it: together they say what the split is and that it buys something.
 *
 * **Why a name list is the right instrument here.** Return types alone catch
 * `getTemplate(): ResponseInterface` but not `getTemplates(): array`, which is
 * an array *of* Responses that reflection sees as a plain `array`. The
 * regression shape is literally these four methods moving back onto the
 * pipeline contract, so they are pinned by name — and the same list is asserted
 * *present* on the factory contract, so deleting them rather than moving them
 * cannot make the pin vacuous.
 *
 * **Revisit condition.** The pipeline genuinely needing a Response from the
 * registry — for example `translate()` returning a `ResponseInterface` instead
 * of a string, which would make the current string hand-off the indirection.
 * That is a real design this split does not serve, and the answer would be to
 * give the translator a factory argument of its own, **not** to widen the
 * pipeline contract back to eight methods. The ownership half of the registry —
 * per-instance templates, no static container — is a separate directive with a
 * separate guard: {@see ResponseTemplateRegistrySeamTest}, untouched by this one.
 */
final class ResponseTemplateFactorySeamTest extends TestCase
{
    /**
     * The Response-producing half of a registry. Pinned by name because their
     * return types do not distinguish them — see the class docblock.
     * @var string[]
     */
    private const array FACTORY_ROLE_METHODS = [
        "getTemplate",
        "getTemplates",
        "isTemplateMatchHash",
        "isTemplateMatchPlain",
    ];

    public function testThePipelineContractCannotReachResponseProduction(): void
    {
        $registryMethods = $this->methodNamesOf(ResponseTemplateManagerInterface::class);

        foreach ((new ReflectionClass(ResponseTemplateManagerInterface::class))->getMethods() as $method) {
            $type = $method->getReturnType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $this->assertFalse(
                is_a($type->getName(), ResponseInterface::class, true),
                "ResponseTemplateManagerInterface::{$method->getName()}() returns a Response. The pipeline "
                . "holds this type, so declaring Response production on it puts translate() one call away "
                . "from re-entering itself. Declare it on ResponseTemplateFactoryInterface instead."
            );
        }

        foreach (self::FACTORY_ROLE_METHODS as $name) {
            $this->assertNotContains(
                $name,
                $registryMethods,
                "{$name}() is back on the pipeline contract. It builds a Response, which re-runs translate(), "
                . "parse and column assembly — the recursion RSRMID-2968 closed by type. It belongs on "
                . "ResponseTemplateFactoryInterface."
            );
        }

        $this->assertSame(
            [],
            array_values(array_intersect($registryMethods, $this->methodNamesOf(ResponseTemplateFactoryInterface::class))),
            "the registry and factory contracts must publish disjoint method sets — an overlap means the "
            . "split is decorative and a consumer can reach either role through either type"
        );
    }

    public function testTheFactoryContractStillCarriesTheResponseProducingHalf(): void
    {
        // Non-vacuity for the name list above: if these four were deleted rather
        // than moved, "absent from the registry contract" would be trivially
        // true and the pin would guard nothing.
        $factoryMethods = $this->methodNamesOf(ResponseTemplateFactoryInterface::class);

        foreach (self::FACTORY_ROLE_METHODS as $name) {
            $this->assertContains($name, $factoryMethods, "ResponseTemplateFactoryInterface must declare {$name}()");
        }

        // Compared as a list of names rather than with is_a()/instanceof/
        // implementsInterface(): each of those folds a literal class-string
        // against a literal interface name into a compile-time constant —
        // PHPStan reports `function.alreadyNarrowedType`, Psalm follows the
        // ReflectionClass template parameter and reports `RedundantCondition`.
        // An assertion an analyser can prove is one it will also refuse.
        foreach ([CNRTemplates::class, IBSTemplates::class] as $class) {
            $implemented = (new ReflectionClass($class))->getInterfaceNames();

            $this->assertContains(
                ResponseTemplateFactoryInterface::class,
                $implemented,
                "{$class} must publish the factory face — the two contracts are two views of one object, "
                . "because only a brand-aware class can build a brand Response"
            );
            $this->assertContains(ResponseTemplateManagerInterface::class, $implemented);
        }
    }

    public function testTheResponseBuildingHookTakesATemplateIdAndNeverWireText(): void
    {
        $hook = new ReflectionMethod(AbstractResponseTemplateManager::class, "createResponseFromTemplateId");

        $this->assertTrue($hook->isAbstract(), "the Response-building hook must stay an abstract per-brand hook");
        $this->assertTrue($hook->isProtected(), "it is an internal hook, not part of either published contract");
        $this->assertFalse(
            method_exists(AbstractResponseTemplateManager::class, "createResponse"),
            "createResponse() is the ambiguous name RSRMID-2968 removed: its callers disagreed on whether "
            . "the argument was a template id or wire text. Reinstating it reopens that."
        );

        // Pinned by name as well as by count: named-argument callers bind to the
        // implementation's parameter name, and a brand override drifting back to
        // `$raw` would re-advertise wire text as an acceptable argument.
        foreach ([AbstractResponseTemplateManager::class, CNRTemplates::class, IBSTemplates::class] as $class) {
            $parameters = (new ReflectionMethod($class, "createResponseFromTemplateId"))->getParameters();

            $this->assertCount(1, $parameters, "{$class}: the hook resolves one template id and nothing else");
            $this->assertSame(
                "templateId",
                $parameters[0]->getName(),
                "{$class}: the parameter must say it is an id — `\$raw` is what let wire text in"
            );
        }
    }

    public function testBothRoutesAgreeWhenATemplatesWireTextIsAnotherTemplatesId(): void
    {
        // The divergent case RSRMID-2968 names. "collide" stores the literal
        // string "empty", which is also a built-in template id. Before the fix
        // getTemplates() passed that wire text to the hook, translate()
        // re-resolved it as an id, and this route handed back the *empty*
        // template while getTemplate("collide") handed back what the literal
        // payload actually parses to.
        foreach ([new CNRTemplates(), new IBSTemplates()] as $registry) {
            $brand = $registry::class;
            $registry->addTemplate("collide", "empty");

            $viaId = $registry->getTemplate("collide");
            $viaAll = $registry->getTemplates()["collide"];

            $this->assertSame(
                $viaId->getDescription(),
                $viaAll->getDescription(),
                "{$brand}: getTemplate() and getTemplates() disagree about the same template id"
            );
            $this->assertSame($viaId->getCode(), $viaAll->getCode(), "{$brand}: same id, two response codes");
            $this->assertNotSame(
                $registry->getTemplate("empty")->getDescription(),
                $viaAll->getDescription(),
                "{$brand}: asking for \"collide\" returned the \"empty\" template — the payload was re-resolved "
                . "as an id instead of being taken as this template's content"
            );
        }
    }

    public function testEveryTemplateResolvesIdenticallyThroughBothRoutes(): void
    {
        // The general form of the case above, over the brands' own built-ins
        // plus a registered one. getTemplates() is keyed by id and must build
        // from that id, so this is an identity by construction — and a
        // getTemplates() that went back to iterating values would still pass
        // for the built-ins, which is precisely why the collision case above is
        // tested separately rather than trusted to this sweep.
        foreach ([new CNRTemplates(), new IBSTemplates()] as $registry) {
            $brand = $registry::class;
            $registry->addTemplate("seamRegistered", "421", "registered on this registry");

            $all = $registry->getTemplates();
            $this->assertNotSame([], $all, "{$brand}: no templates to compare — the sweep would prove nothing");

            foreach ($all as $id => $viaAll) {
                $this->assertSame(
                    $registry->getTemplate((string)$id)->getDescription(),
                    $viaAll->getDescription(),
                    "{$brand}: template \"{$id}\" resolves differently depending on which route asked for it"
                );
            }

            $this->assertSame("registered on this registry", $all["seamRegistered"]->getDescription());
        }
    }

    /**
     * @param class-string $interface
     * @return string[]
     */
    private function methodNamesOf(string $interface): array
    {
        return array_map(
            static fn(ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass($interface))->getMethods()
        );
    }
}
