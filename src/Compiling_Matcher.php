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
use Composer\Semver\Constraint\Constraint_Interface;
/**
 * Helper class to evaluate constraint by compiling and reusing the code to evaluate
 */
class Compiling_Matcher
{
    /**
     * @var array
     * @phpstan-var array<string, callable>
     */
    private static $compiled_checker_cache = [];
    /**
     * @var array
     * @phpstan-var array<string, bool>
     */
    private static $result_cache = [];
    /** @var bool */
    private static $enabled;
    /**
     * @phpstan-var array<Constraint::OP_*, Constraint::STR_OP_*>
     */
    private static $trans_op_int = [Constraint::OP_EQ => Constraint::STR_OP_EQ, Constraint::OP_LT => Constraint::STR_OP_LT, Constraint::OP_LE => Constraint::STR_OP_LE, Constraint::OP_GT => Constraint::STR_OP_GT, Constraint::OP_GE => Constraint::STR_OP_GE, Constraint::OP_NE => Constraint::STR_OP_NE];
    /**
     * Clears the memoization cache once you are done
     *
     * @return void
     */
    public static function clear()
    {
        self::$result_cache = [];
        self::$compiled_checker_cache = [];
    }
    /**
     * Evaluates the expression: $constraint match $operator $version
     *
     * @param int                 $operator
     * @phpstan-param Constraint::OP_*  $operator
     * @param string              $version
     * @return bool
     */
    public static function match(Constraint_Interface $constraint, $operator, $version)
    {
        $result_cache_key = $operator . $constraint . ';' . $version;
        if (isset(self::$result_cache[$result_cache_key])) {
            return self::$result_cache[$result_cache_key];
        }
        if (self::$enabled === null) {
            self::$enabled = !\in_array('eval', explode(',', (string) ini_get('disable_functions')), true);
        }
        if (!self::$enabled) {
            return self::$result_cache[$result_cache_key] = $constraint->matches(new Constraint(self::$trans_op_int[$operator], $version));
        }
        $cache_key = $operator . $constraint;
        if (!isset(self::$compiled_checker_cache[$cache_key])) {
            $code = $constraint->compile($operator);
            self::$compiled_checker_cache[$cache_key] = $function = eval('return function($v, $b){return ' . $code . ';};');
        } else {
            $function = self::$compiled_checker_cache[$cache_key];
        }
        return self::$result_cache[$result_cache_key] = $function($version, strpos($version, 'dev-') === 0);
    }
}