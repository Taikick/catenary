<?php

declare(strict_types=1);

namespace Catenary;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Catenary\Internal\Key;
use Traversable;
use TypeError;

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
     * Exports a native array only when PHP preserves every original key type.
     * PHPが全ての元のキー型を保持できる場合だけネイティブ配列へ変換します。
     *
     * Numeric string keys that PHP converts to integers throw even without collisions; use toPairs losslessly.
     * 整数へ変わる文字列キーは衝突がなくても拒否します。型を損なわない変換にはtoPairsを使います。
     *
     * @return array<K, V>
     * @throws \InvalidArgumentException If PHP would change a key's type. / キーの型が変わる場合。
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->entries as [$key, $value]) {
            if (array_key_first([$key => true]) !== $key) {
                throw new InvalidArgumentException('Native arrays would change a key type. Use toPairs() instead.');
            }
            $result[$key] = $value;
        }
        return $result;
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
     * Counts all entries in constant time, or visits every entry once to count predicate matches.
     * 述語なしなら定数時間で全件数を返し、指定時は全登録を一回ずつ判定して一致件数を返します。
     *
     * @param (callable(V, K): bool)|null $predicate
     * @return int
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    #[\Override]
    public function count(?callable $predicate = null): int
    {
        if ($predicate === null) {
            return count($this->entries);
        }
        $count = 0;
        foreach ($this->entries as [$key, $value]) {
            if ($predicate($value, $key)) {
                ++$count;
            }
        }
        return $count;
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
     * Returns a present value, including null, or the supplied default only when the typed key is absent.
     * 型を区別したキーに値があればnullも保持し、未登録の場合だけ指定した既定値を返します。
     *
     * A callable default is returned as a value and is never invoked by this method.
     * callableの既定値も値としてそのまま返し、このメソッドから呼び出しません。
     *
     * @template D
     * @param int|string $key
     * @param D $default
     * @return V|D
     */
    public function getOrDefault(int|string $key, mixed $default): mixed
    {
        $encoded = Key::encode(key: $key);
        return isset($this->entries[$encoded]) ? $this->entries[$encoded][1] : $default;
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
     * Groups original dictionaries by strictly typed keys in first-seen group order.
     * 型を区別したキーで辞書を分類し、グループの初出順と各登録の元のキー・順序を保持します。
     *
     * Calls keyOf once per entry with value then key; builds each group without repeated immutable copies.
     * 値・キーの順で各登録を一回分類し、不変辞書の反復コピーをせずグループを構築します。
     *
     * @template G of int|string = never
     * @param callable(V, K): G $keyOf
     * @return \Catenary\Dict<G, \Catenary\Dict<K, V>>
     * @throws \Throwable Propagates classifier exceptions unchanged. / 分類処理の例外をそのまま送出します。
     */
    public function groupBy(callable $keyOf): Dict
    {
        $groups = [];
        foreach ($this->entries as $encoded => $pair) {
            [$key, $value] = $pair;
            $group = $keyOf($value, $key);
            $encodedGroup = Key::encode(key: $group);
            $groups[$encodedGroup] ??= [$group, []];
            $groups[$encodedGroup][1][$encoded] = $pair;
        }
        $entries = [];
        foreach ($groups as $encoded => [$group, $members]) {
            $entries[$encoded] = [$group, new self(entries: $members)];
        }
        return new self(entries: $entries);
    }

    /**
     * Splits original entries into accepted and rejected dictionaries with one ordered predicate pass.
     * 挿入順の一回の判定で元の登録を合致・非合致の二辞書へ分け、キー型と値を保持します。
     *
     * @param callable(V, K): bool $predicate
     * @return array{\Catenary\Dict<K, V>, \Catenary\Dict<K, V>}
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function partition(callable $predicate): array
    {
        $accepted = [];
        $rejected = [];
        foreach ($this->entries as $encoded => $pair) {
            [$key, $value] = $pair;
            if ($predicate($value, $key)) {
                $accepted[$encoded] = $pair;
            } else {
                $rejected[$encoded] = $pair;
            }
        }
        return [new self(entries: $accepted), new self(entries: $rejected)];
    }

    /**
     * Eagerly flattens one level of mapped key-value pairs in source and inner traversal order.
     * 変換したキーと値の組を一段だけ即時に平坦化し、元の挿入順と内側の走査順を保持します。
     *
     * Every mapped iterable is consumed once; duplicate typed keys throw rather than overwrite.
     * 各変換結果を一度だけ走査し、型まで同じ重複キーは上書きせず例外にします。
     *
     * @template KNew of int|string = never
     * @template VNew = never
     * @param callable(V, K): iterable<mixed, array{KNew, VNew}> $mapper
     * @return ($mapper is (callable(never, never): array{})
     *     ? \Catenary\Dict<never, never> : \Catenary\Dict<KNew, VNew>)
     * @throws \InvalidArgumentException For duplicate output keys. / 出力キーが重複した場合。
     * @throws \Throwable Propagates mapper and iterable exceptions unchanged. / 変換・走査の例外をそのまま送出します。
     */
    public function flatMap(callable $mapper): Dict
    {
        $entries = [];
        foreach ($this->entries as [$key, $value]) {
            foreach ($mapper($value, $key) as [$newKey, $newValue]) {
                $encoded = Key::encode(key: $newKey);
                if (isset($entries[$encoded])) {
                    throw new InvalidArgumentException(
                        'Duplicate dictionary key. Use merge() for explicit replacement.',
                    );
                }
                $entries[$encoded] = [$newKey, $newValue];
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
     * Adds numeric values in insertion order, returning integer zero for an empty dictionary.
     * 挿入順に数値を加算し、空辞書では整数のゼロを返します。
     *
     * Accepts only int and float values; native floating-point and integer-overflow behavior is preserved.
     * intとfloatの値だけを受け入れ、浮動小数点と整数オーバーフローはPHP本来の計算に従います。
     *
     * @return int|float
     * @throws \TypeError For any non-numeric value, including numeric strings. / 数値文字列を含む非数値型の場合。
     */
    public function sum(): int|float
    {
        $total = 0;
        foreach ($this->entries as [, $value]) {
            if (!is_int($value) && !is_float($value)) {
                throw new TypeError('Dict::sum() accepts only int and float values.');
            }
            $total = is_int($total) && is_int($value)
                ? $total + $value
                : (float) $total + (float) $value;
        }
        return $total;
    }

    /**
     * Formats entries once in insertion order and joins them with an explicit separator and wrappers.
     * 挿入順に各登録を一回整形し、指定した区切り文字・接頭辞・接尾辞で結合します。
     *
     * The formatter receives value then key; empty dictionaries return prefix plus suffix without calling it.
     * 整形処理には値・キーの順で渡し、空辞書では呼び出さず接頭辞と接尾辞だけを返します。
     *
     * @param callable(V, K): string $formatter
     * @param string $separator
     * @param string $prefix
     * @param string $suffix
     * @return string
     * @throws \Throwable Propagates formatter exceptions unchanged. / 整形処理の例外をそのまま送出します。
     */
    public function mkString(
        callable $formatter,
        string $separator = '',
        string $prefix = '',
        string $suffix = '',
    ): string {
        $parts = [];
        foreach ($this->entries as [$key, $value]) {
            $parts[] = $formatter($value, $key);
        }
        return $prefix . implode($separator, $parts) . $suffix;
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
