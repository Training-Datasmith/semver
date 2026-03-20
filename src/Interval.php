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
class Interval
{
    /** @var Constraint */
    private $start;
    /** @var Constraint */
    private $end;
    public function __construct(Constraint $start, Constraint $end)
    {
        $this->start = $start;
        $this->end = $end;
    }
    /**
     * @return Constraint
     */
    public function get_start()
    {
        return $this->start;
    }
    /**
     * @return Constraint
     */
    public function get_end()
    {
        return $this->end;
    }
    /**
     * @return Constraint
     */
    public static function from_zero()
    {
        static $zero;
        if (null === $zero) {
            $zero = new Constraint('>=', '0.0.0.0-dev');
        }
        return $zero;
    }
    /**
     * @return Constraint
     */
    public static function until_positive_infinity()
    {
        static $positive_infinity;
        if (null === $positive_infinity) {
            $positive_infinity = new Constraint('<', PHP_INT_MAX . '.0.0.0');
        }
        return $positive_infinity;
    }
    /**
     * @return self
     */
    public static function any()
    {
        return new self(self::from_zero(), self::until_positive_infinity());
    }
    /**
     * @return array{'names': string[], 'exclude': bool}
     */
    public static function any_dev()
    {
        // any == exclude nothing
        return ['names' => [], 'exclude' => true];
    }
    /**
     * @return array{'names': string[], 'exclude': bool}
     */
    public static function no_dev()
    {
        // nothing == no names included
        return ['names' => [], 'exclude' => false];
    }
}