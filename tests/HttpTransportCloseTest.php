<?php

declare(strict_types=1);

/**
 * CNICTEST
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST;

use CNIC\HttpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for HttpTransport::close().
 *
 * Deliberately network-free: it never binds or contacts a live server, so
 * close() is covered in every environment — including locked-down CI runners
 * where the loopback-based Functional\HttpTransportTest markTestSkipped()s and
 * therefore cannot guarantee close() is exercised. Connection attempts target
 * the privileged, unbound port 1 on loopback, which cURL refuses immediately.
 */
final class HttpTransportCloseTest extends TestCase
{
    /**
     * close() on a transport that never created a handle must be a safe no-op
     * and leave the transport usable for a subsequent request.
     */
    public function testCloseOnFreshTransportLeavesItUsable(): void
    {
        $t = new HttpTransport();
        $t->close(); // handle is still null here — must not error

        [$raw] = $t->post("http://127.0.0.1:1/", "x=1", 2, "UA");
        $this->assertStringStartsWith("httperror|", $raw);
        $t->close();
    }

    /**
     * close() frees a handle created by a prior post(); the next post()
     * transparently recreates it.
     */
    public function testCloseFreesHandleAndAllowsReuse(): void
    {
        $t = new HttpTransport();
        [$raw1] = $t->post("http://127.0.0.1:1/", "x=1", 2, "UA");
        $this->assertStringStartsWith("httperror|", $raw1);

        $t->close();

        [$raw2] = $t->post("http://127.0.0.1:1/", "y=2", 2, "UA");
        $this->assertStringStartsWith("httperror|", $raw2);
        $t->close();
    }

    /**
     * Calling close() repeatedly is idempotent.
     */
    public function testCloseIsIdempotent(): void
    {
        $t = new HttpTransport();
        $t->close();
        $t->close();
        $this->expectNotToPerformAssertions();
    }
}
