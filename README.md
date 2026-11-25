# Catenary

[![CI](https://github.com/Taikick/catenary/actions/workflows/ci.yml/badge.svg)](https://github.com/Taikick/catenary/actions/workflows/ci.yml)
[English](README.en.md)

PHPの配列や`iterable`を、型付きのメソッドチェーンで扱うためのライブラリです。値の変換・絞り込み・検索・集約に加え、値がない場合の処理を`Option`で表現できます。

**PHP 8.3以上・MITライセンス・実行時の外部依存なし。** PHPStanとPsalmの型推論をテストし、フレームワークに依存せず利用できます。

**最新リリース: `v0.3.0`（2026-11-25）。** 1.0前のAPIで、マイナーバージョンでは破壊的変更を行う場合があります。パッチバージョンでは互換性を維持します。Packagistには未登録のため、下記のVCS設定でタグ版を導入してください。旧名からの変更は[移行ガイド](docs/migration-to-catenary.md)を参照してください。

## 値を変換して絞り込む

```php
use Catenary\Vec;

$numbers = Vec::of(1, 2, 3, 4);

$result = $numbers
    ->filter(predicate: fn (int $number): bool => $number % 2 === 0)
    ->map(mapper: fn (int $number): int => $number * 10);

$result->toList();  // [20, 40]
$numbers->toList(); // [1, 2, 3, 4]：元の並びは変更しない

$total = $result->fold(initial: 0, reducer: fn (int $sum, int $number): int => $sum + $number);
// 60
```

`Vec`は順序を持つリストです。`map`は各値を変換し、`filter`は条件に合う値を残し、`fold`は初期値から一つの結果へ集約します。これらの操作は呼び出した時点で評価されます。配列やジェネレータから作るときは`Vec::fromIterable(items: $items)`を使います。入力のキーは捨て、0からの連続した添字になります。

| 型 | 扱うもの |
| --- | --- |
| `Vec<T>` | 即時評価する順序付きリスト。変換しても元のリストを変更しない。 |
| `Option<T>` | 値の存在・欠損。存在する`null`と値の欠損を区別する。 |
| `Dict<K, V>` | 挿入順を保持する辞書。整数キーと文字列キーを区別する。 |
| `Stream<T>` | 走査時に値を生成する遅延処理。必要な範囲だけ読み出せる。 |

## 検索と条件判定

```php
use Catenary\Vec;

$numbers = Vec::of(2, 5, 8);

$found = $numbers->find(predicate: fn (int $number): bool => $number > 5);
$found->getOrElse(fallback: fn (): int => 0); // 8

$numbers->any(predicate: fn (int $number): bool => $number > 10); // false
$numbers->every(predicate: fn (int $number): bool => $number > 0); // true
$numbers->last()->getOrThrow(); // 8
```

`find`は最初に一致した値を`Option`で返します。見つからなければ`None`です。`any`は一つでも条件を満たすか、`every`はすべて満たすかを判定し、結果が決まった時点で走査を止めます。空のリストでは`find`・`first`・`last`は`None`、`any`は`false`、`every`は`true`になり、判定コールバックは呼び出されません。

`first`・`last`・`at`は存在する`null`も保持します。`Vec::fromIterable(items: $vec)`へ既存の`Vec`を渡した場合は、そのインスタンスをそのまま返します。

## 値がない場合を扱う

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

`filterMap`は、変換結果のうち`Some`の値だけをリストに残します。`Option::map`は変換後の`null`を値として保持し、`mapNullable`は`null`を欠損へ変えます。

欠損時に値用のコールバックは実行しません。`getOrElse`の代替処理は欠損時だけ実行します。`fold($ifNone, $ifSome)`は対応する一方だけを実行し、`getOrThrow`には必要時に例外を作る処理も渡せます。

## キーを保って値を扱う

```php
use Catenary\Dict;

$labels = Dict::fromPairs(pairs: [[1, '整数のキー'], ['1', '文字列のキー'], ['memo', null]]);

$labels->get(key: 1)->getOrThrow();     // '整数のキー'
$labels->get(key: '1')->getOrThrow();   // '文字列のキー'
$labels->get(key: 'memo')->isSome();    // true
$labels->get(key: 'missing')->isNone(); // true

$updated = $labels->with(key: 'memo', value: '追記');
$labels->get(key: 'memo')->getOrThrow(); // null：元の辞書は変更しない
```

`Dict::fromPairs`と`Vec::keyBy`は重複キーを`InvalidArgumentException`で拒否します。上書きには`with`を使い、既存キーの位置は保ちます。走査と`toPairs()`は`[キー, 値]`の組を返すため、整数`1`と文字列`'1'`を混同しません。

### 辞書の絞り込み・検索・集約・結合

以下の6メソッドは`v0.2.0`で追加しました。

```php
use Catenary\Dict;

$amounts = Dict::fromPairs(pairs: [[1, 100], ['1', 200], ['extra', 50]]);

$amounts->filter(predicate: fn (int $amount, int|string $key): bool => $amount >= 100)
    ->toPairs(); // [[1, 100], ['1', 200]]
$amounts->find(predicate: fn (int $amount, int|string $key): bool => $key === '1')
    ->getOrThrow(); // ['1', 200]：値だけでなくキーも保持
$amounts->any(predicate: fn (int $amount): bool => $amount > 150);   // true
$amounts->every(predicate: fn (int $amount): bool => $amount > 0);   // true
$amounts->fold(
    initial: 0,
    reducer: fn (int $total, int $amount, int|string $key): int => $total + $amount,
); // 350

$amounts->merge(other: Dict::fromPairs(pairs: [['1', 250], ['new', 75]]))
    ->toPairs(); // [[1, 100], ['1', 250], ['extra', 50], ['new', 75]]
```

述語には値・キーの順で渡し、`filter`は全件、`find`・`any`・`every`は結果が決まるまで挿入順に判定します。空の辞書ではそれぞれ空のDict・None・false・trueになり、述語を呼びません。`find`は`Option<array{K, V}>`を返し、nullの値が一致しても`Some([key, null])`として保持します。`fold`の第3引数はキーで、空なら初期値そのものを返します。

`merge`は型も同じキーを右側の値で上書きし、左側の既存位置を維持します。新しいキーは右側の順番で末尾へ追加し、元の辞書は両方とも変更しません。詳細な評価回数・型・計算量は[API契約](docs/contracts.md#dictキーの型を区別する辞書)を参照してください。

### 辞書の分類・展開・出力

以下の8項目は`v0.3.0`で追加・拡張しました。

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

Dict::fromPairs(pairs: [['memo', null]])->getOrDefault(key: 'memo', default: '未登録'); // null
```

`groupBy`は`Dict<グループキー, Dict<元キー, 元値>>`を返します。グループは最初に現れた順で、各グループ内も元のキー・挿入順・nullを保持します。`partition`は合致・非合致の二つの辞書を返し、どちらもキーを失いません。各コールバックは値・キーの順で一件につき一回呼びます。

`flatMap`は、変換先の`[キー, 値]`の組を返す`iterable`を一段だけ即時展開します。組のリスト・ジェネレータ・Dictを返せます。連想配列そのものではなく、`[['key', $value]]`のように組を返してください。同じ型・値のキーが重複すると`InvalidArgumentException`になり、暗黙に上書きしません。

引数なしの`count()`とPHPの`count($dict)`は従来どおり全件数を定数時間で返します。述語付きでは全件を一回ずつ判定します。`sum()`はint/floatだけを挿入順に加算し、空なら整数0、非数値なら`TypeError`を送出します。数値文字列・bool・nullの暗黙変換はしません。整数の桁あふれでfloatになるため戻り型は`int|float`です。`mkString`は明示的な文字列化コールバックを必須にし、空ならprefixとsuffixだけを返します。

`toArray()`はキーの型を損なう変換を拒否します。たとえば文字列キー`'1'`はPHP配列では整数1になるため、単独でも`InvalidArgumentException`です。そうしたキーも扱う保存・通信には`toPairs()`を使います。`getOrDefault`は欠損時だけ既定値を返し、存在するnullはそのままです。既定値がcallableでも実行しません。遅延生成が必要なら従来の`get(...)->getOrElse(...)`を使います。

## 必要な分だけ生成する

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

生成処理は呼ばれるたびに新しい`iterable`を返します。作成済みジェネレータの使い回しはできません。終端操作ごとに走査が始まるため、副作用が再実行され、外部データが変われば結果も変わります。結果を再利用する場合は`toVec()`で一度リストにします。

`take(0)`は入力を開きません。`first`・`isEmpty`が確認するのは高々一つの**出力要素**です。前段の`filter`で多くの入力を読む場合があり、無限入力に一致がなければ終了しません。`fold`・`toVec`には有限の出力が必要です。入力資源の解放はジェネレータの`finally`で記述します。

## 導入

利用先の`composer.json`にVCSリポジトリとタグ版の依存を追加します。既存の他の依存・repositories設定は維持してください。GitHubのタグ公開とPackagistへの登録は別の操作です。

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

`^0.3.0`は`0.3.0`以上・`0.4.0`未満の更新を許可します。suffixのない`v0.3.0`はComposerでは`stable`に分類されます。APIの互換性は[1.0前の方針](CONTRIBUTING.md#compatibility--互換性)に従います。更新した`composer.lock`をレビュー・保存し、配備には`composer install`を使います。旧パッケージを利用中の場合は[改名に伴う移行手順](docs/migration-to-catenary.md)に従ってください。

未リリースの変更を試す場合だけ`dev-main`を選び、利用先の`composer.lock`でレビュー済みコミットを固定してください。`main`にはタグ版より後の変更が入ります。

## 型・不変性・性能の契約

- 要素のジェネリクスはPHPStan・Psalmで確認します。PHP実行時に格納した全要素の型を検査する仕組みではありません。
- 不変性は浅いものです。格納構造は変更しませんが、要素内のオブジェクトや参照を再帰的に複製・凍結しません。
- `Vec`・`Dict`はPHP配列を利用します。`append`・`with`を大量に繰り返すより、一括構築に`fromIterable`・`fromPairs`を使うとコピーを抑えられます。
- コールバックの例外はそのまま伝播します。評価時点・呼出し順序・キー・`null`の扱いも公開契約です。
- オブジェクトの`serialize()`結果は安定した保存形式として保証しません。保存や通信では、用途に合わせて値を明示的に変換してください。

詳細は[API契約と計算量](docs/contracts.md)に記載しています。

## 開発と検証

開発・テストにはGitでcloneした作業コピーを使います。配布archiveにはテスト・開発ツール・開発用lockfileを含めていません。開発ツールには新しいPHPパッチ版を使い、対応条件は`composer.json`で確認してください。

```sh
git clone https://github.com/Taikick/catenary.git
cd catenary
composer install
composer check
composer bench
```

`composer check`は実行テスト、PHPStan最大レベル、Psalmレベル1、型推論・不正な型の拒否、英日PHPDoc、PSR-12、実行例を検証します。CIはPHP 8.3・8.4・8.5、最小依存、開発依存なしの導入を検証対象にしています。全クラスと名前付き関数・メソッドの説明は英語と日本語の両方で保守します。

[開発・互換性方針](CONTRIBUTING.md) · [API契約](docs/contracts.md) · [ベンチマーク](benchmarks/README.md) · [実行例](examples/basic.php) · [移行ガイド](docs/migration-to-catenary.md) · [MITライセンス](LICENSE)
