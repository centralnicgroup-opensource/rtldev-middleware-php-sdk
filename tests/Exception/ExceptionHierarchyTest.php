<?php

declare(strict_types=1);

namespace CNICTEST\Exception;

use CNIC\Exception\CnicException;
use CNIC\Exception\PaginationException;
use CNIC\Exception\UnsupportedFeatureException;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the additive CNIC\Exception hierarchy.
 *
 * These assert the two guarantees the hierarchy makes: every SDK exception can
 * be caught by the shared CnicException base, and — because that base extends
 * the SPL \Exception — existing `catch (\Exception)` consumer code keeps
 * working unchanged (the change is additive, non-breaking).
 */
final class ExceptionHierarchyTest extends TestCase
{
    public function testSubclassesExtendCnicBase(): void
    {
        $this->assertInstanceOf(CnicException::class, new UnsupportedFeatureException("boom"));
        $this->assertInstanceOf(CnicException::class, new PaginationException("boom"));
    }

    public function testBaseExtendsSplException(): void
    {
        $this->assertInstanceOf(\Exception::class, new CnicException("boom"));
    }

    /**
     * Every subclass — via the base — remains a \Exception, so pre-existing
     * `catch (\Exception)` consumer code continues to catch SDK failures. This
     * is the backward-compatibility guarantee that makes the hierarchy additive.
     */
    public function testSubclassesRemainSplExceptions(): void
    {
        $this->assertInstanceOf(\Exception::class, new UnsupportedFeatureException("boom"));
        $this->assertInstanceOf(\Exception::class, new PaginationException("boom"));
    }
}
