# Testing (deep dive)

Reference for the test harnesses that need more than a one-line rule: the cassette record/replay flow, the functional (loopback) tests, and the deliberate MONIKER/IBS duplication. CLAUDE.md carries the imperative rules and links here. Guard-test authoring rules live in [CONTRIBUTING.md → Guard tests](../../CONTRIBUTING.md#guard-tests); the decisions the guards lock are in [architecture.md](architecture.md).

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

```php
$tpls = (new \CNIC\CNR\ResponseTemplateManager())
    ->addTemplate("OK", "200", "Command completed successfully");

$r = new \CNIC\CNR\Response("OK", templates: $tpls);
```

The registry is instance state (RSRMID-2941), so **there is nothing to tear down** — a template registered here cannot reach a test class that did not ask for it, and the old `resetTemplates()` is gone along with the leak it patched. A class-wide set of templates goes in a `static` property assigned from `setUpBeforeClass()`, as `tests/CNR/ResponseTest.php` does; the responses that need them pass `templates:` explicitly. Do not add a static container back — `tests/ResponseTemplateRegistrySeamTest.php` refuses it.

Templates only reach the translator through that argument, so this route works for a Response built directly, not for one produced by `$client->request()`. Every mock in the suite is a direct construction; a client-level registry was deliberately not added (see the guard's revisit condition).

## MONIKER test files mirroring IBS is intentional

MONIKER and IBS are the same API platform and data format; only the brand URL and credentials differ, and `MONIKER\Client extends IBS\Client` with no `Response` of its own. MONIKER test files may therefore mirror the IBS ones and import IBS classes directly. **Do not** flag that duplication as a coverage gap or propose MONIKER-specific response/parser tests — there is no MONIKER-specific behaviour for them to cover.
