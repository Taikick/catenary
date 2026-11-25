<?php

declare(strict_types=1);

namespace Catenary\Internal;

use Catenary\Option;
use Throwable;

/**
 * Stores the present branch of Option; construct through Option::some().
 * Optionの存在側を保持します。生成にはOption::some()を使用します。
 *
 * @internal
 * @template-covariant T
 * @extends \Catenary\Option<T>
 */
final readonly class Some extends Option
{
    /**
     * Stores a value without copying objects or normalizing null.
     * オブジェクトの複製やnullの変換をせずに値を保持します。
     *
     * @param T $value
     * @return void
     */
    public function __construct(private mixed $value)
    {
    }

    /**
     * Always reports a present value.
     * 値が存在することを常に返します。
     *
     * @return bool
     */
    #[\Override]
    public function isSome(): bool
    {
        return true;
    }

    /**
     * Returns the stored value without evaluating the exception factory.
     * 例外生成処理を評価せず、保持する値を返します。
     *
     * @param callable(): \Throwable|null $exception
     * @return T
     */
    #[\Override]
    public function getOrThrow(?callable $exception = null): mixed
    {
        return $this->value;
    }
}
