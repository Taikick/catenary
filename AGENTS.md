# Typed Collections — contributor rules / 開発ルール

This repository is the framework-independent MIT package `taikick/typed-collections`.
このリポジトリはフレームワーク非依存のMITパッケージです。利用プロダクトの業務ルールや私的資料を持ち込みません。

## Read first / 最初に読む

- `README.md` / `README.ja.md`: public usage and development-preview status.
- `docs/contracts.md`: evaluation, null/key semantics, callback behavior and complexity.
- `CONTRIBUTING.md`: compatibility, analyzer baselines and release procedure.

公開API・評価・null/キー・互換性の契約を先に確認します。利用先アプリへの導入やPackagist公開は、このライブラリの変更とは別の作業です。

## Implementation and tests / 実装とテスト

- Start behavioral changes with a failing runtime or inference test. Preserve the distinction between `Some(null)` and `None`, integer and string keys, eager Vec and fresh-factory Stream.
- Add positive inference fixtures for PHPStan and Psalm, and negative fixtures when rejecting misuse is part of the contract. Do not overwrite inferred types or suppress errors to hide a regression.
- Keep Domain/framework dependencies out of runtime requirements. Prefer explicit final readonly containers; introduce abstraction only for a concrete invariant or contract.
- Every class and named function/method, including private methods and constructors, needs English **and** Japanese explanatory PHPDoc. Retain generic annotations and document exceptions/evaluation behavior.

振る舞い・型推論の失敗から修正し、誤用の拒否も検証します。全クラス・メソッドに英日説明を付け、型を注釈で上書きして推論テストを通しません。不変性は浅く、反復追加には配列コピーのコストがあるという契約も保ちます。

```sh
composer install
composer check
# Performance-related changes / 性能変更時
composer bench
# With Xdebug coverage / カバレッジ対応環境
XDEBUG_MODE=coverage composer coverage
```

Use an up-to-date PHP patch version for the development tools; ensure Composer itself runs with that PHP binary. `composer check` covers tests, both analyzers, rejection fixtures, bilingual docs, PSR-12 and the consumer example. The runtime minimum and current analyzer baselines are specified in composer.json and CONTRIBUTING.md.
開発ツール用のPHPは新しいパッチ版を使い、Composer自体の実行PHPも確認します。CIではPHP 8.3/8.4/8.5・最小依存・開発依存なしの導入を確認します。

## Compatibility and publishing / 互換性と公開

Native and generic types, variance, parameter names, callback counts/order, exceptions, evaluation timing and ordering are public contracts. Update CHANGELOG and migration notes for observable changes. Report benchmark workloads/environment and memory costs along with timing; do not generalize one benchmark into a universal speed claim.
型・変性・引数名・コールバック回数/順序・例外・評価タイミング・並び順も互換性対象です。性能は対象入力・環境・メモリとの交換条件を併記します。

Follow the current request's authorization for commit/push. A prior push does not authorize later releases, Packagist publication or changes to consuming applications.
commit/pushは現在の依頼範囲に従い、過去の許可を将来のリリース・Packagist公開・利用アプリの変更へ拡張しません。
