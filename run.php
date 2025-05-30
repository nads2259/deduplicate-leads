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

// Validate
if (!isset($options['input']) || !isset($options['output'])) {
    echo "Usage: php run.php --input=leads.json --output=output.json [--log=changes.log]\n";
    exit(1);
}

// Extract
$inputFile = $options['input'];
$outputFile = $options['output'];
$logFile = $options['log'] ?? 'changes.log';

// Execute
$deduplicator = new Deduplicator($inputFile, $logFile);
$deduplicator->deduplicate();
// Save Output
$deduplicator->saveOutput($outputFile);

// Message
echo "Deduplication complete.\n";
echo "Results saved to {$outputFile}.\n";
echo "Log written to {$logFile}.\n";

?>
