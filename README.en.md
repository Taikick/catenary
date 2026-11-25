# Catenary

[![CI](https://github.com/Taikick/catenary/actions/workflows/ci.yml/badge.svg)](https://github.com/Taikick/catenary/actions/workflows/ci.yml)
[日本語](README.md)

Typed method chains for PHP arrays and iterables: transform, filter, search and aggregate values, and express missing values with `Option`.

**PHP 8.3+, MIT licensed, zero runtime dependencies.** PHPStan and Psalm inference is covered by tests. No framework integration is required.

**Latest release: `v0.3.0` (2026-11-25).** This is a pre-1.0 API: minor versions may introduce breaking changes, while patches remain compatible. The package is not registered on Packagist; install the tagged version using the VCS configuration below. Existing users should read the [rename migration guide](docs/migration-to-catenary.md#english).

## Transform and filter values

```php
use Catenary\Vec;

$numbers = Vec::of(1, 2, 3, 4);

$result = $numbers
    ->filter(predicate: fn (int $number): bool => $number % 2 === 0)
    ->map(mapper: fn (int $number): int => $number * 10);

$result->toList();  // [20, 40]
$numbers->toList(); // [1, 2, 3, 4]: the original is unchanged

$total = $result->fold(initial: 0, reducer: fn (int $sum, int $number): int => $sum + $number);
// 60
```

`Vec` is an ordered list. `map` transforms each value, `filter` retains matching values, and `fold` aggregates from an initial value. These operations evaluate immediately. Use `Vec::fromIterable(items: $items)` to collect an array or generator; input keys are discarded and indexes start at zero.

| Type | Responsibility |
| --- | --- |
| `Vec<T>` | Eager ordered lists; transformations leave the original unchanged. |
| `Option<T>` | Presence or absence, including the distinction between a present `null` and no value. |
| `Dict<K, V>` | Insertion-ordered dictionaries with distinct integer and string keys. |
| `Stream<T>` | Lazy generation and transformation, consuming only the requested range. |

## Search and test conditions

```php
use Catenary\Vec;

$numbers = Vec::of(2, 5, 8);

$found = $numbers->find(predicate: fn (int $number): bool => $number > 5);
$found->getOrElse(fallback: fn (): int => 0); // 8

$numbers->any(predicate: fn (int $number): bool => $number > 10); // false
$numbers->every(predicate: fn (int $number): bool => $number > 0); // true
$numbers->last()->getOrThrow(); // 8
```

`find` returns the first match as an `Option`, or `None` if nothing matches. `any` stops at the first match; `every` stops at the first failure. On an empty list, `find`, `first` and `last` return `None`, `any` returns `false`, and `every` returns `true`; predicates are never called.

`first`, `last` and `at` preserve a present `null`. Passing an existing `Vec` to `Vec::fromIterable(items: $vec)` returns that same instance.

## Handle absent values

```php
use Catenary\Option;
use Catenary\Vec;

$names = Vec::of(' Ada ', '', ' Grace ')
    ->map(mapper: 'trim')
    ->filterMap(mapper: fn (string $name) => $name === '' ? Option::none() : Option::some(value: $name))
    ->map(mapper: 'strtoupper');

$names->toList(); // ['ADA', 'GRACE']
$names->first()->getOrElse(fallback: fn (): string => 'UNKNOWN'); // 'ADA'

Option::some(value: null)->isSome();                         // true
Option::fromNullable(value: null)->isNone();                 // true
Option::some(value: 1)->map(mapper: fn () => null)->isSome();         // true
Option::some(value: 1)->mapNullable(mapper: fn () => null)->isNone();  // true
```

`filterMap` keeps the values of `Some` results. `Option::map` preserves a resulting `null`; `mapNullable` turns a resulting `null` into absence.

Value callbacks are not called on absence. `getOrElse` evaluates its fallback only when absent. `fold($ifNone, $ifSome)` evaluates exactly one branch. `getOrThrow` also accepts a lazily invoked exception factory.

## Preserve key types

```php
use Catenary\Dict;

$labels = Dict::fromPairs(pairs: [[1, 'integer key'], ['1', 'string key'], ['memo', null]]);

$labels->get(key: 1)->getOrThrow();     // 'integer key'
$labels->get(key: '1')->getOrThrow();   // 'string key'
$labels->get(key: 'memo')->isSome();    // true
$labels->get(key: 'missing')->isNone(); // true

$updated = $labels->with(key: 'memo', value: 'added');
$labels->get(key: 'memo')->getOrThrow(); // null: the original is unchanged
```

`Dict::fromPairs` and `Vec::keyBy` reject duplicate keys with `InvalidArgumentException`. Use `with` for replacement; the existing key keeps its position. Iteration and `toPairs()` produce `[key, value]` pairs, preserving the distinction between integer `1` and string `'1'`.

### Filter, search, fold and merge dictionaries

These six methods were added in `v0.2.0`.

```php
use Catenary\Dict;

$amounts = Dict::fromPairs(pairs: [[1, 100], ['1', 200], ['extra', 50]]);

$amounts->filter(predicate: fn (int $amount, int|string $key): bool => $amount >= 100)
    ->toPairs(); // [[1, 100], ['1', 200]]
$amounts->find(predicate: fn (int $amount, int|string $key): bool => $key === '1')
    ->getOrThrow(); // ['1', 200]: preserves the key as well as its value
$amounts->any(predicate: fn (int $amount): bool => $amount > 150);   // true
$amounts->every(predicate: fn (int $amount): bool => $amount > 0);   // true
$amounts->fold(
    initial: 0,
    reducer: fn (int $total, int $amount, int|string $key): int => $total + $amount,
); // 350

$amounts->merge(other: Dict::fromPairs(pairs: [['1', 250], ['new', 75]]))
    ->toPairs(); // [[1, 100], ['1', 250], ['extra', 50], ['new', 75]]
```

Predicates receive value then key. `filter` visits all entries; `find`, `any` and `every` visit entries in insertion order until the result is known. On an empty dictionary they return an empty Dict, None, false and true respectively, without invoking the predicate. `find` returns `Option<array{K, V}>`, preserving a matching null value as `Some([key, null])`. The third reducer argument of `fold` is the key; an empty dictionary returns the exact initial value.

`merge` takes the right-hand value for each matching typed key while keeping its original left-hand position. New keys append in right-hand order, and neither input dictionary changes. See the [API contracts](docs/contracts.md#dictキーの型を区別する辞書) for evaluation counts, types and complexity.

### Group, expand and export dictionaries

These eight APIs were added or extended in `v0.3.0`.

```php
use Catenary\Dict;

$amounts = Dict::fromPairs(pairs: [['base', 100], ['extra', 50], ['refund', -20]]);

$amounts->groupBy(keyOf: fn (int $amount): string => $amount >= 0 ? 'income' : 'refund')
    ->get(key: 'income')->getOrThrow()->toPairs(); // [['base', 100], ['extra', 50]]
[$income, $refund] = $amounts->partition(predicate: fn (int $amount): bool => $amount >= 0);
$income->toPairs(); // [['base', 100], ['extra', 50]]
$refund->toPairs(); // [['refund', -20]]

$amounts->flatMap(mapper: fn (int $amount, string $key): array => [
    [$key, $amount],
    [$key . ':double', $amount * 2],
])->toPairs(); // [['base', 100], ['base:double', 200], ['extra', 50], ...]

$amounts->count(predicate: fn (int $amount): bool => $amount > 0); // 2
$amounts->sum(); // 130
$amounts->mkString(
    formatter: fn (int $amount, string $key): string => $key . '=' . $amount,
    separator: ', ',
    prefix: '[',
    suffix: ']',
); // '[base=100, extra=50, refund=-20]'
$amounts->toArray(); // ['base' => 100, 'extra' => 50, 'refund' => -20]
$amounts->getOrDefault(key: 'missing', default: 0); // 0

Dict::fromPairs(pairs: [['memo', null]])->getOrDefault(key: 'memo', default: 'missing'); // null
```

`groupBy` returns `Dict<GroupKey, Dict<OriginalKey, OriginalValue>>`. Groups appear in first-seen order; each inner dictionary preserves original keys, insertion order and null values. `partition` returns accepted and rejected dictionaries without losing keys. Each callback receives value then key, once per entry.

`flatMap` eagerly flattens one level of an `iterable` of `[key, value]` pairs. Mappers may return a list of pairs, a generator or a Dict. Return pairs such as `[['key', $value]]`, not a plain associative array. Repeating the same strictly typed key throws `InvalidArgumentException` instead of silently replacing a value.

Argument-free `count()` and PHP's `count($dict)` still return the size in constant time. With a predicate, every entry is tested once. `sum()` adds only int/float values in insertion order, returning integer zero on empty and throwing `TypeError` for nonnumeric values. Numeric strings, bool and null are not coerced. Its return type is `int|float` because integer overflow may produce a float. `mkString` requires an explicit string formatter and returns just prefix plus suffix on empty.

`toArray()` rejects conversions that would change a key's type. For example, PHP arrays turn the string key `'1'` into integer 1, so even that key alone causes `InvalidArgumentException`. Use `toPairs()` for lossless storage or transport of such keys. `getOrDefault` uses its default only when the key is missing, preserving present null values. A callable default is returned as a value, never invoked. For lazy defaults, use the existing `get(...)->getOrElse(...)` chain.

## Generate values on demand

```php
use Catenary\Stream;

$stream = Stream::fromFactory(factory: function (): Generator {
    for ($number = 0; ; ++$number) {
        yield $number;
    }
});

$snapshot = $stream
    ->map(mapper: fn (int $number): int => $number * 2)
    ->take(count: 3)
    ->toVec();

$snapshot->toList(); // [0, 2, 4]
```

The factory must return a fresh iterable on each invocation. Do not return a previously created generator. Every terminal operation starts a traversal: side effects may run again, and external data may have changed. Call `toVec()` once when a reusable snapshot is needed.

`take(0)` does not open the source. `first` and `isEmpty` inspect at most one **output** value. An upstream `filter` may scan many inputs or never terminate on an infinite source with no match. `fold` and `toVec` require finite output. Release source resources in generator `finally` blocks.

## Installation

Add the VCS repository and tagged-version requirement to the consuming project's `composer.json`, preserving unrelated requirements and repository settings. Publishing a GitHub tag and registering on Packagist are separate operations.

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/Taikick/catenary"}
    ],
    "require": {"catenary/collections": "^0.3.0"}
}
```

```sh
composer update catenary/collections --with-dependencies
```

`^0.3.0` allows versions from `0.3.0` up to, but excluding, `0.4.0`. Composer classifies the suffix-free `v0.3.0` tag as `stable`. API compatibility follows the [pre-1.0 policy](CONTRIBUTING.md#compatibility--互換性). Review and commit the resulting `composer.lock`; use `composer install` for deployment. Follow the [migration guide](docs/migration-to-catenary.md#english) when upgrading from the previous package name.

Use `dev-main` only when intentionally trying unreleased changes, and keep the reviewed commit in the consuming project's `composer.lock`. The `main` branch may contain changes made after the latest tag.

## Types, immutability and performance

- PHPStan and Psalm check generic element types. PHP does not recursively enforce those generic types at runtime.
- Immutability is shallow. Contained objects and references are not recursively copied or frozen.
- `Vec` and `Dict` use PHP arrays. For bulk construction, `fromIterable` and `fromPairs` avoid the repeated copying of long `append` or `with` chains.
- Callback exceptions propagate unchanged. Evaluation timing, callback order, keys and null handling are public contracts.
- `serialize()` output is not a stable storage format. Convert values explicitly for storage or transport.

See [API contracts and complexity](docs/contracts.md) for the complete behavior.

## Development and verification

Use a Git clone for development and tests. Distribution archives exclude tests, development tools and the development lockfile. Use an up-to-date PHP patch release for development tools; see `composer.json` for their requirements.

```sh
git clone https://github.com/Taikick/catenary.git
cd catenary
composer install
composer check
composer bench
```

`composer check` covers runtime tests, maximum-level PHPStan, level-1 Psalm, positive inference and negative type fixtures, bilingual PHPDoc, PSR-12 and the executable example. CI is configured for PHP 8.3, 8.4 and 8.5, minimum dependencies and an installation without development dependencies. Every class and named function/method is documented in English and Japanese.

[Contributing and compatibility](CONTRIBUTING.md) · [API contracts](docs/contracts.md) · [Benchmarks](benchmarks/README.md) · [Executable example](examples/basic.php) · [Migration](docs/migration-to-catenary.md#english) · [MIT license](LICENSE)
