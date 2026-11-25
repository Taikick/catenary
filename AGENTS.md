# Catenary — contributor rules / 開発ルール

This repository is the framework-independent MIT package `catenary/collections`.
このリポジトリはフレームワーク非依存のMITパッケージです。利用プロダクトの業務ルールや私的資料を持ち込みません。

## Read first / 最初に読む

- `README.md` / `README.en.md`: Japanese-first public usage, English translation, current release and Packagist registration status. `README.ja.md` remains a compatibility entry linking to the Japanese README.
- `docs/contracts.md`: evaluation, null/key semantics, callback behavior and complexity.
- `CONTRIBUTING.md`: compatibility, analyzer baselines and release procedure.
- `docs/migration-to-catenary.md`: the breaking package/namespace rename, consumer lockfiles and serialization boundaries.

公開API・評価・null/キー・互換性の契約を先に確認します。日本語の主入口はREADME.md、英語版はREADME.en.mdです。利用先アプリへの導入やPackagist公開は、このライブラリの変更とは別の作業です。

## Implementation and tests / 実装とテスト

- Start behavioral changes with a failing runtime or inference test. Preserve the distinction between `Some(null)` and `None`, integer and string keys, eager Vec and fresh-factory Stream.
- Add positive inference fixtures for PHPStan and Psalm, and negative fixtures when rejecting misuse is part of the contract. Do not overwrite inferred types or suppress errors to hide a regression.
- Keep Domain/framework dependencies out of runtime requirements. Prefer explicit final readonly containers; introduce abstraction only for a concrete invariant or contract.
- Every class and named function/method, including private methods and constructors, needs English **and** Japanese explanatory PHPDoc. Document every parameter and return value even when native types exist; use `@return void` for constructors. Retain generic annotations and document exceptions/evaluation behavior.
- Fully qualify class types in PHPDoc, including generic arguments, shapes and callable signatures. Preserve declared templates, type aliases, native types, `static` and `$this`. Import classes in executable PHP instead of writing long fully qualified names inline.
- Prefer named arguments for calls to this package's declared APIs when parameter names are known and stable. Keep positional arguments for variadics, unpacking, external/magic calls or unresolved signatures. Preserve evaluation order and public parameter names.
- Keep the existing PHPUnit runner. Test behaviors with the current suite rather than changing test frameworks during an API or documentation change.

振る舞い・型推論の失敗から修正し、誤用の拒否も検証します。全クラス・メソッドに英日説明、全引数に@param、戻り値に@returnを付けます。PHPDoc内のクラス型は先頭のバックスラッシュを含むFQCNとし、実行コードではimportを使います。宣言したtemplate・aliasや型推論の精度を保ち、注釈による上書きで推論テストを通しません。既知の自前API呼出しは名前付き引数を基本とし、外部・可変長・動的な呼出しは例外です。テストランナーは既存のPHPUnitを維持します。不変性は浅く、反復追加には配列コピーのコストがあるという契約も保ちます。

Run these commands from a Git clone; distribution archives omit tests, development tools and the development lockfile.
以下はGitでcloneした作業コピーで実行します。配布archiveにはテスト・開発ツール・開発用lockfileを含めません。

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

Describe PHP collection operations directly in public documentation. Keep the Japanese and English guides aligned; avoid positioning the package as another library's replacement. The canonical names are `catenary/collections`, `Catenary` and `Taikick/catenary`. Old names belong in migration documentation only; they do not authorize editing historical lockfile references or adding compatibility aliases.
公開文書はPHPのコレクション操作を直接説明し、日本語と英語の内容を揃えます。他ライブラリの代替という訴求は行いません。正式名称は`catenary/collections`・`Catenary`・`Taikick/catenary`です。旧名は移行説明に限定し、過去のlockfileのrevisionを手書き変更したり互換aliasを追加したりしません。

Keep tagged releases, unreleased `main` changes and Packagist registration distinct. Verify the candidate commit or unpublished local tag and its distribution archive before publishing the same commit SHA; never move a published tag.
タグ版・mainの未リリース変更・Packagist登録を区別します。候補コミットまたは未公開のローカルタグと配布archiveを検証し、同じSHAを公開します。公開済みタグは変更しません。

Follow the current request's authorization for commit/push. A prior push does not authorize later releases, Packagist publication or changes to consuming applications.
commit/pushは現在の依頼範囲に従い、過去の許可を将来のリリース・Packagist公開・利用アプリの変更へ拡張しません。
