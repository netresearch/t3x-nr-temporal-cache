#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Simple code coverage threshold checker for Clover XML format.
 *
 * Usage: php check-coverage.php <clover.xml> <threshold>
 *
 * Example: php check-coverage.php .Build/logs/clover.xml 70
 */

if ($argc < 3) {
    echo "Usage: php check-coverage.php <clover.xml> <threshold>\n";
    exit(1);
}

$cloverFile = $argv[1];
$threshold = (float) $argv[2];

if (!file_exists($cloverFile)) {
    echo "Error: Clover file not found: {$cloverFile}\n";
    exit(1);
}

$xml = simplexml_load_file($cloverFile);
if ($xml === false) {
    echo "Error: Failed to parse Clover XML file\n";
    exit(1);
}

// Extract metrics from Clover XML
$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    echo "Error: No metrics found in Clover file\n";
    exit(1);
}

$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    echo "Warning: No statements found in coverage report\n";
    $coverage = 0.0;
} else {
    $coverage = ($coveredStatements / $statements) * 100;
}

echo sprintf(
    "Code Coverage: %.2f%% (%d/%d statements)\n",
    $coverage,
    $coveredStatements,
    $statements
);
echo sprintf("Threshold: %.2f%%\n", $threshold);

if ($coverage < $threshold) {
    echo sprintf(
        "FAIL: Coverage %.2f%% is below threshold %.2f%%\n",
        $coverage,
        $threshold
    );
    exit(1);
}

echo "OK: Coverage meets threshold\n";
exit(0);
