# Typed Collections

[![CI](https://github.com/Taikick/typed-collections/actions/workflows/ci.yml/badge.svg)](https://github.com/Taikick/typed-collections/actions/workflows/ci.yml)
[日本語](README.ja.md)

Immutable PHP collections and `Option` with tested PHPStan and Psalm inference.
PHP **8.3+**, MIT licensed, **zero runtime dependencies**, no framework integration required.

**Development preview:** the initial API is on `main`. No stable release or Packagist package has been published yet.

```php
use Taikick\Collections\Option;
use Taikick\Collections\Vec;

$names = Vec::of(' Ada ', '', ' Grace ')
    ->map('trim')
    ->filterMap(fn (string $name) => $name === '' ? Option::none() : Option::some($name))
    ->map('strtoupper'); // Vec<string>

$first = $names->first()->getOrElse(fn () => 'UNKNOWN');
// $names->toList() === ['ADA', 'GRACE']; $first === 'ADA'
```

## Four explicit abstractions

| Type | Contract |
| --- | --- |
| `Option<T>` | Present value or absence. `some(null)` is present; `fromNullable(null)` is absent. |
| `Vec<T>` | Eager, ordered list. Operations return new lists; iterable keys are discarded. |
| `Dict<K, V>` | Insertion-ordered dictionary. Integer `1` and string `'1'` are different keys. |
| `Stream<T>` | Lazy pipeline from a factory. Every traversal opens a fresh source; no implicit cache. |

Collections are **shallowly immutable**: their structure is immutable, but contained objects are shared and may be mutable. Generic constraints are checked by static analyzers; PHP does not enforce generic element types at runtime. No magic methods, macros or analyzer plugins are required.

### Present null is not absence

```php
Option::some(null)->isSome();                  // true
Option::fromNullable(null)->isNone();          // true
Option::some(1)->map(fn () => null)->isSome();  // true
Option::some(1)->mapNullable(fn () => null)->isNone(); // true
```

`map`, `flatMap` and `filter` never call a value callback on absence. `getOrElse` evaluates its fallback lazily. `fold($ifNone, $ifSome)` evaluates exactly one branch. `getOrThrow` accepts an optional exception factory.

### Keys have explicit types

```php
use Taikick\Collections\Dict;

$dict = Dict::fromPairs([[1, 'integer'], ['1', 'string'], ['empty', null]]);
$dict->get(1)->getOrThrow();       // 'integer'
$dict->get('1')->getOrThrow();     // 'string'
$dict->get('empty')->isSome();     // true
$dict->get('missing')->isNone();   // true
$updated = $dict->with('empty', 'replaced'); // original unchanged
```

`fromPairs` and `Vec::keyBy` reject duplicate keys with `InvalidArgumentException`. Use `with` for explicit replacement; the existing insertion position is retained. Iteration and `toPairs()` produce `[key, value]` pairs. There is intentionally no implicit PHP-array conversion that would merge numeric-string and integer keys.

### Laziness without hidden retention

```php
use Taikick\Collections\Stream;

$stream = Stream::fromFactory(function (): Generator {
    for ($n = 0; ; ++$n) {
        yield $n;
    }
});

$snapshot = $stream->map(fn (int $n) => $n * 2)->take(3)->toVec();
// [0, 2, 4]; exactly three source values requested
```

The factory must return a **fresh iterable** on every invocation. Do not capture and return a previously created generator. Terminal operations open a new traversal and can repeat side effects. Call `toVec()` once to create a reusable snapshot. Factories may read changing data, so repeated results need not be identical.

`take(0)` does not open the source. `first` and `isEmpty` inspect at most one **output** value: an upstream filter can still scan many source values, or never terminate on an infinite source with no match. `any` and `every` short-circuit. `fold` and `toVec` require finite output. Source cleanup belongs in generator `finally` blocks; early termination releases factory-owned generators.

## Installation during development

Until a release is registered on Packagist, add the repository to the consuming project's `composer.json`:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/Taikick/typed-collections"}
    ],
    "require": {"taikick/typed-collections": "dev-main"}
}
```

Then run `composer update taikick/typed-collections`. Commit the consuming project's lockfile so deployments use a reviewed commit. `dev-main` is not a stability guarantee.

## Development and verification

Use a current PHP 8.3 patch release (at least 8.3.16 for Psalm) or newer.

```sh
composer install
composer check
composer bench
```

`composer check` runs runtime tests, PHPStan at maximum level, Psalm at level 1, positive inference fixtures, negative type fixtures, bilingual documentation checks and PSR-12 formatting. CI tests PHP 8.3, 8.4 and 8.5, including coverage and a minimum-dependency job. No analyzer baselines or source suppressions are used.

Every class and named function/method has an **English and Japanese** description, enforced with an AST-based documentation check. Runtime tests cover nulls, strict keys, immutability, algebraic laws, callback failures, laziness and cleanup.

See [API contracts and complexity](docs/contracts.md), [maintenance and compatibility](CONTRIBUTING.md), [benchmarks](benchmarks/README.md), and the [executable example](examples/basic.php).

## Scope

Typed PHP libraries such as [fp4php/functional](https://github.com/fp4php/functional) and [loophp/collection](https://github.com/loophp/collection) already exist. This project explores a deliberately small chainable API with explicit evaluation, null and key semantics, and inference tests on both analyzers. It does not claim to be universally faster or a drop-in replacement for ScalikePHP.

There is no Laravel/Eloquent coupling, serialization format, persistence layer or application-specific code. `Result`, async operations and broad collection interfaces are not part of this initial API.
