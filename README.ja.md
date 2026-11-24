# Typed Collections

[English](README.md)

PHPStanとPsalmの型推論をテストで保証する、PHP向けの不変コレクションと`Option`です。
**PHP 8.3以上・MITライセンス・実行時の外部依存なし**で、フレームワークを問わず利用できます。

**現在は開発プレビューです。安定版のリリースおよびPackagistへの登録はまだ行っていません。**

```php
use Taikick\Collections\Option;
use Taikick\Collections\Vec;

$names = Vec::of(' Ada ', '', ' Grace ')
    ->map('trim')
    ->filterMap(fn (string $name) => $name === '' ? Option::none() : Option::some($name))
    ->map('strtoupper'); // Vec<string>

$first = $names->first()->getOrElse(fn () => 'UNKNOWN');
// $names->toList() は ['ADA', 'GRACE']、$first は 'ADA'
```

## 4つの型と責務

| 型 | 契約 |
| --- | --- |
| `Option<T>` | 値の存在・欠損を表現。`some(null)`は存在、`fromNullable(null)`は欠損。 |
| `Vec<T>` | 即時評価する順序付きリスト。変換結果は新しいリストになり、入力のキーは捨てる。 |
| `Dict<K, V>` | 挿入順を保持する辞書。整数`1`と文字列`'1'`を別のキーとして扱う。 |
| `Stream<T>` | 生成処理から作る遅延パイプライン。走査ごとに入力を開き、暗黙にキャッシュしない。 |

不変性は**浅い不変性**です。格納構造は変更できませんが、要素として保持するオブジェクトは共有され、変更可能な場合があります。ジェネリクスの制約は静的解析で検証し、PHP実行時に要素の型を強制するものではありません。独自の解析プラグインやマジックメソッドは不要です。

`Option::map`は結果のnullを保持し、`mapNullable`はnullを欠損へ変換します。欠損時に値用コールバックは実行しません。`getOrElse`の代替処理は欠損時だけ実行します。

`Dict::fromPairs`と`Vec::keyBy`は重複キーを例外で拒否します。明示的な上書きには`with`を使います。走査と`toPairs()`は`[キー, 値]`の組を返し、PHP配列へ変換する際のキー型の欠落を防ぎます。

`Stream::fromFactory`には、毎回新しいiterableを返す処理を渡します。作成済みジェネレータの使い回しは契約外です。終端操作ごとに生成処理や副作用が再実行され、外部データが変われば結果も変わります。再利用可能なスナップショットが必要なら`toVec()`を一度呼び出します。

`take(0)`は入力を開きません。`first`と`isEmpty`が確認するのは高々一つの**出力要素**であり、前段にfilterがある場合は複数の入力要素を走査します。無限入力で一致がなければ終了しません。`toVec`と`fold`には有限の出力が必要です。入力資源の解放はジェネレータの`finally`で記述します。

## 導入と開発

Packagist登録前の導入方法は[英語READMEのInstallation](README.md#installation-during-development)を参照してください。利用先ではlockfileを管理し、レビュー済みコミットを固定します。

開発には新しいPHP 8.3パッチ版（Psalmのため8.3.16以上）またはそれ以降を使います。

```sh
composer install
composer check
composer bench
```

実行テスト、PHPStan最大レベル、Psalmレベル1、推論結果の検証、不正な型を拒否する検証、英日docコメントのAST検査、PSR-12検査を実行します。CIはPHP 8.3・8.4・8.5と最小依存バージョンを検証します。

公開APIの互換性とジェネリクス、メソッド名、引数名、評価タイミング、キーの扱いはすべて維持管理の対象です。詳細は[API契約と計算量](docs/contracts.md)、[貢献・互換性方針](CONTRIBUTING.md)、[ベンチマーク](benchmarks/README.md)に記載しています。

既存の型付きPHPライブラリにも優れた選択肢があります。本ライブラリは小さなチェーンAPI、評価・null・キーの明確な契約、両解析器の推論テストに重点を置きます。ScalikePHPとの完全互換や、あらゆる処理で最速であることは主張しません。
