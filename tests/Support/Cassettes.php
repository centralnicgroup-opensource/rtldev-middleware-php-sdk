<?php

declare(strict_types=1);

namespace CNICTEST\Support;

use CNIC\AbstractClient;
use CNIC\HttpTransport;

/**
 * Test-suite helper that wires a {@see CassetteTransport} onto a client
 * (RSRMID-2910). Record vs replay is chosen by the `RTLDEV_MW_RECORD` env flag:
 *
 *  - set    → record mode: a real {@see HttpTransport} does the live call and
 *             the wire bytes are captured (needs OTE credentials + throttling).
 *  - unset  → replay mode (default, CI): served from committed cassettes, fully
 *             offline — no credentials, no network, no sleep.
 *
 * @psalm-api
 */
final class Cassettes
{
    /**
     * Whether the suite is running in record mode.
     */
    public static function isRecording(): bool
    {
        $flag = getenv("RTLDEV_MW_RECORD");
        return $flag !== false && $flag !== "" && $flag !== "0";
    }

    /**
     * Build a cassette transport for the given directory and inject it onto the
     * client, returning the transport so the test can select cassettes on it.
     */
    public static function attach(AbstractClient $client, string $dir): CassetteTransport
    {
        $record = self::isRecording();
        $tape = new CassetteTransport($record ? new HttpTransport() : null, $dir, $record);
        $client->setTransport($tape);
        return $tape;
    }

    /**
     * Between-test throttle for record mode only, to avoid OT&E rate-limit/IP-ban
     * on the real API. A no-op in replay mode (offline, nothing to throttle).
     * Call from a brand ClientTest's `tearDown()`.
     */
    public static function throttle(): void
    {
        if (self::isRecording()) {
            sleep(2);
        }
    }
}
