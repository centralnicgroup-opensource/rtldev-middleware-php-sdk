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
 * Carries no transport defaults of its own: this client used to seed
 * CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4 via getDefaultCurlOpts(), forcing IPv4
 * name resolution for every IBS/Moniker integration. That was one customer's
 * network workaround hard-coded into the library; callers who need it set it
 * themselves with setExtraCurlOptions(). Do not re-add a brand default — not
 * here, and not on {@see SocketConfig}, where the hook moved with the option bag
 * in RSRMID-2921. Transport tuning is the caller's decision.
 * (Ref: RSRMID-2915, RSRMID-2913.)
 *
 * @package CNIC\IBS
 */
class Client extends AbstractClient
{
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
     * CNR's client-side rewrite is called from CNR's own hook and is not shared
     * (RSRMID-2922) — this used to be expressed as `needsIDNConvert = false` on
     * the config, i.e. a flag switching off code this brand never wanted.
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
     * @param string $raw raw API response payload
     * @param array<string, string> $cmd flattened command that produced the response
     * @param array{CONNECTION_URL: string} $cfg connection config used for the request
     */
    #[\Override]
    protected function newResponse(string $raw, array $cmd, array $cfg): Response
    {
        return new Response($raw, $cmd, $cfg, $this->context);
    }
}
