<?php

declare(strict_types=1);

namespace Catenary\Tests\Types;

use DateTimeImmutable;
use DateTimeInterface;
use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;

/**
 * Accepts containers of the date interface through a common consumer boundary.
 * 共通の利用側境界で日付インターフェースのコンテナを受け入れます。
 *
 * @param \Catenary\Vec<\DateTimeInterface> $values
 * @param \Catenary\Dict<int|string, \DateTimeInterface> $indexed
 * @param \Catenary\Option<\DateTimeInterface> $optional
 * @param \Catenary\Stream<\DateTimeInterface> $stream
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
 *
 * @param \DateTimeImmutable $date
 * @return void
 */
function supportedVariance(DateTimeImmutable $date): void
{
    $dates = Vec::of($date);
    echo implode(',', dateLabels(
        values: Vec::fromIterable($dates),
        indexed: Dict::fromPairs([['date', $date]]),
        optional: $dates->find(fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0),
        stream: Stream::fromFactory(fn (): array => [$date]),
    ));
    echo (int) $dates->any(fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0);
    echo (int) $dates->every(fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0);
    echo implode(',', dateLabels(
        values: Vec::empty(),
        indexed: Dict::empty(),
        optional: Option::none(),
        stream: Vec::empty()->stream(),
    ));
    $indexedDates = Dict::fromPairs(pairs: [['date', $date]]);
    $moreDates = Dict::fromPairs(pairs: [[1, $date]]);
    $mergedDates = $indexedDates->merge(other: $moreDates);
    echo implode(',', dateLabels(
        values: $mergedDates->values(),
        indexed: $mergedDates->filter(
            predicate: fn (DateTimeInterface $value, int|string $key): bool => $value->getTimestamp() > 0,
        ),
        optional: $mergedDates->find(predicate: fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0)
            ->map(mapper: fn (array $pair): DateTimeInterface => $pair[1]),
        stream: $mergedDates->values()->stream(),
    ));
    echo (int) $mergedDates->any(predicate: fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0);
    echo (int) $mergedDates->every(predicate: fn (DateTimeInterface $value): bool => $value->getTimestamp() > 0);
    $latestDate = $mergedDates->fold(
        initial: null,
        reducer: fn (?DateTimeInterface $latest, DateTimeInterface $next): DateTimeInterface =>
            $latest === null || $next > $latest ? $next : $latest,
    );
    echo $latestDate?->format('Y');
    $visits = 0;
    Vec::of($date)->map(function (DateTimeInterface $value) use (&$visits): string {
        ++$visits;
        return $value->format('Y');
    });
    echo $visits;
}
