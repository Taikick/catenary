<?php

declare(strict_types=1);

namespace Catenary\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Catenary\Dict;

/**
 * Tests insertion-ordered dictionaries with strictly distinguished key types.
 * キーの型を厳密に区別する挿入順辞書を検証します。
 */
final class DictTest extends TestCase
{
    /**
     * Preserves numeric strings, integers, empty keys and present nulls.
     * 数値文字列・整数・空キー・存在するnullを保持することを検証します。
     *
     * @return void
     */
    public function testStrictKeysAndNull(): void
    {
        $pairs = [[1, 'integer'], ['1', 'string'], ['', null], ['i:1', 'prefix'], ["\0", false]];
        $dict = Dict::fromPairs($pairs);
        self::assertSame($pairs, $dict->toPairs());
        self::assertSame($pairs, iterator_to_array($dict));
        self::assertSame(5, count($dict));
        self::assertFalse($dict->isEmpty());
        self::assertSame('integer', $dict->get(1)->getOrThrow());
        self::assertSame('string', $dict->get('1')->getOrThrow());
        self::assertTrue($dict->has(''));
        self::assertTrue($dict->get('')->isSome());
        self::assertNull($dict->get('')->getOrThrow());
        self::assertFalse($dict->has(99));
        self::assertTrue($dict->get(99)->isNone());
        self::assertTrue(Dict::empty()->isEmpty());
    }

    /**
     * Rejects repeated keys even when the previous value was null.
     * 先行する値がnullでも重複キーを拒否することを検証します。
     *
     * @return void
     */
    public function testDuplicateKeysAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Dict::fromPairs([['x', null], ['x', 1]]);
    }

    /**
     * Makes replacement explicit while retaining the original insertion position.
     * 置換を明示的に行い、元の挿入位置を保つことを検証します。
     *
     * @return void
     */
    public function testImmutableUpdates(): void
    {
        $dict = Dict::fromPairs([['a', 1], ['b', 2]]);
        $updated = $dict->with('a', null)->with(1, 'x');
        self::assertSame([['a', null], ['b', 2], [1, 'x']], $updated->toPairs());
        self::assertSame([['a', 1], ['b', 2]], $dict->toPairs());
        self::assertSame([['b', 2]], $dict->without('a')->toPairs());
        self::assertSame([['a', 1], ['b', 2]], $dict->without('missing')->toPairs());
        self::assertSame([['b', 2], ['a', 3]], $dict->without('a')->with('a', 3)->toPairs());
    }

    /**
     * Maps values with their original key and extracts ordered views.
     * 元のキーとともに値を変換し、順序付きの一覧を取り出せることを検証します。
     *
     * @return void
     */
    public function testMapValuesAndViews(): void
    {
        $dict = Dict::fromPairs([[1, 'a'], ['1', 'b']]);
        self::assertSame([1, '1'], $dict->keys()->toList());
        self::assertSame(['a', 'b'], $dict->values()->toList());
        self::assertSame(
            [[1, 'int:a'], ['1', 'string:b']],
            $dict->mapValues(
                fn (string $value, int|string $key): string => get_debug_type($key) . ':' . $value,
            )->toPairs(),
        );
    }
}
