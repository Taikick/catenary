<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Unit;

use Generator;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

/**
 * Tests deferred evaluation, short-circuiting and explicit stream lifetime.
 * 遅延評価・短絡評価・明示的なストリームの寿命を検証します。
 */
final class StreamTest extends TestCase
{
    /**
     * Defers factories and mappers and does not read beyond a take boundary.
     * 生成処理と変換を遅延し、takeの境界より先を読まないことを検証します。
     */
    public function testLazyTakeDoesNotOverRead(): void
    {
        $factories = $visits = $maps = 0;
        $source = Stream::fromFactory(function () use (&$factories, &$visits): Generator {
            ++$factories;
            for ($n = 0;; ++$n) {
                ++$visits;
                yield $n;
            }
        });
        $chain = $source->map(function (int $n) use (&$maps): int {
            ++$maps;
            return $n * 2;
        })->take(3);
        self::assertSame([0, 0, 0], [$factories, $visits, $maps]);
        self::assertSame([0, 2, 4], $chain->toVec()->toList());
        self::assertSame([1, 3, 3], [$factories, $visits, $maps]);
        self::assertSame([0, 2, 4], $chain->toVec()->toList());
        self::assertSame([2, 6, 6], [$factories, $visits, $maps]);
    }

    /**
     * Avoids opening the source at all when taking zero values.
     * 取り出し数がゼロなら入力を開かないことを検証します。
     */
    public function testTakeZero(): void
    {
        $source = Stream::fromFactory(fn () => throw new LogicException('Must not be opened'));
        self::assertSame([], $source->take(0)->toVec()->toList());
    }

    /**
     * Inspects at most one yielded element for first and emptiness checks.
     * 先頭取得と空判定で高々一つの出力要素だけを確認することを検証します。
     */
    public function testFirstAndIsEmptyShortCircuit(): void
    {
        $source = Stream::fromFactory(function (): Generator {
            yield null;
            throw new LogicException('Read too far');
        });
        self::assertTrue($source->first()->isSome());
        self::assertNull($source->first()->getOrThrow());
        self::assertFalse($source->isEmpty());
        self::assertTrue(Stream::fromFactory(fn (): array => [])->isEmpty());
        self::assertTrue(Stream::fromFactory(fn (): array => [])->first()->isNone());
    }

    /**
     * Composes lazy transformations while discarding all source keys.
     * 入力のキーを捨てながら遅延変換を合成することを検証します。
     */
    public function testTransformations(): void
    {
        $stream = Stream::fromFactory(fn (): array => ['a' => 1, 'b' => 2, 'c' => 3]);
        self::assertSame([1, 2, 3], iterator_to_array($stream));
        self::assertSame(
            [2, -2],
            $stream->filter(fn (int $n): bool => $n % 2 === 0)
                ->flatMap(fn (int $n): array => ['a' => $n, 'b' => -$n])->toVec()->toList(),
        );
        self::assertSame([null, 3], $stream->filterMap(fn (int $n): Option => match ($n) {
            1 => Option::some(null),
            2 => Option::none(),
            default => Option::some($n),
        })->toVec()->toList());
        self::assertSame([2, 3], $stream->drop(1)->toVec()->toList());
        self::assertSame([], $stream->drop(9)->toVec()->toList());
        self::assertSame(6, $stream->fold(0, fn (int $sum, int $n): int => $sum + $n));
    }

    /**
     * Stops predicates as soon as the terminal result is known.
     * 終端結果が確定した時点で条件の評価を止めることを検証します。
     */
    public function testAnyAndEvery(): void
    {
        $source = Stream::fromFactory(function (): Generator {
            yield 1;
            yield 2;
            throw new LogicException('Read too far');
        });
        self::assertTrue($source->any(fn (int $n): bool => $n === 2));
        self::assertFalse($source->every(fn (int $n): bool => $n < 2));
        $empty = Stream::fromFactory(fn (): array => []);
        self::assertFalse($empty->any(fn (): bool => true));
        self::assertTrue($empty->every(fn (): bool => false));
        self::assertFalse(Vec::of(1, 2)->stream()->any(fn (int $n): bool => $n > 3));
        self::assertTrue(Vec::of(1, 2)->stream()->every(fn (int $n): bool => $n > 0));
    }

    /**
     * Releases generator resources after early termination and exceptions.
     * 途中終了や例外の発生後にジェネレータの資源を解放することを検証します。
     */
    public function testResourceCleanup(): void
    {
        $closed = 0;
        $source = Stream::fromFactory(function () use (&$closed): Generator {
            try {
                yield 1;
                yield 2;
            } finally {
                ++$closed;
            }
        });
        self::assertSame([1], $source->take(1)->toVec()->toList());
        self::assertSame(1, $closed);
        self::assertSame(1, $source->first()->getOrThrow());
        self::assertSame(2, $closed);
        try {
            $source->map(fn () => throw new LogicException('mapper failed'))->toVec();
            self::fail('Mapper exception was swallowed.');
        } catch (LogicException $error) {
            self::assertSame('mapper failed', $error->getMessage());
        }
        self::assertSame(3, $closed);
    }

    /**
     * Rejects negative sizes immediately, before consuming the source.
     * 入力の走査前に負のサイズを即座に拒否することを検証します。
     */
    public function testInvalidSizes(): void
    {
        $source = Stream::fromFactory(fn () => throw new LogicException('Must not be opened'));
        foreach ([fn () => $source->take(-1), fn () => $source->drop(-1)] as $operation) {
            try {
                $operation();
                self::fail('Invalid size was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Materializes an explicit snapshot without hidden caching in the stream.
     * ストリーム内で暗黙にキャッシュせず、明示的なスナップショットを作れることを検証します。
     */
    public function testMaterialization(): void
    {
        $generation = 0;
        $source = Stream::fromFactory(function () use (&$generation): array {
            return [++$generation];
        });
        $snapshot = $source->toVec();
        self::assertSame([1], $snapshot->toList());
        self::assertSame([2], $source->toVec()->toList());
        self::assertSame([1], $snapshot->stream()->toVec()->toList());
        self::assertSame([1], $snapshot->stream()->toVec()->toList());
        self::assertSame(2, $generation);
    }
}
