<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\Exception\UnsupportedFeatureException;
use CNIC\HttpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the transport-owned cURL option guard (RSRMID-2919).
 *
 * Before RSRMID-2919, HttpTransport::post() merged the caller's options *under*
 * its own set, so anything the transport touched was silently discarded on the
 * wire — a caller asking for a 5s timeout got 300s with no signal. The options
 * are now split: the caller wins on everything the transport does not consider
 * its own, and the handful it does own are rejected loudly instead of ignored.
 *
 * These tests are fully offline: the guard runs before the cURL handle is
 * created, so no request is attempted. The complementary "the caller's value
 * really does reach the wire" assertions live in
 * {@see \CNICTEST\Functional\HttpTransportTest}, which needs a real socket.
 */
final class HttpTransportCurlOptionsTest extends TestCase
{
    /**
     * The protected set is a deliberate, closed list of two kinds of option:
     * request-envelope invariants (overriding CURLOPT_RETURNTRANSFER makes
     * curl_exec() return true and response parsing gets nothing) and TLS
     * verification posture (a generic convenience bag must not be able to turn
     * certificate checking off).
     *
     * Pinning the exact list here on purpose: widening it silently re-breaks a
     * caller's legitimate tuning, narrowing it silently gives away one of the
     * two guarantees above. Changing this list means editing this test, and the
     * commit that does so should say which guarantee it is trading.
     */
    public function testProtectedOptionsAreExactlyTheEnvelopeAndTlsKeys(): void
    {
        $this->assertSame(
            [
                CURLOPT_URL,
                CURLOPT_POST,
                CURLOPT_POSTFIELDS,
                CURLOPT_RETURNTRANSFER,
                CURLOPT_HEADER,
                CURLOPT_SSL_VERIFYPEER,
                CURLOPT_SSL_VERIFYHOST,
            ],
            array_keys(HttpTransport::PROTECTED_OPTIONS)
        );
    }

    /**
     * The constant doubles as the name lookup used to build the rejection
     * message, so every entry must actually carry its constant's name — a
     * mismatch would produce a message pointing at the wrong option.
     */
    public function testProtectedOptionsMapEachKeyToItsConstantName(): void
    {
        foreach (HttpTransport::PROTECTED_OPTIONS as $opt => $name) {
            $this->assertTrue(defined($name), "{$name} is not a defined constant");
            $this->assertSame($opt, constant($name), "{$name} does not name option {$opt}");
        }
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function protectedOptionProvider(): array
    {
        return [
            "CURLOPT_URL" => [CURLOPT_URL],
            "CURLOPT_POST" => [CURLOPT_POST],
            "CURLOPT_POSTFIELDS" => [CURLOPT_POSTFIELDS],
            "CURLOPT_RETURNTRANSFER" => [CURLOPT_RETURNTRANSFER],
            "CURLOPT_HEADER" => [CURLOPT_HEADER],
            "CURLOPT_SSL_VERIFYPEER" => [CURLOPT_SSL_VERIFYPEER],
            "CURLOPT_SSL_VERIFYHOST" => [CURLOPT_SSL_VERIFYHOST],
        ];
    }

    #[DataProvider("protectedOptionProvider")]
    public function testPostRejectsAProtectedOption(int $opt): void
    {
        $t = new HttpTransport();
        $this->expectException(UnsupportedFeatureException::class);
        $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [$opt => 1]);
    }

    /**
     * The whole point of the change is that the failure is *visible*. The
     * message must name the option the caller actually passed, not just say
     * "invalid option" — the caller has an integer constant in hand and no way
     * to map it back without help.
     */
    public function testRejectionMessageNamesTheOffendingOption(): void
    {
        $t = new HttpTransport();
        try {
            $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [CURLOPT_RETURNTRANSFER => 0]);
            $this->fail("expected UnsupportedFeatureException");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString("CURLOPT_RETURNTRANSFER", $e->getMessage());
        }
    }

    public function testRejectionMessageListsEveryOffendingOption(): void
    {
        $t = new HttpTransport();
        try {
            $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [
                CURLOPT_URL => "http://evil.test/",
                CURLOPT_HEADER => 1,
            ]);
            $this->fail("expected UnsupportedFeatureException");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString("CURLOPT_URL", $e->getMessage());
            $this->assertStringContainsString("CURLOPT_HEADER", $e->getMessage());
        }
    }

    /**
     * The security property that made the old transport-wins behaviour worth
     * keeping: letting callers win on collision must NOT become a way to switch
     * off certificate verification through a generic option bag.
     */
    public function testTlsVerificationCannotBeWeakenedThroughTheOptionBag(): void
    {
        $t = new HttpTransport();
        $this->expectException(UnsupportedFeatureException::class);
        $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
    }

    /**
     * The guard must run before any connection is attempted — a rejected option
     * is a programming error, not a network event, and must not cost a request.
     * Pointing at a port nothing listens on: if the guard were to run after the
     * request, this would return the "httperror|" tuple instead of throwing.
     */
    public function testRejectionHappensBeforeAnyRequestIsAttempted(): void
    {
        $t = new HttpTransport();
        $this->expectException(UnsupportedFeatureException::class);
        $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [CURLOPT_POST => 0]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function transportHeaderProvider(): array
    {
        return [
            "Content-Type" => ["Content-Type: application/json"],
            "Content-Length" => ["Content-Length: 0"],
            "Connection" => ["Connection: close"],
            "Expect" => ["Expect: 100-continue"],
            // matching is case-insensitive, as HTTP header names are
            "lower-cased" => ["content-length: 0"],
        ];
    }

    /**
     * Caller headers are additive. Restating one of the transport's own is
     * rejected rather than allowed to win: Content-Type and Content-Length are
     * derived from the POST body, so overriding one corrupts the request in the
     * silent way this change exists to remove — the same reason
     * CURLOPT_POSTFIELDS itself is protected.
     */
    #[DataProvider("transportHeaderProvider")]
    public function testPostRejectsAHeaderTheTransportOwns(string $header): void
    {
        $t = new HttpTransport();
        $this->expectException(UnsupportedFeatureException::class);
        $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [CURLOPT_HTTPHEADER => [$header]]);
    }

    public function testHeaderRejectionMessageNamesTheOffendingHeader(): void
    {
        $t = new HttpTransport();
        try {
            $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [
                CURLOPT_HTTPHEADER => ["Content-Length: 999"],
            ]);
            $this->fail("expected UnsupportedFeatureException");
        } catch (UnsupportedFeatureException $e) {
            $this->assertStringContainsString("content-length", $e->getMessage());
        }
    }

    /**
     * A non-array CURLOPT_HTTPHEADER cannot be appended to, so it is left in
     * place for cURL to reject with its own TypeError. Pinned because the
     * alternative — quietly dropping it, or letting it through to replace the
     * transport's header list wholesale — is the failure mode this change
     * removes. Nothing is sent either way: curl_setopt_array() raises before
     * curl_exec() is reached.
     */
    public function testNonArrayHeaderOptionIsRejectedByCurlBeforeSending(): void
    {
        $t = new HttpTransport();
        $this->expectException(\TypeError::class);
        $t->post("http://127.0.0.1:1/", "x=1", 5, "UA", [CURLOPT_HTTPHEADER => "X-Nope: 1"]);
    }
}
