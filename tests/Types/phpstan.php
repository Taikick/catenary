<?php

declare(strict_types=1);

namespace Catenary\Tests\Types;

use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;

use function PHPStan\Testing\assertType;

/**
 * Asserts inferred types without annotating or overriding intermediate values.
 * 中間値の型を注釈で上書きせず、推論された型を検証します。
 *
 * @param list<int> $numbers
 * @param list<string> $strings
 * @param string|null $nullable
 * @param int $number
 * @param string $text
 * @return void
 */
function inference(array $numbers, array $strings, ?string $nullable, int $number, string $text): void
{
    $vec = Vec::fromIterable($numbers);
    assertType('Catenary\\Vec<int>', $vec);
    assertType('Catenary\\Vec<int>', Vec::fromIterable($vec));
    assertType('Catenary\\Option<int>', $vec->find(fn (int $n): bool => $n > 0));
    assertType('Catenary\\Option<int>', $vec->last());
    assertType('bool', $vec->any(fn (int $n): bool => $n > 0));
    assertType('bool', $vec->every(fn (int $n): bool => $n > 0));
    assertType('Catenary\\Option<null>', Vec::of(null)->find(fn (): bool => true));
    assertType('Catenary\\Option<null>', Vec::of(null)->last());
    assertType('Catenary\\Option<never>', Vec::empty()->last());
    assertType('Catenary\\Vec<decimal-int-string>', $vec->map(fn (int $n): string => (string) $n));
    assertType('Catenary\\Vec<int|string>', $vec->append($text));
    assertType('Catenary\\Vec<int|string>', $vec->concat($strings));
    assertType('Catenary\\Vec<Catenary\\Vec<int>>', $vec->chunk(2));
    assertType('Catenary\\Option<int>', $vec->first());
    assertType('Catenary\\Vec<int>', $vec->filterMap(fn (int $n): Option => Option::some(value: $n)));
    assertType('Catenary\\Vec<int>', $vec->flatMap(fn (int $n): array => [$n]));
    assertType('int', $vec->fold(0, fn (int $a, int $n): int => $a + $n));
    assertType('Catenary\\Option<string>', Option::fromNullable(value: $nullable));
    $nullOnly = Option::fromNullable(value: null);
    assertType('Catenary\\Option<never>', $nullOnly);
    assertType('string', $nullOnly->getOrElse(fallback: fn (): string => $text));
    $nullOnlyMap = Option::some(value: $number)->mapNullable(mapper: fn (): null => null);
    assertType('Catenary\\Option<never>', $nullOnlyMap);
    assertType('string', $nullOnlyMap->getOrElse(fallback: fn (): string => $text));
    assertType('Catenary\\Option<null>', Option::some(value: null));
    assertType('Catenary\\Option<never>', Option::none());
    assertType(
        'Catenary\\Option<decimal-int-string>',
        Option::some(value: $number)->map(fn (int $n): string => (string) $n),
    );
    assertType('Catenary\\Option<string>', Option::some(value: $number)->mapNullable(fn (): ?string => $nullable));
    assertType(
        'Catenary\\Option<string>',
        Option::some(value: $number)->flatMap(fn (): Option => Option::fromNullable(value: $nullable)),
    );
    assertType('int|string', Option::some(value: $number)->getOrElse(fn (): string => $text));
    $dict = Dict::fromPairs([[$text, $number]]);
    assertType('Catenary\\Dict<string, int>', $dict);
    assertType('Catenary\\Dict<int|string, int|string>', $dict->with($number, $text));
    assertType(
        'Catenary\\Dict<string, non-empty-string>',
        $dict->mapValues(fn (int $n, string $k): string => $k . $n),
    );
    assertType('Catenary\\Option<int>', $dict->get($text));
    assertType('Catenary\\Vec<string>', $dict->keys());
    assertType('Catenary\\Vec<int>', $dict->values());
    assertType('Catenary\\Dict<string, int>', $vec->keyBy(fn (int $n): string => (string) $n));
    $stream = Stream::fromFactory(fn (): array => $numbers);
    assertType('Catenary\\Stream<int>', $stream);
    assertType(
        'Catenary\\Stream<decimal-int-string>',
        $stream->map(fn (int $n): string => (string) $n)->filter(fn (string $s): bool => $s !== '0')->take(2),
    );
    assertType('Catenary\\Vec<int>', $stream->toVec());
    assertType('Catenary\\Stream<int>', $vec->stream());
    assertType('Catenary\\Vec<never>', Vec::empty());
    assertType('Catenary\\Vec<*NEVER*>', Vec::of());
    assertType('Catenary\\Vec<*NEVER*>', Vec::fromIterable([]));
    assertType('Catenary\\Dict<never, never>', Dict::empty());
    assertType('Catenary\\Dict<never, never>', Dict::fromPairs([]));
    assertType('Catenary\\Stream<*NEVER*>', Stream::fromFactory(fn (): array => []));
}
