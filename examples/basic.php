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
echo json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
