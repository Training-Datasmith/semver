<?php

declare (strict_types=1);
/*
 * This file is part of composer/semver.
 *
 * (c) Composer <https://github.com/composer>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */
namespace Composer\Semver;

use Composer\Semver\Constraint\Constraint;
class Comparator
{
    /**
     * Evaluates the expression: $version1 > $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function greater_than($version1, $version2)
    {
        return self::compare($version1, '>', $version2);
    }
    /**
     * Evaluates the expression: $version1 >= $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function greater_than_or_equal_to($version1, $version2)
    {
        return self::compare($version1, '>=', $version2);
    }
    /**
     * Evaluates the expression: $version1 < $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function less_than($version1, $version2)
    {
        return self::compare($version1, '<', $version2);
    }
    /**
     * Evaluates the expression: $version1 <= $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function less_than_or_equal_to($version1, $version2)
    {
        return self::compare($version1, '<=', $version2);
    }
    /**
     * Evaluates the expression: $version1 == $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function equal_to($version1, $version2)
    {
        return self::compare($version1, '==', $version2);
    }
    /**
     * Evaluates the expression: $version1 != $version2.
     *
     * @param string $version1
     * @param string $version2
     *
     * @return bool
     */
    public static function not_equal_to($version1, $version2)
    {
        return self::compare($version1, '!=', $version2);
    }
    /**
     * Evaluates the expression: $version1 $operator $version2.
     *
     * @param string $version1
     * @param string $operator
     * @param string $version2
     *
     * @return bool
     *
     * @phpstan-param Constraint::STR_OP_*  $operator
     */
    public static function compare($version1, $operator, $version2)
    {
        $constraint = new Constraint($operator, $version2);
        return $constraint->match_specific(new Constraint('==', $version1), true);
    }
}