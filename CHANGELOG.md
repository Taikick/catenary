# Changelog

## Unreleased

## 0.3.0 — 2026-11-25

Additive dictionary operations and an optional count predicate; the PHP 8.3 runtime minimum is unchanged.
辞書操作の追加と任意の件数判定述語の拡張で、実行時の最低PHP 8.3は変更しません。

- Add `Dict::groupBy` with keyed inner dictionaries, `partition` with accepted/rejected dictionaries, and eager pair-producing `flatMap` with duplicate-key rejection.
  元キーを保つ内側辞書を返す`Dict::groupBy`、合致・非合致へ分ける`partition`、組を即時展開して重複キーを拒否する`flatMap`を追加。
- Extend `Dict::count` with an optional predicate while preserving argument-free `Countable` behavior; add strict numeric `sum` and explicitly formatted `mkString`.
  引数なしのCountable動作を維持して`Dict::count`へ任意の述語を追加し、数値型だけを加算する`sum`と文字列化を明示する`mkString`を追加。
- Add `Dict::getOrDefault` for a direct default value without replacing present null, and `toArray` that rejects PHP key coercion instead of losing strict key identity.
  存在するnullを置換しない値指定の`Dict::getOrDefault`と、キー型を失わずPHPの暗黙変換を拒否する`toArray`を追加。
- Cover callback evaluation, empty/null/key boundaries, inference and misuse; add output-validated grouping, partitioning and expansion benchmarks.
  コールバック評価・空/null/キーの境界、型推論・誤用を検証し、出力の一致を確認する分類・分割・展開のベンチマークを追加。

## 0.2.0 — 2026-11-25

Additive dictionary APIs; the PHP 8.3 runtime minimum is unchanged.
辞書APIを追加するリリースで、実行時の最低PHP 8.3は変更しません。

- Add eager `Dict::filter`, short-circuit `find` / `any` / `every`, left `fold` and immutable `merge`, preserving insertion order and strict integer/string key identity.
  挿入順と整数・文字列キーの区別を保つ、即時抽出の`Dict::filter`、短絡する`find`・`any`・`every`、左畳み込みの`fold`、不変な`merge`を追加。
- Return the first matching key-value pair from `Dict::find` as `Option<array{K, V}>`, including matching null values. Predicates receive value then key; reducers receive accumulator, value and key.
  `Dict::find`は最初に一致するキーと値の組を`Option<array{K, V}>`で返し、nullの値も保持。述語は値・キー、集約は累積値・値・キーの順で呼び出す。
- Merge with explicit right-hand replacement, keeping existing key positions and appending new right-hand keys in order. Neither input is modified and result key/value types widen to include both dictionaries.
  結合は同じ型のキーを右側の値で明示置換し、既存位置と新規キーの順序を維持。両方の入力を変更せず、結果のキー型・値型は両辞書の型へ拡張。

## 0.1.0 — 2026-11-25

Initial tagged release of the framework-independent PHP collection library.
フレームワークに依存しないPHPコレクション操作ライブラリの初回タグ版です。

- Fix null-only `Option::fromNullable` and `mapNullable` inference in PHPStan and Psalm: they now infer `Option<never>`, retaining the exact fallback result type without changing runtime behavior.
  nullだけを扱う`fromNullable`・`mapNullable`を両解析器で`Option<never>`と推論するよう修正。実行時の動作を変えず、代替処理の型に不要なmixed・nullが残る問題を解消。
- Rename the development package to `catenary/collections` and its public namespace to `Catenary`, without legacy aliases. See the [migration guide](docs/migration-to-catenary.md).
  開発版をCatenaryへ改名し、旧名のaliasは設けず移行手順を公開。日本語READMEを主入口にし、英語版も整備。
- Complete bilingual parameter/return documentation and qualify PHPDoc class names; validate all named declarations in CI.
  英日説明と全引数・戻り値のdocを補完し、PHPDocのクラス名を完全修飾。CIで全宣言を検査。
- Add `Vec::find`, `any`, `every` and `last` with explicit short-circuit, empty and present-null behavior.
  短絡評価・空リスト・存在するnullを区別する`Vec::find`・`any`・`every`・`last`を追加。
- Reuse an existing immutable Vec in `fromIterable` without collecting it again. Other iterables still detach first-level references and discard keys during one eager traversal.
  `fromIterable`は既存Vecを同じインスタンスのまま再利用。他のiterableは一回の即時走査・キー破棄・第一階層の参照切離しを維持。
- Cover new query inference and misuse in PHPStan/Psalm, and benchmark early, late and absent matches plus snapshot reuse.
  新しい検索の型推論と誤用をPHPStan/Psalmで検証し、先頭・中間・末尾・欠損の検索とスナップショット再利用を測定。
- Introduce `Option`, `Vec`, `Dict` and `Stream` with covariant generics and explicit null, key and evaluation semantics.
  共変ジェネリクスと明示的なnull・キー・評価の契約を持つ4つの型を追加。
- Add runtime/property-law tests, PHPStan/Psalm inference and rejection fixtures, bilingual documentation checks, formatting, reproducible benchmarks and PHP 8.3–8.5 CI.
  実行・法則テスト、型の推論と拒否の検証、英日コメント検査、書式検査、再現可能なベンチマーク、PHP 8.3〜8.5のCIを追加。

Install the tag through the documented VCS repository. Packagist registration is a separate operation.
導入にはREADMEのVCS設定を使用します。Packagistへの登録は別の操作です。
