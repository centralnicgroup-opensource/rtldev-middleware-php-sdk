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
 * The format is the part that varies per brand — CNR joins command, POST body,
 * error and plain response with newlines; IBS emits a labelled REQUEST/RESPONSE
 * block — while the destination does not. So the brand supplies `format()` and a
 * {@see LogSinkInterface} supplies the destination: one formatter serves every
 * sink, instead of each sink carrying a copy of the format.
 *
 * {@see log()} is `final` on purpose. A subclass reintroducing its own `log()`
 * would be behaviour-identical under the default {@see EchoSink} and would
 * silently ignore any injected sink — exactly the erosion this class exists to
 * prevent. A logger that genuinely owns its destination implements
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
    abstract public function format(string $post, ResponseInterface $response, ?string $error = null): string;

    /**
     * Format the record, then hand it to the sink. The whole implementation of
     * the contract — see the class docblock for why it may not be overridden.
     *
     * @param string $post Post request data in string format (already masked)
     */
    #[\Override]
    final public function log(string $post, ResponseInterface $response, ?string $error = null): void
    {
        $this->sink->write($this->format($post, $response, $error));
    }
}
