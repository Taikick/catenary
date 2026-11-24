<?php

declare(strict_types=1);

$filename = $argv[1] ?? dirname(__DIR__) . '/build/coverage.xml';
$report = simplexml_load_file($filename);
if ($report === false) {
    throw new RuntimeException('Could not load the Clover coverage report.');
}
$metrics = $report->project->metrics;
$total = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$percent = $total > 0 ? 100 * $covered / $total : 0;
echo sprintf("Line coverage: %.2f%% (%d/%d), required: 95%%\n", $percent, $covered, $total);
exit($percent >= 95 ? 0 : 1);
