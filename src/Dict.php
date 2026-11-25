<?php

declare(strict_types=1);

namespace Catenary;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Catenary\Internal\Key;
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
 * @implements \IteratorAggregate<int, array{K, V}>
 */
final readonly class Dict implements Countable, IteratorAggregate
{
    /**
     * Stores pairs indexed by their encoded, strictly typed keys.
     * 型を区別して符号化したキーで索引付けされた組を保持します。
     *
     * @param array<string, array{K, V}> $entries
     * @return void
     */
    private function __construct(private array $entries = [])
    {
    }

    /**
     * Creates an empty dictionary compatible with any key and value types.
     * 任意のキー型・値型と互換性を持つ空の辞書を作成します。
     *
     * @return \Catenary\Dict<never, never>
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
     * @return ($pairs is array{} ? \Catenary\Dict<never, never> : \Catenary\Dict<KNew, VNew>)
     * @throws \InvalidArgumentException For a duplicate key. / キーが重複した場合。
     */
    public static function fromPairs(iterable $pairs): Dict
    {
        if ($pairs === []) {
            return self::empty();
        }
        $entries = [];
        foreach ($pairs as [$key, $value]) {
            $encoded = Key::encode(key: $key);
            if (isset($entries[$encoded])) {
                throw new InvalidArgumentException('Duplicate dictionary key. Use with() for explicit replacement.');
            }
            $entries[$encoded] = [$key, $value];
        }
        return new self(entries: $entries);
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
     * @return \Traversable<int, array{K, V}>
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
     *
     * @return int
     */
    #[\Override]
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Checks whether the dictionary has no entries.
     * 辞書に登録値がないかどうかを調べます。
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Checks key presence without confusing null values with missing entries.
     * nullの値を欠損と混同せずにキーの存在を調べます。
     *
     * @param int|string $key
     * @return bool
     */
    public function has(int|string $key): bool
    {
        return isset($this->entries[Key::encode(key: $key)]);
    }

    /**
     * Looks up a strictly typed key and preserves a present null value.
     * キーの型を厳密に区別して検索し、存在するnullを保持します。
     *
     * @return \Catenary\Option<V>
     * @param int|string $key
     */
    public function get(int|string $key): Option
    {
        $encoded = Key::encode(key: $key);
        return isset($this->entries[$encoded]) ? Option::some(value: $this->entries[$encoded][1]) : Option::none();
    }

    /**
     * Returns the first matching key-value pair, stopping before later entries; no match yields None.
     * 最初に一致したキーと値の組で走査を終了し、一致がなければNoneを返します。
     *
     * A null value remains present inside its pair; the predicate receives value then key in insertion order.
     * nullの値も組の中に保持し、述語には挿入順で値・キーの順に渡します。
     *
     * @param callable(V, K): bool $predicate
     * @return \Catenary\Option<array{K, V}>
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function find(callable $predicate): Option
    {
        foreach ($this->entries as $pair) {
            [$key, $value] = $pair;
            if ($predicate($value, $key)) {
                return Option::some(value: $pair);
            }
        }
        return Option::none();
    }

    /**
     * Stops at the first accepted entry; empty dictionaries return false without invoking the predicate.
     * 最初の一致で終了し、空辞書では述語を呼ばずfalseを返します。
     *
     * @param callable(V, K): bool $predicate
     * @return bool
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->entries as [$key, $value]) {
            if ($predicate($value, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stops at the first rejected entry; empty dictionaries return true without invoking the predicate.
     * 最初の不一致で終了し、空辞書では述語を呼ばずtrueを返します。
     *
     * @param callable(V, K): bool $predicate
     * @return bool
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->entries as [$key, $value]) {
            if (!$predicate($value, $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Inserts or replaces a value in a new dictionary, preserving existing positions.
     * 新しい辞書へ値を挿入または置換し、既存キーの位置は保持します。
     *
     * @template KAdded of int|string
     * @template VAdded
     * @param KAdded $key
     * @param VAdded $value
     * @return \Catenary\Dict<K|KAdded, V|VAdded>
     */
    public function with(int|string $key, mixed $value): Dict
    {
        return new self(entries: [...$this->entries, Key::encode(key: $key) => [$key, $value]]);
    }

    /**
     * Combines dictionaries once, with right-hand values replacing exactly matching typed keys.
     * 二つの辞書を一括結合し、型を含めて一致するキーを右側の値で置換します。
     *
     * Existing left positions stay fixed; new keys follow right insertion order without changing either source.
     * 左側の既存位置を保持し、新規キーを右側の挿入順で追加して、元の両辞書は変更しません。
     *
     * @template KOther of int|string
     * @template VOther
     * @param \Catenary\Dict<KOther, VOther> $other
     * @return \Catenary\Dict<K|KOther, V|VOther>
     */
    public function merge(Dict $other): Dict
    {
        return new self(entries: [...$this->entries, ...$other->entries]);
    }

    /**
     * Removes a key from a new dictionary; absent keys have no effect.
     * 新しい辞書からキーを除去します。存在しないキーは結果に影響しません。
     *
     * @return \Catenary\Dict<K, V>
     * @param int|string $key
     */
    public function without(int|string $key): Dict
    {
        $entries = $this->entries;
        unset($entries[Key::encode(key: $key)]);
        return new self(entries: $entries);
    }

    /**
     * Eagerly tests each entry once and retains matching values with their original typed keys and order.
     * 各項目を即時に一回判定し、一致した値を元のキー型と順序のまま保持します。
     *
     * @param callable(V, K): bool $predicate
     * @return \Catenary\Dict<K, V>
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function filter(callable $predicate): Dict
    {
        $entries = [];
        foreach ($this->entries as $encoded => $pair) {
            [$key, $value] = $pair;
            if ($predicate($value, $key)) {
                $entries[$encoded] = $pair;
            }
        }
        return new self(entries: $entries);
    }

    /**
     * Transforms values with their keys while preserving key types and order.
     * キーとともに値を変換し、キーの型と順序は保持します。
     *
     * @template U
     * @param callable(V, K): U $mapper
     * @return \Catenary\Dict<K, U>
     */
    public function mapValues(callable $mapper): Dict
    {
        $entries = [];
        foreach ($this->entries as $encoded => [$key, $value]) {
            $entries[$encoded] = [$key, $mapper($value, $key)];
        }
        return new self(entries: $entries);
    }

    /**
     * Folds once in insertion order, passing accumulator, value and key; empty dictionaries return the initial value.
     * 挿入順で一度集約し累積値・値・キーを渡します。空辞書では初期値をそのまま返します。
     *
     * @template A
     * @template B
     * @param A $initial
     * @param callable(A|B, V, K): B $reducer
     * @return A|B
     * @throws \Throwable Propagates reducer exceptions unchanged. / 集約処理の例外をそのまま送出します。
     */
    public function fold(mixed $initial, callable $reducer): mixed
    {
        $accumulator = $initial;
        foreach ($this->entries as [$key, $value]) {
            $accumulator = $reducer($accumulator, $value, $key);
        }
        return $accumulator;
    }

    /**
     * Returns keys in their insertion order, retaining their original types.
     * 元の型を維持したキーを挿入順で返します。
     *
     * @return \Catenary\Vec<K>
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
     * @return \Catenary\Vec<V>
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
