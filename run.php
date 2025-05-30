<?php

/**
 * CLI Script to run the Deduplicator class.
 *
 * Usage:
 * php run.php --input=leads.json --output=output.json [--log=changes.log]
 */

require_once 'Deduplicator.php';

// CLI options/inputs/logs
$options = getopt("", ["input:", "output:", "log::"]);

function printUsage(): void {
    echo "Usage: php run.php " .
         "--input=leads.json " .
         "--output=output.json [--log=changes.log]" 
         . PHP_EOL;
}

// Validate args
if (!isset($options['input']) || !isset($options['output'])) {
    echo "Error: Both --input " .
         "and --output parameters are required." . PHP_EOL;
    printUsage();
    exit(1);
}

$inputFile = trim($options['input']);
$outputFile = trim($options['output']);
$logFile = isset($options['log']) ? trim($options['log']) : 'changes.log';

// Validate
if ($inputFile === '') {
    echo "Error: --input parameter cannot be empty." . PHP_EOL;
    printUsage();
    exit(1);
}
if ($outputFile === '') {
    echo "Error: --output parameter cannot be empty." . PHP_EOL;
    printUsage();
    exit(1);
}
if ($logFile === '') {
    echo "Error: --log parameter cannot be empty if provided." . PHP_EOL;
    printUsage();
    exit(1);
}

// Check input file
if (!file_exists($inputFile) || !is_file($inputFile)) {
    echo "Error: Input file '{$inputFile}' " .
         "does not exist or is not a file." . PHP_EOL;
    exit(1);
}

// Check Output path
if (is_dir($outputFile)) {
    echo "Error: Output path '{$outputFile}' " .
         "is a directory, not a file." . PHP_EOL;
    exit(1);
}

// Check log path
if (is_dir($logFile)) {
    echo "Error: Log path '{$logFile}' is a " .
         "directory, not a file." . PHP_EOL;
    exit(1);
}

// CHeck write permissions
$parentOutputDir = dirname($outputFile);
if (!is_writable($parentOutputDir)) {
    echo "Error: No write permission to the " .
         "output directory '{$parentOutputDir}'." . PHP_EOL;
    exit(1);
}
$parentLogDir = dirname($logFile);
if (!is_writable($parentLogDir)) {
    echo "Error: No write permission to the " .
         "log directory '{$parentLogDir}'." . PHP_EOL;
    exit(1);
}

try {
    $deduplicator = new Deduplicator($inputFile, $logFile);
    $deduplicator->deduplicate();
    $deduplicator->saveOutput($outputFile);

    // Success message
    echo "Deduplication complete." . PHP_EOL;
    echo "Results saved to {$outputFile}." . PHP_EOL;
    echo "Log written to {$logFile}." . PHP_EOL;
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

?>
