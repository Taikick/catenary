<?php

declare(strict_types=1);

namespace Catenary\Tests\Unit;

use Catenary\Dict;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Checks ordered dictionary transformations, queries and immutable merges.
 * 挿入順辞書の変換・検索・不変な結合を検証します。
 */
final class DictOperationsTest extends TestCase
{
    /**
     * Filters each entry once while preserving typed keys, nulls and object identities.
     * 各項目を一回判定し、キー型・null・オブジェクトの同一性を保持します。
     *
     * @return void
     */
    public function testFilterPreservesKeysValuesAndOrderWithoutMutatingTheSource(): void
    {
        // GIVEN: Keys include distinct integer/string forms and false-like values / 型の異なるキーと偽相当の値です。
        $object = new stdClass();
        $pairs = [[1, null], ['1', false], ['', 0], ["\0", ''], [-2, $object], ['i:1', 'skip']];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Retain a subset using both value and original key / 値と元のキーで絞り込みます。
        $filtered = $dict->filter(predicate: function (mixed $value, int|string $key) use (&$visited): bool {
            $visited[] = [$key, $value];
            return $key !== '1' && $value !== 'skip';
        });

        // THEN: No values are normalized or reevaluated and the source stays unchanged / 正規化・再評価せず元を保ちます。
        self::assertSame([[1, null], ['', 0], ["\0", ''], [-2, $object]], $filtered->toPairs());
        self::assertSame($pairs, $visited);
        self::assertSame($pairs, $dict->toPairs());
        self::assertSame($object, $filtered->get(key: -2)->getOrThrow());
        self::assertNotSame($dict, $filtered);
    }

    /**
     * Retains all or no entries without changing the source dictionary.
     * 全件合致・全件不一致でも元の辞書を変更しません。
     *
     * @return void
     */
    public function testFilterSupportsAllAndNoMatches(): void
    {
        // GIVEN: Every value is present even when null / nullも存在する値として持つ辞書です。
        $pairs = [[1, null], ['1', false]];
        $dict = Dict::fromPairs(pairs: $pairs);

        // WHEN: Retain all entries or reject all entries / 全件を残す場合と全件を除く場合です。
        $all = $dict->filter(predicate: fn (): bool => true);
        $none = $dict->filter(predicate: fn (): bool => false);

        // THEN: Values and source remain unchanged / 値と元の辞書を保持します。
        self::assertSame($pairs, $all->toPairs());
        self::assertTrue($none->isEmpty());
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Retains original pairs when a predicate replaces its by-reference arguments.
     * 述語が参照引数を置き換えても元のキーと値の組を保持します。
     *
     * @return void
     */
    public function testPredicatesCannotReplaceRetainedKeysOrValuesThroughReferenceArguments(): void
    {
        // GIVEN: A callable replaces both local arguments before accepting the entry / 引数を置換してから合致を返します。
        $pairs = [[1, 10], ['1', 20]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $predicate = static function (int &$value, int|string &$key): bool {
            $value = 999;
            $key = 'replacement';
            return true;
        };

        // WHEN: Filter and find call the predicate with detached local arguments / 切り離した引数で述語を実行します。
        $filtered = $dict->filter(predicate: $predicate);
        $found = $dict->find(predicate: $predicate);

        // THEN: Original pairs and typed lookup remain consistent / 元の組と型を区別した検索を保持します。
        self::assertSame($pairs, $filtered->toPairs());
        self::assertSame([1, 10], $found->getOrThrow());
        self::assertSame(10, $filtered->get(key: 1)->getOrThrow());
        self::assertSame(20, $filtered->get(key: '1')->getOrThrow());
        self::assertFalse($filtered->has(key: 'replacement'));
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Returns the first matching pair including null without reading later entries.
     * nullを含む最初の一致した組を返し、後続の項目を読みません。
     *
     * @return void
     */
    public function testFindReturnsTheFirstPairAndPreservesPresentNull(): void
    {
        // GIVEN: Null occurs twice with strictly distinct keys / 型の異なるキーにnullが二回現れます。
        $pairs = [['before', false], [1, null], ['1', null], ['after', 3]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Find the first null in insertion order / 挿入順で最初のnullを検索します。
        $found = $dict->find(predicate: function (mixed $value, int|string $key) use (&$visited): bool {
            $visited[] = [$key, $value];
            return $value === null;
        });

        // THEN: Presence includes the key and null value, and later matches remain unread / キーとnullを保持して終了します。
        self::assertTrue($found->isSome());
        self::assertSame([1, null], $found->getOrThrow());
        self::assertSame([['before', false], [1, null]], $visited);
        self::assertSame(
            ['1', null],
            $dict->find(predicate: fn (mixed $value, int|string $key): bool => $key === '1')->getOrThrow(),
        );
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Stops existence and universal queries at the first deciding entry.
     * 存在判定と全件判定は結果が確定する最初の項目で終了します。
     *
     * @return void
     */
    public function testAnyAndEveryShortCircuitWithValuesAndTypedKeys(): void
    {
        // GIVEN: The second key decides both predicates / 二番目のキーで両述語の結果が決まります。
        $dict = Dict::fromPairs(pairs: [[1, null], ['1', false], ['', 0]]);
        $anyVisited = $everyVisited = [];

        // WHEN: Query for the string key and reject it in the universal query / 文字列キーの存在と非存在を判定します。
        $any = $dict->any(predicate: function (mixed $value, int|string $key) use (&$anyVisited): bool {
            $anyVisited[] = [$key, $value];
            return $key === '1';
        });
        $every = $dict->every(predicate: function (mixed $value, int|string $key) use (&$everyVisited): bool {
            $everyVisited[] = [$key, $value];
            return $key !== '1';
        });

        // THEN: No predicate evaluates the third entry / どちらも三番目を評価しません。
        self::assertTrue($any);
        self::assertFalse($every);
        self::assertSame([[1, null], ['1', false]], $anyVisited);
        self::assertSame([[1, null], ['1', false]], $everyVisited);
    }

    /**
     * Returns empty identities without invoking predicates or the reducer.
     * 空辞書では述語・集約処理を呼ばず、それぞれの空の結果を返します。
     *
     * @return void
     */
    public function testEmptyOperationsDoNotInvokeCallbacks(): void
    {
        // GIVEN: An empty dictionary and a callback that must never run / 空辞書と実行禁止の処理です。
        $dict = Dict::empty();
        $fail = fn () => throw new LogicException('Callback must not run');
        $initial = new stdClass();

        // WHEN / THEN: Empty identities preserve even the initial object identity / 空の結果と初期オブジェクトを保ちます。
        self::assertSame([], $dict->filter(predicate: $fail)->toPairs());
        self::assertTrue($dict->find(predicate: $fail)->isNone());
        self::assertFalse($dict->any(predicate: $fail));
        self::assertTrue($dict->every(predicate: $fail));
        self::assertSame($initial, $dict->fold(initial: $initial, reducer: $fail));
        self::assertNull($dict->fold(initial: null, reducer: $fail));
    }

    /**
     * Evaluates all entries exactly once when no early decision is possible.
     * 途中で判定できない場合は全項目を順番に一度だけ評価します。
     *
     * @return void
     */
    public function testCompleteQueriesVisitEveryPairOnce(): void
    {
        // GIVEN: No matching value and no rejected value / 一致なしの検索と全件合致の判定です。
        $pairs = [[1, 4], ['1', 5], ["\0", 6]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $findVisited = $anyVisited = $everyVisited = [];

        // WHEN: Evaluate each query to exhaustion / 最後の項目まで各判定を実行します。
        $found = $dict->find(predicate: function (int $value, int|string $key) use (&$findVisited): bool {
            $findVisited[] = [$key, $value];
            return $value < 0;
        });
        $any = $dict->any(predicate: function (int $value, int|string $key) use (&$anyVisited): bool {
            $anyVisited[] = [$key, $value];
            return $value < 0;
        });
        $every = $dict->every(predicate: function (int $value, int|string $key) use (&$everyVisited): bool {
            $everyVisited[] = [$key, $value];
            return $value > 0;
        });

        // THEN: Each query preserves visitation order and count / 順序と評価回数を保持します。
        self::assertTrue($found->isNone());
        self::assertFalse($any);
        self::assertTrue($every);
        self::assertSame($pairs, $findVisited);
        self::assertSame($pairs, $anyVisited);
        self::assertSame($pairs, $everyVisited);
    }

    /**
     * Folds from the explicit seed with accumulator, value and key in order.
     * 明示した初期値から累積値・値・キーの順で挿入順に集約します。
     *
     * @return void
     */
    public function testFoldPreservesAccumulatorOrderAndSupportsAChangedResultType(): void
    {
        // GIVEN: A null seed becomes a list of the original typed pairs / null初期値から元の組の一覧を作ります。
        $pairs = [[1, 4], ['1', 5], ['', null]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $visited = [];

        // WHEN: Every reducer receives the accumulated previous result / 毎回直前の結果を集約処理へ渡します。
        $result = $dict->fold(
            initial: null,
            reducer: function (?array $accumulator, ?int $value, int|string $key) use (&$visited): array {
                $visited[] = [$accumulator, $value, $key];
                return [...($accumulator ?? []), [$key, $value]];
            },
        );

        // THEN: Keys and null survive, and reductions occur once in insertion order / キー・nullと一回ずつの順序を保ちます。
        self::assertSame($pairs, $result);
        self::assertSame([
            [null, 4, 1],
            [[[1, 4]], 5, '1'],
            [[[1, 4], ['1', 5]], null, ''],
        ], $visited);
        self::assertSame(9, $dict->fold(initial: 0, reducer: fn (int $sum, ?int $value): int => $sum + ($value ?? 0)));
        self::assertSame($pairs, $dict->toPairs());
    }

    /**
     * Propagates the same callback exception and stops before later entries.
     * コールバックが投げた例外をそのまま伝播し、後続の項目を評価しません。
     *
     * @return void
     */
    public function testCallbackExceptionsPropagateWithoutPartialSourceMutation(): void
    {
        // GIVEN: Every operation throws at the second original key / 全操作で二番目のキーから同じ例外を投げます。
        $pairs = [[1, 1], ['1', 2], ['', 3]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $failure = new LogicException('Expected callback failure');

        foreach (['filter', 'find', 'any', 'every', 'fold'] as $operation) {
            $visited = [];
            $predicate = function (int $value, int|string $key) use (&$visited, $failure, $operation): bool {
                $visited[] = [$key, $value];
                if ($key === '1') {
                    throw $failure;
                }
                return $operation === 'filter' || $operation === 'every';
            };

            // WHEN / THEN: Failure is neither swallowed nor wrapped / 例外を握り潰したり包み直したりしません。
            try {
                if ($operation === 'fold') {
                    $dict->fold(initial: 0, reducer: fn (int $sum, int $value, int|string $key): int =>
                        $predicate($value, $key) ? $sum + $value : $sum);
                } else {
                    $dict->$operation(predicate: $predicate);
                }
                self::fail('The callback exception was swallowed.');
            } catch (LogicException $caught) {
                self::assertSame($failure, $caught);
            }
            self::assertSame([[1, 1], ['1', 2]], $visited);
            self::assertSame($pairs, $dict->toPairs());
        }
    }

    /**
     * Merges right-hand replacements in their original positions and appends new typed keys.
     * 右側の置換値を元の位置へ反映し、型を区別した新規キーを末尾へ追加します。
     *
     * @return void
     */
    public function testMergeUsesRightValuesWithoutChangingEitherSourceOrExistingPositions(): void
    {
        // GIVEN: Overlapping typed keys, nulls and new keys in a different order / 重複キー・null・順序の異なる新規キーです。
        $object = new stdClass();
        $leftPairs = [[1, 'int'], ['1', 'string'], ['', null], ["\0", false], ['last', 9]];
        $rightPairs = [['new', $object], ['1', null], ["\0", 4], [1, 5], [-2, 'negative'], ['i:1', 'prefix']];
        $left = Dict::fromPairs(pairs: $leftPairs);
        $right = Dict::fromPairs(pairs: $rightPairs);

        // WHEN: Right wins on exactly equal typed keys / 型を含め同じキーだけ右側の値を採用します。
        $merged = $left->merge(other: $right);

        // THEN: Replacements keep positions and new keys follow right insertion order / 置換位置と新規キーの挿入順を保ちます。
        self::assertSame([
            [1, 5], ['1', null], ['', null], ["\0", 4], ['last', 9],
            ['new', $object], [-2, 'negative'], ['i:1', 'prefix'],
        ], $merged->toPairs());
        self::assertTrue($merged->get(key: '1')->isSome());
        self::assertNull($merged->get(key: '1')->getOrThrow());
        self::assertSame($object, $merged->get(key: 'new')->getOrThrow());
        self::assertSame($leftPairs, $left->toPairs());
        self::assertSame($rightPairs, $right->toPairs());
        self::assertNotSame($left, $merged);
        self::assertNotSame($right, $merged);
    }

    /**
     * Preserves the value-level identity laws for empty and self merges.
     * 空辞書および自分自身との結合で値と順序が変わらないことを検証します。
     *
     * @return void
     */
    public function testMergeSupportsEmptySelfAndDisjointTypedKeys(): void
    {
        // GIVEN: One dictionary and the empty identity / 辞書と空の単位元です。
        $pairs = [[1, null], ['1', false]];
        $dict = Dict::fromPairs(pairs: $pairs);
        $empty = Dict::empty();

        // WHEN / THEN: Both empty sides and self preserve the same pairs / 空の左右と自己結合で組の内容を保持します。
        self::assertSame($pairs, $dict->merge(other: $empty)->toPairs());
        self::assertSame($pairs, $empty->merge(other: $dict)->toPairs());
        self::assertSame($pairs, $dict->merge(other: $dict)->toPairs());
        self::assertSame([], $empty->merge(other: $empty)->toPairs());
        self::assertSame(
            [[1, null], ['1', false]],
            Dict::fromPairs(pairs: [[1, null]])->merge(other: Dict::fromPairs(pairs: [['1', false]]))->toPairs(),
        );
    }
}
