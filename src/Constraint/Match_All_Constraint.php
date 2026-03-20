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

/**
 * Defines the absence of a constraint.
 *
 * This constraint matches everything.
 */
class Match_All_Constraint implements Constraint_Interface
{
    /** @var string|null */
    protected $pretty_string;
    /**
     * @return bool
     */
    public function matches(Constraint_Interface $provider)
    {
        return true;
    }
    /**
     * {@inheritDoc}
     */
    public function compile($other_operator)
    {
        return 'true';
    }
    /**
     * {@inheritDoc}
     */
    public function set_pretty_string($pretty_string)
    {
        $this->pretty_string = $pretty_string;
    }
    /**
     * {@inheritDoc}
     */
    public function get_pretty_string()
    {
        if ($this->pretty_string) {
            return $this->pretty_string;
        }
        return (string) $this;
    }
    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return '*';
    }
    /**
     * {@inheritDoc}
     */
    public function get_upper_bound()
    {
        return Bound::positive_infinity();
    }
    /**
     * {@inheritDoc}
     */
    public function get_lower_bound()
    {
        return Bound::zero();
    }
}