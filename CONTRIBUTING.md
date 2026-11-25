# Catenary — メンテナンスと貢献 / Maintenance and contributions

公開パッケージは`catenary/collections`、名前空間は`Catenary`、リポジトリは[Taikick/catenary](https://github.com/Taikick/catenary)です。[日本語README](README.md)を主入口に、[英語README](README.en.md)、[API契約](docs/contracts.md)、[改名の移行ガイド](docs/migration-to-catenary.md)を併せて保守します。README.ja.mdは既存リンク用の案内です。

Maintain the Japanese-first [README](README.md), [English guide](README.en.md), [API contracts](docs/contracts.md) and [rename migration guide](docs/migration-to-catenary.md#english) together. Public documentation explains PHP collection operations directly. The package is `catenary/collections`, with the `Catenary` namespace in the `Taikick/catenary` repository.

## Development contract / 開発契約

1. Add a failing behavior or inference test before changing implementation. Fix defects with a regression test.
   実装変更前に失敗する動作または推論のテストを追加し、不具合には再発防止テストを付けます。
2. Run `composer check`. For a performance change, also run `composer bench` and retain the environment and output validation.
   `composer check`を実行します。性能変更では`composer bench`も実行し、環境情報と結果の同等性を確認します。
3. Explain every class and named function/method in both English and Japanese, including private methods and constructors. Include `@param` for every parameter and `@return` for every method/function even when native types exist; constructors use `@return void`. Update generic PHPDoc and behavioral docs together.
   非公開メソッド・コンストラクタも含め、全クラスと名前付き関数・メソッドに英日両方の説明を付けます。ネイティブ型があっても全引数の@param・戻り値の@returnを省略せず、コンストラクタは@return voidとします。型注釈と動作の文書も同時に更新します。
4. Avoid mutable shared state, framework coupling, magic dispatch and blanket analyzer suppressions. A new abstraction needs a concrete contract and more than a naming convenience.
   共有可変状態、フレームワークへの結合、マジックメソッドへの委譲、解析エラーの一括抑制を避けます。抽象化には名前を揃える以上の具体的な契約が必要です。
5. Fully qualify PHPDoc class types, including generic, shape and callable positions; preserve templates, aliases, native types and late-bound types. Use imports in executable code. Prefer named arguments for known package declarations while preserving positional arguments for external, variadic, unpacked, dynamic or unresolved calls. Keep the current PHPUnit runner and public parameter names.
   PHPDocのクラス型はgeneric・shape・callableの内部も先頭のバックスラッシュを含むFQCNにし、template・alias・組込み型・遅延束縛型の意味を保ちます。実行コードではimportを使い、既知の自前APIへは名前付き引数を基本とします。外部・可変長・展開・動的・未確定の呼出しは位置引数を維持し、評価順を変えません。既存のPHPUnitと公開引数名を維持します。

The root lockfile pins development tooling. It does not force consumers to use these development dependencies. Dependabot opens monthly updates; merging them requires the full test matrix. PHPStan 2.3.7+ and Psalm 7.17+ are the initial tested analyzer baselines; older versions are not currently promised. PHP runtime support is 8.3+, tested on 8.3/8.4/8.5. Development tooling needs an up-to-date patch release.
ルートのlockfileは開発ツールを固定するもので、利用プロダクトへ開発依存を強制しません。Dependabotは月次の更新PRを作成し、マージにはテスト行列の通過を求めます。初期の検証基準はPHPStan 2.3.7以上・Psalm 7.17以上で、それ以前は保証しません。実行時はPHP 8.3以上を対象とし、8.3・8.4・8.5で検証します。開発ツールには新しいパッチ版が必要です。

## Compatibility / 互換性

Before 1.0, a minor version may break API contracts. Patch versions must remain compatible. From 1.0 onward, incompatible changes require a major version, with a migration guide. Document all user-visible changes in the changelog. `main` is a development branch; release tags are immutable.
1.0以前はマイナーバージョンでAPI変更を行う場合がありますが、パッチバージョンでは互換性を維持します。1.0以降の非互換変更は移行ガイドを伴うメジャーバージョンで行います。利用者に見える変更はCHANGELOGへ記載し、リリースタグは変更しません。mainは開発ブランチです。

The public contract includes native signatures, generic types and variance, parameter names (named arguments), callback order/count, evaluation timing, exceptions, key/null semantics and ordering. Tests and `Internal` classes are not extension APIs. Serialized object internals are not a stable storage format.
公開契約にはネイティブな型宣言・ジェネリクスと変性・名前付き引数の引数名・コールバックの順序と回数・評価タイミング・例外・キー/nullの扱い・順序を含みます。testsとInternalは拡張用APIではありません。オブジェクト内部のシリアライズ結果は安定した保存形式として保証しません。

The Catenary rename is a breaking development-version change without legacy class aliases. Keep previous commits and consumer lock references intact until the consumer is explicitly migrated. A repository rename does not rewrite historical package metadata. Use the migration guide to update requirements, imports and the reviewed lockfile together.
Catenaryへの改名は、旧クラスaliasを提供しない開発版の破壊的変更です。過去のコミットと利用先lockfileの固定先は明示移行まで保持します。リポジトリ名を変えても過去のパッケージ情報は変わりません。移行ガイドに従い、require・imports・レビュー済みlockfileを一緒に更新します。

## Release procedure / リリース手順

- Review the changelog and compatibility impact, including minimum PHP/analyzer versions. Run the complete CI matrix on the exact release commit.
  CHANGELOGと互換性影響を確認し、PHP・解析器の最低バージョンも検討します。リリース対象コミットで全CIを通します。
- Validate a Composer installation from the candidate tag in a separate consuming project, without development dependencies. Run the shipped example and consuming products' regression suites.
  候補タグから独立した利用プロジェクトへ開発依存なしで導入し、同梱例と利用プロダクトの回帰テストを実行します。
- Create the release tag and release notes only after review. Packagist registration/release publication is a separate maintainer operation; CI never publishes automatically.
  レビュー後にタグとリリースノートを作成します。Packagist登録・公開は別途メンテナが操作し、CIから自動公開しません。

Report reproducible bugs with PHP and analyzer versions, a minimal example and expected behavior. Prefer small API additions justified by use in consuming products over large speculative interfaces.
不具合報告にはPHP・解析器のバージョン、最小再現コード、期待する動作を添えます。大きな推測上のインターフェースより、利用プロダクトの実例で裏付けられた小さなAPI追加を優先します。
