<?php

declare(strict_types=1);

namespace Catenary\Tests\Types;

use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;

/**
 * Intentionally violates contracts; both analyzers must reject every marked line.
 * 意図的に契約へ違反し、両解析器が印を付けた各行を拒否することを検証します。
 *
 * @return void
 */
function rejectedPrograms(): void
{
    Vec::of(1)->map(static fn (string $value): string => $value); // @expect-type-error
    Vec::of(1)->find(static fn (string $value): bool => $value !== ''); // @expect-type-error
    Vec::of(1)->any(static fn (string $value): bool => $value !== ''); // @expect-type-error
    Vec::of(1)->every(static fn (string $value): bool => $value !== ''); // @expect-type-error
    Vec::of(1)->find(static fn (int $value): int => $value); // @expect-type-error
    Vec::of(1)->any(static fn (int $value): int => $value); // @expect-type-error
    Vec::of(1)->every(static fn (int $value): int => $value); // @expect-type-error
    Option::some(value: 1)->flatMap(static fn (int $value): int => $value); // @expect-type-error
    Dict::empty()->with(1.5, 'value'); // @expect-type-error
    $stream = Stream::fromFactory(static fn (): array => [1]);
    $stream->filter(static fn (int $n): string => (string) $n); // @expect-type-error
    echo (int) Vec::of(1)->at('first')->isSome(); // @expect-type-error
}
