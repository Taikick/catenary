<?php

declare(strict_types=1);

use Taikick\Collections\Vec;

require dirname(__DIR__) . '/vendor/autoload.php';
$peerAutoload = __DIR__ . '/peers/vendor/autoload.php';
if (is_file($peerAutoload)) {
    require $peerAutoload;
}

/**
 * Measures equivalent operations after warmup and verifies every returned value.
 * ウォームアップ後に同等な操作を測定し、毎回の返却値を検証します。
 *
 * @param callable(): mixed $operation
 * @return array{median_ms: float, median_additional_peak_bytes: int}
 */
$measure = static function (callable $operation, mixed $expected, int $iterations): array {
    if ($operation() !== $expected) {
        throw new RuntimeException('Benchmark warmup produced a different result.');
    }
    $times = [];
    $peaks = [];
    for ($run = 0; $run < $iterations; ++$run) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $start = hrtime(true);
        $result = $operation();
        $times[] = (hrtime(true) - $start) / 1_000_000;
        $peaks[] = max(0, memory_get_peak_usage() - $before);
        if ($result !== $expected) {
            throw new RuntimeException('Benchmark produced a different result.');
        }
        unset($result);
    }
    sort($times);
    sort($peaks);
    $middle = intdiv($iterations, 2);
    return ['median_ms' => round($times[$middle], 4), 'median_additional_peak_bytes' => $peaks[$middle]];
};

$options = getopt('', ['size:', 'iterations:']);
$size = filter_var($options['size'] ?? 20_000, FILTER_VALIDATE_INT);
$iterations = filter_var($options['iterations'] ?? 7, FILTER_VALIDATE_INT);
if ($size === false || $size < 1 || $iterations === false || $iterations < 1 || $iterations % 2 !== 1) {
    fwrite(STDERR, "Use --size=<positive integer> and --iterations=<positive odd integer>.\n");
    exit(1);
}

$values = range(0, $size - 1);
$unique = [
    'native_strict_key_loop' => static function () use ($values): array {
        $seen = [];
        $result = [];
        foreach ($values as $value) {
            $key = 'i:' . $value;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $value;
            }
        }
        return $result;
    },
    'vec_unique_by' => static fn (): array => Vec::fromIterable($values)->uniqueBy(fn (int $n): int => $n)->toList(),
];
if (class_exists(ScalikePHP\Seq::class)) {
    $unique['scalikephp_0_8_3_distinct_by'] = static fn (): array =>
        ScalikePHP\Seq::fromArray($values)->distinctBy(fn (int $n): int => $n)->toArray();
}

$results = [];
foreach ($unique as $name => $operation) {
    $results['unique_integer_ids'][$name] = $measure($operation, $values, $iterations);
}

$eager = static fn (): array => Vec::fromIterable($values)->map(fn (int $n): int => $n * 2)->take(10)->toList();
$lazy = static fn (): array => Vec::fromIterable($values)
    ->stream()->map(fn (int $n): int => $n * 2)->take(10)->toVec()->toList();
$expected = array_map(fn (int $n): int => $n * 2, array_slice($values, 0, 10));
$results['map_then_take_10']['eager'] = $measure($eager, $expected, $iterations);
$results['map_then_take_10']['lazy'] = $measure($lazy, $expected, $iterations);

echo json_encode([
    'environment' => [
        'php' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'architecture' => php_uname('m'),
        'xdebug_loaded' => extension_loaded('xdebug'),
        'opcache_cli' => ini_get('opcache.enable_cli'),
        'jit' => ini_get('opcache.jit'),
        'jit_buffer_size' => ini_get('opcache.jit_buffer_size'),
        'size' => $size,
        'iterations' => $iterations,
        'scalikephp' => class_exists(ScalikePHP\Seq::class) ? '0.8.3' : null,
    ],
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
