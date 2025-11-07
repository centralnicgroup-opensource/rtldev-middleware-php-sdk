<?php

ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

define("CSV_PROCESS_LIMIT", 0); // 0 = unlimited
define("CSV_SEPARATOR", ",");

/**
 * Load and parse CSV data from aeserver_domains.csv
 * 
 * @param int $maxLines Maximum number of data rows to load (0 = unlimited)
 * @return array<int, array<string, string>> Array of associative arrays with CSV data
 */
function loadAEServerDomains(int $maxLines = 10): array
{
    $csvFile = __DIR__ . '/domains_aeserver_deletiondate.csv';
    
    if (!file_exists($csvFile)) {
        throw new \Exception("CSV file not found: " . $csvFile);
    }
    
    if (!is_readable($csvFile)) {
        throw new \Exception("CSV file is not readable: " . $csvFile);
    }
    
    $data = [];
    $headers = [];
    $lineNumber = 0;
    $dataRows = 0;
    
    if (($handle = fopen($csvFile, 'r')) !== false) {
        try {
            // Read the header row - using semicolon as delimiter
            if (($headerRow = fgetcsv($handle, 0, CSV_SEPARATOR)) !== false) {
                $headers = cleanHeaders($headerRow);
                if (empty($headers) || empty(array_filter($headers))) {
                    throw new \Exception("Invalid or empty header row in CSV");
                }
            } else {
                throw new \Exception("Could not read header row from CSV");
            }

            // successfully fetched header
            $lineNumber++;
            
            // Read data rows - using semicolon as delimiter
            while (($row = fgetcsv($handle, 0, CSV_SEPARATOR)) !== false) {              
                $lineNumber++;

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate row length matches header count
                if (count($row) !== count($headers)) {
                    $idx = $lineNumber - 1;
                    error_log("Warning: Row at Index #{$idx} has " . count($row) . " columns, expected " . count($headers));
                    continue;
                }

                // Trim whitespace from values and combine with headers
                $trimmedRow = array_map('trim', $row);

                $data[] = array_combine($headers, $trimmedRow);
                $dataRows++;
                
                // Check max lines limit (0 means unlimited)
                if ($maxLines > 0 && $dataRows >= $maxLines) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    } else {
        throw new \Exception("Could not open CSV file: " . $csvFile);
    }

    foreach($data as &$d) {
        if (isset($d["deletion_date"]) && $d["deletion_date"] === "UNDEF") {
            unset($d["deletion_date"]);
        }
    }
    return $data;
}


/**
 * Cast our UTC API timestamps to local timestamp string and unix timestamp
 * @param string $date API timestamp (YYYY-MM-DD HH:ii:ss)
 * @return array{ts:int,short:string,long:string}
 */
function castDate(string $date): array
{
    // $date at CNR e.g. 2024-12-10 13:17:55.0
    $utcDate = str_replace(" ", "T", $date) . "Z"; //RFC 3339, T date/time separator, Z for UTC suffix
    $ts = (int)strtotime($utcDate);
    return [
        "ts" => $ts,
        "short" => date("Y-m-d", $ts),
        "long" => date("Y-m-d H:i:s", $ts)
    ];
}

/**
 * Print a formatted table row
 * @param array<string> $columns
 * @param array<int> $widths
 */
function printTableRow(array $columns, array $widths): void
{
    echo "| ";
    foreach ($columns as $i => $column) {
        $padded = str_pad(substr($column, 0, $widths[$i]), $widths[$i]);
        echo $padded . " | ";
    }
    echo "\n";
}

/**
 * Print table separator
 * @param array<int> $widths
 */
function printTableSeparator(array $widths): void
{
    echo "+";
    foreach ($widths as $width) {
        echo str_repeat("-", $width + 2) . "+";
    }
    echo "\n";
}

/**
 * Clean CSV headers by removing BOM, invisible characters, and extra whitespace
 * @param array<string> $headers Raw headers from CSV
 * @return array<string> Cleaned headers
 */
function cleanHeaders(array $headers): array
{
    return array_map(function(string $header): string {
        // Remove BOM (Byte Order Mark) if present
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        
        // Remove all invisible characters (including tabs, newlines, etc.)
        $header = preg_replace('/[\x00-\x1F\x7F-\x9F]/', '', $header);
        
        // Remove non-breaking spaces and other Unicode whitespace
        $header = preg_replace('/[\x{00A0}\x{FEFF}]/u', '', $header);
        
        // Trim regular whitespace
        $header = trim($header);
        
        return $header;
    }, $headers);
}

function calculateExpiryDatesCurrent(array $domain): string
{
    $expirationTS = castDate($domain['paid_until'] ?? $domain['registration_expiration_date']);
    
    // Check if renewal date is earlier
    if (!empty($domain['renewaldate'])) {
        $renewalTS = castDate($domain['renewaldate']);
        if ($renewalTS["ts"] < $expirationTS["ts"]) {
            $expirationTS = $renewalTS;
        }
    }
    
    // Apply renewal mode logic
    if (isset($domain['domain_renewalmode']) && $domain['domain_renewalmode'] === "RENEWONCE") {
        $periods = $domain['renewal_periods'] ?? [1];
        $ts = strtotime($expirationTS["long"] . " +$periods[0] year");
    } else {
        $ts = (int) $expirationTS["ts"];
    }

    return $ts;
}

function calculateExpiryDatesNew(array $domain): string
{
    // cast our UTC API timestamp format to useful formats in local timezone
    $expirationdate = castDate($domain["registration_expiration_date"]);
    $paiduntildate = castDate($domain["paid_until"]);

    $cnr_account_renewal_mode = strtoupper($domain["registrar_renewalmode"]); // ToDo: read from account
    $domain_renewal_mode = strtoupper($domain["domain_renewalmode"]);

    $auto_renew = (
        ($domain_renewal_mode === "AUTORENEW" || $domain_renewal_mode === "RENEWONCE")
        || ($domain_renewal_mode === "DEFAULT" && ($cnr_account_renewal_mode === "DEFAULT" || $cnr_account_renewal_mode === "AUTORENEW"))
    );
    $isExtendedLifeCycle = isset($domain["deletion_date"]) && !$auto_renew;

    // --- Extended Life Cycle is turned on and deletion_date is set
    $ts = $expirationdate["ts"];
    $paiduntildate = castDate($domain["paid_until"]);
    if ($isExtendedLifeCycle) {
        $deletiondate = castDate($domain["deletion_date"]);
        if ($deletiondate["ts"] > $paiduntildate["ts"]) {
            return $paiduntildate["ts"];
        }
    }

    // --- Standard Life Cycle
    $ts = $paiduntildate;
    if (isset($domain["renewaldate"])) {
        $renewaldate = castDate($domain["renewaldate"]);
        if ($renewaldate["ts"] < $paiduntildate["ts"]) {
            $ts = $renewaldate;
        }
    }

    // --- Special Handling of requested renewal via "RENEWONCE"
    if (isset($domain['domain_renewalmode']) && $domain['domain_renewalmode'] === "RENEWONCE") {
        $periods = $domain['renewal_periods'] ?? [1];
        return strtotime($ts["long"] . " +$periods[0] year"); // TODO: handle false
    }

    return (int)$ts["ts"];
}

/**
 * Load and parse CSV data from hexonet.csv
 * 
 * @param int $maxLines Maximum number of data rows to load (0 = unlimited)
 * @return array<int, array<string, string>> Array of associative arrays with CSV data
 */
function loadHexonetDomains(int $maxLines = 0): array
{
    $csvFile = __DIR__ . '/hexonet.csv';
    
    if (!file_exists($csvFile)) {
        throw new \Exception("CSV file not found: " . $csvFile);
    }
    
    if (!is_readable($csvFile)) {
        throw new \Exception("CSV file is not readable: " . $csvFile);
    }
    
    $data = [];
    $headers = [];
    $lineNumber = 0;
    $dataRows = 0;
    
    if (($handle = fopen($csvFile, 'r')) !== false) {
        try {
            // Read the header row - hexonet CSV uses comma as delimiter
            if (($headerRow = fgetcsv($handle, 0, ',')) !== false) {
                $headers = cleanHeaders($headerRow);
                
                // Remove quotes from headers if present
                $headers = array_map(function($header) {
                    return trim($header, '"');
                }, $headers);
                
                if (empty($headers) || empty(array_filter($headers))) {
                    throw new \Exception("Invalid or empty header row in CSV");
                }
            } else {
                throw new \Exception("Could not read header row from CSV");
            }

            // Successfully fetched header
            $lineNumber++;
            
            // Read data rows
            while (($row = fgetcsv($handle, 0, ',')) !== false) {              
                $lineNumber++;

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Validate row length matches header count
                if (count($row) !== count($headers)) {
                    $idx = $lineNumber - 1;
                    error_log("Warning: Row at Index #{$idx} has " . count($row) . " columns, expected " . count($headers));
                    continue;
                }

                // Trim whitespace and quotes from values, then combine with headers
                $trimmedRow = array_map(function($value) {
                    return trim($value, " \t\n\r\0\x0B\"");
                }, $row);

                $data[] = array_combine($headers, $trimmedRow);
                $dataRows++;
                
                // Check max lines limit (0 means unlimited)
                if ($maxLines > 0 && $dataRows >= $maxLines) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }
    } else {
        throw new \Exception("Could not open CSV file: " . $csvFile);
    }

    // Clean up undefined values and normalize data
    foreach($data as &$d) {
        // Remove or normalize undefined/empty values
        foreach ($d as $key => &$value) {
            if ($value === '' || $value === 'UNDEF' || $value === 'NULL') {
                unset($d[$key]);
            }
        }
        
        // Normalize the domain name field if it exists under different column names
        if (isset($d['OBJECTID']) && !isset($d['domain'])) {
            $d['domain'] = $d['OBJECTID'];
        }
    }
    
    return $data;
}

// Usage example:
try {
    $diff_days_domains = [];

    // Load AE Server domains (existing)
    $aeserverDomains = loadAEServerDomains(CSV_PROCESS_LIMIT); // CSV_PROCESS_LIMIT = 0 => unlimited

    // Load Hexonet domains (new)
    $hexonetDomains = loadHexonetDomains(CSV_PROCESS_LIMIT);

    echo "\n" . str_repeat("=", 120) . "\n";
    echo "AESERVER DOMAINS PROCESSING REPORT\n";
    echo str_repeat("=", 120) . "\n";
    echo "Total domains loaded: " . count($aeserverDomains) . "\n\n";
    
    // Define column widths for table
    $headers = ['Domain', 'Renewal Date', 'Paid Until', 'Reg Expiry', 'Deletion Date', 'Mode', 'WHMCS Expiry (OLD)', 'WHMCS Expiry (NEW)', 'Diff days'];
    $widths = [50, 19, 19, 19, 19, 12, 19, 19, 10];
    $dlength = 0;
    $longest = '';
    foreach($aeserverDomains as $domain) {
        if (strlen($domain['domain']) > $dlength) {
            $dlength = strlen($domain['domain']);
            $longest = $domain['domain'];
        }
    }
    $widths[0] = round($dlength + 10);
    
    // Print table header
    printTableSeparator($widths);
    printTableRow($headers, $widths);
    printTableSeparator($widths);
    
    // Process and display each domain
    $tlds = [];
    $lineNumber = 0;
    foreach ($aeserverDomains as &$domain) {
        // Calculate expiry date (OLD)
        $domain['whmcs_expiry_current'] = calculateExpiryDatesCurrent($domain);

        // Calculate expiry date (NEW)
        $domain['whmcs_expiry_new'] = calculateExpiryDatesNew($domain);

        // Calculate difference in days
        $domain['whmcs_expiry_diff'] = round( ($domain['whmcs_expiry_new'] - $domain['whmcs_expiry_current']) / (60*60*24) );
        if ( ($domain['whmcs_expiry_diff'] > 2) || ($domain['whmcs_expiry_diff'] < -2) ) {
            $diff_days_domains[] = $domain['domain'];
        }
        $domain['whmcs_expiry_diff'] .= "d";

        // Prepare row data
        $rowData = [
            $domain['domain'] ?? 'N/A',
            isset($domain['renewaldate']) ? $domain['renewaldate'] : 'N/A',
            isset($domain['paid_until']) ? $domain['paid_until'] : 'N/A',
            isset($domain['registration_expiration_date']) ? $domain['registration_expiration_date'] : 'N/A',
            isset($domain['deletion_date']) ? $domain['deletion_date'] : 'N/A',
            $domain['domain_renewalmode'] ?? 'N/A',
            date("Y-m-d H:i:s", $domain['whmcs_expiry_current']),
            date("Y-m-d H:i:s", $domain['whmcs_expiry_new']),
            $domain['whmcs_expiry_diff']
        ];
        
        // Print table row
        printTableRow($rowData, $widths);

        list($sld, $tld) = explode('.', $domain['domain'], 2);
        if (!isset($tlds[$tld])) {
            $tlds[$tld] = ["count" => 0, "diff" => $domain['whmcs_expiry_diff']];
        }
        $tlds[$tld]["count"]++;
    }
    printTableSeparator($widths);

    echo "\nlongest domain: " . $longest . " (length: " . strlen($longest) . ")\n\n";
    // Sort TLDs alphabetically
    ksort($tlds);
    
    echo "\nTLD SUMMARY\n";
    echo str_repeat("=", 30) . "\n";
    
    // Print TLD table header
    $tldHeaders = ['TLD', 'Count', 'Diff Days'];
    $tldWidths = [30, 10, 10];
    
    printTableSeparator($tldWidths);
    printTableRow($tldHeaders, $tldWidths);
    printTableSeparator($tldWidths);

    // Print TLD data
    foreach ($tlds as $tld => $data) {
        printTableRow([$tld, (string)$data["count"], $data["diff"]], $tldWidths);
    }
    
    printTableSeparator($tldWidths);
    echo "Total TLDs: " . count($tlds) . "\n";

    // DOMAINS WITH SIGNIFICANT EXPIRY DATE DIFFERENCES
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "DOMAINS WITH EXPIRY DATE DIFFERENCES (>2 days)\n";
    echo str_repeat("=", 80) . "\n";
    
    if (empty($diff_days_domains)) {
        echo "✅ No domains found with significant expiry date differences.\n";
    } else {
        echo "Found " . count($diff_days_domains) . " domain(s) with differences > 2 days:\n\n";
        
        // Sort domains alphabetically
        sort($diff_days_domains);
        
        // Group domains by first letter for better readability
        $groupedDomains = [];
        foreach ($diff_days_domains as $domain) {
            $firstLetter = strtoupper($domain[0]);
            $groupedDomains[$firstLetter][] = $domain;
        }
        
        $counter = 1;
        foreach ($groupedDomains as $letter => $domains) {
            echo "[$letter]\n";
            foreach ($domains as $domain) {
                echo sprintf("  %3d. %s\n", $counter++, $domain);
            }
            echo "\n";
        }
        
        echo "⚠️  These domains require attention due to significant expiry date changes.\n";
    }
    
} catch (\Exception $e) {
    echo "Error loading CSV: " . $e->getMessage() . "\n";
}
