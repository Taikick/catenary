<?php

declare(strict_types=1);

namespace Catenary\Tests\Types;

use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;
use Generator;

/**
 * Checks Psalm's inferred generic types without replacing them using inline type overrides.
 * インライン注釈による型の上書きをせず、Psalmが推論するジェネリクスを検証します。
 *
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param string|null $nullable
 * @param int $number
 * @param string $text
 * @return void
 */
function psalmInference(array $numbers, array $strings, ?string $nullable, int $number, string $text): void
{
    $vec = Vec::fromIterable($numbers);
    /** @psalm-check-type-exact $vec = Vec<int> */
    $reused = Vec::fromIterable($vec);
    /** @psalm-check-type-exact $reused = Vec<int> */
    $found = $vec->find(fn (int $n): bool => $n > 0);
    /** @psalm-check-type-exact $found = Option<int> */
    $last = $vec->last();
    /** @psalm-check-type-exact $last = Option<int> */
    $any = $vec->any(fn (int $n): bool => $n > 0);
    /** @psalm-check-type-exact $any = bool */
    $every = $vec->every(fn (int $n): bool => $n > 0);
    /** @psalm-check-type-exact $every = bool */
    $foundNull = Vec::of(null)->find(fn (): bool => true);
    /** @psalm-check-type-exact $foundNull = Option<null> */
    $lastNull = Vec::of(null)->last();
    /** @psalm-check-type-exact $lastNull = Option<null> */
    $emptyLast = Vec::empty()->last();
    /** @psalm-check-type-exact $emptyLast = Option<never> */
    $mapped = $vec->map(fn (int $n): string => (string) $n);
    /** @psalm-check-type-exact $mapped = Vec<decimal-int-string> */
    $widened = $vec->append($text)->concat($strings);
    /** @psalm-check-type-exact $widened = Vec<int|string> */
    $chunks = $vec->chunk(2);
    /** @psalm-check-type-exact $chunks = Vec<Vec<int>> */
    $first = $vec->first();
    /** @psalm-check-type-exact $first = Option<int> */
    $filtered = $vec->filterMap(fn (int $n): Option => Option::some(value: $n));
    /** @psalm-check-type-exact $filtered = Vec<int> */
    $flat = $vec->flatMap(fn (int $n): array => [$n]);
    /** @psalm-check-type-exact $flat = Vec<int> */
    $sum = $vec->fold(0, fn (int $a, int $n): int => $a + $n);
    /** @psalm-check-type-exact $sum = int */
    $option = Option::fromNullable(value: $nullable);
    /** @psalm-check-type-exact $option = Option<string> */
    $nullOnly = Option::fromNullable(value: null);
    /** @psalm-check-type-exact $nullOnly = Option<never> */
    $nullOnlyFallback = $nullOnly->getOrElse(fallback: fn (): string => $text);
    /** @psalm-check-type-exact $nullOnlyFallback = string */
    $nullOnlyMap = Option::some(value: $number)->mapNullable(mapper: fn (): null => null);
    /** @psalm-check-type-exact $nullOnlyMap = Option<never> */
    $nullOnlyMapFallback = $nullOnlyMap->getOrElse(fallback: fn (): string => $text);
    /** @psalm-check-type-exact $nullOnlyMapFallback = string */
    $presentNull = Option::some(value: null);
    /** @psalm-check-type-exact $presentNull = Option<null> */
    $absent = Option::none();
    /** @psalm-check-type-exact $absent = Option<never> */
    $nullableMap = Option::some(value: $number)->mapNullable(fn (): ?string => $nullable);
    /** @psalm-check-type-exact $nullableMap = Option<string> */
    $bound = Option::some(value: $number)->flatMap(fn (): Option => Option::fromNullable(value: $nullable));
    /** @psalm-check-type-exact $bound = Option<string> */
    $fallback = Option::some(value: $number)->getOrElse(fn (): string => $text);
    /** @psalm-check-type-exact $fallback = int|string */
    $dict = Dict::fromPairs([[$text, $number]]);
    /** @psalm-check-type-exact $dict = Dict<string, int> */
    $newDict = $dict->with($number, $text);
    /** @psalm-check-type-exact $newDict = Dict<int|string, int|string> */
    $mappedDict = $dict->mapValues(fn (int $n, string $k): string => $k . $n);
    /** @psalm-check-type-exact $mappedDict = Dict<string, non-falsy-string> */
    $filteredDict = $dict->filter(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $filteredDict = Dict<string, int> */
    $foundPair = $dict->find(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $foundPair = Option<array{string, int}> */
    $dictAny = $dict->any(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $dictAny = bool */
    $dictEvery = $dict->every(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $dictEvery = bool */
    $dictTotal = $dict->fold(
        initial: 0,
        reducer: fn (int $total, int $value, string $key): int => $total + $value + strlen($key),
    );
    /** @psalm-check-type-exact $dictTotal = int */
    $nullableTotal = $dict->fold(
        initial: null,
        reducer: fn (?int $total, int $value, string $key): int => ($total ?? 0) + $value + strlen($key),
    );
    /** @psalm-check-type-exact $nullableTotal = null|int */
    $emptyFold = Dict::empty()->fold(initial: $text, reducer: fn (string $total): string => $total);
    /** @psalm-check-type-exact $emptyFold = string */
    $otherDict = Dict::fromPairs(pairs: [[$number, $text]]);
    $merged = $dict->merge(other: $otherDict);
    /** @psalm-check-type-exact $merged = Dict<int|string, int|string> */
    $mergedEmpty = $dict->merge(other: Dict::empty());
    /** @psalm-check-type-exact $mergedEmpty = Dict<string, int> */
    $emptyMerged = Dict::empty()->merge(other: $dict);
    /** @psalm-check-type-exact $emptyMerged = Dict<string, int> */
    $bothEmpty = Dict::empty()->merge(other: Dict::empty());
    /** @psalm-check-type-exact $bothEmpty = Dict<never, never> */
    $filteredEmpty = Dict::empty()->filter(predicate: fn (): bool => true);
    /** @psalm-check-type-exact $filteredEmpty = Dict<never, never> */
    $nullableDict = Dict::fromPairs(pairs: [[$number, $nullable], [$text, null]]);
    $filteredNullable = $nullableDict->filter(predicate: fn (): bool => true);
    /** @psalm-check-type-exact $filteredNullable = Dict<int|string, null|string> */
    $foundNullable = $nullableDict->find(predicate: fn (): bool => true);
    /** @psalm-check-type-exact $foundNullable = Option<array{int|string, null|string}> */
    $nullDict = Dict::fromPairs(pairs: [[$text, null]]);
    $foundNullPair = $nullDict->find(predicate: fn (): bool => true);
    /** @psalm-check-type-exact $foundNullPair = Option<array{string, null}> */
    $lookup = $dict->get($text);
    /** @psalm-check-type-exact $lookup = Option<int> */
    $keys = $dict->keys();
    /** @psalm-check-type-exact $keys = Vec<string> */
    $values = $dict->values();
    /** @psalm-check-type-exact $values = Vec<int> */
    $indexed = $vec->keyBy(fn (int $n): string => (string) $n);
    /** @psalm-check-type-exact $indexed = Dict<decimal-int-string, int> */
    $stream = Stream::fromFactory(fn (): array => $numbers);
    /** @psalm-check-type-exact $stream = Stream<int> */
    $chain = $stream->map(fn (int $n): string => (string) $n)->filter(fn (string $s): bool => $s !== '0')->take(2);
    /** @psalm-check-type-exact $chain = Stream<decimal-int-string> */
    $materialized = $stream->toVec();
    /** @psalm-check-type-exact $materialized = Vec<int> */
    $lazy = $vec->stream();
    /** @psalm-check-type-exact $lazy = Stream<int> */
    echo count($vec);
    $emptyVec = Vec::empty();
    /** @psalm-check-type-exact $emptyVec = Vec<never> */
    $emptyOf = Vec::of();
    /** @psalm-check-type-exact $emptyOf = Vec<never> */
    $emptyIterable = Vec::fromIterable([]);
    /** @psalm-check-type-exact $emptyIterable = Vec<never> */
    $emptyDict = Dict::empty();
    /** @psalm-check-type-exact $emptyDict = Dict<never, never> */
    $emptyPairs = Dict::fromPairs([]);
    /** @psalm-check-type-exact $emptyPairs = Dict<never, never> */
    $emptyStream = Stream::fromFactory(fn (): array => []);
    /** @psalm-check-type-exact $emptyStream = Stream<never> */
    echo count($emptyVec);
}


/**
 * Checks dictionary extensions and the exact types of empty and nullable results.
 * 辞書拡張と空・nullを含む結果の正確な型を検査します。
 *
 * @param int $number
 * @param string $text
 * @param string|null $nullable
 * @return void
 */
function psalmDictExtensionInference(int $number, string $text, ?string $nullable): void
{
    $dict = Dict::fromPairs(pairs: [[$text, $number]]);
    $groups = $dict->groupBy(keyOf: fn (int $value, string $key): string => $key);
    /** @psalm-check-type-exact $groups = Dict<string, Dict<string, int>> */
    $mixedGroups = $dict->groupBy(keyOf: fn (int $value): int|string => $value > 0 ? $number : $text);
    /** @psalm-check-type-exact $mixedGroups = Dict<int|string, Dict<string, int>> */
    $partition = $dict->partition(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $partition = array{Dict<string, int>, Dict<string, int>} */
    $pairs = $dict->flatMap(mapper: fn (int $value, string $key): array => [[$value, $key]]);
    /** @psalm-check-type-exact $pairs = Dict<int, string> */
    $dictionaries = $dict->flatMap(
        mapper: fn (int $value, string $key): Dict => Dict::fromPairs(pairs: [[$value, $key]]),
    );
    /** @psalm-check-type-exact $dictionaries = Dict<int, string> */
    $generatedPairs = $dict->flatMap(mapper: static function (int $value, string $key): Generator {
        yield [$value, $key];
    });
    /** @psalm-check-type-exact $generatedPairs = Dict<int, string> */
    $count = $dict->count();
    /** @psalm-check-type-exact $count = int */
    $matchingCount = $dict->count(predicate: fn (int $value, string $key): bool => $value > 0 && $key !== '');
    /** @psalm-check-type-exact $matchingCount = int */
    $total = $dict->sum();
    /** @psalm-check-type-exact $total = int|float */
    $formatted = $dict->mkString(formatter: fn (int $value, string $key): string => $key . $value);
    /** @psalm-check-type-exact $formatted = string */
    $native = $dict->toArray();
    /** @psalm-check-type-exact $native = array<string, int> */
    $defaultValue = $dict->getOrDefault(key: $text, default: $text);
    /** @psalm-check-type-exact $defaultValue = int|string */
    $defaultNull = $dict->getOrDefault(key: $text, default: null);
    /** @psalm-check-type-exact $defaultNull = int|null */
    $nullableDict = Dict::fromPairs(pairs: [[$number, $nullable], [$text, null]]);
    $nullableDefault = $nullableDict->getOrDefault(key: $text, default: $number);
    /** @psalm-check-type-exact $nullableDefault = int|null|string */
    $nativeNullable = $nullableDict->toArray();
    /** @psalm-check-type-exact $nativeNullable = array<int|string, null|string> */
    $nullableGroups = $nullableDict->groupBy(keyOf: fn (?string $value, int|string $key): int => $number);
    /** @psalm-check-type-exact $nullableGroups = Dict<int, Dict<int|string, null|string>> */
    $flatEmpty = $dict->flatMap(mapper: fn (): array => []);
    /** @psalm-check-type-exact $flatEmpty = Dict<never, never> */
    $flatEmptyWithArguments = $dict->flatMap(mapper: fn (int $value, string $key): array => []);
    /** @psalm-check-type-exact $flatEmptyWithArguments = Dict<never, never> */
    $emptyGroups = Dict::empty()->groupBy(keyOf: fn (): string => $text);
    /** @psalm-check-type-exact $emptyGroups = Dict<string, Dict<never, never>> */
    $emptyPartition = Dict::empty()->partition(predicate: fn (): bool => true);
    /** @psalm-check-type-exact $emptyPartition = array{Dict<never, never>, Dict<never, never>} */
    $emptyNative = Dict::empty()->toArray();
    /** @psalm-check-type-exact $emptyNative = array<never, never> */
    $emptyDefault = Dict::empty()->getOrDefault(key: $text, default: $text);
    /** @psalm-check-type-exact $emptyDefault = string */
    $defaultClosure = fn (): string => $text;
    $preservedClosure = Dict::empty()->getOrDefault(key: $text, default: $defaultClosure);
    /** @psalm-check-type-exact $preservedClosure = pure-Closure():string */
    $closureResult = $preservedClosure();
    /** @psalm-check-type-exact $closureResult = string */
    $wide = $dict->with(key: $number, value: $text);
    $widePartition = $wide->partition(predicate: fn (int|string $value, int|string $key): bool => $value === $key);
    /** @psalm-check-type-exact $widePartition = array{Dict<int|string, int|string>, Dict<int|string, int|string>} */
}
