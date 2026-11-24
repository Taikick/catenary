<?php

declare(strict_types=1);

namespace Taikick\Collections\Tests\Types;

use DateTimeImmutable;
use DateTimeInterface;
use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

/**
 * Accepts containers of the date interface through a common consumer boundary.
 * 共通の利用側境界で日付インターフェースのコンテナを受け入れます。
 *
 * @param Vec<DateTimeInterface> $values
 * @param Dict<int|string, DateTimeInterface> $indexed
 * @param Option<DateTimeInterface> $optional
 * @param Stream<DateTimeInterface> $stream
 * @return list<string>
 */
function dateLabels(Vec $values, Dict $indexed, Option $optional, Stream $stream): array
{
    return $values->concat($indexed->values())
        ->concat($stream->toVec())
        ->append($optional->getOrElse(fn (): DateTimeInterface => new DateTimeImmutable()))
        ->map(fn (DateTimeInterface $date): string => $date->format('Y-m-d'))
        ->toList();
}

/**
 * Proves subtype substitution, empty-container compatibility and impure callbacks.
 * 派生型の代入、空コンテナの互換性、副作用のあるコールバックの許容を検証します。
 */
function supportedVariance(DateTimeImmutable $date): void
{
    echo implode(',', dateLabels(
        Vec::of($date),
        Dict::fromPairs([['date', $date]]),
        Option::some($date),
        Stream::fromFactory(fn (): array => [$date]),
    ));
    echo implode(',', dateLabels(Vec::empty(), Dict::empty(), Option::none(), Vec::empty()->stream()));
    $visits = 0;
    Vec::of($date)->map(function (DateTimeInterface $value) use (&$visits): string {
        ++$visits;
        return $value->format('Y');
    });
    echo $visits;
}
