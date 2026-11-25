<?php

declare(strict_types=1);

namespace Catenary;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Catenary\Internal\Key;
use Traversable;

/**
 * An eagerly evaluated, ordered, shallowly immutable list of values.
 * 即時評価される、順序付きの浅い不変性を持つ値のリストです。
 *
 * @template-covariant T = never
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Vec implements Countable, IteratorAggregate
{
    /**
     * Stores an already normalized list.
     * 連続した整数キーに正規化済みのリストを保持します。
     *
     * @param list<T> $items
     * @return void
     */
    private function __construct(private array $items = [])
    {
    }

    /**
     * Creates an empty list compatible with every element type.
     * あらゆる要素型と互換性を持つ空のリストを作成します。
     *
     * @return \Catenary\Vec<never>
     */
    public static function empty(): Vec
    {
        return new self();
    }

    /**
     * Collects arguments in their supplied order, discarding argument names.
     * 引数名を捨て、渡された順に値を収集します。
     *
     * @template U
     * @param U ...$items
     * @return \Catenary\Vec<U>
     */
    public static function of(mixed ...$items): Vec
    {
        return new self(items: array_values($items));
    }

    /**
     * Reuses a Vec snapshot; eagerly consumes every other iterable once, discarding keys.
     * Vecは同じスナップショットを再利用し、他のiterableはキーを捨てて一度だけ即時走査します。
     *
     * @template U
     * @param iterable<mixed, U> $items
     * @return \Catenary\Vec<U>
     */
    public static function fromIterable(iterable $items): Vec
    {
        if ($items instanceof self) {
            return $items;
        }
        $values = [];
        foreach ($items as $item) {
            $values[] = $item;
        }
        return new self(items: $values);
    }

    /**
     * Returns a zero-based PHP list; modifying it does not modify this Vec.
     * ゼロ始まりのPHPリストを返します。返却配列の変更はVecに影響しません。
     *
     * @return list<T>
     */
    public function toList(): array
    {
        return $this->items;
    }

    /**
     * Iterates over values with consecutive zero-based indexes.
     * ゼロ始まりの連続したインデックスで値を走査します。
     *
     * @return \Traversable<int, T>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    /**
     * Returns the number of elements in constant time.
     * 要素数を定数時間で返します。
     *
     * @return int
     */
    #[\Override]
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Checks for an empty list in constant time.
     * リストが空かどうかを定数時間で調べます。
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Looks up a zero-based index; negative or missing indexes return None.
     * ゼロ始まりの位置を検索し、負数や存在しない位置ならNoneを返します。
     *
     * @return \Catenary\Option<T>
     * @param int $index
     */
    public function at(int $index): Option
    {
        return array_key_exists($index, $this->items) ? Option::some(value: $this->items[$index]) : Option::none();
    }

    /**
     * Returns the first element, preserving a present null.
     * 存在するnullを保持したまま先頭要素を返します。
     *
     * @return \Catenary\Option<T>
     */
    public function first(): Option
    {
        return $this->at(0);
    }

    /**
     * Returns the last element in constant time, preserving present null and empty absence.
     * 定数時間で末尾を取得し、存在するnullと空リストの欠損を区別します。
     *
     * @return \Catenary\Option<T>
     */
    public function last(): Option
    {
        return $this->at(count($this->items) - 1);
    }

    /**
     * Returns the first matching value, including null; later predicates are not evaluated.
     * nullを含め最初に合致した値を返し、それ以降の述語を評価しません。空ならNoneです。
     *
     * @param callable(T): bool $predicate
     * @return \Catenary\Option<T>
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function find(callable $predicate): Option
    {
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return Option::some(value: $item);
            }
        }
        return Option::none();
    }

    /**
     * Stops at the first accepted value; empty vectors return false without invoking the predicate.
     * 最初の一致で終了し、空リストでは述語を呼ばずfalseを返します。
     *
     * @param callable(T): bool $predicate
     * @return bool
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function any(callable $predicate): bool
    {
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stops at the first rejected value; empty vectors return true without invoking the predicate.
     * 最初の不一致で終了し、空リストでは述語を呼ばずtrueを返します。
     *
     * @param callable(T): bool $predicate
     * @return bool
     * @throws \Throwable Propagates predicate exceptions unchanged. / 述語の例外をそのまま送出します。
     */
    public function every(callable $predicate): bool
    {
        foreach ($this->items as $item) {
            if (!$predicate($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Transforms every element once in encounter order.
     * 各要素を出現順に一度ずつ変換します。
     *
     * @template U
     * @param callable(T): U $mapper
     * @return \Catenary\Vec<U>
     */
    public function map(callable $mapper): Vec
    {
        $values = [];
        foreach ($this->items as $item) {
            $values[] = $mapper($item);
        }
        return new self(items: $values);
    }

    /**
     * Retains matching elements in order and reindexes the result.
     * 条件に合う要素を順序を保って残し、結果の添字を振り直します。
     *
     * @param callable(T): bool $predicate
     * @return \Catenary\Vec<T>
     */
    public function filter(callable $predicate): Vec
    {
        $values = [];
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                $values[] = $item;
            }
        }
        return new self(items: $values);
    }

    /**
     * Maps to options and retains only present values, including null.
     * Optionへ変換し、nullを含めて存在する値だけを収集します。
     *
     * @template U
     * @param callable(T): \Catenary\Option<U> $mapper
     * @return \Catenary\Vec<U>
     */
    public function filterMap(callable $mapper): Vec
    {
        $values = [];
        foreach ($this->items as $item) {
            $option = $mapper($item);
            if ($option->isSome()) {
                $values[] = $option->getOrThrow();
            }
        }
        return new self(items: $values);
    }

    /**
     * Flattens one level of mapped iterables, discarding inner keys.
     * 変換したiterableを一段だけ平坦化し、内側のキーを捨てます。
     *
     * @template U
     * @param callable(T): iterable<mixed, U> $mapper
     * @return \Catenary\Vec<U>
     */
    public function flatMap(callable $mapper): Vec
    {
        $values = [];
        foreach ($this->items as $item) {
            foreach ($mapper($item) as $value) {
                $values[] = $value;
            }
        }
        return new self(items: $values);
    }

    /**
     * Folds left from an explicit initial value.
     * 明示した初期値から左方向へ順に畳み込みます。
     *
     * @template A
     * @template B
     * @param A $initial
     * @param callable(A|B, T): B $reducer
     * @return A|B
     */
    public function fold(mixed $initial, callable $reducer): mixed
    {
        $accumulator = $initial;
        foreach ($this->items as $item) {
            $accumulator = $reducer($accumulator, $item);
        }
        return $accumulator;
    }

    /**
     * Returns a new list with an appended value, widening the element type.
     * 要素型を拡張し、末尾に値を追加した新しいリストを返します。
     *
     * @template U
     * @param U $value
     * @return \Catenary\Vec<T|U>
     */
    public function append(mixed $value): Vec
    {
        return new self(items: [...$this->items, $value]);
    }

    /**
     * Appends values from another iterable without preserving its keys.
     * 別のiterableのキーを捨てて値を末尾に連結します。
     *
     * @template U
     * @param iterable<mixed, U> $items
     * @return \Catenary\Vec<T|U>
     */
    public function concat(iterable $items): Vec
    {
        $values = $this->items;
        foreach ($items as $item) {
            $values[] = $item;
        }
        return new self(items: $values);
    }

    /**
     * Takes at most the requested number of leading elements.
     * 指定した個数を上限として先頭から要素を取り出します。
     *
     * @return \Catenary\Vec<T>
     * @throws \InvalidArgumentException For a negative count. / 個数が負の場合。
     * @param int $count
     */
    public function take(int $count): Vec
    {
        if ($count < 0) {
            throw new InvalidArgumentException('The take count must be non-negative.');
        }
        return new self(items: array_slice($this->items, 0, $count));
    }

    /**
     * Skips the requested number of leading elements.
     * 指定した個数だけ先頭の要素を読み飛ばします。
     *
     * @return \Catenary\Vec<T>
     * @throws \InvalidArgumentException For a negative count. / 個数が負の場合。
     * @param int $count
     */
    public function drop(int $count): Vec
    {
        if ($count < 0) {
            throw new InvalidArgumentException('The drop count must be non-negative.');
        }
        return new self(items: array_slice($this->items, $count));
    }

    /**
     * Splits values into nonempty chunks no larger than the requested size.
     * 指定サイズ以下の空でないチャンクへ値を分割します。
     *
     * @return \Catenary\Vec<\Catenary\Vec<T>>
     * @throws \InvalidArgumentException For a non-positive size. / サイズが正でない場合。
     * @param int $size
     */
    public function chunk(int $size): Vec
    {
        if ($size < 1) {
            throw new InvalidArgumentException('The chunk size must be positive.');
        }
        $chunks = [];
        foreach (array_chunk($this->items, $size) as $chunk) {
            $chunks[] = new self(items: $chunk);
        }
        return new self(items: $chunks);
    }

    /**
     * Separates accepted and rejected values, preserving order in each list.
     * 条件に合う値と合わない値を、それぞれの順序を保って分離します。
     *
     * @param callable(T): bool $predicate
     * @return array{\Catenary\Vec<T>, \Catenary\Vec<T>}
     */
    public function partition(callable $predicate): array
    {
        $yes = [];
        $no = [];
        foreach ($this->items as $item) {
            if ($predicate($item)) {
                $yes[] = $item;
            } else {
                $no[] = $item;
            }
        }
        return [new self(items: $yes), new self(items: $no)];
    }

    /**
     * Keeps the first value per strict key using expected linear-time hashing.
     * 型を区別するキーごとの最初の値を、期待線形時間のハッシュ処理で残します。
     *
     * @param callable(T): (int|string) $keyOf
     * @return \Catenary\Vec<T>
     */
    public function uniqueBy(callable $keyOf): Vec
    {
        $seen = [];
        $values = [];
        foreach ($this->items as $item) {
            $key = Key::encode(key: $keyOf($item));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $values[] = $item;
            }
        }
        return new self(items: $values);
    }

    /**
     * Indexes values by strict keys, rejecting duplicates rather than overwriting.
     * 型を区別するキーで値に索引を付け、重複時は上書きせず拒否します。
     *
     * @template K of array-key
     * @param callable(T): K $keyOf
     * @return \Catenary\Dict<K, T>
     * @throws \InvalidArgumentException For a duplicate key. / キーが重複した場合。
     */
    public function keyBy(callable $keyOf): Dict
    {
        $pairs = [];
        foreach ($this->items as $item) {
            $pairs[] = [$keyOf($item), $item];
        }
        return Dict::fromPairs($pairs);
    }

    /**
     * Returns a stably sorted copy using an explicit comparator.
     * 明示した比較処理を使い、安定ソートしたコピーを返します。
     *
     * @param callable(T, T): int $compare
     * @return \Catenary\Vec<T>
     */
    public function sorted(callable $compare): Vec
    {
        $items = $this->items;
        usort($items, $compare);
        return new self(items: $items);
    }

    /**
     * Creates a lazy stream that traverses this snapshot on each evaluation.
     * 評価のたびにこのスナップショットを走査する遅延ストリームを作成します。
     *
     * @return \Catenary\Stream<T>
     */
    public function stream(): Stream
    {
        return Stream::fromFactory(fn (): array => $this->items);
    }
}
