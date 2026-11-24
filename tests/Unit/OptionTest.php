<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase;
use Taikick\Collections\Option;
use UnderflowException;

/**
 * Tests the distinction between absence and a present value.
 * 値がない状態と、値が存在する状態の区別を検証します。
 */
final class OptionTest extends TestCase
{
    /**
     * Preserves null, false, zero and empty strings when explicitly present.
     * 明示的に保持したnull・false・ゼロ・空文字を欠損扱いしないことを検証します。
     */
    public function testSomePreservesEveryValue(): void
    {
        foreach ([null, false, 0, '', []] as $value) {
            $option = Option::some($value);
            self::assertTrue($option->isSome());
            self::assertFalse($option->isNone());
            self::assertSame($value, $option->getOrThrow());
        }
    }

    /**
     * Converts only null into absence at nullable boundaries.
     * nullableな境界でnullだけを欠損へ変換することを検証します。
     */
    public function testFromNullable(): void
    {
        self::assertTrue(Option::fromNullable(null)->isNone());
        foreach ([false, 0, ''] as $value) {
            self::assertSame($value, Option::fromNullable($value)->getOrThrow());
        }
    }

    /**
     * Distinguishes ordinary mapping from nullable mapping.
     * 通常の変換とnullを欠損へ変換する操作の違いを検証します。
     */
    public function testMapping(): void
    {
        $some = Option::some(2);
        self::assertSame('4', $some->map(fn (int $n): string => (string) ($n * 2))->getOrThrow());
        self::assertTrue($some->map(fn (): null => null)->isSome());
        self::assertTrue($some->mapNullable(fn (): null => null)->isNone());
        self::assertSame(4, $some->mapNullable(fn (int $n): ?int => $n * 2)->getOrThrow());
        self::assertSame(3, $some->flatMap(fn (int $n): Option => Option::some($n + 1))->getOrThrow());
        self::assertTrue($some->filter(fn (int $n): bool => $n > 5)->isNone());
        self::assertSame($some, $some->filter(fn (int $n): bool => $n === 2));
        self::assertSame(2, $some->getOrThrow());
    }

    /**
     * Never invokes value callbacks for an absent option.
     * 値がない場合に値用コールバックを呼ばないことを検証します。
     */
    public function testNoneShortCircuits(): void
    {
        $fail = fn () => self::fail('An absent option evaluated a value callback.');
        $none = Option::none();
        self::assertTrue($none->map($fail)->isNone());
        self::assertTrue($none->mapNullable($fail)->isNone());
        self::assertTrue($none->flatMap($fail)->isNone());
        self::assertTrue($none->filter($fail)->isNone());
        self::assertSame('missing', $none->fold(fn (): string => 'missing', $fail));
    }

    /**
     * Evaluates only the selected fallback or fold branch.
     * 選択された代替処理またはfoldの分岐だけを評価することを検証します。
     */
    public function testLazyFallbackAndFold(): void
    {
        self::assertSame(4, Option::none()->getOrElse(fn (): int => 4));
        $fail = fn () => self::fail('An unused fallback was evaluated.');
        self::assertSame(null, Option::some(null)->getOrElse($fail));
        self::assertSame('3', Option::some(3)->fold($fail, fn (int $n): string => (string) $n));
    }

    /**
     * Throws a standard exception when an absent value is required.
     * 欠損値を取り出そうとした場合に標準例外を送出することを検証します。
     */
    public function testMissingValueThrows(): void
    {
        $this->expectException(UnderflowException::class);
        Option::none()->getOrThrow();
    }

    /**
     * Supports a lazily created domain-specific exception.
     * 呼び出し側固有の例外を必要時だけ生成できることを検証します。
     */
    public function testCustomException(): void
    {
        $exception = new LogicException('Missing record');
        $this->expectExceptionObject($exception);
        Option::none()->getOrThrow(fn (): LogicException => $exception);
    }

    /**
     * Checks identity, composition and monadic laws over representative values.
     * 代表値を使って恒等則・合成則・モナド則を検証します。
     */
    public function testAlgebraicLaws(): void
    {
        $f = fn (int $n): int => $n + 3;
        $g = fn (int $n): int => $n * 2;
        $bind = fn (int $n): Option => $n < 0 ? Option::none() : Option::some($n + 1);
        $next = fn (int $n): Option => Option::some($n * 3);
        foreach (range(-10, 10) as $value) {
            $option = Option::some($value);
            self::assertEquals($option, $option->map(fn (int $n): int => $n));
            self::assertEquals($option->map($f)->map($g), $option->map(fn (int $n): int => $g($f($n))));
            self::assertEquals($bind($value), $option->flatMap($bind));
            self::assertEquals($option, $option->flatMap(Option::some(...)));
            self::assertEquals(
                $option->flatMap($bind)->flatMap($next),
                $option->flatMap(fn (int $n): Option => $bind($n)->flatMap($next)),
            );
        }
    }
}
