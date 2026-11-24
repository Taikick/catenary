<?php

declare(strict_types=1);

namespace Taikick\Collections\Internal;

use Taikick\Collections\Option;
use Throwable;
use UnderflowException;

/**
 * Represents absence; construct through Option::none().
 * 欠損状態を表します。生成にはOption::none()を使用します。
 *
 * @internal
 * @extends Option<never>
 */
final readonly class None extends Option
{
    /**
     * Always reports absence.
     * 値が存在しないことを常に返します。
     */
    #[\Override]
    public function isSome(): bool
    {
        return false;
    }

    /**
     * Throws the supplied exception or a standard missing-value exception.
     * 指定された例外、または欠損値を示す標準例外を送出します。
     *
     * @param (callable(): Throwable)|null $exception
     * @throws Throwable Always throws. / 常に例外を送出します。
     */
    #[\Override]
    public function getOrThrow(?callable $exception = null): never
    {
        throw $exception === null ? new UnderflowException('Cannot unwrap an absent Option.') : $exception();
    }
}
