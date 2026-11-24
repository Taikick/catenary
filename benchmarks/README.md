# Reproducible benchmarks / 再現可能なベンチマーク

```sh
composer bench
php benchmarks/run.php --size=20000 --iterations=7

# Optional, isolated comparison with ScalikePHP 0.8.3 / 任意の独立した比較環境
composer install --working-dir=benchmarks/peers
composer bench
```

The script validates full outputs, warms up each operation and reports the median of an odd number of trials. It records PHP, OS/architecture, OPcache/JIT and Xdebug settings. Peak memory is the additional PHP allocator usage above the per-trial baseline; it is not process RSS or total input storage.
各操作は返却値全体の一致確認とウォームアップを行い、奇数回の試行の中央値を出力します。PHP・OS/アーキテクチャ・OPcache/JIT・Xdebugの設定を記録します。ピークメモリは試行開始時から増えたPHPアロケータ使用量であり、プロセス全体のRSSや入力全体の保存領域ではありません。

`unique_integer_ids` measures construction plus strict identity-key deduplication and conversion to a list. The input is unique integers: this intentionally exercises the quadratic worst case of a linear-search deduplicator. It is not representative of every duplicate distribution, key type or collection operation. The native loop uses the same tagged-key strategy as Vec but has no callable dispatch overhead.
`unique_integer_ids`は構築・型を区別する同一キーによる重複排除・リスト化を測定します。入力は重複のない整数で、線形探索による重複排除の二次的な最悪ケースを意図的に確認します。すべての重複分布・キー型・操作を代表するものではありません。ネイティブループはVecと同じ型タグ付きキーを使いますが、コールバック呼び出しのコストはありません。

`map_then_take_10` compares eager and lazy pipelines returning identical values. Both include creating an input Vec; it measures bounded evaluation, not the cost of loading an external data source. Streams do not make every operation faster.
`map_then_take_10`は同一の値を返す即時・遅延パイプラインを比較します。両方に入力Vecの構築を含み、評価件数を制限する効果を測ります。外部データ読込のコストを測定するものではなく、ストリームがすべての操作を高速にするという意味ではありません。

Use the same PHP binary, flags, workload and machine for comparisons. Run without Xdebug for performance measurements. CI checks that the benchmark executes and validates its outputs; it does not enforce wall-clock thresholds. Do not publish speed claims without the complete environment, workload and raw result.
比較には同じPHPバイナリ・設定・入力・マシンを使い、性能測定時はXdebugを無効にします。CIは実行と出力の一致を確認し、実時間の閾値を課しません。速度を公表する場合は環境・入力・元の測定結果を併記します。
