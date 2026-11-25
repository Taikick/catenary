# Catenaryへの移行

[日本語の利用ガイド](../README.md) · [English](#english)

このガイドは、旧名の未リリース開発版から、初期タグ版Catenary `v0.1.0`（2026-11-25）へ移行する手順です。パッケージ名・名前空間の変更は、既存の利用先にとって破壊的変更です。旧クラス名のaliasや旧パッケージ名の互換層は提供しません。

| 対象 | 旧名 | 新名 |
| --- | --- | --- |
| Composerパッケージ | `taikick/typed-collections` | `catenary/collections` |
| 公開名前空間 | `Taikick\Collections` | `Catenary` |
| GitHubリポジトリ | `Taikick/typed-collections` | `Taikick/catenary` |

## 1. 変更前の固定状態を残す

まず利用先の`composer.json`・`composer.lock`をバージョン管理し、利用中のrevisionを確認します。

```sh
composer show --locked taikick/typed-collections
```

リポジトリのURLや現在のブランチ名を変更しても、過去のコミットのコード・名前空間・Composerメタデータは変わりません。旧revisionを指すlockfileは旧版を指したままです。`composer.lock`のパッケージ名・URL・`source.reference`を文字列置換して新名へ見せかけないでください。

元のrequireが`dev-main#旧コミット`を指定している場合も、旧ハッシュを新しいパッケージ名へ移すだけでは移行できません。新名を含むコミットを解決してレビューし、そのrevisionを新しいlockfileで固定します。

## 2. 利用先の依存指定とimportsを変更する

利用先の`composer.json`で、VCSのURLを`https://github.com/Taikick/catenary`へ更新します。既存の他の依存・repositories設定は維持します。

requireの変更だけを先に行う場合は、次のようにまだ依存を更新せず編集できます。

```sh
composer remove taikick/typed-collections --no-update
composer require 'catenary/collections:^0.1.0' --no-update
```

変更後の該当部分は次の形です。

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/Taikick/catenary"}
    ],
    "require": {"catenary/collections": "^0.1.0"}
}
```

`^0.1.0`は`0.1.x`のタグ版を対象とし、`0.2.0`への更新を含みません。Packagistには未登録なのでVCS設定が必要です。未リリースの変更を試す場合の`dev-main`とは区別してください。

PHPのimportsも変更します。

```php
// Before / 変更前
use Taikick\Collections\Dict;
use Taikick\Collections\Option;
use Taikick\Collections\Stream;
use Taikick\Collections\Vec;
```

```php
// After / 変更後
use Catenary\Dict;
use Catenary\Option;
use Catenary\Stream;
use Catenary\Vec;
```

PHPDocの型、解析器の設定、テストfixture、明示したクラス名文字列も確認します。旧`Internal`クラスを直接使うコードは、公開ファクトリへ見直してください。`Internal`は拡張用APIではありません。

他の依存パッケージが旧パッケージを要求している場合は、その依存元の対応も必要です。手書きの`replace`やクラスaliasで互換性があるものとして扱わないでください。

## 3. 対象を限定して依存を更新する

旧パッケージがlockfileに残っている利用先で、次を実行します。

```sh
composer update catenary/collections taikick/typed-collections --with-dependencies
composer show --locked catenary/collections
```

更新対象は新旧のパッケージと、その解決に必要な依存に限定します。`--with-dependencies`は依存先も更新しますが、利用先自身が直接requireしている別パッケージまで一括更新する指定ではありません。ここで必要性を確認せず全体の`composer update`や`--with-all-dependencies`へ広げないでください。新パッケージの実行時依存はPHPだけです。

新名を含むコードが解決されたことと、他の依存の差分を確認します。旧パッケージに別の依存元がある場合は、その解消状況も確認してください。`composer dump-autoload`だけでは依存の改名や固定revisionの更新は行われません。

## 4. 実行・解析・保存形式を確認する

利用先の実行テストと静的解析を実行します。とくに次を確認してください。

- 新しい`Catenary`のautoloadと、ジェネリクスを含むimports・PHPDoc。
- `Some(null)`と`None`の区別、`Dict`の整数・文字列キー、コールバック回数・例外・評価時点。
- `Vec::fromIterable(items: $vec)`が同じ`Vec`インスタンスを返すこと。独立したラッパーの生成を前提にしているコードを見直します。要素の深いコピーは行いません。
- API・キャッシュ・永続化で利用している独自の値変換と、遅延処理の再走査。

PHPオブジェクトの`serialize()`結果にはクラス名や内部構造が含まれます。このライブラリはそれを安定した保存形式として保証せず、旧名前空間のオブジェクトを新しい名前空間へ自動復元しません。保存済みオブジェクトがある場合は、旧版で読み出してアプリ固有の明示形式へ変換するなど、利用先で移行方法を決めてください。文字列置換によるシリアライズデータの修復は行わないでください。

確認後、変更した`composer.json`・`composer.lock`・利用コードを一緒にレビューします。配備先ではレビュー済みlockfileを使って`composer install`します。タグ版の導入で利用先アプリの配備やPackagistへの登録が自動的に行われることはありません。Catenaryは1.0前のAPIであり、マイナーバージョンの互換性方針は[CONTRIBUTING](../CONTRIBUTING.md#compatibility--互換性)を参照してください。

## English

This guide migrates the previous unreleased development package to the initial Catenary `v0.1.0` tag (2026-11-25). It is a breaking rename: Composer package `taikick/typed-collections` becomes `catenary/collections`, the public namespace `Taikick\Collections` becomes `Catenary`, and the repository is `https://github.com/Taikick/catenary`. There are no compatibility aliases for the old classes or package name.

1. Preserve the consuming project's existing `composer.json` and `composer.lock`. Inspect its pinned revision with `composer show --locked taikick/typed-collections`. A repository rename does not rewrite old commits. Old lock entries still refer to their original code and metadata; never relabel package names or `source.reference` by editing the lockfile.
2. Change the VCS URL, remove the old direct requirement and add `catenary/collections:^0.1.0`. This permits `0.1.x` tags and excludes `0.2.0`. The VCS configuration is required while the package is unregistered on Packagist; `dev-main` is reserved for intentionally testing unreleased changes. `composer remove taikick/typed-collections --no-update` followed by `composer require 'catenary/collections:^0.1.0' --no-update` edits the requirements without updating dependencies. Keep unrelated requirements and repositories. Do not transfer an old `dev-main#commit` pin to the new name: review a commit that actually contains the renamed package and namespace.
3. Update imports to `Catenary\Vec`, `Catenary\Option`, `Catenary\Dict` and `Catenary\Stream`. Check generic PHPDoc, analyzer configuration, fixtures and explicit class-name strings. Replace direct usage of unsupported `Internal` classes with public factories. Dependencies that still require the old package need their own migration; a custom `replace` or class alias is not a compatibility guarantee.
4. With the old package still locked, run `composer update catenary/collections taikick/typed-collections --with-dependencies`. Inspect the result with `composer show --locked catenary/collections`. This limits the update to the named packages and their dependencies; it does not unlock unrelated direct requirements as `--with-all-dependencies` would. Do not broaden the update without reviewing why it is needed. `composer dump-autoload` alone cannot change package requirements or locked revisions.
5. Run the consuming project's runtime and static-analysis suites. Verify null/key semantics, callback behavior, application serialization and repeated lazy traversal. `Vec::fromIterable(items: $vec)` now returns that same `Vec` instance rather than allocating a wrapper; elements are not deeply copied. Review code that relies on distinct wrapper identities.
6. Review the new lockfile and consuming code together. Deploy with `composer install` from that reviewed lockfile.

Serialized PHP objects are not a stable interchange format for this library. Old serialized instances include the previous class names and cannot be assumed to restore under the new namespace. Where needed, read them using the old version and migrate to an application-defined value representation. Do not repair serialized objects through string replacement. Installing the tag does not deploy the consuming application or register the package on Packagist. Catenary is a pre-1.0 API; see the [compatibility policy](../CONTRIBUTING.md#compatibility--互換性) before changing the minor-version requirement.
