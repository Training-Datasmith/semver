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
namespace Composer\Semver\Constraint;

class Bound
{
    /**
     * @var string
     */
    private $version;
    /**
     * @var bool
     */
    private $is_inclusive;
    /**
     * @param string $version
     * @param bool   $isInclusive
     */
    public function __construct($version, $is_inclusive)
    {
        $this->version = $version;
        $this->is_inclusive = $is_inclusive;
    }
    /**
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }
    /**
     * @return bool
     */
    public function is_inclusive()
    {
        return $this->is_inclusive;
    }
    /**
     * @return bool
     */
    public function is_zero()
    {
        return $this->get_version() === '0.0.0.0-dev' && $this->is_inclusive();
    }
    /**
     * @return bool
     */
    public function is_positive_infinity()
    {
        return $this->get_version() === PHP_INT_MAX . '.0.0.0' && !$this->is_inclusive();
    }
    /**
     * Compares a bound to another with a given operator.
     *
     * @param string $operator
     * @return bool
     */
    public function compare_to(Bound $other, $operator)
    {
        if (!\in_array($operator, ['<', '>'], true)) {
            throw new \InvalidArgumentException('Does not support any other operator other than > or <.');
        }
        // If they are the same it doesn't matter
        if ($this == $other) {
            return false;
        }
        $compare_result = version_compare($this->get_version(), $other->get_version());
        // Not the same version means we don't need to check if the bounds are inclusive or not
        if (0 !== $compare_result) {
            return ('>' === $operator ? 1 : -1) === $compare_result;
        }
        // Question we're answering here is "am I higher than $other?"
        return '>' === $operator ? $other->is_inclusive() : !$other->is_inclusive();
    }
    public function __toString()
    {
        return sprintf('%s [%s]', $this->get_version(), $this->is_inclusive() ? 'inclusive' : 'exclusive');
    }
    /**
     * @return self
     */
    public static function zero()
    {
        return new Bound('0.0.0.0-dev', true);
    }
    /**
     * @return self
     */
    public static function positive_infinity()
    {
        return new Bound(PHP_INT_MAX . '.0.0.0', false);
    }
}