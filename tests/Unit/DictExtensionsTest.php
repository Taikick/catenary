<?php

declare(strict_types=1);

namespace Catenary\Tests\Unit;

use Catenary\Dict;
use Generator;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

/**
 * Verifies dictionary grouping, expansion, aggregation and explicit boundary conversions.
 * 辞書の分類・展開・集計と明示的な境界変換を検証します。
 */
final class DictExtensionsTest extends TestCase
{
    /**
     * Keeps first-seen group order, original typed keys and shared object identities.
     * グループの初出順、元のキー型、共有オブジェクトの同一性を保持します。
     *
     * @return void
     */
    public function testGroupByPreservesStrictGroupAndEntryKeysInTheirOriginalOrder(): void
    {
        // GIVEN: Integer and string keys coexist with null and a shared object / 型違いのキーとnull・オブジェクトです。
        $object = new stdClass();
        $pairs = [[1, null], ['1', $object], ['', false], ["\0", 0], [-2, 'last']];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: The first and third entries share the integer group / 一番目と三番目を整数グループに分類します。
        $grouped = $dict->groupBy(keyOf: function (mixed $value, int|string $key) use (&$visited): int|string {
            $visited[] = [$key, $value];
            return match ($key) {
                1, '' => 1,
                '1', -2 => '1',
                default => "\0",
            };
        });

        // THEN: Both levels retain their typed insertion order and source values / 両階層のキー型・挿入順を保持します。
        self::assertSame([1, '1', "\0"], $grouped->keys()->toList());
        self::assertSame([[1, null], ['', false]], $grouped->get(key: 1)->getOrThrow()->toPairs());
        self::assertSame([['1', $object], [-2, 'last']], $grouped->get(key: '1')->getOrThrow()->toPairs());
        self::assertSame([["\0", 0]], $grouped->get(key: "\0")->getOrThrow()->toPairs());
        self::assertSame($pairs, $visited);
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Splits each pair once into accepted and rejected dictionaries without losing nulls.
     * 各組を一度判定して合致・非合致の辞書へ分け、nullも保持します。
     *
     * @return void
     */
    public function testPartitionReturnsAcceptedThenRejectedAndEvaluatesEachEntryOnce(): void
    {
        // GIVEN: False-like values are distinct values, not absent entries / 偽相当の値も欠損ではありません。
        $pairs = [[1, null], ['1', false], ['', 0], [-2, 'text']];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Select entries by their original key type / 元のキーの型で分類します。
        [$accepted, $rejected] = $dict->partition(
            predicate: function (mixed $value, int|string $key) use (&$visited): bool {
                $visited[] = [$key, $value];
                return is_int($key);
            },
        );

        // THEN: Each half preserves its source order / 各側は元の順序を保持します。
        self::assertSame([[1, null], [-2, 'text']], $accepted->toPairs());
        self::assertSame([['1', false], ['', 0]], $rejected->toPairs());
        self::assertSame($pairs, $visited);
        self::assertSame($pairs, $dict->toPairs());
        [$all, $none] = $dict->partition(predicate: fn (): bool => true);
        self::assertSame($pairs, $all->toPairs());
        self::assertSame([], $none->toPairs());
        [$none, $all] = $dict->partition(predicate: fn (): bool => false);
        self::assertSame([], $none->toPairs());
        self::assertSame($pairs, $all->toPairs());
    }

    /**
     * Keeps retained original pairs even when callbacks replace local reference arguments.
     * コールバックが参照引数を置き換えても保持する元の組は変更しません。
     *
     * @return void
     */
    public function testGroupingAndPartitioningDetachCallbackArgumentsFromRetainedPairs(): void
    {
        // GIVEN: A callback mutates both arguments after choosing the group / 分類を決めてから引数を変えます。
        $pairs = [[1, 10], ['1', 20]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $group = static function (int &$value, int|string &$key): string {
            $value = 999;
            $key = 'replacement';
            return 'same';
        };
        $predicate = static function (int &$value, int|string &$key): bool {
            $accepted = $key === 1;
            $value = 999;
            $key = 'replacement';
            return $accepted;
        };

        // WHEN: Group and partition using reference-taking callbacks / 参照を受け取る処理で分類します。
        $grouped = $dict->groupBy(keyOf: $group);
        [$accepted, $rejected] = $dict->partition(predicate: $predicate);

        // THEN: Local callback replacements do not corrupt the retained lookup / 参照引数の置換で検索を壊しません。
        self::assertSame($pairs, $grouped->get(key: 'same')->getOrThrow()->toPairs());
        self::assertSame([[1, 10]], $accepted->toPairs());
        self::assertSame([['1', 20]], $rejected->toPairs());
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Eagerly consumes inner pairs once in outer and inner order, including Dict iterables.
     * Dictも含む内側の組を外側・内側の順で一度だけ即時収集します。
     *
     * @return void
     */
    public function testFlatMapConsumesGeneratorsAndDictionariesInOrder(): void
    {
        // GIVEN: A mapper expands one entry, omits another and returns a Dict for the last / 展開・省略・辞書を組み合わせます。
        $source = Dict::fromPairs(pairs: [['first', 1], ['skip', 2], ['last', 3]]);
        $events = [];
        $object = new stdClass();
        $generator = static function () use (&$events): Generator {
            try {
                $events[] = 'inner:int';
                yield 'ignored' => [1, null];
                $events[] = 'inner:string';
                yield 'ignored' => ['1', false];
            } finally {
                $events[] = 'inner:closed';
            }
        };

        // WHEN: Expansion finishes before flatMap returns / flatMapから戻る前に展開を完了します。
        $expanded = $source->flatMap(
            mapper: function (int $value, string $key) use (&$events, $generator, $object): iterable {
                $events[] = [$key, $value];
                return match ($key) {
                    'first' => $generator(),
                    'skip' => [],
                    default => Dict::fromPairs(pairs: [['object', $object], ['', 3]]),
                };
            },
        );

        // THEN: Yield keys are ignored, pair keys and shared objects are retained / yieldキーを捨て、組のキーを保持します。
        self::assertSame([[1, null], ['1', false], ['object', $object], ['', 3]], $expanded->toPairs());
        self::assertSame([
            ['first', 1], 'inner:int', 'inner:string', 'inner:closed', ['skip', 2], ['last', 3],
        ], $events);
        self::assertSame([['first', 1], ['skip', 2], ['last', 3]], $source->toPairs());
    }

    /**
     * Rejects repeated typed keys within or across expansions even after a null value.
     * 展開内・展開間の型も同じ重複キーを、先行値がnullでも拒否します。
     *
     * @return void
     */
    public function testFlatMapRejectsDuplicateKeysWithoutEvaluatingLaterSourceEntries(): void
    {
        // GIVEN: Duplication can occur in one result or across two results / 一つの結果内と二つの結果間の重複です。
        foreach ([true, false] as $withinOneResult) {
            $source = Dict::fromPairs(pairs: [['first', 1], ['second', 2], ['after', 3]]);
            $visited = [];

            // WHEN / THEN: The first duplicate stops consumption and leaves the source unchanged / 最初の重複で止めます。
            try {
                $source->flatMap(
                    mapper: function (int $value, string $key) use (&$visited, $withinOneResult): array {
                        $visited[] = $key;
                        return $withinOneResult ? [[1, null], [1, $value]] : [[1, $value === 1 ? null : $value]];
                    },
                );
                self::fail('Duplicate typed keys must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame($withinOneResult ? ['first'] : ['first', 'second'], $visited);
            }
            self::assertSame([['first', 1], ['second', 2], ['after', 3]], $source->toPairs());
        }
    }

    /**
     * Stops at a duplicate generated pair and closes the interrupted inner generator.
     * 生成した組の重複で直ちに終了し、中断した内側のジェネレータを解放します。
     *
     * @return void
     */
    public function testFlatMapClosesAnInnerGeneratorWhenItsDuplicateKeyIsRejected(): void
    {
        // GIVEN: A duplicate precedes an observable forbidden third read / 重複の後に読取禁止の三番目を置きます。
        $source = Dict::fromPairs(pairs: [['first', 1], ['after', 2]]);
        $events = [];

        // WHEN / THEN: Reject the duplicate without resuming beyond it / 重複を拒否し、それより先へ進めません。
        try {
            $source->flatMap(mapper: function (int $value, string $key) use (&$events): Generator {
                $events[] = $key;
                try {
                    yield [1, null];
                    yield [1, $value];
                    $events[] = 'read-too-far';
                } finally {
                    $events[] = 'closed';
                }
            });
            self::fail('Duplicate generated keys must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(['first', 'closed'], $events);
        }
    }

    /**
     * Detaches first-level references from pairs produced by a mapper.
     * 変換処理が返す組に含まれた第一階層の参照を切り離します。
     *
     * @return void
     */
    public function testFlatMapDoesNotRetainReferencesFromInputPairs(): void
    {
        // GIVEN: The mapped pair references mutable local variables / 返す組が変更可能な変数を参照しています。
        $key = 'original';
        $value = 1;
        $source = Dict::fromPairs(pairs: [['source', 0]]);

        // WHEN: Change the external references after eager construction / 即時構築後に参照元を変更します。
        $expanded = $source->flatMap(mapper: function () use (&$key, &$value): array {
            return [[&$key, &$value]];
        });
        $key = 'changed';
        $value = 2;

        // THEN: The result holds the original pair and lookup / 元の組と検索結果を保持します。
        self::assertSame([['original', 1]], $expanded->toPairs());
        self::assertSame(1, $expanded->get(key: 'original')->getOrThrow());
    }

    /**
     * Propagates generator failures unchanged and executes their finally block.
     * ジェネレータの例外をそのまま伝播し、finallyを実行します。
     *
     * @return void
     */
    public function testFlatMapClosesAFailingGeneratorAndDoesNotVisitLaterEntries(): void
    {
        // GIVEN: The inner generator fails after one pair / 内側の生成処理が一組の後で失敗します。
        $failure = new LogicException('inner failure');
        $source = Dict::fromPairs(pairs: [['first', 1], ['after', 2]]);
        $events = [];

        // WHEN / THEN: No partial Dict is returned and the same exception escapes / 部分結果を返さず同じ例外を伝播します。
        try {
            $source->flatMap(mapper: function (int $value, string $key) use (&$events, $failure): Generator {
                $events[] = $key;
                try {
                    yield [$key, $value];
                    throw $failure;
                } finally {
                    $events[] = 'closed';
                }
            });
            self::fail('The generator failure must propagate.');
        } catch (LogicException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(['first', 'closed'], $events);
        self::assertSame([['first', 1], ['after', 2]], $source->toPairs());
    }

    /**
     * Counts all predicate matches while preserving the existing Countable behavior.
     * 述語に合致した全件を数え、既存のCountable動作も保持します。
     *
     * @return void
     */
    public function testPredicateCountVisitsEveryEntryAndPlainCountRemainsCompatible(): void
    {
        // GIVEN: Typed keys and nullable values must reach the predicate unchanged / 型付きキーとnullを渡します。
        $pairs = [[1, null], ['1', false], ['', 0], [-1, null]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Count all null values rather than short-circuiting / 短絡せずnullの全件を数えます。
        $count = $dict->count(predicate: function (mixed $value, int|string $key) use (&$visited): bool {
            $visited[] = [$key, $value];
            return $value === null;
        });

        // THEN: Both the optional predicate and the no-argument protocol work / 任意述語と引数なしの両方を保ちます。
        self::assertSame(2, $count);
        self::assertSame($pairs, $visited);
        self::assertSame(4, $dict->count());
        self::assertSame(4, count($dict));
        self::assertSame(4, $dict->count(predicate: null));
        self::assertSame(0, $dict->count(predicate: fn (): bool => false));
        self::assertSame(4, $dict->count(predicate: fn (): bool => true));
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Sums native numbers in order, preserving empty zero and native floating-point behavior.
     * ネイティブ数値を順番に加算し、空のゼロと浮動小数点の動作を保持します。
     *
     * @return void
     */
    public function testSumSupportsIntegersFloatsOverflowAndNativeNonFiniteValues(): void
    {
        // GIVEN / WHEN / THEN: Numeric arithmetic follows PHP without normalizing keys / キーを正規化せずPHPの加算を使います。
        self::assertSame(0, Dict::empty()->sum());
        self::assertSame(6, Dict::fromPairs(pairs: [[1, 3], ['1', 4], ['', -1]])->sum());
        self::assertSame(2.5, Dict::fromPairs(pairs: [['a', 1], ['b', 2.5], ['c', -1]])->sum());
        self::assertSame(0.0, Dict::fromPairs(pairs: [['a', 0.0]])->sum());
        self::assertSame(PHP_INT_MAX + 1, Dict::fromPairs(pairs: [['a', PHP_INT_MAX], ['b', 1]])->sum());
        self::assertSame(INF, Dict::fromPairs(pairs: [['a', INF]])->sum());
        self::assertTrue(is_nan(Dict::fromPairs(pairs: [['a', NAN]])->sum()));
        self::assertSame(1.0, Dict::fromPairs(pairs: [['a', 1.0e16], ['b', -1.0e16], ['c', 1.0]])->sum());
    }

    /**
     * Rejects every nonnumeric value instead of coercing strings, booleans or null.
     * 文字列・真偽値・null等を暗黙変換せず、数値以外を拒否します。
     *
     * @return void
     */
    public function testSumRejectsNonnumericValuesWithoutChangingItsSource(): void
    {
        // GIVEN: Each unsupported value follows one valid numeric entry / 正しい数値の後に非対応値を置きます。
        foreach (['2', '2.5', 'text', true, false, null, [], new stdClass()] as $value) {
            $pairs = [['valid', 1], ['invalid', $value]];
            $dict = Dict::fromPairs(pairs: $pairs);

            // WHEN / THEN: The invalid value is a type failure and the snapshot stays intact / 型エラーで元を保持します。
            try {
                $dict->sum();
                self::fail('Nonnumeric values must not be coerced into a total.');
            } catch (TypeError) {
                self::assertSame($pairs, $dict->toPairs());
            }
        }
    }

    /**
     * Formats each entry once in insertion order with explicit separators and delimiters.
     * 挿入順に各項目を一回整形し、明示した区切りと囲みを使います。
     *
     * @return void
     */
    public function testMkStringUsesExplicitFormattingAndPreservesAllText(): void
    {
        // GIVEN: Typed keys, null and Unicode need caller-defined rendering / キー型・null・日本語を明示整形します。
        $pairs = [[1, null], ['1', '日本語'], ['', '']];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Include both the original key type and formatted value / 元のキー型と値を含めます。
        $text = $dict->mkString(
            formatter: function (?string $value, int|string $key) use (&$visited): string {
                $visited[] = [$key, $value];
                return (is_int($key) ? 'int:' : 'str:') . $key . '=' . ($value ?? '<null>');
            },
            separator: ' | ',
            prefix: '[',
            suffix: ']',
        );

        // THEN: Null never becomes absence and empty strings keep their separators / nullと空文字を勝手に省略しません。
        self::assertSame('[int:1=<null> | str:1=日本語 | str:=]', $text);
        self::assertSame($pairs, $visited);
        self::assertSame($pairs, $dict->toPairs());
        self::assertSame('ab', Dict::fromPairs(pairs: [[1, 'a'], [2, 'b']])->mkString(formatter: fn ($v) => $v));
        self::assertSame('[only]', Dict::fromPairs(pairs: [[1, 'only']])->mkString(
            formatter: fn (string $value): string => $value,
            separator: ',',
            prefix: '[',
            suffix: ']',
        ));
    }

    /**
     * Converts only representable native array keys and detaches the returned array structure.
     * PHP配列で型を保持できるキーだけ変換し、返す配列構造を切り離します。
     *
     * @return void
     */
    public function testToArrayPreservesRepresentableKeyTypesValuesAndOrder(): void
    {
        // GIVEN: Decimal-looking strings that PHP preserves coexist with integers / 型を保持する数値風文字列と整数です。
        $object = new stdClass();
        $outsideIntegerRange = (string) PHP_INT_MAX . '0';
        $pairs = [
            [1, null], ['01', false], ['+1', 0], ['1.0', ''], ['', $object], ["\0", 'x'],
            [-2, 'negative'], [$outsideIntegerRange, 'large'],
        ];
        $dict = Dict::fromPairs(pairs: $pairs);

        // WHEN: Export to a native associative array / ネイティブ連想配列へ変換します。
        $array = $dict->toArray();

        // THEN: Every key keeps its exact type and objects remain shared / キー型を保ちオブジェクトは浅く共有します。
        self::assertSame([1, '01', '+1', '1.0', '', "\0", -2, $outsideIntegerRange], array_keys($array));
        self::assertSame([null, false, 0, '', $object, 'x', 'negative', 'large'], array_values($array));
        $array[1] = 'changed';
        $array['new'] = 5;
        self::assertSame($pairs, $dict->toPairs());
        self::assertSame([], Dict::empty()->toArray());
    }

    /**
     * Rejects numeric string key coercion even when no second key would collide.
     * 他のキーと衝突しない場合も、数値文字列キーの型変換を拒否します。
     *
     * @return void
     */
    public function testToArrayRejectsEveryLossyNativeKeyConversion(): void
    {
        // GIVEN: Single numeric strings and an explicit integer/string collision / 単独数値文字列と実際の衝突です。
        $cases = [
            [['1', 'value']], [['0', null]], [['-1', false]], [[1, 'int'], ['1', 'str']],
            [[(string) PHP_INT_MIN, 'minimum']], [[(string) PHP_INT_MAX, 'maximum']],
        ];
        foreach ($cases as $pairs) {
            $dict = Dict::fromPairs(pairs: $pairs);

            // WHEN / THEN: No silently changed key or partial result is returned / キーの暗黙変換や部分結果を返しません。
            try {
                $dict->toArray();
                self::fail('Native array key coercion must be rejected.');
            } catch (InvalidArgumentException) {
                self::assertSame($pairs, $dict->toPairs());
            }
        }
    }

    /**
     * Returns the default only for absence and treats callable defaults as ordinary values.
     * 欠損時だけ既定値を返し、callableの既定値も通常の値として扱います。
     *
     * @return void
     */
    public function testGetOrDefaultPreservesPresentNullFalseLikeValuesAndCallableIdentity(): void
    {
        // GIVEN: Every false-like value is present under a strict typed key / 各偽相当値を型付きキーに保存します。
        $dict = Dict::fromPairs(pairs: [[1, null], ['1', false], ['', 0], ['text', '']]);
        $default = new stdClass();
        $callable = fn () => throw new LogicException('A value default must not be invoked.');

        // WHEN / THEN: The default has no lazy semantics and keys remain distinct / 遅延処理をせずキーを区別します。
        self::assertNull($dict->getOrDefault(key: 1, default: $default));
        self::assertFalse($dict->getOrDefault(key: '1', default: $default));
        self::assertSame(0, $dict->getOrDefault(key: '', default: $default));
        self::assertSame('', $dict->getOrDefault(key: 'text', default: $default));
        self::assertSame($default, $dict->getOrDefault(key: 'missing', default: $default));
        self::assertSame($callable, $dict->getOrDefault(key: 'missing', default: $callable));
        self::assertNull(Dict::empty()->getOrDefault(key: 1, default: null));
        self::assertSame('fallback', Dict::fromPairs(pairs: [[1, 'int']])->getOrDefault(key: '1', default: 'fallback'));
    }

    /**
     * Produces empty identities without invoking any transformation or predicate.
     * 変換・述語を一度も呼ばず、空辞書に対応した結果を返します。
     *
     * @return void
     */
    public function testEmptyOperationsNeverInvokeTheirCallbacks(): void
    {
        // GIVEN: Any callback evaluation is a failure / コールバックが実行されたら失敗です。
        $dict = Dict::empty();
        $fail = fn () => throw new LogicException('Empty dictionaries must not evaluate callbacks.');

        // WHEN / THEN: All empty identities are explicit / 空の場合の結果を明示します。
        self::assertSame([], $dict->groupBy(keyOf: $fail)->toPairs());
        [$accepted, $rejected] = $dict->partition(predicate: $fail);
        self::assertSame([], $accepted->toPairs());
        self::assertSame([], $rejected->toPairs());
        self::assertSame([], $dict->flatMap(mapper: $fail)->toPairs());
        self::assertSame(0, $dict->count(predicate: $fail));
        self::assertSame('', $dict->mkString(formatter: $fail));
        self::assertSame('[]', $dict->mkString(formatter: $fail, separator: ',', prefix: '[', suffix: ']'));
    }

    /**
     * Propagates callback failures unchanged and stops before subsequent source entries.
     * コールバックの例外をそのまま伝播し、後続の項目を評価しません。
     *
     * @return void
     */
    public function testCallbackFailuresDoNotReturnPartialResultsOrMutateTheSource(): void
    {
        // GIVEN: Each operation fails at the second entry / 各操作は二番目の項目で失敗します。
        $pairs = [[1, 1], ['1', 2], ['', 3]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $failure = new LogicException('Expected callback failure');
        foreach (['groupBy', 'partition', 'flatMap', 'count', 'mkString'] as $operation) {
            $visited = [];
            $callback = function (int $value, int|string $key) use (&$visited, $operation, $failure): mixed {
                $visited[] = [$key, $value];
                if ($key === '1') {
                    throw $failure;
                }
                return match ($operation) {
                    'groupBy' => 'group',
                    'partition', 'count' => true,
                    'flatMap' => [[$key, $value]],
                    default => 'formatted',
                };
            };

            // WHEN / THEN: The exact exception escapes and the remaining entry stays unread / 同じ例外で後続を読みません。
            try {
                $dict->$operation($callback);
                self::fail('The callback failure must propagate.');
            } catch (LogicException $caught) {
                self::assertSame($failure, $caught);
            }
            self::assertSame([[1, 1], ['1', 2]], $visited);
            self::assertSame($pairs, $dict->toPairs());
        }
    }
}
