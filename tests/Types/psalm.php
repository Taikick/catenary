<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Types;

use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

/**
 * Checks Psalm's inferred generic types without replacing them using inline type overrides.
 * インライン注釈による型の上書きをせず、Psalmが推論するジェネリクスを検証します。
 *
 * @param list<int> $numbers
 * @param list<string> $strings
 */
function psalmInference(array $numbers, array $strings, ?string $nullable, int $number, string $text): void
{
    $vec = Vec::fromIterable($numbers);
    /** @psalm-check-type-exact $vec = Vec<int> */
    $mapped = $vec->map(fn (int $n): string => (string) $n);
    /** @psalm-check-type-exact $mapped = Vec<decimal-int-string> */
    $widened = $vec->append($text)->concat($strings);
    /** @psalm-check-type-exact $widened = Vec<int|string> */
    $chunks = $vec->chunk(2);
    /** @psalm-check-type-exact $chunks = Vec<Vec<int>> */
    $first = $vec->first();
    /** @psalm-check-type-exact $first = Option<int> */
    $filtered = $vec->filterMap(fn (int $n): Option => Option::some($n));
    /** @psalm-check-type-exact $filtered = Vec<int> */
    $flat = $vec->flatMap(fn (int $n): array => [$n]);
    /** @psalm-check-type-exact $flat = Vec<int> */
    $sum = $vec->fold(0, fn (int $a, int $n): int => $a + $n);
    /** @psalm-check-type-exact $sum = int */
    $option = Option::fromNullable($nullable);
    /** @psalm-check-type-exact $option = Option<string> */
    $presentNull = Option::some(null);
    /** @psalm-check-type-exact $presentNull = Option<null> */
    $absent = Option::none();
    /** @psalm-check-type-exact $absent = Option<never> */
    $nullableMap = Option::some($number)->mapNullable(fn (): ?string => $nullable);
    /** @psalm-check-type-exact $nullableMap = Option<string> */
    $bound = Option::some($number)->flatMap(fn (): Option => Option::fromNullable($nullable));
    /** @psalm-check-type-exact $bound = Option<string> */
    $fallback = Option::some($number)->getOrElse(fn (): string => $text);
    /** @psalm-check-type-exact $fallback = int|string */
    $dict = Dict::fromPairs([[$text, $number]]);
    /** @psalm-check-type-exact $dict = Dict<string, int> */
    $newDict = $dict->with($number, $text);
    /** @psalm-check-type-exact $newDict = Dict<int|string, int|string> */
    $mappedDict = $dict->mapValues(fn (int $n, string $k): string => $k . $n);
    /** @psalm-check-type-exact $mappedDict = Dict<string, non-falsy-string> */
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
