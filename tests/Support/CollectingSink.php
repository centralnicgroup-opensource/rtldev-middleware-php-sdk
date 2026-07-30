<?php

declare(strict_types=1);

/**
 * CNICTEST\Support
 * Copyright © Team Internet Group PLC
 */

namespace CNICTEST\Support;

use CNIC\LogSinkInterface;

/**
 * In-memory log sink: keeps every written message instead of writing it out.
 *
 * The second real implementation of {@see LogSinkInterface} alongside the
 * shipped {@see \CNIC\EchoSink}, and the reason debug output can be asserted
 * without output buffering (RSRMID-2925).
 */
final class CollectingSink implements LogSinkInterface
{
    /**
     * Everything written to this sink, in order.
     * @var string[]
     */
    private array $messages = [];

    #[\Override]
    public function write(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * All messages written so far, in order.
     * @return string[]
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * The messages written so far, concatenated — the bytes an echoing sink
     * would have emitted.
     */
    public function contents(): string
    {
        return implode("", $this->messages);
    }
}
