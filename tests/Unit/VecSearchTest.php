<?php

declare(strict_types=1);

namespace Catenary\Tests\Unit;

use Catenary\Vec;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Checks short-circuit queries and immutable snapshot reuse.
 * 短絡する検索操作と不変スナップショットの再利用を検証します。
 */
final class VecSearchTest extends TestCase
{
    /**
     * Finds the first accepted value without confusing a present null with absence.
     * 存在するnullと欠損を混同せず、最初に合致した値で検索を終了します。
     *
     * @return void
     */
    public function testFindPreservesNullAndStopsAtTheFirstMatch(): void
    {
        // GIVEN: A null match appears before later values / 後続要素より前にnullの一致があります。
        $values = Vec::of('before', null, 'after');
        $visited = [];
        // WHEN: Search for null in encounter order / 出現順でnullを検索します。
        $found = $values->find(function (?string $value) use (&$visited): bool {
            $visited[] = $value;
            return $value === null;
        });
        // THEN: Null remains present and later values are untouched / nullは存在したままで後続を評価しません。
        self::assertTrue($found->isSome());
        self::assertNull($found->getOrThrow());
        self::assertSame(['before', null], $visited);
        self::assertSame(['before', null, 'after'], $values->toList());
    }

    /**
     * Stops positive and universal queries at their deciding element.
     * 存在判定と全件判定は結果が確定した要素で走査を終了します。
     *
     * @return void
     */
    public function testAnyAndEveryShortCircuitInOrder(): void
    {
        // GIVEN: The second element decides both queries / 二番目の要素で両判定が確定します。
        $values = Vec::of(1, 2, 3);
        $anyVisited = $everyVisited = [];
        // WHEN: Inspect the first match and first rejection / 最初の一致と最初の不一致を調べます。
        $any = $values->any(function (int $value) use (&$anyVisited): bool {
            $anyVisited[] = $value;
            return $value === 2;
        });
        $every = $values->every(function (int $value) use (&$everyVisited): bool {
            $everyVisited[] = $value;
            return $value < 2;
        });
        // THEN: Neither predicate sees the third element / どちらの述語も三番目を受け取りません。
        self::assertTrue($any);
        self::assertFalse($every);
        self::assertSame([1, 2], $anyVisited);
        self::assertSame([1, 2], $everyVisited);
    }

    /**
     * Returns the empty-query identities without invoking predicates.
     * 空リストでは述語を実行せず各判定の既定結果を返します。
     *
     * @return void
     */
    public function testEmptyQueriesDoNotEvaluatePredicates(): void
    {
        // GIVEN: An empty vector and a predicate that must never run / 空リストと実行禁止の述語です。
        $empty = Vec::empty();
        $predicate = fn () => throw new LogicException('Predicate must not run');
        // WHEN / THEN: Find is absent, any false and every true / 検索は欠損、存在判定はfalse、全件判定はtrueです。
        self::assertTrue($empty->find($predicate)->isNone());
        self::assertFalse($empty->any($predicate));
        self::assertTrue($empty->every($predicate));
    }

    /**
     * Inspects every element once when no earlier decision is possible.
     * 途中で結果が決まらない場合は各要素を一度だけ評価します。
     *
     * @return void
     */
    public function testCompleteQueriesEvaluateEachElementOnce(): void
    {
        // GIVEN: No match for find/any and no rejection for every / 検索と存在判定は一致せず全件判定は全て一致します。
        $values = Vec::of(1, 2, 3);
        $findVisited = $anyVisited = $everyVisited = [];
        // WHEN: Run all terminal queries / 各終端操作を実行します。
        $found = $values->find(function (int $value) use (&$findVisited): bool {
            $findVisited[] = $value;
            return $value > 3;
        });
        $any = $values->any(function (int $value) use (&$anyVisited): bool {
            $anyVisited[] = $value;
            return $value > 3;
        });
        $every = $values->every(function (int $value) use (&$everyVisited): bool {
            $everyVisited[] = $value;
            return $value <= 3;
        });
        // THEN: Absence and booleans agree after one ordered pass / 順序通り一度の走査で欠損と真偽を確定します。
        self::assertTrue($found->isNone());
        self::assertFalse($any);
        self::assertTrue($every);
        self::assertSame([1, 2, 3], $findVisited);
        self::assertSame([1, 2, 3], $anyVisited);
        self::assertSame([1, 2, 3], $everyVisited);
    }

    /**
     * Preserves callback exception identity for each short-circuit terminal.
     * 各短絡終端操作でコールバック例外の同一性を保持します。
     *
     * @return void
     */
    public function testQueryExceptionsPropagateUnchanged(): void
    {
        // GIVEN: One sentinel exception and a nonempty vector / 一つの例外と空でないリストです。
        $failure = new LogicException('Expected failure');
        $throw = fn () => throw $failure;
        $values = Vec::of(1);
        // WHEN / THEN: No query swallows or wraps the exception / 例外を握り潰したり包み直したりしません。
        $queries = [fn () => $values->find($throw), fn () => $values->any($throw), fn () => $values->every($throw)];
        foreach ($queries as $run) {
            try {
                $run();
                self::fail('The predicate exception was swallowed.');
            } catch (LogicException $caught) {
                self::assertSame($failure, $caught);
            }
        }
    }

    /**
     * Reads the last position without treating false-like values as missing.
     * false相当の値を欠損とせず末尾位置の値を取得します。
     *
     * @return void
     */
    public function testLastPreservesPresentValuesAndEmptyAbsence(): void
    {
        // GIVEN: Empty and populated vectors with false-like last elements / 空とfalse相当の末尾を持つリストです。
        self::assertTrue(Vec::empty()->last()->isNone());
        foreach ([null, false, 0, ''] as $last) {
            $values = Vec::of('first', $last);
            // WHEN: Read the final position / 最終位置を読みます。
            $found = $values->last();
            // THEN: Presence and original order remain intact / 存在と元の順序を保持します。
            self::assertTrue($found->isSome());
            self::assertSame($last, $found->getOrThrow());
            self::assertSame(['first', $last], $values->toList());
        }
    }

    /**
     * Reuses an immutable Vec while preserving detached array references.
     * 不変Vecを再利用しつつ入力配列の参照切離しを維持します。
     *
     * @return void
     */
    public function testFromIterableReusesVecWithoutWeakeningArrayIsolation(): void
    {
        // GIVEN: A snapshot created from a referenced scalar / スカラー参照から作ったスナップショットです。
        $value = 1;
        $original = Vec::fromIterable(['key' => &$value]);
        $empty = Vec::empty();
        // WHEN: Wrap snapshots again and mutate the original input / 再度構築して元入力を変更します。
        $reused = Vec::fromIterable($original);
        $value = 2;
        $exported = $reused->toList();
        $exported[0] = 3;
        // THEN: The existing Vec is reused and its structure stays unchanged / 既存Vecを再利用し構造は変化しません。
        self::assertSame($original, $reused);
        self::assertSame($empty, Vec::fromIterable($empty));
        self::assertSame([1], $reused->toList());
    }
}
