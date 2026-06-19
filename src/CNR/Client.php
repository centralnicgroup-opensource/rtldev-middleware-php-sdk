<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © CentralNic Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractClient;
use CNIC\CNR\Logger as L;
use CNIC\CNR\Response;
use CNIC\CommandFormatter;

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
     * @return $this
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
     * @throws \Exception in case Command Parameter LAST is in use while using this method
     */
    public function requestNextResponsePage(Response $rr): ?Response
    {
        $mycmd = $rr->getCommand();
        if (array_key_exists("LAST", $mycmd)) {
            throw new \Exception("Parameter LAST in use. Please remove it to avoid issues in requestNextPage.");
        }
        $first = 0;
        if (array_key_exists("FIRST", $mycmd)) {
            $first = (int) $mycmd["FIRST"];
        }
        $total = $rr->getRecordsTotalCount();
        $limit = $rr->getRecordsLimitation();
        $first += $limit;
        if ($first < $total) {
            $mycmd["FIRST"] = $first;
            $mycmd["LIMIT"] = $limit;
            return $this->request($mycmd);
        }
        return null;
    }

    /**
     * Request all pages/entries for the given query command
     * @param array<string, scalar|scalar[]|null> $cmd API list command to use
     * @return Response[]
     */
    public function requestAllResponsePages(array $cmd): array
    {
        $responses = [];
        $rr = $this->request(array_merge([], $cmd, ["FIRST" => 0]));
        $tmp = $rr;
        $idx = 0;
        do {
            $responses[$idx++] = $tmp;
            $tmp = $this->requestNextResponsePage($tmp);
        } while ($tmp instanceof Response);
        return $responses;
    }
}
