<?php

declare(strict_types=1);

/**
 * CNIC
 * Copyright © Team Internet Group PLC
 */

namespace CNIC;

/**
 * Shared foundation for brand loggers: implement {@see format()}, inherit the
 * destination.
 *
 * ## Why the seam is here (RSRMID-2925)
 *
 * `LoggerInterface::log()` used to return `void` and every implementation ended
 * in `echo`. The seam therefore sat at the sink, while the only thing that
 * actually varied between brands was the format — CNR joins command, POST body,
 * error and plain response with newlines; IBS emits a labelled
 * REQUEST/RESPONSE block. The destination was identical in both.
 *
 * So the split is the other way round now: the brand supplies `format()`, and a
 * {@see LogSinkInterface} supplies the destination. One formatter serves every
 * sink, instead of each sink carrying a copy of the format.
 *
 * {@see log()} is `final` on purpose. A subclass that reintroduced its own
 * `log()` would be behaviour-identical under the default {@see EchoSink} and
 * would silently ignore any injected sink — exactly the erosion this class
 * exists to prevent. A logger that genuinely owns its destination implements
 * {@see LoggerInterface} directly instead of extending this class.
 *
 * @psalm-api
 * @package CNIC
 */
abstract class AbstractLogger implements LoggerInterface
{
    /**
     * @param LogSinkInterface $sink Destination for formatted records; defaults to standard output
     */
    public function __construct(protected readonly LogSinkInterface $sink = new EchoSink())
    {
    }

    #[\Override]
    abstract public function format(string $post, ResponseInterface $r, ?string $error = null): string;

    /**
     * Format the record, then hand it to the sink. The whole implementation of
     * the contract — see the class docblock for why it may not be overridden.
     *
     * @param string $post Post request data in string format (already secured)
     * @param ResponseInterface $r Response to log
     * @param string|null $error Error message (optional)
     */
    #[\Override]
    final public function log(string $post, ResponseInterface $r, ?string $error = null): void
    {
        $this->sink->write($this->format($post, $r, $error));
    }
}
