# API contracts / API契約

Public classes live in `Taikick\Collections`. `Internal` is not a supported extension API.
公開クラスは`Taikick\Collections`に属します。`Internal`は拡張用の公開APIではありません。

## Type and ownership rules / 型と所有の規則

- All containers are covariant in their element types. Adding values widens the result's type; the original container's type and contents stay unchanged.
  各コンテナの要素型は共変です。追加操作は結果の型を拡張し、元のコンテナの型と内容は変更しません。
- `Vec` and `Dict` use PHP arrays with copy-on-write. They are not persistent trees: repeated immutable `append`/`with` calls can cost quadratic total time. Use `fromIterable`/`fromPairs` for bulk construction.
  `Vec`と`Dict`はPHP配列のコピーオンライトを使います。永続木構造ではないため、不変な追加操作を繰り返すと合計時間が二次的に増えます。一括構築には`fromIterable`・`fromPairs`を使います。
- Immutability is shallow. Objects, resources and references inside element values are not recursively copied or frozen.
  不変性は浅いものです。要素内のオブジェクト・資源・参照を再帰的に複製または凍結しません。
- Callbacks accept values, not implicit indexes, unless explicitly documented (`Dict::mapValues` gets value then key). Any PHP callable is accepted. Callbacks may have side effects or throw; exceptions propagate unchanged.
  明記された例外を除き、コールバックには値だけを渡します。`Dict::mapValues`には値・キーの順で渡します。PHPのcallableを受け入れ、副作用を禁止せず、例外はそのまま伝播します。
- Generic PHPDoc types are part of the public contract. Static analysis checks callback/element types. Native PHP checks native parameter types; heterogeneous container contents are not recursively runtime-validated.
  ジェネリクスのPHPDocも公開契約です。コールバックや要素の型は静的解析で確認します。実行時はPHPのネイティブ引数型の検査を行い、格納内容の型を再帰的に検査しません。

## Option

`Option<T>` is an abstract, readonly sum-type API with internal `Some<T>` and `None` implementations. Use factories; inheritance outside this package is not a supported extension mechanism.
`Option<T>`はreadonlyな抽象APIで、内部実装は`Some<T>`と`None`です。生成にはファクトリを使い、パッケージ外からの継承は拡張手段として保証しません。

| API | Behavior / 動作 |
| --- | --- |
| `some(value)` | Always present, including null / nullを含め常に存在 |
| `none()` | `Option<never>`; no value / 値なし |
| `fromNullable(value)` | Only null becomes None / nullのみ欠損へ変換 |
| `isSome()`, `isNone()` | Presence queries / 存在・欠損の判定 |
| `getOrThrow(exception?)` | Value or lazily constructed exception; default `UnderflowException` / 値または必要時に生成する例外 |
| `getOrElse(fallback)` | Value or lazy fallback / 値または遅延代替処理 |
| `map(mapper)` | Maps Some, preserves resulting null / 存在する値を変換し結果のnullを保持 |
| `mapNullable(mapper)` | Maps Some, null result becomes None / null結果を欠損へ変換 |
| `flatMap(mapper)` | Mapper returns `Option<U>` / Optionを返す変換 |
| `filter(predicate)` | Keeps matching Some / 条件に合う値を保持 |
| `fold(ifNone, ifSome)` | Exactly one branch executes / 対応する一方の分岐を実行 |

Each operation performs constant container work plus at most one callback.
コンテナ自体の処理は定数時間で、コールバックは高々一回実行します。

## Vec

| API | Behavior / 動作 | Time / 時間 |
| --- | --- | --- |
| `empty()`, `of(...items)`, `fromIterable(items)` | Eager list creation; keys discarded / 即時構築しキーを捨てる | O(1), O(n), O(n) |
| `toList()` | Copy-on-write PHP list / コピーオンライトのPHPリスト | O(1) until mutation / 変更までO(1) |
| `count()`, `isEmpty()`, `at(index)`, `first()` | Index queries preserve null; missing/negative index is None / nullを保持し不正位置は欠損 | O(1) |
| `getIterator()` | Zero-based ordered values / ゼロ始まりの順序付き値 | O(n) traversal / 全走査 |
| `map`, `filter`, `filterMap` | Eager transformations; filterMap unwraps only Some / 即時変換・存在する結果のみ抽出 | O(n) + callbacks |
| `flatMap` | One-level flatten; inner keys discarded / 一段平坦化して内側キーを捨てる | O(n + m) |
| `fold(initial, reducer)` | Left fold; initial returned unchanged on empty / 左畳み込み・空なら初期値 | O(n) + callbacks |
| `append(value)`, `concat(items)` | New list, widening element type / 要素型を拡張した新規リスト | O(n), O(n + m) |
| `take(count)`, `drop(count)` | Non-negative count required / 非負個数が必要 | O(result size) |
| `chunk(size)` | Positive size; trailing remainder retained / 正のサイズ・末尾の余りを保持 | O(n) |
| `partition(predicate)` | `[accepted, rejected]`; one predicate call per value / 合致・非合致の順で返し各値を一回判定 | O(n) + callbacks |
| `uniqueBy(keyOf)` | Strict int/string keys; keep first occurrence / 型を区別し最初の出現を保持 | Expected O(n) / 期待O(n) |
| `keyBy(keyOf)` | `Dict<K,T>`; duplicate keys throw / 重複キーは例外 | Expected O(n) / 期待O(n) |
| `sorted(compare)` | Stable sorted copy / 安定ソートしたコピー | O(n log n) + comparisons |
| `stream()` | Lazy view over this snapshot / このスナップショットの遅延走査 | O(1) creation / 生成 |

Negative take/drop counts and non-positive chunk sizes throw `InvalidArgumentException` immediately. `uniqueBy` accepts only int/string keys, including negative integers, numeric strings, empty strings and strings containing null bytes. It does not define object equality or float hashing.
take/dropの負数、chunkの0以下は即座に`InvalidArgumentException`になります。重複判定のキーは整数・文字列のみで、負の整数・数値文字列・空文字・nullバイトを含む文字列も扱います。オブジェクトの等価性や浮動小数点数のハッシュは定義しません。

## Dict

| API | Behavior / 動作 | Time / 時間 |
| --- | --- | --- |
| `empty()`, `fromPairs(pairs)` | Empty or insertion-ordered creation / 空または挿入順の構築 | O(1), expected O(n) |
| `has(key)`, `get(key)` | Strict key lookup; present null stays Some / キーの型を区別しnullも存在として保持 | Expected O(1) |
| `count()`, `isEmpty()` | Size queries / 件数の判定 | O(1) |
| `with(key, value)` | Explicit replacement or insertion; preserves old position / 明示的な置換または追加・既存位置を保持 | O(n) copying |
| `without(key)` | Remove key; missing is harmless / キーを除去・未登録でも正常終了 | O(n) copying |
| `mapValues(mapper)` | Mapper receives `(value, key)` / 値・キーの順で変換 | O(n) + callbacks |
| `keys()`, `values()` | Ordered Vec views / 順序付きVecを返す | O(n) |
| `toPairs()`, `getIterator()` | `[key, value]` pairs; lossless key types / 型を損なわないキーと値の組 | O(n) |

Duplicates are rejected during bulk construction, even if the previous value is null. Removing and reinserting a key moves it to the end. Hash complexity assumes ordinary PHP hash-table behavior and bounded key length; hashing long strings also costs their length.
一括構築での重複は先行値がnullでも拒否します。除去後に再挿入したキーは末尾に移動します。ハッシュの計算量は通常のPHPハッシュテーブルと有界なキー長を前提とし、長い文字列のハッシュには文字列長に応じたコストがかかります。

## Stream

| API | Behavior / 動作 |
| --- | --- |
| `fromFactory(factory)` | Stores callable without opening source / 入力を開かず生成処理を保持 |
| `getIterator()` | Fresh source per traversal; source keys discarded / 走査ごとに新規入力・キーは捨てる |
| `map`, `filter`, `filterMap`, `flatMap` | Lazy equivalents of Vec operations / Vecの対応操作の遅延版 |
| `take(count)` | No read beyond requested output; zero does not open source / 上限より先を読まずゼロなら入力を開かない |
| `drop(count)` | Skips input values lazily / 値を遅延して読み飛ばす |
| `first()`, `isEmpty()` | At most one output value; no materialization / 高々一出力を確認・実体化しない |
| `any(predicate)`, `every(predicate)` | Short-circuit; empty yields false/true respectively / 短絡評価・空ならfalse/true |
| `fold(initial, reducer)` | Consumes finite output / 有限の出力を走査し切る |
| `toVec()` | Explicit materialization and reusable snapshot / 明示的に実体化して再利用可能にする |

Pipeline construction is O(1) per stage. Streaming stages retain O(1) container state per stage, excluding the source, callbacks and values they allocate. Total retained state is proportional to pipeline depth. `toVec` retains O(n) output. Traversal cost includes all upstream work needed to produce the requested output. Side effects, source exceptions and callback exceptions propagate during traversal. Factories must return fresh iterables; no single-use generator adapter is exposed in this API.
構築は段ごとにO(1)です。各段が保持するコンテナ状態はO(1)ですが、入力・コールバックやそれらの確保領域は含みません。全体の保持状態は段数に比例します。`toVec`はO(n)の出力を保持します。走査の時間には要求出力を作るために必要な上流の処理も含まれます。副作用・入力例外・コールバック例外は走査中に生じます。生成処理は毎回新しいiterableを返す契約で、一回限りのジェネレータ用アダプタは公開していません。
