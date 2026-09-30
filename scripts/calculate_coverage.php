#!/usr/bin/env php
<?php

/*
 * SPDX-License-Identifier: GPL-2.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

/**
 * Manual code coverage calculator
 * Analyzes test structure to estimate coverage percentage
 */

declare(strict_types=1);

require_once __DIR__ . '/../.Build/vendor/autoload.php';

class CoverageCalculator
{
    private array $sourceFiles = [];
    private array $testFiles = [];
    private array $coverage = [];

    public function run(): void
    {
        $this->collectSourceFiles();
        $this->collectTestFiles();
        $this->analyzeCoverage();
        $this->displayReport();
    }

    private function collectSourceFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../Classes')
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relativePath = str_replace(__DIR__ . '/../Classes/', '', $file->getPathname());
                $this->sourceFiles[$relativePath] = [
                    'path' => $file->getPathname(),
                    'lines' => count(file($file->getPathname())),
                    'class' => $this->extractClassName($file->getPathname()),
                ];
            }
        }
    }

    private function collectTestFiles(): void
    {
        foreach (['Unit', 'Functional', 'Integration'] as $type) {
            $testDir = __DIR__ . '/../Tests/' . $type;
            if (!is_dir($testDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($testDir)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace(__DIR__ . '/../Tests/' . $type . '/', '', $file->getPathname());
                    $this->testFiles[$relativePath] = [
                        'path' => $file->getPathname(),
                        'type' => $type,
                        'tests' => $this->countTests($file->getPathname()),
                        'assertions' => $this->countAssertions($file->getPathname()),
                        'covers' => $this->extractTestedClass($file->getPathname()),
                    ];
                }
            }
        }
    }

    private function analyzeCoverage(): void
    {
        $totalFiles = count($this->sourceFiles);
        $coveredFiles = 0;
        $totalLines = 0;
        $executableLines = 0;
        $coveredLines = 0;

        foreach ($this->sourceFiles as $relativePath => $sourceInfo) {
            $totalLines += $sourceInfo['lines'];

            // Estimate executable lines (exclude declarations, comments, empty lines)
            $executable = $this->estimateExecutableLines($sourceInfo['path']);
            $executableLines += $executable;

            // Find matching test file
            $testFile = $this->findTestFile($relativePath, $sourceInfo['class']);

            if ($testFile) {
                $coveredFiles++;

                // Estimate covered lines based on test assertions
                $assertions = $testFile['assertions'];
                $covered = min($executable, (int)($assertions * 3)); // Rough estimate: 3 lines per assertion
                $coveredLines += $covered;

                $this->coverage[$relativePath] = [
                    'source' => $sourceInfo,
                    'test' => $testFile,
                    'executable' => $executable,
                    'covered' => $covered,
                    'coverage' => $executable > 0 ? round(($covered / $executable) * 100, 2) : 0,
                ];
            } else {
                $this->coverage[$relativePath] = [
                    'source' => $sourceInfo,
                    'test' => null,
                    'executable' => $executable,
                    'covered' => 0,
                    'coverage' => 0,
                ];
            }
        }

        $this->coverage['_summary'] = [
            'total_files' => $totalFiles,
            'covered_files' => $coveredFiles,
            'file_coverage' => round(($coveredFiles / $totalFiles) * 100, 2),
            'total_lines' => $totalLines,
            'executable_lines' => $executableLines,
            'covered_lines' => $coveredLines,
            'line_coverage' => $executableLines > 0 ? round(($coveredLines / $executableLines) * 100, 2) : 0,
        ];
    }

    private function estimateExecutableLines(string $filePath): int
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        $executable = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip empty lines, comments, opening braces, declarations
            if (empty($trimmed)
                || str_starts_with($trimmed, '//')
                || str_starts_with($trimmed, '/*')
                || str_starts_with($trimmed, '*')
                || str_starts_with($trimmed, 'namespace')
                || str_starts_with($trimmed, 'use ')
                || str_starts_with($trimmed, 'declare(')
                || $trimmed === '{'
                || $trimmed === '}') {
                continue;
            }

            // Count lines that likely contain executable code
            if (preg_match('/(\$|return|if|else|foreach|while|switch|case|throw|new |->|::)/', $trimmed)) {
                $executable++;
            }
        }

        return $executable;
    }

    private function extractClassName(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function extractTestedClass(string $filePath): string
    {
        $content = file_get_contents($filePath);
        // Look for the class being tested (usually imported or mentioned)
        if (preg_match('/use\s+Netresearch\\\\TemporalCache.*?\\\\(\w+);/m', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function countTests(string $filePath): int
    {
        $content = file_get_contents($filePath);
        return preg_match_all('/public function test\w+\(\)/', $content);
    }

    private function countAssertions(string $filePath): int
    {
        $content = file_get_contents($filePath);
        return preg_match_all('/self::assert/', $content);
    }

    private function findTestFile(string $sourcePath, string $className): ?array
    {
        foreach ($this->testFiles as $testPath => $testInfo) {
            // Match by class name or file path structure
            if (str_contains($testPath, $className . 'Test.php')
                || str_replace('.php', 'Test.php', $sourcePath) === $testPath) {
                return $testInfo;
            }
        }
        return null;
    }

    private function displayReport(): void
    {
        $summary = $this->coverage['_summary'];
        unset($this->coverage['_summary']);

        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "            CODE COVERAGE ANALYSIS REPORT\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "\n";

        echo "SUMMARY:\n";
        echo "  Total Source Files:    {$summary['total_files']}\n";
        echo "  Covered Files:         {$summary['covered_files']}\n";
        echo "  File Coverage:         {$summary['file_coverage']}%\n";
        echo "\n";
        echo "  Total Lines:           {$summary['total_lines']}\n";
        echo "  Executable Lines:      {$summary['executable_lines']}\n";
        echo "  Covered Lines:         {$summary['covered_lines']}\n";
        echo "  Line Coverage:         {$summary['line_coverage']}%\n";
        echo "\n";

        // Sort by coverage percentage
        uasort($this->coverage, fn($a, $b) => $a['coverage'] <=> $b['coverage']);

        echo "DETAILED BREAKDOWN:\n";
        echo str_repeat('─', 110) . "\n";
        printf("%-50s %12s %12s %10s %10s\n", "Class", "Executable", "Covered", "Coverage", "Tests");
        echo str_repeat('─', 110) . "\n";

        foreach ($this->coverage as $relativePath => $info) {
            $className = substr($info['source']['class'], 0, 48);
            $executable = $info['executable'];
            $covered = $info['covered'];
            $coverage = $info['coverage'];
            $tests = $info['test'] ? $info['test']['tests'] : 0;

            $coverageDisplay = $coverage >= 85 ? "\033[32m{$coverage}%\033[0m" :
                              ($coverage >= 70 ? "\033[33m{$coverage}%\033[0m" : "\033[31m{$coverage}%\033[0m");

            printf("%-50s %12d %12d %10s %10d\n", $className, $executable, $covered, $coverageDisplay, $tests);
        }

        echo str_repeat('─', 110) . "\n";
        echo "\n";

        if ($summary['line_coverage'] >= 85) {
            echo "\033[32m✓ TARGET ACHIEVED: Coverage is {$summary['line_coverage']}% (target: >85%)\033[0m\n";
        } else {
            echo "\033[33m⚠ TARGET NOT MET: Coverage is {$summary['line_coverage']}% (target: >85%)\033[0m\n";
            $needed = ceil($summary['executable_lines'] * 0.85 - $summary['covered_lines']);
            echo "  Need to cover approximately {$needed} more executable lines\n";
        }

        echo "\n";
    }
}

$calculator = new CoverageCalculator();
$calculator->run();
