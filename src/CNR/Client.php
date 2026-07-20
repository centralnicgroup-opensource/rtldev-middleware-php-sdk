<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractClient;
use CNIC\CNR\Logger as L;
use CNIC\CNR\Response;
use CNIC\CommandFormatter;
use CNIC\Exception\PaginationException;

/**
 * CNR API Client
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Client extends AbstractClient
{
    /**
     * Instantiate CNR SocketConfig
     */
    #[\Override]
    protected function newSocketConfig(): SocketConfig
    {
        return new SocketConfig();
    }

    /**
     * Set default CNR logger
     */
    #[\Override]
    public function setDefaultLogger(): static
    {
        $this->logger = new L();
        return $this;
    }

    /**
     * Perform API request using the given command
     * @param array<string, scalar|scalar[]|null> $cmd API command to request (optional for session login)
     */
    #[\Override]
    public function request(array $cmd = []): Response
    {
        $mycmd = CommandFormatter::flattenCommand($cmd);
        $mycmd = $this->autoIDNConvert($mycmd);
        $cfg = ["CONNECTION_URL" => $this->socketURL];
        $data = $this->getPOSTData($mycmd);
        [$raw, $error] = $this->executeCurl($data, $cfg);
        $response = new Response($raw, $mycmd, $cfg, $this->context);
        if ($this->debugMode) {
            $this->logger->log($this->getPOSTData($mycmd, true), $response, $error);
        }
        return $response;
    }

    /**
     * Request the next page of list entries for the current list query
     * @param Response $rr API Response of current page
     * @throws PaginationException in case Command Parameter LAST is in use while using this method
     */
    public function requestNextResponsePage(Response $rr): ?Response
    {
        $mycmd = $rr->getCommand();
        if (array_key_exists("LAST", $mycmd)) {
            throw new PaginationException("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        // Delegate the termination decision to the Response pagination helper so
        // "is there a next page?" lives in one place (Response::hasNextPage())
        // rather than being re-derived from total/limit arithmetic here. This
        // also subsumes the former LIMIT<=0 guard: a non-positive page size makes
        // getCurrentPageNumber() null, so hasNextPage() returns false and
        // requestAllResponsePages() terminates instead of re-requesting the same
        // page forever (see testRequestNextResponsePageZeroLimit).
        if (!$rr->hasNextPage()) {
            return null;
        }
        $first = 0;
        if (array_key_exists("FIRST", $mycmd)) {
            $first = (int) $mycmd["FIRST"];
        }
        $limit = $rr->getRecordsLimitation();
        $mycmd["FIRST"] = $first + $limit;
        $mycmd["LIMIT"] = $limit;
        return $this->request($mycmd);
    }

    /**
     * Request all pages/entries for the given query command
     * @param array<string, scalar|scalar[]|null> $cmd API list command to use
     * @return Response[]
     */
    public function requestAllResponsePages(array $cmd): array
    {
        $responses = [];
        $rr = $this->request(array_merge($cmd, ["FIRST" => 0]));
        $tmp = $rr;
        $idx = 0;
        do {
            $responses[$idx++] = $tmp;
            $tmp = $this->requestNextResponsePage($tmp);
        } while ($tmp instanceof Response);
        return $responses;
    }
}
