# API契約と計算量

Catenaryは、PHPでコレクションの変換・検索・集約と、値の存在・欠損を扱うライブラリです。公開クラスの名前空間は`Catenary`です。`Internal`以下は拡張用の公開APIではありません。
Public classes live in `Catenary`. `Internal` is not a supported extension API.

## 型・所有・評価の規則

- All containers are covariant in their element types. Adding values widens the result's type; the original container's type and contents stay unchanged.
  各コンテナの要素型は共変です。追加操作は結果の型を拡張し、元のコンテナの型と内容は変更しません。
- `Vec` and `Dict` use PHP arrays with copy-on-write. They are not persistent trees: repeated immutable `append`/`with` calls can cost quadratic total time. Use `fromIterable`/`fromPairs` for bulk construction.
  `Vec`と`Dict`はPHP配列のコピーオンライトを使います。永続木構造ではないため、不変な追加操作を繰り返すと合計時間が二次的に増えます。一括構築には`fromIterable`・`fromPairs`を使います。
- Immutability is shallow. Objects, resources and references inside element values are not recursively copied or frozen.
  不変性は浅いものです。要素内のオブジェクト・資源・参照を再帰的に複製または凍結しません。
  `Vec::fromIterable`と`Dict::fromPairs`は入力配列の第一階層の参照を値から切り離します。
- Callbacks accept values, not implicit indexes, unless explicitly documented. Dict mappers and predicates receive `(value, key)`; Dict reducers receive `(accumulator, value, key)`. Any PHP callable is accepted. Callbacks may have side effects or throw; exceptions propagate unchanged.
  明記された例外を除き、コールバックには値だけを渡します。Dictの変換・判定には値・キー、集約には累積値・値・キーの順で渡します。PHPのcallableを受け入れ、副作用を禁止せず、例外はそのまま伝播します。
- Generic PHPDoc types are part of the public contract. Static analysis checks callback/element types. Native PHP checks native parameter types; heterogeneous container contents are not recursively runtime-validated.
  ジェネリクスのPHPDocも公開契約です。コールバックや要素の型は静的解析で確認します。実行時はPHPのネイティブ引数型の検査を行い、格納内容の型を再帰的に検査しません。

## Option：値の存在と欠損

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

`fromNullable(null)`と、返値型が`null`だけの`mapNullable`は、PHPStan・Psalmのどちらでも`Option<never>`と推論します。その後の`getOrElse`は代替処理の型を保持し、存在しない値の`mixed`や`null`を戻り型へ混ぜません。nullableな入力ではnullを除いた型を保持し、明示的な`some(null)`は従来どおり`Option<null>`です。
Both analyzers infer `Option<never>` for null-only nullable conversions, so `getOrElse` retains the fallback's result type. Nullable unions exclude null from the present type; explicit `some(null)` remains `Option<null>`.

Each operation performs constant container work plus at most one callback.
コンテナ自体の処理は定数時間で、コールバックは高々一回実行します。

## Vec：順序付きリスト

`Vec<T>`は即時評価するゼロ始まりのリストです。変換では元の構造を変更せず、検索結果は欠損を区別する`Option<T>`で受け取れます。`find`・`any`・`every`は出現順に判定し、結果が決まった時点で終了します。全件を評価したい場合は`filter`を使います。

| API | Behavior / 動作 | Time / 時間 |
| --- | --- | --- |
| `empty()`, `of(...items)` | 空リスト・引数順のリストを構築しキーを捨てる / Eager list creation; keys discarded | O(1), O(n) |
| `fromIterable(items)` | 既存Vecは同一インスタンスを再利用。他は一回の即時走査 / Reuse a Vec; collect any other iterable once | VecはO(1)、他はO(n) |
| `toList()` | Copy-on-write PHP list / コピーオンライトのPHPリスト | O(1) until mutation / 変更までO(1) |
| `count()`, `isEmpty()`, `at(index)`, `first()`, `last()` | nullを保持し空・範囲外・負の添字は欠損 / Index queries preserve null; absent positions are None | O(1) |
| `find(predicate)` | 最初の一致をOptionで返す。一致なし・空はNone、nullの一致はSome(null) / First matching Option | 最悪O(n)＋述語、最初の一致で終了 |
| `any(predicate)` | 最初の一致でtrue。空は述語を呼ばずfalse / First match wins; false on empty | 最悪O(n)＋述語、最初の一致で終了 |
| `every(predicate)` | 最初の不一致でfalse。空は述語を呼ばずtrue / First rejection stops; true on empty | 最悪O(n)＋述語、最初の不一致で終了 |
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

検索・判定の述語型は`callable(T): bool`です。各要素を高々一回評価し、判定後の中間コレクションを作りません。述語が投げた例外は同じ例外のまま伝播します。副作用がある述語では、全件評価する`filter`と短絡する`find`の評価回数が異なる点に注意してください。

`Vec::fromIterable($existingVec)`は`===`でも同じVecを返します。オブジェクトの複製や要素の凍結を要求する操作ではありません。他のiterableを一度だけ収集する挙動と、配列の第一階層の参照切離しは変わりません。

Negative take/drop counts and non-positive chunk sizes throw `InvalidArgumentException` immediately. `uniqueBy` accepts only int/string keys, including negative integers, numeric strings, empty strings and strings containing null bytes. It does not define object equality or float hashing.
take/dropの負数、chunkの0以下は即座に`InvalidArgumentException`になります。重複判定のキーは整数・文字列のみで、負の整数・数値文字列・空文字・nullバイトを含む文字列も扱います。オブジェクトの等価性や浮動小数点数のハッシュは定義しません。

## Dict：キーの型を区別する辞書

`filter`・`find`・`any`・`every`・`fold`・`merge`は`v0.2.0`で追加しました。
`filter`, `find`, `any`, `every`, `fold` and `merge` were added in `v0.2.0`.

`groupBy`・`partition`・`flatMap`・述語付き`count`・`sum`・`mkString`・`toArray`・`getOrDefault`は`v0.3.0`で追加・拡張しました。
`groupBy`, `partition`, `flatMap`, predicate-based `count`, `sum`, `mkString`, `toArray` and `getOrDefault` were added or extended in `v0.3.0`.

| API | Behavior / 動作 | Time / 時間 |
| --- | --- | --- |
| `empty()`, `fromPairs(pairs)` | Empty or insertion-ordered creation / 空または挿入順の構築 | O(1), expected O(n) |
| `has(key)`, `get(key)` | Strict key lookup; present null stays Some / キーの型を区別しnullも存在として保持 | Expected O(1) |
| `count()`, `count(null)`, `isEmpty()` | Size queries; PHP `count($dict)` unchanged / 件数の判定、PHPのcountも従来どおり | O(1) |
| `count(predicate)` | Count matches after testing every entry once / 全件を一回判定して一致件数を返す | O(n) + callbacks |
| `getOrDefault(key, default)` | Missing-only value fallback; null and callable values preserved / 欠損時だけ既定値を返し、null・callableを値として保持 | Expected O(1) |
| `with(key, value)` | Explicit replacement or insertion; preserves old position / 明示的な置換または追加・既存位置を保持 | O(n) copying |
| `without(key)` | Remove key; missing is harmless / キーを除去・未登録でも正常終了 | O(n) copying |
| `mapValues(mapper)` | Mapper receives `(value, key)` / 値・キーの順で変換 | O(n) + callbacks |
| `groupBy(keyOf)` | `Dict<G, Dict<K, V>>`; strict group keys and original inner keys / グループ・元キーとも型を区別し内側も辞書にする | Expected O(n) + callbacks / 期待O(n) |
| `partition(predicate)` | `[accepted, rejected]` dictionaries; original keys/order / 元キーと順序を保持して合致・非合致の辞書へ分割 | O(n) + callbacks |
| `flatMap(mapper)` | Eager one-level expansion of pairs; duplicate typed keys throw / 組を一段だけ即時展開し型の等しい重複キーを拒否 | Expected O(n + m) + callbacks / 出力m件、期待O(n + m) |
| `sum()` | Strict int/float addition; empty is int 0 / int・floatだけを加算し空なら整数0 | O(n) |
| `mkString(formatter, separator, prefix, suffix)` | Explicit `(value, key): string` formatting; empty is prefix + suffix / 明示的に文字列化し、空なら接頭辞と接尾辞だけ | O(n + L) + callbacks / 出力Lバイト |
| `filter(predicate)` | Keep matching entries and their original key types/order; eager / キーの型・順序を保ち条件に合う登録を即時抽出 | O(n) + callbacks |
| `find(predicate)` | First matching `[key, value]` as `Option<array{K, V}>`; empty/missing is None / 最初に一致する組を返し、空・一致なしは欠損 | Worst O(n) + callbacks; short-circuit / 最悪O(n)、短絡 |
| `any(predicate)` | First match returns true; empty is false / 最初の一致でtrue、空はfalse | Worst O(n) + callbacks; short-circuit / 最悪O(n)、短絡 |
| `every(predicate)` | First rejection returns false; empty is true / 最初の不一致でfalse、空はtrue | Worst O(n) + callbacks; short-circuit / 最悪O(n)、短絡 |
| `fold(initial, reducer)` | Left fold with `(accumulator, value, key)`; empty returns initial unchanged / 累積値・値・キーの順で左畳み込み、空なら初期値をそのまま返す | O(n) + callbacks |
| `merge(other)` | Right-hand values replace matching typed keys at their original positions; new keys append / 同じ型のキーは右側の値で既存位置を置換し、新しいキーは末尾へ追加 | Expected O(n + m) / 期待O(n + m) |
| `keys()`, `values()` | Ordered Vec views / 順序付きVecを返す | O(n) |
| `toPairs()`, `getIterator()` | `[key, value]` pairs; lossless key types / 型を損なわないキーと値の組 | O(n) |
| `toArray()` | Native array only if every key retains its type; coercion throws / 全キーの型を保持できる場合だけPHP配列へ変換 | Expected O(n) / 期待O(n) |

Duplicates are rejected during bulk construction, even if the previous value is null. Removing and reinserting a key moves it to the end. Hash complexity assumes ordinary PHP hash-table behavior and bounded key length; hashing long strings also costs their length.
一括構築での重複は先行値がnullでも拒否します。除去後に再挿入したキーは末尾に移動します。ハッシュの計算量は通常のPHPハッシュテーブルと有界なキー長を前提とし、長い文字列のハッシュには文字列長に応じたコストがかかります。

Dict predicates have type `callable(V, K): bool`. They run in insertion order, at most once per visited entry. `filter` visits every entry and retains O(k) output for k matches. `find`/`any`/`every` stop when their result is known, retain O(1) additional container state and do not build an intermediate dictionary. Empty dictionaries do not invoke the predicate. A matching null value produces `Some([key, null])` from `find`, not None; `get(key)` continues to return just the value as `Option<V>`. Callback exceptions propagate unchanged and no partial result is returned.
Dictの述語は`callable(V, K): bool`で、挿入順に訪問した各登録を高々一回評価します。`filter`は全件を評価し、一致したk件分の出力をO(k)で保持します。`find`・`any`・`every`は結果が決まると終了し、中間辞書を作らず、コンテナ自体の追加状態はO(1)です。空なら述語を呼びません。`find`でnullの値が一致した場合はNoneではなく`Some([key, null])`です。キー指定の`get`は引き続き値だけを`Option<V>`で返します。コールバックの例外はそのまま伝播し、部分的な結果は返しません。

`fold` accepts initial type A and reducer `callable(A|B, V, K): B`, returning A|B. It passes the previous result to the next call in insertion order, or returns the exact initial value on an empty dictionary. Its extra memory is the accumulator and callback allocations, not a materialized list of entries. Container immutability does not freeze a mutable accumulator or objects modified by a callback.
`fold`は初期値Aと`callable(A|B, V, K): B`の集約処理を受け取り、A|Bを返します。挿入順に直前の結果を次の呼出しへ渡し、空の辞書では初期値そのものを返します。追加メモリは累積値とコールバックが確保する領域で、登録一覧を別途実体化しません。コンテナの不変性は、可変な累積値やコールバックが操作するオブジェクトを凍結するものではありません。

`merge` explicitly replaces values, like repeated `with`, while combining the dictionaries in one operation. Exact typed-key matches use the right-hand value, including null, without moving the existing left-hand position. Right-hand keys absent on the left append in right-hand insertion order. Integer `1` and string `'1'` remain separate, and neither input dictionary changes. Merging `Dict<K, V>` with `Dict<KOther, VOther>` yields `Dict<K|KOther, V|VOther>`. The result retains at most O(n + m) entries; values are not deep-copied. This avoids the repeated whole-dictionary copies of a loop calling `with`.
`merge`は`with`と同様に明示的に値を置換し、二つの辞書を一回の操作で結合します。型も同じキーにはnullを含む右側の値を使い、左側の既存位置は動かしません。左側にない右側のキーは、右側の挿入順で末尾へ追加します。整数`1`と文字列`'1'`は別のキーとして保持し、どちらの入力も変更しません。`Dict<K, V>`と`Dict<KOther, VOther>`の結合結果は`Dict<K|KOther, V|VOther>`です。出力の保持領域は最大O(n + m)で、値の深いコピーは行いません。`with`をループで繰り返す場合の辞書全体の反復コピーを避けられます。

### 分類・展開・集計・出力の契約 / Grouping, expansion, aggregation and export

`groupBy` accepts `callable(V, K): G`, with G restricted to int|string, and returns `Dict<G, Dict<K, V>>`. Groups follow the order of their first occurrence. Each inner dictionary retains original key types, relative insertion order and null values. Integer group key `1` differs from string `'1'`. `partition` returns `array{Dict<K, V>, Dict<K, V>}` in accepted/rejected order. Both operations visit every source entry once, preserve the original pair even if a callback changes its local arguments by reference, and keep O(n) total entries plus group containers. Empty input yields an empty grouping dictionary or two empty dictionaries without invoking callbacks.
`groupBy`は`callable(V, K): G`を受け取り、Gをint|stringに限定して`Dict<G, Dict<K, V>>`を返します。グループは最初の出現順で、各内側辞書は元のキー型・相対的な挿入順・nullを保持します。グループキーの整数`1`と文字列`'1'`も別です。`partition`は合致・非合致の順に`array{Dict<K, V>, Dict<K, V>}`を返します。両方とも元の各項目を一回だけ訪問し、コールバックがローカル引数を参照渡しで変更しても元の組を保持します。合計O(n)件とグループのコンテナを保持し、空ならコールバックを呼ばず空の分類辞書または二つの空辞書を返します。

`flatMap` accepts `callable(V, K): iterable<mixed, array{KNew, VNew}>`, with KNew restricted to int|string, and returns `Dict<KNew, VNew>`. It visits source entries in insertion order and completely consumes each returned iterable before invoking the next mapper. Inner iterable indexes are discarded; the keys inside its pairs are retained. A Dict is itself a suitable iterable of pairs. Empty iterables omit an entry; an empty source does not invoke the mapper. Repeated strictly typed output keys throw `InvalidArgumentException`, even when an earlier value is null, matching `fromPairs` rather than the replacement behavior of `merge`. Output retains O(m) entries, excluding input/callback allocations. An exception returns no partial result and propagates unchanged; callback side effects are not rolled back. Infinite inner iterables cannot complete.
`flatMap`は`callable(V, K): iterable<mixed, array{KNew, VNew}>`を受け取り、KNewをint|stringに限定して`Dict<KNew, VNew>`を返します。元辞書を挿入順で訪問し、返されたiterableを最後まで走査してから次の変換処理を呼びます。内側iterableの添字は捨て、組の中のキーを保持します。Dict自身も組のiterableとして返せます。空のiterableなら出力を省き、元辞書が空なら変換処理を呼びません。型まで一致する出力キーの重複は、先行値がnullでも`InvalidArgumentException`です。上書きする`merge`ではなく`fromPairs`と同じ方針です。出力は入力やコールバックの確保領域を除きO(m)件を保持します。例外はそのまま伝播して部分結果を返さず、コールバックの副作用は巻き戻しません。無限の内側iterableは完了できません。

`count(predicate)` calls `callable(V, K): bool` once per entry, in insertion order, without an intermediate dictionary. It uses O(1) additional container state; empty dictionaries return 0 without invoking the predicate. Omitting the predicate, passing null, or using PHP's `count($dict)` retains the constant-time size query.
`count(predicate)`は中間辞書を作らず、`callable(V, K): bool`を挿入順で一件につき一回呼びます。追加のコンテナ状態はO(1)、空なら述語を呼ばず0を返します。述語の省略・null指定・PHPの`count($dict)`は、定数時間の全件数取得を維持します。

`sum()` adds int/float values from integer 0 in insertion order, with O(1) extra container state. Numeric strings, bool, null, arrays and objects throw `TypeError` when encountered; it does not follow PHP's permissive numeric coercions. Values are checked at runtime: calling `sum()` on an arbitrary Dict is not statically rejected based on its V parameter. The result is `int|float`, including for integer-only input because [integer overflow produces a float](https://www.php.net/manual/en/language.types.integer.php). Empty dictionaries return int 0. Native floating-point rounding, `NAN` and infinities remain possible; this is not a decimal/money arithmetic API.
`sum()`は整数0からint/floatの値を挿入順に加算し、追加のコンテナ状態はO(1)です。数値文字列・bool・null・配列・オブジェクトは見つかった時点で`TypeError`になり、PHPの緩い数値変換には従いません。値の数値型は実行時に確認し、任意のDictへの`sum()`呼出し自体をVの型に基づいて静的に拒否するものではありません。[整数の桁あふれはfloatになる](https://www.php.net/manual/en/language.types.integer.php)ため、整数だけでも戻り型は`int|float`です。空なら整数0を返します。浮動小数点の丸め・`NAN`・無限大はPHPの演算どおりに生じ得るため、十進数・金額専用の演算APIではありません。

`mkString` requires `callable(V, K): string`, called once per entry in insertion order; callback return types follow the general static generic contract above. Separator, prefix and suffix default to the empty string. Separators occur only between formatted entries. Empty input does not invoke the formatter and returns prefix + suffix. Formatting is explicit: null, objects, arrays and keys are not implicitly assigned a display meaning. The retained output is O(L) bytes for L output bytes; intermediate formatter/container allocations can add O(n + L). The formatter and its exceptions are evaluated immediately.
`mkString`は`callable(V, K): string`を必須とし、挿入順で一件につき一回呼びます。コールバックの戻り型は前述の静的なgeneric契約に従います。separator・prefix・suffixの既定値は空文字で、separatorは項目間だけに入ります。空なら文字列化を呼ばずprefixとsuffixだけを返します。null・オブジェクト・配列・キーの表示上の意味を暗黙に決めず、文字列化を明示します。出力LバイトをO(L)で保持し、コールバックや中間コンテナの確保分としてO(n + L)が加わり得ます。文字列化とその例外は呼出し時に即時評価されます。

`toArray()` returns `array<K, V>` in insertion order, with O(n) output storage, only if PHP preserves every original key's type. [Native PHP arrays coerce some numeric string keys to integers](https://www.php.net/manual/en/language.types.array.php). Such keys throw `InvalidArgumentException` even in isolation: `'1'` is rejected, while `'01'`, `'+1'`, nonnumeric strings and integers can be preserved. This rejects type loss before an integer/string collision can silently discard a value. It performs no recursive value conversion and preserves null values. Use `toPairs()` for a lossless representation of every valid Dict key.
`toArray()`はPHPが元キーの型をすべて保持できる場合だけ、挿入順の`array<K, V>`を返し、O(n)の出力領域を使います。[PHP配列は一部の数値文字列キーを整数へ変換します](https://www.php.net/manual/en/language.types.array.php)。そのため`'1'`は単独でも`InvalidArgumentException`で拒否し、`'01'`・`'+1'`・非数値文字列・整数など型を保持できるキーは変換できます。型の損失そのものを拒否するため、整数/文字列の衝突で値を失うこともありません。値を再帰変換せずnullも保持します。全ての有効なDictキーを損失なく表すには`toPairs()`を使います。

`getOrDefault(key, default)` returns V|D for a default of type D. It looks up the exact typed key and returns a present value unchanged, including null. Only absence returns the supplied default, with no invocation when that default is callable. The default expression is evaluated by PHP before the method call, just like any argument; use `get(key)->getOrElse(fallback)` for lazy creation. The lookup uses O(1) extra container state and does not allocate an Option.
`getOrDefault(key, default)`は既定値Dに対してV|Dを返します。キーを型も含めて照合し、存在すればnullを含め元の値をそのまま返します。欠損時だけ指定した既定値を返し、callableでも実行しません。既定値の式自体は他の引数と同じく呼出し前にPHPが評価するため、遅延生成には`get(key)->getOrElse(fallback)`を使います。検索時の追加状態はO(1)でOptionを生成しません。

## Stream：必要な分だけ遅延評価

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
