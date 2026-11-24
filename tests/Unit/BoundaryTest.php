<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Unit;

use Generator;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

/**
 * Exercises ownership, failure propagation and deterministic reference properties.
 * 所有・例外伝播・決定的な参照実装との性質を検証します。
 */
final class BoundaryTest extends TestCase
{
    /**
     * Isolates array structure while deliberately sharing mutable object elements.
     * 配列構造を分離しつつ、変更可能な要素オブジェクトは意図的に共有します。
     */
    public function testShallowImmutability(): void
    {
        $object = new stdClass();
        $object->value = 1;
        $input = [$object, 2];
        $vec = Vec::fromIterable($input);
        $input[] = 3;
        $output = $vec->toList();
        $output[] = 4;
        self::assertSame([$object, 2], $vec->toList());
        $object->value = 9;
        self::assertSame($object, $vec->first()->getOrThrow());
        self::assertSame(9, $vec->first()->getOrThrow()->value);
    }

    /**
     * Keeps first-level values independent from references in input arrays.
     * 入力配列内の参照から第一階層の値を分離して保持します。
     */
    public function testInputReferencesAreDetached(): void
    {
        $value = 1;
        $key = 'original';
        $vec = Vec::fromIterable([&$value]);
        $dict = Dict::fromPairs([[&$key, &$value]]);
        $value = 2;
        $key = 'changed';
        self::assertSame([1], $vec->toList());
        self::assertSame([['original', 1]], $dict->toPairs());
    }

    /**
     * Preserves exception identity at eager and lazy callback boundaries.
     * 即時・遅延コールバックの境界で例外の同一性を保持することを検証します。
     */
    public function testExceptionsPropagateUnchanged(): void
    {
        $failure = new LogicException('Expected failure');
        $throw = fn () => throw $failure;
        foreach (
            [
            fn () => Option::some(1)->map($throw),
            fn () => Vec::of(1)->map($throw),
            fn () => Dict::fromPairs([['a', 1]])->mapValues($throw),
            fn () => Stream::fromFactory($throw)->toVec(),
            fn () => Vec::of(1)->stream()->fold(0, $throw),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('The exception was swallowed.');
            } catch (LogicException $error) {
                self::assertSame($failure, $error);
            }
        }
        self::assertSame(1, Option::some(1)->getOrThrow($throw));
    }

    /**
     * Collects generator values once, preserving duplicate source keys as values.
     * ジェネレータを一度だけ収集し、入力キーが重複してもすべての値を保持します。
     */
    public function testGeneratorCollectionAndEmptyFolds(): void
    {
        $source = static function (): Generator {
            yield 'same' => 1;
            yield 'same' => 2;
        };
        self::assertSame([1, 2], Vec::fromIterable($source())->toList());
        self::assertSame([1, 2], Stream::fromFactory($source)->toVec()->toList());
        self::assertSame([], Vec::empty()->fold([], fn () => self::fail('Unexpected reduction')));
        self::assertSame([], Vec::empty()->stream()->fold([], fn () => self::fail('Unexpected reduction')));
        self::assertSame([], Dict::fromPairs([])->toPairs());
        self::assertSame([1], Vec::of(first: 1)->toList());
    }

    /**
     * Checks strict deduplication, partitions and eager/lazy equivalence on many inputs.
     * 複数の入力で厳密な重複排除・分割・即時遅延処理の同等性を検証します。
     */
    public function testReferenceProperties(): void
    {
        $pool = [0, '0', 1, '1', -1, '-1', '', '01', 'i:1', "\0"];
        foreach (range(0, 60) as $length) {
            $values = [];
            for ($i = 0; $i < $length; ++$i) {
                $values[] = $pool[($i * 7 + $length * 3) % count($pool)];
            }
            $expected = [];
            foreach ($values as $value) {
                if (!in_array($value, $expected, true)) {
                    $expected[] = $value;
                }
            }
            $vec = Vec::fromIterable($values);
            self::assertSame($expected, $vec->uniqueBy(fn (int|string $v): int|string => $v)->toList());
            [$ints, $strings] = $vec->partition('is_int');
            self::assertSame(array_values(array_filter($values, 'is_int')), $ints->toList());
            self::assertSame(array_values(array_filter($values, 'is_string')), $strings->toList());
            self::assertSame(
                $vec->filter('is_string')->map('strlen')->drop(1)->take(4)->toList(),
                $vec->stream()->filter('is_string')->map('strlen')->drop(1)->take(4)->toVec()->toList(),
            );
        }
    }

    /**
     * Keeps equal elements in their original order during sorting.
     * ソート時に比較結果が等しい要素の元の順序を保持します。
     */
    public function testStableSorting(): void
    {
        $values = [[2, 'first'], [1, 'middle'], [2, 'last']];
        $sorted = Vec::fromIterable($values)->sorted(fn (array $a, array $b): int => $a[0] <=> $b[0]);
        self::assertSame([[1, 'middle'], [2, 'first'], [2, 'last']], $sorted->toList());
    }
}
