<?php

declare(strict_types=1);

/**
 * CNIC\IBS
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\IBS;

use CNIC\AbstractClient;
use CNIC\CommandFormatter;
use CNIC\IBS\Logger as L;
use CNIC\IBS\Response;
use CNIC\IBS\SocketConfig;
use CNIC\LogSinkInterface;

/**
 * IBS API Client
 *
 * Carries no transport defaults of its own, and must not grow any — not here, and
 * not on {@see SocketConfig}, which owns the option bag. Forcing IPv4 resolution
 * (CURLOPT_IPRESOLVE) for every IBS/Moniker integration is one host's network
 * workaround hard-coded into the library; a caller who needs it sets it with
 * setExtraCurlOptions(). Transport tuning is the caller's decision.
 *
 * @package CNIC\IBS
 */
class Client extends AbstractClient
{
    /**
     * Narrowed from {@see AbstractClient::__construct()}'s `?AbstractSocketConfig`
     * to this brand's config, mirroring the covariant {@see newSocketConfig()}
     * below — see {@see \CNIC\CNR\Client::__construct()} for why the narrowing is
     * load-bearing and why PHP permits it here (RSRMID-2966).
     *
     * {@see \CNIC\MONIKER\Client} narrows it one step further: the two brands share
     * this platform but not its endpoints, so a Moniker client must refuse an IBS
     * config rather than silently talk to the IBS host.
     *
     * @param SocketConfig|null $socketConfig IBS connection configuration to adopt;
     *        null builds the brand default
     */
    public function __construct(?SocketConfig $socketConfig = null)
    {
        parent::__construct($socketConfig);
    }

    /**
     * Instantiate IBS SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }

    /**
     * Instantiate the IBS logger writing to the given sink
     */
    #[\Override]
    protected function newLogger(LogSinkInterface $sink): L
    {
        return new L($sink);
    }

    /**
     * Perform API request using the given command.
     *
     * The IBS/Moniker platform exposes many endpoints under one host, where the
     * path selects the operation (e.g. `Domain/Create`, `Domain/Info`). The base
     * host is configured on the SocketConfig (`liveUrl`/`oteUrl`, host only, with
     * a trailing slash); the per-operation path is appended by
     * {@see \CNIC\AbstractClient::performRequest()} and therefore must be
     * supplied per request.
     *
     * @param array<string, scalar|scalar[]|null> $cmd API command to request
     * @param string $path Path segment appended to the base URL to select the endpoint
     */
    #[\Override]
    public function request(array $cmd = [], string $path = ""): Response
    {
        $r = $this->performRequest($cmd, $path);
        assert($r instanceof Response);
        return $r;
    }

    /**
     * Flatten the given command into wire form, injecting the JSON response format.
     *
     * Deliberately no IDN handling: the IBS/Moniker platform converts IDNs
     * server-side, so the command reaches the wire with its unicode values intact.
     * CNR's client-side rewrite is called from CNR's own hook and is not shared —
     * do not express this as a flag that switches off shared code, which is the
     * shape that replaced.
     * @param array<string, scalar|scalar[]|null> $cmd API command
     * @return array<string, string>
     */
    #[\Override]
    protected function buildCommand(array $cmd): array
    {
        return CommandFormatter::flattenCommand($cmd + ["ResponseFormat" => "JSON"], false);
    }

    /**
     * Instantiate an IBS Response for the given raw payload.
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     * @param string|null $error transport error, if any; non-null means $raw is unusable
     */
    #[\Override]
    protected function newResponse(string $raw, array $cmd, array $cfg, ?string $error = null): Response
    {
        return new Response($raw, $cmd, $cfg, $this->context, error: $error);
    }
}
