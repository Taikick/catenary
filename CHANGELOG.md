# Changelog

## Unreleased

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

No published releases yet. / 公開済みリリースはまだありません。
