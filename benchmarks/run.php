<?php

declare(strict_types=1);

use Catenary\Dict;
use Catenary\Vec;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Measures equivalent operations after warmup and verifies every returned value.
 * ウォームアップ後に同等な操作を測定し、毎回の返却値を検証します。
 *
 * @param callable(): mixed $operation
 * @param mixed $expected
 * @param int $iterations
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

$snapshot = Vec::fromIterable($values);
$positions = ['first' => 0, 'middle' => intdiv($size, 2), 'last' => $size - 1, 'missing' => $size];
foreach ($positions as $position => $target) {
    $matches = static fn (int $value): bool => $value === $target;
    $accepts = static fn (int $value): bool => $value !== $target;
    $expectedValue = $target < $size ? $target : null;
    $results['find_' . $position] = [
        'filter_then_first' => $measure(
            static fn (): ?int => $snapshot->filter($matches)->first()->getOrElse(static fn () => null),
            $expectedValue,
            $iterations,
        ),
        'direct_find' => $measure(
            static fn (): ?int => $snapshot->find($matches)->getOrElse(static fn () => null),
            $expectedValue,
            $iterations,
        ),
    ];
    $results['any_' . $position] = [
        'filter_then_nonempty' => $measure(
            static fn (): bool => !$snapshot->filter($matches)->isEmpty(),
            $target < $size,
            $iterations,
        ),
        'direct_any' => $measure(static fn (): bool => $snapshot->any($matches), $target < $size, $iterations),
    ];
    $results['every_' . $position] = [
        'filter_then_count' => $measure(
            static fn (): bool => $snapshot->filter($accepts)->count() === $size,
            $target >= $size,
            $iterations,
        ),
        'direct_every' => $measure(static fn (): bool => $snapshot->every($accepts), $target >= $size, $iterations),
    ];
}
$results['reuse_existing_vec'] = [
    'recollect_iterator' => $measure(
        static fn (): array => Vec::fromIterable($snapshot->getIterator())->toList(),
        $values,
        $iterations,
    ),
    'reuse_snapshot' => $measure(static fn (): array => Vec::fromIterable($snapshot)->toList(), $values, $iterations),
];
if (Vec::fromIterable($snapshot) !== $snapshot) {
    throw new RuntimeException('Snapshot reuse must retain the Vec identity.');
}

$pairs = [];
$rightPairs = [];
$expectedMerged = [];
$newPairs = [];
$expectedFiltered = [];
foreach ($values as $value) {
    $pairs[] = [$value, $value];
    $pairs[] = [(string) $value, $value];
    $replacement = $value % 5 === 0 ? null : -$value;
    $rightPairs[] = [(string) $value, $replacement];
    $rightPairs[] = ['new:' . $value, $value];
    $expectedMerged[] = [$value, $value];
    $expectedMerged[] = [(string) $value, $replacement];
    $newPairs[] = ['new:' . $value, $value];
    if ($value % 2 === 0) {
        $expectedFiltered[] = [(string) $value, $value];
    }
}
$expectedMerged = [...$expectedMerged, ...$newPairs];
$dictionary = Dict::fromPairs(pairs: $pairs);
$rightDictionary = Dict::fromPairs(pairs: $rightPairs);
$results['dict_filter_typed_keys'] = [
    'native_pair_loop' => $measure(static function () use ($pairs): array {
        $filtered = [];
        foreach ($pairs as [$key, $value]) {
            if (is_string($key) && $value % 2 === 0) {
                $filtered[] = [$key, $value];
            }
        }
        return $filtered;
    }, $expectedFiltered, $iterations),
    'dict_filter' => $measure(
        static fn (): array => $dictionary
            ->filter(predicate: static fn (int $value, int|string $key): bool => is_string($key) && $value % 2 === 0)
            ->toPairs(),
        $expectedFiltered,
        $iterations,
    ),
];
foreach ($positions as $position => $target) {
    $matchesKey = static fn (int $value, int|string $key): bool => $key === (string) $target;
    $acceptsKey = static fn (int $value, int|string $key): bool => $key !== (string) $target;
    $expectedPair = $target < $size ? [(string) $target, $target] : null;
    $results['dict_find_' . $position] = [
        'filter_then_first_pair' => $measure(
            static fn (): ?array => $dictionary->filter(predicate: $matchesKey)->toPairs()[0] ?? null,
            $expectedPair,
            $iterations,
        ),
        'direct_find' => $measure(
            static fn (): ?array => $dictionary->find(predicate: $matchesKey)
                ->getOrElse(fallback: static fn () => null),
            $expectedPair,
            $iterations,
        ),
    ];
    $results['dict_any_' . $position] = [
        'filter_then_nonempty' => $measure(
            static fn (): bool => !$dictionary->filter(predicate: $matchesKey)->isEmpty(),
            $target < $size,
            $iterations,
        ),
        'direct_any' => $measure(
            static fn (): bool => $dictionary->any(predicate: $matchesKey),
            $target < $size,
            $iterations,
        ),
    ];
    $results['dict_every_' . $position] = [
        'filter_then_count' => $measure(
            static fn (): bool => $dictionary->filter(predicate: $acceptsKey)->count() === count($pairs),
            $target >= $size,
            $iterations,
        ),
        'direct_every' => $measure(
            static fn (): bool => $dictionary->every(predicate: $acceptsKey),
            $target >= $size,
            $iterations,
        ),
    ];
}
$results['dict_merge_typed_keys'] = [
    'native_encoded_pair_loop' => $measure(static function () use ($pairs, $rightPairs): array {
        $merged = [];
        foreach ([$pairs, $rightPairs] as $source) {
            foreach ($source as [$key, $value]) {
                $merged[(is_int($key) ? 'i:' : 's:') . $key] = [$key, $value];
            }
        }
        return array_values($merged);
    }, $expectedMerged, $iterations),
    'dict_merge' => $measure(
        static fn (): array => $dictionary->merge(other: $rightDictionary)->toPairs(),
        $expectedMerged,
        $iterations,
    ),
];
if ($dictionary->toPairs() !== $pairs || $rightDictionary->toPairs() !== $rightPairs) {
    throw new RuntimeException('Dictionary operations must leave both input snapshots unchanged.');
}
unset($rightDictionary, $rightPairs, $expectedMerged, $newPairs);

$expectedGroups = [];
$expectedRejected = [];
foreach ($pairs as [$key, $value]) {
    $group = is_int($key) ? $value % 4 : (string) ($value % 4);
    $encoded = (is_int($group) ? 'i:' : 's:') . $group;
    if (!isset($expectedGroups[$encoded])) {
        $expectedGroups[$encoded] = [$group, []];
    }
    $expectedGroups[$encoded][1][] = [$key, $value];
    if (!is_string($key) || $value % 2 !== 0) {
        $expectedRejected[] = [$key, $value];
    }
}
$expectedGroups = array_values($expectedGroups);
$results['dict_group_by_typed_keys'] = [
    'native_encoded_pair_loop' => $measure(static function () use ($pairs): array {
        $groups = [];
        foreach ($pairs as [$key, $value]) {
            $group = is_int($key) ? $value % 4 : (string) ($value % 4);
            $encoded = (is_int($group) ? 'i:' : 's:') . $group;
            if (!isset($groups[$encoded])) {
                $groups[$encoded] = [$group, []];
            }
            $groups[$encoded][1][] = [$key, $value];
        }
        return array_values($groups);
    }, $expectedGroups, $iterations),
    'dict_group_by' => $measure(
        static fn (): array => $dictionary
            ->groupBy(keyOf: static fn (int $value, int|string $key): int|string =>
                is_int($key) ? $value % 4 : (string) ($value % 4))
            ->mapValues(mapper: static fn (Dict $group): array => $group->toPairs())
            ->toPairs(),
        $expectedGroups,
        $iterations,
    ),
];
unset($expectedGroups);
$results['dict_partition_typed_keys'] = [
    'native_pair_loop' => $measure(static function () use ($pairs): array {
        $accepted = [];
        $rejected = [];
        foreach ($pairs as [$key, $value]) {
            if (is_string($key) && $value % 2 === 0) {
                $accepted[] = [$key, $value];
            } else {
                $rejected[] = [$key, $value];
            }
        }
        return [$accepted, $rejected];
    }, [$expectedFiltered, $expectedRejected], $iterations),
    'dict_partition' => $measure(static function () use ($dictionary): array {
        [$accepted, $rejected] = $dictionary->partition(
            predicate: static fn (int $value, int|string $key): bool => is_string($key) && $value % 2 === 0,
        );
        return [$accepted->toPairs(), $rejected->toPairs()];
    }, [$expectedFiltered, $expectedRejected], $iterations),
];
unset($expectedRejected, $expectedFiltered);
$expectedExpanded = [];
foreach ($pairs as [$key, $value]) {
    $expectedExpanded[] = [$key, $value];
    $expectedExpanded[] = ['extra:' . (is_int($key) ? 'i:' : 's:') . $key, -$value];
}
$results['dict_flat_map_typed_keys'] = [
    'native_unique_pair_loop' => $measure(static function () use ($pairs): array {
        $expanded = [];
        foreach ($pairs as [$key, $value]) {
            foreach ([[$key, $value], ['extra:' . (is_int($key) ? 'i:' : 's:') . $key, -$value]] as $pair) {
                $encoded = (is_int($pair[0]) ? 'i:' : 's:') . $pair[0];
                if (isset($expanded[$encoded])) {
                    throw new InvalidArgumentException('Duplicate benchmark output key.');
                }
                $expanded[$encoded] = $pair;
            }
        }
        return array_values($expanded);
    }, $expectedExpanded, $iterations),
    'dict_flat_map' => $measure(
        static fn (): array => $dictionary->flatMap(mapper: static fn (int $value, int|string $key): array => [
            [$key, $value],
            ['extra:' . (is_int($key) ? 'i:' : 's:') . $key, -$value],
        ])->toPairs(),
        $expectedExpanded,
        $iterations,
    ),
];
if ($dictionary->toPairs() !== $pairs) {
    throw new RuntimeException('Dictionary extensions must leave the input snapshot unchanged.');
}

echo json_encode([
    'environment' => [
        'php' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'architecture' => php_uname('m'),
        'xdebug_loaded' => extension_loaded('xdebug'),
        'memory_limit' => ini_get('memory_limit'),
        'opcache_cli' => ini_get('opcache.enable_cli'),
        'jit' => ini_get('opcache.jit'),
        'jit_buffer_size' => ini_get('opcache.jit_buffer_size'),
        'size' => $size,
        'iterations' => $iterations,
    ],
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
