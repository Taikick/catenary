# コレクション操作の性能確認

```sh
composer bench
php benchmarks/run.php --size=20000 --iterations=7
php benchmarks/run.php --size=100000 --iterations=7
```

各操作でウォームアップと返却値全体の一致を確認し、奇数回の試行の中央値を出力します。PHPバージョン・OS・CPUアーキテクチャ・memory_limit・OPcache・JIT・Xdebugの設定もJSONへ記録します。

`median_additional_peak_bytes`は各試行開始時点から増えたPHPアロケータ使用量です。プロセス全体のRSS、入力全体の保存領域、別プロセスのDB読込は含みません。CIでは時間の閾値を課さず、実行と結果の一致を確認します。

## 測定する操作

| 出力キー | 入力と確認内容 |
| --- | --- |
| `unique_integer_ids` | 重複なし整数を構築し、型を区別するキーで重複排除してリストへ戻す。ネイティブループは同じ型タグ付きキーを使うがコールバック呼出しを伴わない。 |
| `map_then_take_10` | 即時変換と遅延変換が同じ先頭最大10件を返すことを確認。両方に入力Vecの構築を含む。 |
| `find_first/middle/last/missing` | 構築済みVecの先頭・中間・末尾・一致なしを検索。全件filter後のfirstと短絡するfindを比較する。 |
| `any_first/middle/last/missing` | 同じ位置の一致を存在判定。全件filter後の空判定とanyを比較する。 |
| `every_first/middle/last/missing` | 同じ位置で不一致となる全件判定。全件filter後の件数比較とeveryを比較する。missingは全件が条件を満たす。 |
| `reuse_existing_vec` | 既存VecのIteratorを再収集する場合とfromIterableで同一Vecを再利用する場合を比較。返却値全体とVecの同一性も確認する。 |
| `dict_filter_typed_keys` | 同じ数値の整数キーと文字列キーを各1件、計`2 × size`件持つ構築済み辞書から、偶数値の文字列キーだけを保持。組を直接走査するループとfilterの出力全体を照合する。 |
| `dict_find_first/middle/last/missing` | 上記辞書の文字列キーを検索し、全件filter後の先頭組と短絡findを比較。返却するキーの型も照合する。 |
| `dict_any_first/middle/last/missing` | 同じ文字列キーを存在判定し、全件filter後の空判定と短絡anyを比較する。 |
| `dict_every_first/middle/last/missing` | 同じ文字列キーで不一致となる全件判定。全件filter後の件数比較と短絡everyを比較する。 |
| `dict_merge_typed_keys` | 左`2 × size`件、右`2 × size`件。右は文字列キーだけを置換して`size`件を追加し、結果は`3 × size`件。nullによる置換・整数キーの保持・元位置・追加順を全件照合する。 |

検索・判定では入力Vecを測定区間の外で一度構築し、早期終了と入力構築のコストを分けます。末尾一致や一致なしでは全走査が必要です。先頭一致の結果だけで、通常の全操作が高速になるとは判断しません。既存Vecの再利用は定数時間ですが、元データの取得や作成まで定数時間になるわけではありません。

Dictの測定はv0.2.0で追加しました。入力Dictも測定区間外で構築し、出力の`toPairs()`は測定に含めます。mergeのネイティブループは毎回キーを符号化しますが、構築済みDictは既存の内部キーを利用します。filterのネイティブループはコールバック呼出しを伴いません。この差を含む操作単位の測定であり、速度の一般化や入力構築込みの比較には使いません。文字列キーは対応する整数キーの直後にあるため、`first`でも最初の2件を判定します。これらの試行後に両入力の不変性も確認し、v0.3.0の追加操作の試行後にはその入力辞書も再確認します。

## v0.3.0の辞書操作

以下はv0.3.0で追加した測定です。上記と同じ構築済みの`2 × size`件（整数キー・文字列キー各`size`件）の辞書を使い、型と順序を含む全出力の一致を毎回確認します。
These workloads, added in v0.3.0, use the same prebuilt dictionary with `2 × size` entries, checking every result's value, key type and ordering.

| 出力キー / Workload | 入力と確認内容 / Checks |
| --- | --- |
| `dict_group_by_typed_keys` | 値を4で割った余りを、元キーと同じ整数/文字列型のグループキーにする。最大8グループの初出順と内側の元キー・順序を照合。Group by remainder modulo 4 with distinct integer/string group keys; compare first-seen group order and all original inner keys. |
| `dict_partition_typed_keys` | 偶数値の文字列キーを合致側、それ以外を非合致側に分け、全件が一方だけへ元順序で出ることを照合。Partition even-valued string keys from all other entries and compare both ordered outputs. |
| `dict_flat_map_typed_keys` | 各組を元の組と追加の`extra:i:`/`extra:s:`キー・符号反転値へ展開して`4 × size`件を作る。重複拒否付きのネイティブループと全件照合。Expand each pair to itself and a uniquely typed extra key with negated value, yielding `4 × size` output entries. |

分類は内側のDictも`toPairs()`へ変換し、全ての出力変換を測定時間とメモリに含めます。ネイティブループはコールバックや不変コンテナを生成しないため、コンテナの生成・変換コストを含む操作単位の比較です。flatMapのネイティブ側も全出力キーを符号化して重複を検査します。計算量はgroupBy/partitionが期待O(n)、flatMapが期待O(n + m)ですが、出力を保存する領域はそれぞれO(n)、O(m)必要です。大きな入力での結果を、遅延処理やデータ取得の性能へ一般化しません。
Grouping converts each inner Dict to pairs, and all output conversions are included in timing and memory. Native loops do not allocate immutable containers or invoke callbacks, so this compares complete operations including those costs. The native flatMap loop also encodes and checks every output key for duplicates. Grouping/partitioning take expected O(n) work and O(n) output storage; flatMap takes expected O(n + m) work and O(m) output storage. These workloads do not measure lazy pipelines or source I/O.

## 比較条件

同じPHP実行ファイル・設定・入力・マシンを使い、Xdebugを無効にして測定してください。速度を記載する場合は環境・入力・元のJSONを併記します。メモリと評価回数も確認し、微小な時間差だけでAPIの優劣を決めません。

実行テストでは、短絡位置までの呼出し回数と順序、空リストの結果、Some(null)、例外伝播、配列参照の切離しを別に検証します。ベンチマークは正しさの検証に代わるものではありません。
