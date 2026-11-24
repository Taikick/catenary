<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Types;

use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

use function PHPStan\Testing\assertType;

/**
 * Asserts inferred types without annotating or overriding intermediate values.
 * 中間値の型を注釈で上書きせず、推論された型を検証します。
 *
 * @param list<int> $numbers
 * @param list<string> $strings
 */
function inference(array $numbers, array $strings, ?string $nullable, int $number, string $text): void
{
    $vec = Vec::fromIterable($numbers);
    assertType('Taikick\\Collections\\Vec<int>', $vec);
    assertType('Taikick\\Collections\\Vec<decimal-int-string>', $vec->map(fn (int $n): string => (string) $n));
    assertType('Taikick\\Collections\\Vec<int|string>', $vec->append($text));
    assertType('Taikick\\Collections\\Vec<int|string>', $vec->concat($strings));
    assertType('Taikick\\Collections\\Vec<Taikick\\Collections\\Vec<int>>', $vec->chunk(2));
    assertType('Taikick\\Collections\\Option<int>', $vec->first());
    assertType('Taikick\\Collections\\Vec<int>', $vec->filterMap(fn (int $n): Option => Option::some($n)));
    assertType('Taikick\\Collections\\Vec<int>', $vec->flatMap(fn (int $n): array => [$n]));
    assertType('int', $vec->fold(0, fn (int $a, int $n): int => $a + $n));
    assertType('Taikick\\Collections\\Option<string>', Option::fromNullable($nullable));
    assertType('Taikick\\Collections\\Option<null>', Option::some(null));
    assertType('Taikick\\Collections\\Option<never>', Option::none());
    assertType(
        'Taikick\\Collections\\Option<decimal-int-string>',
        Option::some($number)->map(fn (int $n): string => (string) $n),
    );
    assertType('Taikick\\Collections\\Option<string>', Option::some($number)->mapNullable(fn (): ?string => $nullable));
    assertType(
        'Taikick\\Collections\\Option<string>',
        Option::some($number)->flatMap(fn (): Option => Option::fromNullable($nullable)),
    );
    assertType('int|string', Option::some($number)->getOrElse(fn (): string => $text));
    $dict = Dict::fromPairs([[$text, $number]]);
    assertType('Taikick\\Collections\\Dict<string, int>', $dict);
    assertType('Taikick\\Collections\\Dict<int|string, int|string>', $dict->with($number, $text));
    assertType(
        'Taikick\\Collections\\Dict<string, non-empty-string>',
        $dict->mapValues(fn (int $n, string $k): string => $k . $n),
    );
    assertType('Taikick\\Collections\\Option<int>', $dict->get($text));
    assertType('Taikick\\Collections\\Vec<string>', $dict->keys());
    assertType('Taikick\\Collections\\Vec<int>', $dict->values());
    assertType('Taikick\\Collections\\Dict<string, int>', $vec->keyBy(fn (int $n): string => (string) $n));
    $stream = Stream::fromFactory(fn (): array => $numbers);
    assertType('Taikick\\Collections\\Stream<int>', $stream);
    assertType(
        'Taikick\\Collections\\Stream<decimal-int-string>',
        $stream->map(fn (int $n): string => (string) $n)->filter(fn (string $s): bool => $s !== '0')->take(2),
    );
    assertType('Taikick\\Collections\\Vec<int>', $stream->toVec());
    assertType('Taikick\\Collections\\Stream<int>', $vec->stream());
    assertType('Taikick\\Collections\\Vec<never>', Vec::empty());
    assertType('Taikick\\Collections\\Vec<*NEVER*>', Vec::of());
    assertType('Taikick\\Collections\\Vec<*NEVER*>', Vec::fromIterable([]));
    assertType('Taikick\\Collections\\Dict<never, never>', Dict::empty());
    assertType('Taikick\\Collections\\Dict<never, never>', Dict::fromPairs([]));
    assertType('Taikick\\Collections\\Stream<*NEVER*>', Stream::fromFactory(fn (): array => []));
}
