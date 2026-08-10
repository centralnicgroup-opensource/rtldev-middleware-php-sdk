<?php

declare(strict_types=1);

/**
 * CNIC\CNR
 * Copyright © Team Internet Group PLC
 */

namespace CNIC\CNR;

use CNIC\AbstractResponse;
use CNIC\CNR\ResponseParser as RP;
use CNIC\CNR\ResponseTranslator as RT;
use CNIC\Column;
use CNIC\ColumnInterface;
use CNIC\Exception\UnsupportedFeatureException;
use CNIC\ExtendedResponseInterface;
use CNIC\Record;
use CNIC\ResponseParserInterface;
use CNIC\ResponseTemplateManagerInterface;

/**
 * CNR Response
 *
 * Extends the shared AbstractResponse with the CNR wire specifics — the
 * translate()/populate() hooks, the CODE/DESCRIPTION status accessors and the
 * column-driven pagination primitives — and adds the richer CNR-only
 * capabilities declared on {@see ExtendedResponseInterface} (telemetry,
 * transient/pending status and the list-hash projection) that flat platforms
 * like IBS/Moniker do not provide.
 *
 * @psalm-api
 * @package CNIC\CNR
 */
class Response extends AbstractResponse implements ExtendedResponseInterface
{
    /**
     * Command parameter keys carrying sensitive data (masked before storage).
     * CNR uses upper-case keys. Declared once in {@see SensitiveFields::KEYS},
     * shared with {@see \CNIC\CNR\SocketConfig}.
     * @var string[]
     */
    protected array $sensitiveFields = SensitiveFields::KEYS;

    /**
     * Regex for pagination related column keys.
     * The alternation is grouped so the ^…$ anchors apply to every keyword;
     * without the group only TOTAL/LAST are anchored and COUNT|LIMIT|FIRST
     * would match anywhere, wrongly stripping real columns such as COUNTRY,
     * FIRSTNAME, DISCOUNT or ACCOUNT from getColumnKeys()/getListHash().
     * @var non-empty-string
     */
    protected string $paginationKeys = "/^(TOTAL|COUNT|LIMIT|FIRST|LAST)$/";

    /**
     * Translate the raw API response into its canonical form using the CNR
     * translator. $cmd is already sanitized.
     * @param array<string, string> $cmd API command used within this request
     * @param array{CONNECTION_URL?: string} $placeholders
     * @param string|null $error transport error, if any; non-null means $raw is unusable
     * @param ResponseTemplateManagerInterface|null $templates registry to resolve template ids against; null uses CNR's built-ins
     */
    #[\Override]
    protected function translate(
        string $raw,
        array $cmd,
        array $placeholders,
        ?string $error = null,
        ?ResponseTemplateManagerInterface $templates = null
    ): string {
        return RT::translate($raw, $cmd, $placeholders, $error, $templates);
    }

    /**
     * Parse the translated response into the hash and build the column/record
     * lists from it. CNR exposes its columns under the PROPERTY sub-array and
     * assembles records only when properties are present.
     *
     * $cmd is forwarded to keep the parse call uniform across brands even though
     * the CNR parser ignores it — see ResponseParserInterface::parse().
     * @param array<string, string> $cmd API command used within this request, already sanitized
     */
    #[\Override]
    protected function populate(string $raw, ResponseParserInterface $parser, array $cmd): void
    {
        $this->hash = $parser->parse($raw, $cmd);
        // A PROPERTY that is absent or not an array yields no columns and no
        // records — the same as the is_array() guard this replaced.
        $properties = $this->getHashArray("PROPERTY");
        if ($properties !== []) {
            foreach (array_keys($properties) as $k) {
                $key = strval($k);
                $this->addColumn($key, self::stringCells($key, $properties[$k]));
            }
            $this->assembleRecords();
        }
    }

    /**
     * Narrow one parsed PROPERTY entry to the string list a CNR column takes.
     *
     * The CNR wire format is textual, so every cell of a real response is already
     * a string and this rejects nothing. It exists because the parse step is a
     * seam: the contract returns array<string, mixed>, so the brand's own shape
     * has to be re-established here rather than read off the concrete parser's
     * return type.
     *
     * **This outlived the type it was introduced to defend (RSRMID-2942).** It
     * once backed `CNR\Column`'s `string` binding; that class is gone, and the
     * check stays for two reasons that never depended on it. A parser handing CNR
     * a non-string is a *programming error*, and the only parsers that can are
     * substitutes — so failing loudly at construction beats surfacing as a null
     * three calls later. And keeping CNR cells string-guaranteed is what lets
     * {@see \CNIC\ColumnInterface::getStringByIndex()} never answer null-for-wrong-type
     * on this brand: the accessor is only as self-explaining as the data behind it.
     *
     * Both checks **throw** rather than skipping or coercing: silently dropping
     * data would be the no-op this project rules out, and coercing would invent a
     * value the wire could never carry. Keep the container check as strict as the
     * cells — casting it with (array) instead quietly turns a bare string into a
     * one-cell column while a bare int still throws one line later, coercing a bad
     * container while refusing a bad cell.
     *
     * Note the deliberate asymmetry with populate(): a *missing or non-array*
     * PROPERTY block yields no columns rather than throwing, because most CNR
     * responses legitimately have none. This is about the contents of a block that
     * does exist.
     *
     * Written as an explicit loop rather than array_filter(..., "is_string"):
     * PHPStan narrows that form, Psalm does not (MixedReturnTypeCoercion), and
     * the loop leaves only the one MixedAssignment below to suppress — the same
     * trade already made in AbstractResponse::assembleRecords().
     * @return string[]
     * @throws UnsupportedFeatureException if the entry is not an array, or a cell is not a string
     */
    private static function stringCells(string $key, mixed $values): array
    {
        if (!is_array($values)) {
            throw new UnsupportedFeatureException(
                "CNR columns are string lists: PROPERTY[{$key}] is a " . get_debug_type($values) . "."
            );
        }
        $cells = [];
        /** @psalm-suppress MixedAssignment the loop variable is mixed by design; is_string() narrows it below */
        foreach ($values as $cell) {
            if (!is_string($cell)) {
                throw new UnsupportedFeatureException(
                    "CNR columns are string-valued: PROPERTY[{$key}] carries a " . get_debug_type($cell) . "."
                );
            }
            $cells[] = $cell;
        }
        return $cells;
    }

    /**
     * Get API response code
     */
    #[\Override]
    public function getCode(): int
    {
        return intval($this->getHashString("CODE"), 10);
    }

    /**
     * Get API response description
     */
    #[\Override]
    public function getDescription(): string
    {
        return $this->getHashString("DESCRIPTION");
    }

    /**
     * Get Queuetime of API response
     */
    #[\Override]
    public function getQueuetime(): float
    {
        if (array_key_exists("QUEUETIME", $this->hash)) {
            return floatval($this->getHashString("QUEUETIME"));
        }
        return 0.00;
    }

    /**
     * Get Runtime of API response
     */
    #[\Override]
    public function getRuntime(): float
    {
        if (array_key_exists("RUNTIME", $this->hash)) {
            return floatval($this->getHashString("RUNTIME"));
        }
        return 0.00;
    }

    /**
     * Check if current API response represents an error case
     * API response code is an 5xx code
     */
    #[\Override]
    public function isError(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "5";
    }

    /**
     * Check if current API response represents a success case
     * API response code is an 2xx code
     */
    #[\Override]
    public function isSuccess(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "2";
    }

    /**
     * Check if current API response represents a temporary error case
     * API response code is an 4xx code
     */
    #[\Override]
    public function isTmpError(): bool
    {
        return substr($this->getHashString("CODE"), 0, 1) === "4";
    }

    /**
     * Check if current operation is returned as pending
     */
    #[\Override]
    public function isPending(): bool
    {
        return isset($this->hash["PENDING"]) && $this->hash["PENDING"] === "1";
    }

    /**
     * Add a column to the column list
     *
     * CNR responses are plaintext, so column values are always strings —
     * stringCells() guarantees it before this is called. The shared CNIC\Column
     * is nonetheless used as-is, exactly like IBS\Response::addColumn(): the
     * value type is expressed to consumers by getStringByIndex(), not by a
     * column subclass (RSRMID-2942). See IBS\Response::addColumn() for why each
     * brand builds its Column locally and hands the finished instance to the
     * shared registerColumn() bookkeeping rather than to a shared factory.
     *
     * Protected since RSRMID-2939, and called only from populate(): records are
     * assembled from the columns once, at the end of construction, so a column
     * added afterwards was absent from every record — present in getColumns() and
     * getColumnKeys(), invisible to getRecord()/getRecords() and to iteration.
     * @param string[] $data array of column data, already narrowed by stringCells()
     */
    protected function addColumn(string $columnName, array $data): static
    {
        return $this->registerColumn(new Column($columnName, $data));
    }

    /**
     * Instantiate the record type for this brand.
     * @param array<string,mixed> $row
     */
    #[\Override]
    protected function newRecord(array $row): Record
    {
        return new Record($row);
    }

    /**
     * Instantiate the response parser for this brand.
     */
    #[\Override]
    protected function newResponseParser(): ResponseParserInterface
    {
        return new RP();
    }

    /**
     * Coerce a raw pagination column value to a base-10 integer.
     *
     * getStringByIndex() already narrows to ?string (CNR cells are always
     * strings, unlike IBS's, which may carry nested arrays/objects); this
     * just re-narrows a missing/absent value to null so the caller can fall
     * back.
     */
    private function columnInt(?string $value): ?int
    {
        return $value === null ? null : intval($value, 10);
    }

    /**
     * Get Index of first row in this response
     */
    #[\Override]
    public function getFirstRecordIndex(): ?int
    {
        $col = $this->getColumn("FIRST");
        if ($col instanceof ColumnInterface) {
            return $this->columnInt($col->getStringByIndex(0)) ?? 0;
        }
        if ($this->getRecordsCount() !== 0) {
            return 0;
        }
        return null;
    }

    /**
     * Get last record index of the current list query
     */
    #[\Override]
    public function getLastRecordIndex(): ?int
    {
        $col = $this->getColumn("LAST");
        if ($col instanceof ColumnInterface) {
            $l = $this->columnInt($col->getStringByIndex(0));
            if ($l !== null) {
                return $l;
            }
        }
        $c = $this->getRecordsCount();
        if ($c !== 0) {
            return $c - 1;
        }
        return null;
    }

    /**
     * Get Response as List Hash including useful meta data for tables
     * @return array{LIST: list<array<string, mixed>>, meta: array{columns: string[], pg: array<string, int|null>}}
     */
    #[\Override]
    public function getListHash(): array
    {
        // Resolve the pagination-stripped column set once (regex runs a single
        // time inside getColumnKeys(true)); reuse it to filter every row via
        // array_intersect_key instead of a per-cell preg_match. Record data keys
        // are always a subset of the column keys (see assembleRecords()), so this
        // yields output identical to unsetting each pagination-matching cell.
        $columns = $this->getColumnKeys(true);
        $keepKeys = array_flip($columns);
        $lh = [];
        foreach ($this->records as $rec) {
            $lh[] = array_intersect_key($rec->getData(), $keepKeys);
        }
        return [
            "LIST" => $lh,
            "meta" => [
                "columns" => $columns,
                "pg" => $this->getPagination()
            ]
        ];
    }

    /**
     * Get total count of records available for the list query, or `null` when
     * this response carries no TOTAL column (a non-list response).
     *
     * No `getRecordsCount()` fallback (RSRMID-2943): a non-list response now
     * reports "no total" honestly instead of a count that only happened to
     * equal the record count.
     */
    #[\Override]
    public function getRecordsTotalCount(): ?int
    {
        $col = $this->getColumn("TOTAL");
        return $col instanceof ColumnInterface ? $this->columnInt($col->getStringByIndex(0)) : null;
    }

    /**
     * Get limit(ation) setting of the current list query — the count of
     * requested rows — or `null` when this response carries no LIMIT column.
     *
     * No `getRecordsCount()` fallback (RSRMID-2943), for the same reason as
     * {@see getRecordsTotalCount()}: `0` is a real, requested limit and must
     * stay distinguishable from "this response carries no LIMIT column at
     * all".
     */
    #[\Override]
    public function getRecordsLimitation(): ?int
    {
        $col = $this->getColumn("LIMIT");
        return $col instanceof ColumnInterface ? $this->columnInt($col->getStringByIndex(0)) : null;
    }
}
