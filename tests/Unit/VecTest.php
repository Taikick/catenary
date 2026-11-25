<?php

declare(strict_types=1);

namespace Catenary\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Catenary\Option;
use Catenary\Vec;

/**
 * Tests eager, ordered, immutable sequences.
 * 即時評価される順序付き不変シーケンスを検証します。
 */
final class VecTest extends TestCase
{
    /**
     * Reindexes iterable values and preserves null during lookup.
     * iterableを連番へ整え、検索時にnullを保持することを検証します。
     *
     * @return void
     */
    public function testConstructionAndLookup(): void
    {
        $vec = Vec::fromIterable(['a' => null, 9 => 'x']);
        self::assertSame([null, 'x'], $vec->toList());
        self::assertSame([null, 'x'], iterator_to_array($vec));
        self::assertSame(2, count($vec));
        self::assertFalse($vec->isEmpty());
        self::assertTrue($vec->first()->isSome());
        self::assertNull($vec->at(0)->getOrThrow());
        self::assertTrue($vec->at(-1)->isNone());
        self::assertTrue($vec->at(2)->isNone());
        self::assertTrue(Vec::empty()->first()->isNone());
        self::assertTrue(Vec::of()->isEmpty());
    }

    /**
     * Accepts general callables and composes transformations without mutation.
     * 一般のcallableを受け入れ、元の値を変えず変換を合成できることを検証します。
     *
     * @return void
     */
    public function testTransformationChain(): void
    {
        $vec = Vec::of('a', 'abc', 'ab');
        self::assertSame([3, 2], $vec->map('strlen')->filter(fn (int $n): bool => $n > 1)->toList());
        self::assertSame(['a', 'abc', 'ab'], $vec->toList());
        self::assertSame([1, -1, 2, -2], Vec::of(1, 2)->flatMap(fn (int $n): array => [$n, -$n])->toList());
        self::assertSame([null, 2], Vec::of(0, 1, 2)->filterMap(fn (int $n): Option => match ($n) {
            0 => Option::some(value: null),
            1 => Option::none(),
            default => Option::some(value: $n),
        })->toList());
        self::assertSame(6, Vec::of(1, 2, 3)->fold(0, fn (int $sum, int $n): int => $sum + $n));
    }

    /**
     * Builds new sequences and keeps original storage unchanged.
     * 新しいシーケンスを作成し元の格納内容を保持することを検証します。
     *
     * @return void
     */
    public function testAppendConcatAndSlices(): void
    {
        $vec = Vec::of(1, 2, 3);
        self::assertSame([1, 2, 3, 'x'], $vec->append('x')->toList());
        self::assertSame([1, 2, 3, 4, 5], $vec->concat([4, 5])->toList());
        self::assertSame([], $vec->take(0)->toList());
        self::assertSame([1, 2], $vec->take(2)->toList());
        self::assertSame([3], $vec->drop(2)->toList());
        self::assertSame([], $vec->drop(10)->toList());
        self::assertSame([1, 2, 3], $vec->toList());
    }

    /**
     * Splits sequences into nonempty chunks, including the trailing remainder.
     * 末尾の余りを含め、空でないチャンクへ分割することを検証します。
     *
     * @return void
     */
    public function testChunkAndPartition(): void
    {
        self::assertSame([[1, 2], [3]], Vec::of(1, 2, 3)->chunk(2)->map(fn (Vec $v): array => $v->toList())->toList());
        self::assertSame([], Vec::empty()->chunk(2)->toList());
        [$yes, $no] = Vec::of(1, 2, 3)->partition(fn (int $n): bool => $n % 2 === 1);
        self::assertSame([1, 3], $yes->toList());
        self::assertSame([2], $no->toList());
    }

    /**
     * Rejects invalid slice and chunk sizes consistently.
     * 不正な切り出し・チャンクのサイズを一貫して拒否することを検証します。
     *
     * @return void
     */
    public function testInvalidSizes(): void
    {
        $operations = [
            fn () => Vec::of(1)->take(-1),
            fn () => Vec::of(1)->drop(-1),
            fn () => Vec::of(1)->chunk(0),
            fn () => Vec::of(1)->chunk(-1),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('Invalid size was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Deduplicates in encounter order using strictly typed scalar keys.
     * 型を区別したスカラーキーで最初の出現順に重複排除することを検証します。
     *
     * @return void
     */
    public function testUniqueBy(): void
    {
        $visits = 0;
        $vec = Vec::of(1, '1', '01', 1, '1');
        self::assertSame([1, '1', '01'], $vec->uniqueBy(function (int|string $value) use (&$visits): int|string {
            ++$visits;
            return $value;
        })->toList());
        self::assertSame(5, $visits);
    }

    /**
     * Indexes values without silently discarding duplicates.
     * 重複を暗黙に捨てずに値へキーを付けられることを検証します。
     *
     * @return void
     */
    public function testKeyByAndSort(): void
    {
        $vec = Vec::of('cc', 'a', 'bbb');
        self::assertSame('cc', $vec->keyBy('strlen')->get(2)->getOrThrow());
        self::assertSame(['a', 'bbb', 'cc'], $vec->sorted('strcmp')->toList());
        self::assertSame(['cc', 'a', 'bbb'], $vec->toList());
        $this->expectException(InvalidArgumentException::class);
        Vec::of('a', 'b')->keyBy('strlen');
    }

    /**
     * Checks map identity and composition over many deterministic sequences.
     * 複数の決定的な入力でmapの恒等則と合成則を検証します。
     *
     * @return void
     */
    public function testMapLaws(): void
    {
        foreach (range(0, 30) as $size) {
            $vec = Vec::fromIterable($size === 0 ? [] : range(1, $size));
            self::assertEquals($vec, $vec->map(fn (int $n): int => $n));
            self::assertEquals(
                $vec->map(fn (int $n): int => $n + 1)->map(fn (int $n): int => $n * 2),
                $vec->map(fn (int $n): int => ($n + 1) * 2),
            );
        }
    }
}
