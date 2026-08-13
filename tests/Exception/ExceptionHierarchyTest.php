<?php

declare(strict_types=1);

namespace CNICTEST\Exception;

use CNIC\Exception\CnicException;
use CNIC\Exception\DuplicateColumnException;
use CNIC\Exception\InvalidConfigurationException;
use CNIC\Exception\InvalidDateTimeException;
use CNIC\Exception\MalformedResponseException;
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
        $this->assertInstanceOf(CnicException::class, new InvalidDateTimeException("boom"));
        $this->assertInstanceOf(CnicException::class, new InvalidConfigurationException("boom"));
        $this->assertInstanceOf(CnicException::class, new DuplicateColumnException("boom"));
        $this->assertInstanceOf(CnicException::class, new MalformedResponseException("boom"));
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
        $this->assertInstanceOf(\Exception::class, new InvalidDateTimeException("boom"));
        $this->assertInstanceOf(\Exception::class, new InvalidConfigurationException("boom"));
        $this->assertInstanceOf(\Exception::class, new DuplicateColumnException("boom"));
        $this->assertInstanceOf(\Exception::class, new MalformedResponseException("boom"));
    }

    /**
     * The additive guarantee for MalformedResponseException (RSRMID-2967): the
     * two CNR\Response::stringCells() throw sites previously raised
     * UnsupportedFeatureException directly, so an existing
     * `catch (UnsupportedFeatureException)` around either site must keep
     * catching now that they raise the more specific subclass instead.
     */
    public function testMalformedResponseRemainsAnUnsupportedFeature(): void
    {
        $this->assertInstanceOf(UnsupportedFeatureException::class, new MalformedResponseException("boom"));
    }
}
