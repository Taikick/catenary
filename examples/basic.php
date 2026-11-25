<?php

declare(strict_types=1);

use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;

// Accept a consumer autoloader when running the installed example / 配布例では利用先のautoloadを指定できます。
require $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';

$names = Vec::of(' Ada ', '', ' Grace ')
    ->map('trim')
    ->filterMap(fn (string $name): Option => $name === '' ? Option::none() : Option::some(value: $name))
    ->map('strtoupper');

$dictionary = Dict::fromPairs([[1, 'integer'], ['1', 'string'], ['nullable', null]]);
$numbers = Stream::fromFactory(function (): Generator {
    for ($n = 0;; ++$n) {
        yield $n;
    }
})->map(fn (int $n): int => $n * 2)->take(3)->toVec();

$actual = [$names->toList(), $dictionary->toPairs(), $numbers->toList()];
$expected = [['ADA', 'GRACE'], [[1, 'integer'], ['1', 'string'], ['nullable', null]], [0, 2, 4]];
if ($actual !== $expected || !$dictionary->get('nullable')->isSome()) {
    throw new RuntimeException('The public API example produced an unexpected result.');
}
$visited = 0;
$found = $numbers->find(predicate: function (int $number) use (&$visited): bool {
    ++$visited;
    return $number === 2;
});
if (
    $found->getOrThrow() !== 2 || $visited !== 2 || $numbers->last()->getOrThrow() !== 4
    || !$numbers->any(predicate: fn (int $number): bool => $number === 4)
    || !$numbers->every(predicate: fn (int $number): bool => $number % 2 === 0)
    || Vec::fromIterable(items: $numbers) !== $numbers
) {
    throw new RuntimeException('The public query example produced an unexpected result.');
}
// Exercise the installed dictionary APIs without development dependencies / 開発依存なしで配布版の辞書APIを確認します。
$amounts = Dict::fromPairs(pairs: [[1, 100], ['1', 200], ['fee', -50]]);
[$positive, $negative] = $amounts->partition(predicate: fn (int $amount): bool => $amount >= 0);
$dictActual = [
    $amounts->groupBy(keyOf: fn (int $amount, int|string $key): int|string => is_int($key) ? 1 : '1')
        ->mapValues(mapper: fn (Dict $group): array => $group->toPairs())->toPairs(),
    [$positive->toPairs(), $negative->toPairs()],
    $amounts->flatMap(mapper: fn (int $amount, int|string $key): array => [
        [$key, $amount],
        ['double:' . (is_int($key) ? 'i:' : 's:') . $key, $amount * 2],
    ])->toPairs(),
    [$amounts->count(), count($amounts), $amounts->count(predicate: fn (int $amount): bool => $amount >= 0)],
    $amounts->sum(),
    $amounts->mkString(
        formatter: fn (int $amount, int|string $key): string =>
            (is_int($key) ? 'i:' : 's:') . $key . '=' . $amount,
        separator: '|',
        prefix: '[',
        suffix: ']',
    ),
    $amounts->without(key: '1')->toArray(),
    [$dictionary->getOrDefault(key: 'nullable', default: 'missing'),
        $dictionary->getOrDefault(key: 'absent', default: 'missing')],
];
$dictExpected = [
    [[1, [[1, 100]]], ['1', [['1', 200], ['fee', -50]]]],
    [[[1, 100], ['1', 200]], [['fee', -50]]],
    [[1, 100], ['double:i:1', 200], ['1', 200], ['double:s:1', 400], ['fee', -50], ['double:s:fee', -100]],
    [3, 3, 2],
    250,
    '[i:1=100|s:1=200|s:fee=-50]',
    [1 => 100, 'fee' => -50],
    [null, 'missing'],
];
$fallback = static fn (): string => 'lazy value';
if (
    $dictActual !== $dictExpected
    || $amounts->toPairs() !== [[1, 100], ['1', 200], ['fee', -50]]
    || $dictionary->getOrDefault(key: 'absent', default: $fallback) !== $fallback
) {
    throw new RuntimeException('The public dictionary example produced an unexpected result.');
}

// Native arrays cannot preserve the numeric string key / 数値文字列キーの型を保てない配列変換は拒否します。
$coercionRejected = false;
try {
    $amounts->toArray();
} catch (InvalidArgumentException) {
    $coercionRejected = true;
}
if (!$coercionRejected) {
    throw new RuntimeException('The dictionary export must reject native key coercion.');
}
echo json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
