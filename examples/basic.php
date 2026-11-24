<?php

declare(strict_types=1);

use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;

require dirname(__DIR__) . '/vendor/autoload.php';

$names = Vec::of(' Ada ', '', ' Grace ')
    ->map('trim')
    ->filterMap(fn (string $name): Option => $name === '' ? Option::none() : Option::some($name))
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
echo json_encode($actual, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
