<?php

declare(strict_types=1);

namespace CNICTEST;

use CNIC\AbstractClient;
use CNIC\AbstractLogger;
use CNIC\ClientFactory as CF;
use CNIC\CNR\Client as CNRClient;
use CNIC\CNR\Logger as CNRLogger;
use CNIC\CNR\Response as CNRResponse;
use CNIC\EchoSink;
use CNIC\IBS\Client as IBSClient;
use CNIC\IBS\Logger as IBSLogger;
use CNIC\LoggerInterface;
use CNIC\LogSinkInterface;
use CNIC\MONIKER\Client as MONIKERClient;
use CNIC\ResponseInterface;
use CNICTEST\Support\CollectingSink;
use CNICTEST\Support\SpyTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Locks the logger seam at the format, not the sink (RSRMID-2925).
 *
 * **The directive.** `LoggerInterface::format()` returns the debug record and is
 * declared on the *interface*; where that record goes is a
 * {@see LogSinkInterface}, injected. A brand logger supplies formatting only —
 * {@see AbstractLogger::log()} is final and is the sole writer.
 *
 * **The failure mode.** The contract used to be `log(): void` with an `echo`
 * inside every implementation, so the half with actual logic (the format) could
 * only be observed through output buffering, and an integrator wanting the
 * record in a file or a PSR-3 logger had to reimplement the brand's format. Two
 * regressions would restore that: dropping `format()` from the interface while
 * leaving it on the concrete loggers, and a brand logger writing output itself
 * instead of through the sink.
 *
 * **Why structural.** The first regression is invisible behaviourally: every
 * brand-level test calls `format()` on the concrete class and would keep
 * passing, while interface-typed consumers — the ones this project mandates —
 * silently lose access to the string. Only reflection over `LoggerInterface`
 * sees it. The sink half is checked behaviourally below, because injecting a
 * non-echo sink does expose a logger that writes on its own.
 *
 * **What would justify revisiting.** A brand whose debug record genuinely cannot
 * be produced as a string ahead of writing it — streaming megabyte payloads
 * incrementally, say. Nothing in CNR, IBS or Moniker comes close: all three
 * build a complete string today.
 */
final class LoggerSeamTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<AbstractLogger>}>
     */
    public static function brandLoggerClassProvider(): array
    {
        return [
            "CNR" => [CNRLogger::class],
            "IBS" => [IBSLogger::class],
        ];
    }

    /**
     * @return array<string, array{0: class-string<AbstractClient>}>
     */
    public static function brandClientClassProvider(): array
    {
        return [
            "CNR" => [CNRClient::class],
            "IBS" => [IBSClient::class],
            "MONIKER" => [MONIKERClient::class],
        ];
    }

    /**
     * @return array<string, array{0: class-string<AbstractLogger>, 1: \Closure(LogSinkInterface): AbstractLogger}>
     */
    public static function brandLoggerProvider(): array
    {
        return [
            "CNR" => [
                CNRLogger::class,
                static fn(LogSinkInterface $sink): AbstractLogger => new CNRLogger($sink),
            ],
            "IBS" => [
                IBSLogger::class,
                static fn(LogSinkInterface $sink): AbstractLogger => new IBSLogger($sink),
            ],
        ];
    }

    /**
     * @return array<string, array{0: class-string<AbstractClient>, 1: \Closure(): AbstractClient}>
     */
    public static function brandClientProvider(): array
    {
        return [
            "CNR" => [CNRClient::class, static fn(): AbstractClient => CF::cnr()],
            "IBS" => [IBSClient::class, static fn(): AbstractClient => CF::ibs()],
            "MONIKER" => [MONIKERClient::class, static fn(): AbstractClient => CF::moniker()],
        ];
    }

    /**
     * The seam itself: the formatted record must be obtainable through the
     * interface, as a string. Declared on the concrete loggers only, it is
     * unreachable to every consumer typed against `LoggerInterface`.
     */
    public function testFormatIsDeclaredOnTheInterfaceAndReturnsAString(): void
    {
        $rc = new ReflectionClass(LoggerInterface::class);
        $this->assertTrue(
            $rc->hasMethod("format"),
            "LoggerInterface::format() must exist: the debug record has to be reachable through the "
            . "interface, not only through a brand's concrete Logger (RSRMID-2925)."
        );

        $type = $rc->getMethod("format")->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame(
            "string",
            $type->getName(),
            "LoggerInterface::format() must return the record. A format() returning void is the old log()."
        );
    }

    /**
     * A brand logger contributes formatting and nothing else. Declaring `log()`
     * is how the sink gets bypassed, so the base marks it final — asserted here
     * as well, because removing `final` is the enabling step and is itself
     * behaviour-preserving.
     *
     * @param class-string<AbstractLogger> $loggerClass
     */
    #[DataProvider("brandLoggerClassProvider")]
    public function testBrandLoggersDeclareFormatOnlyAndInheritAFinalLog(string $loggerClass): void
    {
        $rc = new ReflectionClass($loggerClass);
        $this->assertTrue($rc->hasMethod("format"), "{$loggerClass}::format() must exist.");
        $this->assertSame(
            $loggerClass,
            $rc->getMethod("format")->getDeclaringClass()->getName(),
            "{$loggerClass} must declare its own format() — that is the brand's entire contribution."
        );

        $log = $rc->getMethod("log");
        $this->assertSame(
            AbstractLogger::class,
            $log->getDeclaringClass()->getName(),
            "{$loggerClass} must not declare log(): writing is the sink's job, and an override would "
            . "silently ignore an injected sink (RSRMID-2925)."
        );
        $this->assertTrue(
            $log->isFinal(),
            "AbstractLogger::log() must stay final — it is the only thing preventing a subclass from "
            . "writing output itself."
        );
    }

    /**
     * The behavioural half: with a sink injected, nothing reaches output and the
     * sink receives exactly what format() returned. This is what catches a
     * logger that writes on its own — invisible under the default EchoSink.
     *
     * @param class-string<AbstractLogger> $loggerClass
     * @param \Closure(LogSinkInterface): AbstractLogger $factory
     */
    #[DataProvider("brandLoggerProvider")]
    public function testInjectedSinkReceivesTheRecordAndOutputStaysSilent(
        string $loggerClass,
        \Closure $factory
    ): void {
        $sink = new CollectingSink();
        $logger = $factory($sink);
        $r = self::stubResponse();

        $this->expectOutputString("");
        $logger->log("post=data", $r, "boom");

        $this->assertSame(
            [$logger->format("post=data", $r, "boom")],
            $sink->messages(),
            "{$loggerClass} must write through the sink, once, with exactly what format() returned."
        );
    }

    /**
     * `newLogger()` is the brand hook, `setLogSink()` the injection point — the
     * same factory-hook/injection pair used for the transport and the response
     * parser. Without it the sink could only be chosen by naming the brand's
     * Logger class, which is what this project tells integrators not to do.
     *
     * @param class-string<AbstractClient> $clientClass
     */
    #[DataProvider("brandClientClassProvider")]
    public function testClientsExposeTheSinkSeamThroughAFactoryHook(string $clientClass): void
    {
        $rc = new ReflectionClass($clientClass);

        $hook = $rc->getMethod("newLogger");
        $this->assertTrue($hook->isProtected(), "newLogger() is an internal factory hook, not public API.");
        $sinkParam = $hook->getParameters()[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $sinkParam);
        $this->assertSame(
            LogSinkInterface::class,
            $sinkParam->getName(),
            "{$clientClass}::newLogger() must take the sink, so the destination is chosen by the caller."
        );

        $this->assertTrue(
            $rc->getMethod("setLogSink")->isPublic(),
            "setLogSink() is the integrator-facing injection point and must stay public."
        );
        $this->assertFalse(
            $rc->hasMethod("setDefaultLogger"),
            "setDefaultLogger() was replaced by the newLogger() hook in RSRMID-2925; re-adding it puts a "
            . "second, sinkless way of building the logger back on the client."
        );
    }

    /**
     * End to end through the client: swapping the sink keeps the brand format
     * and moves the bytes. This is the integrator use case the seam exists for.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandClientProvider")]
    public function testSetLogSinkIsFluentAndKeepsTheBrandFormat(string $clientClass, \Closure $factory): void
    {
        $cl = $factory();
        $sink = new CollectingSink();
        $this->assertSame($cl, $cl->setLogSink($sink), "{$clientClass}::setLogSink() must be fluent.");

        $logger = self::readLogger($cl);
        $r = self::stubResponse();

        $this->expectOutputString("");
        $logger->log("post=data", $r);
        $this->assertSame($logger->format("post=data", $r), $sink->contents());
    }

    /**
     * The client's default is still the echo sink, so debug mode emits the same
     * bytes as before for anyone who injects nothing.
     *
     * @param \Closure(): AbstractClient $factory
     */
    #[DataProvider("brandClientProvider")]
    public function testTheClientDefaultsToTheEchoSink(string $clientClass, \Closure $factory): void
    {
        $sink = (new ReflectionClass(AbstractLogger::class))
            ->getProperty("sink")
            ->getValue(self::readLogger($factory()));

        $this->assertInstanceOf(
            EchoSink::class,
            $sink,
            "{$clientClass} must still default to standard output."
        );
    }

    /**
     * A custom logger owning both halves is still accepted — the seam narrows
     * what a *brand* logger does, not what an integrator may supply — and the
     * client really routes a debug request through it. Driven end to end over a
     * canned transport, because "the setter is fluent" would not show that
     * `performRequest()` still reaches the logger it was given.
     */
    public function testTheClientLogsARealRequestThroughACustomLogger(): void
    {
        $custom = new class implements LoggerInterface {
            /** @var string[] */
            public array $written = [];

            #[\Override]
            public function format(string $post, ResponseInterface $response, ?string $error = null): string
            {
                return "custom:" . $response->getCode();
            }

            #[\Override]
            public function log(string $post, ResponseInterface $response, ?string $error = null): void
            {
                $this->written[] = $this->format($post, $response, $error);
            }
        };

        $cl = CF::cnr();
        $this->assertSame($cl, $cl->setCustomLogger($custom), "setCustomLogger() must be fluent.");
        $cl->setTransport(new SpyTransport())->enableDebugMode();

        $this->expectOutputString("");
        $cl->request(["COMMAND" => "StatusAccount"]);

        $this->assertSame(["custom:200"], $custom->written, "the client must log through the custom logger.");
    }

    /**
     * The one composition rule worth pinning, because it is order-dependent and
     * documented rather than obvious: `setLogSink()` rebuilds the *brand* logger
     * around the sink, so it replaces a custom logger set before it. The reverse
     * order keeps the custom logger, whose own destination is its business.
     */
    public function testSetLogSinkReplacesAPreviouslySetCustomLoggerAndNotTheReverse(): void
    {
        $sink = new CollectingSink();
        $custom = new class implements LoggerInterface {
            public bool $used = false;

            #[\Override]
            public function format(string $post, ResponseInterface $response, ?string $error = null): string
            {
                return "custom";
            }

            #[\Override]
            public function log(string $post, ResponseInterface $response, ?string $error = null): void
            {
                $this->used = true;
            }
        };

        $sunk = CF::cnr()->setCustomLogger($custom)->setLogSink($sink);
        $this->assertInstanceOf(CNRLogger::class, self::readLogger($sunk));
        $this->assertFalse($custom->used);

        $kept = CF::cnr()->setLogSink($sink)->setCustomLogger($custom);
        $this->assertSame(
            $custom,
            (new ReflectionClass(AbstractClient::class))->getProperty("logger")->getValue($kept)
        );
    }

    /**
     * The client keeps its logger private to the SDK; the seam is asserted on
     * the instance the client actually built, not on a replica.
     */
    private static function readLogger(AbstractClient $cl): AbstractLogger
    {
        $logger = (new ReflectionClass(AbstractClient::class))->getProperty("logger")->getValue($cl);
        self::assertInstanceOf(AbstractLogger::class, $logger);
        return $logger;
    }

    /**
     * Any response will do here — these tests are about the wiring, not the
     * format. Brand format assertions live in tests/CNR|IBS/LoggerTest.php.
     */
    private static function stubResponse(): ResponseInterface
    {
        return new CNRResponse(
            "[RESPONSE]\r\nCODE=200\r\nDESCRIPTION=Command completed successfully\r\nEOF\r\n",
            ["COMMAND" => "StatusAccount"],
            ["CONNECTION_URL" => "https://api.rrpproxy.net/api/call.cgi"]
        );
    }
}
