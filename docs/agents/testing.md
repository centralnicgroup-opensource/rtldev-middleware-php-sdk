# Testing (deep dive)

Reference for the test harnesses that need more than a one-line rule: the cassette record/replay flow, the functional (loopback) tests, and the deliberate MONIKER/IBS duplication. CLAUDE.md carries the imperative rules and links here. Guard-test authoring rules live in [CONTRIBUTING.md → Guard tests](../../CONTRIBUTING.md#guard-tests); the decisions the guards lock are in [architecture.md](architecture.md).

## A green run is a complete run (RSRMID-2964)

`.github/phpunit.xml` pairs `stopOnDefect="true"` with `failOnWarning`, `failOnNotice` and `failOnRisky`. That pairing is the point: `stopOnDefect` halts on an error, failure, warning or risky result, and the three `failOn*` attributes make every one of those categories exit non-zero. Before they were set, the config displayed all six diagnostic categories and stopped on them but failed on none, so **a single "Undefined array key" in `src/` truncated the suite from 618 tests to 191 and still exited 0** — a green CI on under a third of the suite.

Two consequences worth keeping in mind:

- Read the **exit code**, not the summary line. A red run stops at the first defect, so its "passed" count says nothing about what else is broken — set the failing test aside temporarily if you need the rest of the picture.
- Do not treat this config as coverage for a guard test whose subject is a PHP diagnostic. It lives in a file an unrelated change can edit, and the guard would go quietly vacuous. Assert on the diagnostic inside the test instead — see [CONTRIBUTING.md → Guard tests](../../CONTRIBUTING.md#guard-tests).

`failOnSkipped` is deliberately **not** set: the functional loopback test below skips legitimately when it cannot bind a port. `failOnDeprecation` is unset pending a check that the 8.4/8.5 CI legs are deprecation-free.

## `request()`-path tests are cassette-driven (RSRMID-2910)

The brand `ClientTest`s drive the full `request()`/`login()`/`logout()`/pagination lifecycle through `CNICTEST\Support\CassetteTransport`, injected via `AbstractClient::setTransport()` — the `TransportInterface` seam.

- **Replay is the default** (`composer test`): every live call is served from a committed JSON cassette under `tests/<Brand>/cassettes/`. Fully offline — no credentials, no network, no `sleep()`. This is what makes the suite runnable in a locked-down environment and what removed the throttle wait from the normal loop.
- **Recording** is opt-in (`composer test:record`, i.e. `RTLDEV_MW_RECORD=1`): the cassette transport delegates to a nested `HttpTransport`, does the real OT&E calls, captures the wire bytes **pre-`translate()`**, and rewrites the cassettes. It needs the `RTLDEV_MW_CI_*` credentials and keeps the `sleep(2)` throttle.
- **Cassette format:** a bare JSON array of `{"raw","error"}` exchanges, in order. One logical operation may drive several `post()` calls (pagination is the usual case), so the array is a tape, not a map. Each assertion selects its tape with `self::$tape->useCassette("<name>")`.
- **The connection-failure case (`code=421`) is a hand-authored fixture**, `conn-error.json`, served through a dedicated replay-only transport. It is deliberately outside the record path: the cURL error text depends on the resolver, so a record run would otherwise overwrite it with an environment-specific message.
- **Re-record only when the exercised API behaviour changes**, and spot-check the `git diff`. There is no scrubbing step — the protection is OT&E-test-account discipline. `tests/` is `export-ignore`d, so cassettes never ship to Packagist.

## Functional tests are the one deliberate network exception

`tests/Functional/` drives `HttpTransport` against a **local PHP built-in HTTP server over loopback** — still no external API. It exists to assert wire-level behaviour that templates and spies cannot: that per-call cURL options do not leak across the reused handle, and that a caller's timeout actually takes effect (a stalling endpoint plus a 1s caller timeout — asserting the effect, not the option value). That is the "in the bag is not on the wire" lesson from RSRMID-2919, which two releases of bag-asserting unit tests failed to catch.

The server starts in `setUpBeforeClass()` and the class calls `markTestSkipped()` if it cannot bind a port, so a runner without loopback permission goes yellow rather than red.

## Mocking is template-driven, never a mocking framework

Register mock API responses on a `ResponseTemplateManager` instance and hand that instance to the Response; do not add Mockery or Prophecy. Where a substitute for a collaborator is needed, the repo uses hand-written spies against the interface seams — `CNICTEST\Support\SpyTransport` (`TransportInterface`) and `CNICTEST\Support\SpyResponseParser` (`ResponseParserInterface`). Both reach the behaviour through public API with no reflection and no subclassing, which is the property those seams exist to provide.

`SpyTransport` can end a call in three ways, and they are not interchangeable. A canned **raw** response is the happy path; a canned **error** is a cURL failure that arrives as element `[1]` and becomes a 421 response (RSRMID-2969); a canned **throw** propagates out of `request()` and past every line after it (RSRMID-2980, for the CNR `login()`/`logout()` cleanup). The throw is typed `CnicException`, not `\Throwable` — `TransportInterface::post()` declares only `UnsupportedFeatureException`, and a double that could raise a `\TypeError` would widen the seam's contract instead of reproducing it.

Reach for the spy only for what the real transport cannot show. `close()`-reached-the-transport is such a case — `HttpTransport` exposes nothing to assert it on — but a *throw* is not: `HttpTransport::post()` rejects a transport-owned cURL option before it touches the handle, so a test can drive the real transport and stay offline. Prefer that, since it makes the throw a fact about production code rather than a property of a double; and when you do, point the client's URL at a closed local port, because `new Client()` defaults to the **LIVE** endpoint and the no-request guarantee otherwise rests on another class's constant.

```php
$tpls = (new \CNIC\CNR\ResponseTemplateManager())
    ->addTemplate("OK", "200", "Command completed successfully");

$r = new \CNIC\CNR\Response("OK", templates: $tpls);
```

The registry is instance state (RSRMID-2941), so **there is nothing to tear down** — a template registered here cannot reach a test class that did not ask for it, and the old `resetTemplates()` is gone along with the leak it patched. A class-wide set of templates goes in a `static` property assigned from `setUpBeforeClass()`, as `tests/CNR/ResponseTest.php` does; the responses that need them pass `templates:` explicitly. Do not add a static container back — `tests/ResponseTemplateRegistrySeamTest.php` refuses it.

Templates only reach the translator through that argument, so this route works for a Response built directly, not for one produced by `$client->request()`. Every mock in the suite is a direct construction; a client-level registry was deliberately not added (see the guard's revisit condition).

## MONIKER test files mirroring IBS is intentional

MONIKER and IBS are the same API platform and data format; only the brand URL and credentials differ, and `MONIKER\Client extends IBS\Client` with no `Response` of its own. MONIKER test files may therefore mirror the IBS ones and import IBS classes directly. **Do not** flag that duplication as a coverage gap or propose MONIKER-specific response/parser tests — there is no MONIKER-specific behaviour for them to cover.

## Coverage

`composer test` runs with coverage on; the HTML report lands in `reports/html/` and Clover in `reports/clover/coverage.xml`. To see which lines are actually uncovered rather than reading the percentages:

```bash
php -r '$x = new SimpleXMLElement(file_get_contents("reports/clover/coverage.xml"));
foreach ($x->xpath("//file") as $f) { foreach ($f->line as $l) {
  if ((int)$l["count"] === 0) { echo $f["name"], ":", $l["num"], "\n"; } } }'
```

**Do not chase 100%.** A line that no correctly-typed caller can reach is not a coverage gap, and the two ways of making it look covered are both worse than leaving it red:

- **Do not add `@codeCoverageIgnore`.** RSRMID-2977 set the precedent — the intl-less branch in `IDNCommandRewriter` was wrapped in ignore markers on a justification that had quietly become false, and the marker is what kept anyone from noticing. Unreachable by contract means **delete the branch**, not hide it. Where an analyser demands the narrowing that creates the branch (PHPStan L9 on a `string|null` return, say), prefer a call that cannot produce the null at all: `CNR\ResponseParser` uses `str_replace` for its literal CRLF fold precisely so there is no `?: $raw` fallback to leave uncovered.
- **Do not collapse a guard onto one line** — a ternary or `??` chain moves the untaken branch onto a line the report calls covered. Line coverage rises, nothing is tested, and the next reader believes it was.

What to do instead: check whether the branch is genuinely unreachable, and if a legitimate seam reaches it, write the test. `ClientConfigSeamTest::testCnrConfigAccessorRefusesAForeignConfig()` covers a guard whose own docblock called itself unreachable — PHP exempts constructors from LSP under class inheritance, so a subclass calling the grandparent constructor reaches it without reflection. If it stays unreachable, **say so in the docblock, and say which half of the condition is which** — see `CNR\Response::metaInt()`, the suite's one deliberately uncovered line. An accurate "this is why it is red" beats a green number, the same rule [architecture.md](architecture.md) applies to guard tests: state what is and is not caught rather than overclaiming.
