<?php

declare(strict_types=1);

namespace Taikick\Collections;

use Taikick\Collections\Internal\None;
use Taikick\Collections\Internal\Some;
use Throwable;
use UnderflowException;

/**
 * Represents either a present value (including null) or absence.
 * nullを含む値の存在、または値の欠損を表します。
 *
 * @template-covariant T
 */
abstract readonly class Option
{
    /**
     * Wraps a value without interpreting null as absence.
     * nullを欠損と解釈せず、そのまま値として包みます。
     *
     * @template U
     * @param U $value
     * @return Option<U>
     */
    final public static function some(mixed $value): Option
    {
        return new Some($value);
    }

    /**
     * Creates an absent option, compatible with any value type.
     * 任意の値型と互換性のある欠損状態を作成します。
     *
     * @return Option<never>
     */
    final public static function none(): Option
    {
        return new None();
    }

    /**
     * Converts null to absence and preserves every other value.
     * nullを欠損へ変換し、それ以外の値は保持します。
     *
     * @template U
     * @param U|null $value
     * @return Option<U>
     */
    final public static function fromNullable(mixed $value): Option
    {
        return $value === null ? self::none() : self::some($value);
    }

    /**
     * Reports whether a value is present, even when that value is null.
     * 値がnullの場合も含め、値が存在するかを返します。
     */
    abstract public function isSome(): bool;

    /**
     * Reports whether the option is absent.
     * 値が欠損しているかを返します。
     */
    final public function isNone(): bool
    {
        return !$this->isSome();
    }

    /**
     * Returns the value or throws a lazily created exception on absence.
     * 値を返し、欠損時は必要になった時点で生成した例外を送出します。
     *
     * @param (callable(): Throwable)|null $exception
     * @return T
     * @throws UnderflowException When absent and no exception factory is supplied. / 欠損時に例外生成処理の指定がない場合。
     * @throws Throwable The exception produced by the supplied factory. / 指定した処理が生成する例外。
     */
    abstract public function getOrThrow(?callable $exception = null): mixed;

    /**
     * Returns the value or evaluates a fallback only on absence.
     * 値を返すか、欠損の場合だけ代替処理を評価します。
     *
     * @template U
     * @param callable(): U $fallback
     * @return T|U
     */
    final public function getOrElse(callable $fallback): mixed
    {
        return $this->isSome() ? $this->getOrThrow() : $fallback();
    }

    /**
     * Maps a present value once; a null result remains present.
     * 存在する値を一度変換し、結果がnullでも存在する値として保持します。
     *
     * @template U
     * @param callable(T): U $mapper
     * @return Option<U>
     */
    final public function map(callable $mapper): Option
    {
        return $this->isSome() ? self::some($mapper($this->getOrThrow())) : self::none();
    }

    /**
     * Maps a present value, converting a null result into absence.
     * 存在する値を変換し、結果がnullなら欠損へ変換します。
     *
     * @template U
     * @param callable(T): (U|null) $mapper
     * @return Option<U>
     */
    final public function mapNullable(callable $mapper): Option
    {
        return $this->isSome() ? self::fromNullable($mapper($this->getOrThrow())) : self::none();
    }

    /**
     * Chains an option-producing operation without nesting options.
     * Optionを返す処理を、Optionを入れ子にせず連結します。
     *
     * @template U
     * @param callable(T): Option<U> $mapper
     * @return Option<U>
     */
    final public function flatMap(callable $mapper): Option
    {
        return $this->isSome() ? $mapper($this->getOrThrow()) : self::none();
    }

    /**
     * Keeps a present value only when the predicate accepts it.
     * 存在する値が条件を満たす場合だけ保持します。
     *
     * @param callable(T): bool $predicate
     * @return Option<T>
     */
    final public function filter(callable $predicate): Option
    {
        return $this->isSome() && $predicate($this->getOrThrow()) ? $this : self::none();
    }

    /**
     * Evaluates exactly one branch and returns its result.
     * 存在状態に対応する一方の分岐だけを評価して結果を返します。
     *
     * @template U
     * @param callable(): U $ifNone
     * @param callable(T): U $ifSome
     * @return U
     */
    final public function fold(callable $ifNone, callable $ifSome): mixed
    {
        return $this->isSome() ? $ifSome($this->getOrThrow()) : $ifNone();
    }
}
