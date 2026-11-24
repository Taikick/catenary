<?php

declare(strict_types=1);

namespace Taikick\Collections;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * A lazy pipeline that requests a fresh iterable for every traversal.
 * 走査のたびに新しいiterableを要求する遅延処理パイプラインです。
 *
 * Values are not cached; factories must return a fresh iterable each time.
 * 値はキャッシュしません。生成処理は毎回新しいiterableを返す必要があります。
 *
 * @template-covariant T
 * @implements IteratorAggregate<int, T>
 */
final readonly class Stream implements IteratorAggregate
{
    /**
     * Stores the factory without opening its source.
     * 入力を開かずに生成処理を保持します。
     *
     * @param Closure(): iterable<mixed, T> $factory
     */
    private function __construct(private Closure $factory)
    {
    }

    /**
     * Defers a factory that must provide a fresh iterable for each traversal.
     * 走査ごとに新しいiterableを提供する生成処理を遅延登録します。
     *
     * @template U
     * @param callable(): iterable<mixed, U> $factory
     * @return Stream<U>
     */
    public static function fromFactory(callable $factory): Stream
    {
        return new self(Closure::fromCallable($factory));
    }

    /**
     * Opens the source on first iteration and discards its keys.
     * 最初の反復時に入力を開き、入力のキーを捨てて走査します。
     *
     * @return Traversable<int, T>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        foreach (($this->factory)() as $value) {
            yield $value;
        }
    }

    /**
     * Lazily transforms each requested value once per traversal.
     * 各走査で要求された値を一度ずつ遅延変換します。
     *
     * @template U
     * @param callable(T): U $mapper
     * @return Stream<U>
     */
    public function map(callable $mapper): Stream
    {
        return self::fromFactory(function () use ($mapper): Generator {
            foreach ($this as $value) {
                yield $mapper($value);
            }
        });
    }

    /**
     * Lazily retains values satisfying the predicate, in source order.
     * 条件を満たす値を入力順に遅延して残します。
     *
     * @param callable(T): bool $predicate
     * @return Stream<T>
     */
    public function filter(callable $predicate): Stream
    {
        return self::fromFactory(function () use ($predicate): Generator {
            foreach ($this as $value) {
                if ($predicate($value)) {
                    yield $value;
                }
            }
        });
    }

    /**
     * Lazily extracts present mapped values, including explicit nulls.
     * 変換結果の存在する値を、明示的なnullを含めて遅延抽出します。
     *
     * @template U
     * @param callable(T): Option<U> $mapper
     * @return Stream<U>
     */
    public function filterMap(callable $mapper): Stream
    {
        return self::fromFactory(function () use ($mapper): Generator {
            foreach ($this as $value) {
                $option = $mapper($value);
                if ($option->isSome()) {
                    yield $option->getOrThrow();
                }
            }
        });
    }

    /**
     * Lazily flattens one level of mapped iterables, discarding inner keys.
     * 変換したiterableを遅延して一段平坦化し、内側のキーを捨てます。
     *
     * @template U
     * @param callable(T): iterable<mixed, U> $mapper
     * @return Stream<U>
     */
    public function flatMap(callable $mapper): Stream
    {
        return self::fromFactory(function () use ($mapper): Generator {
            foreach ($this as $value) {
                foreach ($mapper($value) as $inner) {
                    yield $inner;
                }
            }
        });
    }

    /**
     * Yields at most count values without reading a subsequent source value.
     * 次の入力値を余分に読まず、指定個数を上限として値を返します。
     *
     * @return Stream<T>
     * @throws InvalidArgumentException For a negative count, immediately. / 個数が負の場合に即座に送出します。
     */
    public function take(int $count): Stream
    {
        if ($count < 0) {
            throw new InvalidArgumentException('The take count must be non-negative.');
        }
        return self::fromFactory(function () use ($count): Generator {
            if ($count === 0) {
                return;
            }
            $remaining = $count;
            foreach ($this as $value) {
                yield $value;
                if (--$remaining === 0) {
                    return;
                }
            }
        });
    }

    /**
     * Lazily skips count leading values before yielding subsequent values.
     * 先頭の指定個数を遅延して読み飛ばし、その後の値を返します。
     *
     * @return Stream<T>
     * @throws InvalidArgumentException For a negative count, immediately. / 個数が負の場合に即座に送出します。
     */
    public function drop(int $count): Stream
    {
        if ($count < 0) {
            throw new InvalidArgumentException('The drop count must be non-negative.');
        }
        return self::fromFactory(function () use ($count): Generator {
            $remaining = $count;
            foreach ($this as $value) {
                if ($remaining > 0) {
                    --$remaining;
                    continue;
                }
                yield $value;
            }
        });
    }

    /**
     * Returns the first output value and closes this traversal early.
     * 最初の出力値を返し、この走査を途中で終了します。
     *
     * @return Option<T>
     */
    public function first(): Option
    {
        foreach ($this as $value) {
            return Option::some($value);
        }
        return Option::none();
    }

    /**
     * Checks for an output value without materializing the stream.
     * ストリームを実体化せず、出力値があるかを調べます。
     */
    public function isEmpty(): bool
    {
        return $this->first()->isNone();
    }

    /**
     * Stops at the first match; returns false for an empty stream.
     * 最初に条件を満たした時点で終了し、空の場合はfalseを返します。
     *
     * @param callable(T): bool $predicate
     */
    public function any(callable $predicate): bool
    {
        foreach ($this as $value) {
            if ($predicate($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stops at the first rejection; returns true for an empty stream.
     * 最初に条件を満たさなかった時点で終了し、空の場合はtrueを返します。
     *
     * @param callable(T): bool $predicate
     */
    public function every(callable $predicate): bool
    {
        foreach ($this as $value) {
            if (!$predicate($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Consumes a finite stream with a left fold from an explicit initial value.
     * 明示した初期値から左方向へ畳み込み、有限ストリームを走査し切ります。
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
        foreach ($this as $value) {
            $accumulator = $reducer($accumulator, $value);
        }
        return $accumulator;
    }

    /**
     * Materializes a finite stream into an explicit immutable snapshot.
     * 有限ストリームを明示的な不変スナップショットとして実体化します。
     *
     * @return Vec<T>
     */
    public function toVec(): Vec
    {
        return Vec::fromIterable($this);
    }
}
