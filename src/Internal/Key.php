<?php

declare(strict_types=1);

namespace Taikick\Collections\Internal;

/**
 * Encodes scalar keys without PHP's numeric-string key coercion.
 * PHPによる数値文字列キーの変換を避けてスカラーキーを符号化します。
 *
 * @internal
 */
final class Key
{
    /**
     * Prevents constructing this stateless internal utility.
     * 状態を持たない内部ユーティリティの生成を防ぎます。
     */
    private function __construct()
    {
    }

    /**
     * Produces a collision-free key for integers and strings.
     * 整数と文字列の間で衝突しないキーを生成します。
     */
    public static function encode(int|string $key): string
    {
        return (is_int($key) ? 'i:' : 's:') . $key;
    }
}
