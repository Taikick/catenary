<?php

declare(strict_types=1);

namespace Taikick\Collections;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Taikick\Collections\Internal\Key;
use Traversable;

/**
 * An insertion-ordered immutable dictionary that distinguishes 1 from '1'.
 * 整数1と文字列'1'を区別する、挿入順を保持した不変辞書です。
 *
 * Iteration yields [key, value] pairs to preserve key types losslessly.
 * キーの型を損なわないよう、走査では[key, value]の組を返します。
 *
 * @template-covariant K of int|string = never
 * @template-covariant V = never
 * @implements IteratorAggregate<int, array{K, V}>
 */
final readonly class Dict implements Countable, IteratorAggregate
{
    /**
     * Stores pairs indexed by their encoded, strictly typed keys.
     * 型を区別して符号化したキーで索引付けされた組を保持します。
     *
     * @param array<string, array{K, V}> $entries
     */
    private function __construct(private array $entries = [])
    {
    }

    /**
     * Creates an empty dictionary compatible with any key and value types.
     * 任意のキー型・値型と互換性を持つ空の辞書を作成します。
     *
     * @return Dict<never, never>
     */
    public static function empty(): Dict
    {
        return new self();
    }

    /**
     * Consumes key-value pairs once and rejects duplicate keys.
     * キーと値の組を一度走査して収集し、重複キーを拒否します。
     *
     * @template KNew of int|string = never
     * @template VNew = never
     * @param iterable<mixed, array{KNew, VNew}> $pairs
     * @return ($pairs is array{} ? Dict<never, never> : Dict<KNew, VNew>)
     * @throws InvalidArgumentException For a duplicate key. / キーが重複した場合。
     */
    public static function fromPairs(iterable $pairs): Dict
    {
        if ($pairs === []) {
            return self::empty();
        }
        $entries = [];
        foreach ($pairs as [$key, $value]) {
            $encoded = Key::encode($key);
            if (isset($entries[$encoded])) {
                throw new InvalidArgumentException('Duplicate dictionary key. Use with() for explicit replacement.');
            }
            $entries[$encoded] = [$key, $value];
        }
        return new self($entries);
    }

    /**
     * Returns lossless key-value pairs in insertion order.
     * 型を失わないキーと値の組を挿入順で返します。
     *
     * @return list<array{K, V}>
     */
    public function toPairs(): array
    {
        return array_values($this->entries);
    }

    /**
     * Iterates over key-value pairs with zero-based sequence indexes.
     * ゼロ始まりの連番でキーと値の組を走査します。
     *
     * @return Traversable<int, array{K, V}>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        foreach ($this->entries as $pair) {
            yield $pair;
        }
    }

    /**
     * Returns the entry count in constant time.
     * 登録件数を定数時間で返します。
     */
    #[\Override]
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Checks whether the dictionary has no entries.
     * 辞書に登録値がないかどうかを調べます。
     */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Checks key presence without confusing null values with missing entries.
     * nullの値を欠損と混同せずにキーの存在を調べます。
     */
    public function has(int|string $key): bool
    {
        return isset($this->entries[Key::encode($key)]);
    }

    /**
     * Looks up a strictly typed key and preserves a present null value.
     * キーの型を厳密に区別して検索し、存在するnullを保持します。
     *
     * @return Option<V>
     */
    public function get(int|string $key): Option
    {
        $encoded = Key::encode($key);
        return isset($this->entries[$encoded]) ? Option::some($this->entries[$encoded][1]) : Option::none();
    }

    /**
     * Inserts or replaces a value in a new dictionary, preserving existing positions.
     * 新しい辞書へ値を挿入または置換し、既存キーの位置は保持します。
     *
     * @template KAdded of int|string
     * @template VAdded
     * @param KAdded $key
     * @param VAdded $value
     * @return Dict<K|KAdded, V|VAdded>
     */
    public function with(int|string $key, mixed $value): Dict
    {
        return new self([...$this->entries, Key::encode($key) => [$key, $value]]);
    }

    /**
     * Removes a key from a new dictionary; absent keys have no effect.
     * 新しい辞書からキーを除去します。存在しないキーは結果に影響しません。
     *
     * @return Dict<K, V>
     */
    public function without(int|string $key): Dict
    {
        $entries = $this->entries;
        unset($entries[Key::encode($key)]);
        return new self($entries);
    }

    /**
     * Transforms values with their keys while preserving key types and order.
     * キーとともに値を変換し、キーの型と順序は保持します。
     *
     * @template U
     * @param callable(V, K): U $mapper
     * @return Dict<K, U>
     */
    public function mapValues(callable $mapper): Dict
    {
        $entries = [];
        foreach ($this->entries as $encoded => [$key, $value]) {
            $entries[$encoded] = [$key, $mapper($value, $key)];
        }
        return new self($entries);
    }

    /**
     * Returns keys in their insertion order, retaining their original types.
     * 元の型を維持したキーを挿入順で返します。
     *
     * @return Vec<K>
     */
    public function keys(): Vec
    {
        $keys = [];
        foreach ($this->entries as [$key]) {
            $keys[] = $key;
        }
        return Vec::fromIterable($keys);
    }

    /**
     * Returns values in their keys' insertion order.
     * 対応するキーの挿入順に値を返します。
     *
     * @return Vec<V>
     */
    public function values(): Vec
    {
        $values = [];
        foreach ($this->entries as [, $value]) {
            $values[] = $value;
        }
        return Vec::fromIterable($values);
    }
}
