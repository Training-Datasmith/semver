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
 * Defines a constraint.
 */
class Constraint implements Constraint_Interface
{
    /* operator integer values */
    public const OP_EQ = 0;
    public const OP_LT = 1;
    public const OP_LE = 2;
    public const OP_GT = 3;
    public const OP_GE = 4;
    public const OP_NE = 5;
    /* operator string values */
    public const STR_OP_EQ = '==';
    public const STR_OP_EQ_ALT = '=';
    public const STR_OP_LT = '<';
    public const STR_OP_LE = '<=';
    public const STR_OP_GT = '>';
    public const STR_OP_GE = '>=';
    public const STR_OP_NE = '!=';
    public const STR_OP_NE_ALT = '<>';
    /**
     * Operator to integer translation table.
     *
     * @var array
     * @phpstan-var array<self::STR_OP_*, self::OP_*>
     */
    private static $trans_op_str = ['=' => self::OP_EQ, '==' => self::OP_EQ, '<' => self::OP_LT, '<=' => self::OP_LE, '>' => self::OP_GT, '>=' => self::OP_GE, '<>' => self::OP_NE, '!=' => self::OP_NE];
    /**
     * Integer to operator translation table.
     *
     * @var array
     * @phpstan-var array<self::OP_*, self::STR_OP_*>
     */
    private static $trans_op_int = [self::OP_EQ => '==', self::OP_LT => '<', self::OP_LE => '<=', self::OP_GT => '>', self::OP_GE => '>=', self::OP_NE => '!='];
    /**
     * @var int
     * @phpstan-var self::OP_*
     */
    protected $operator;
    /** @var string */
    protected $version;
    /** @var string|null */
    protected $pretty_string;
    /** @var Bound */
    protected $lower_bound;
    /** @var Bound */
    protected $upper_bound;
    /**
     * Sets operator and version to compare with.
     *
     * @param string $operator
     * @param string $version
     *
     * @throws \InvalidArgumentException if invalid operator is given.
     *
     * @phpstan-param self::STR_OP_* $operator
     */
    public function __construct($operator, $version)
    {
        if (!isset(self::$trans_op_str[$operator])) {
            throw new \InvalidArgumentException(sprintf('Invalid operator "%s" given, expected one of: %s', $operator, implode(', ', self::get_supported_operators())));
        }
        $this->operator = self::$trans_op_str[$operator];
        $this->version = $version;
    }
    /**
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }
    /**
     * @return string
     *
     * @phpstan-return self::STR_OP_*
     */
    public function get_operator()
    {
        return self::$trans_op_int[$this->operator];
    }
    /**
     * @return bool
     */
    public function matches(Constraint_Interface $provider)
    {
        if ($provider instanceof self) {
            return $this->match_specific($provider);
        }
        // turn matching around to find a match
        return $provider->matches($this);
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
        return $this->__toString();
    }
    /**
     * Get all supported comparison operators.
     *
     * @return array
     *
     * @phpstan-return list<self::STR_OP_*>
     */
    public static function get_supported_operators()
    {
        return array_keys(self::$trans_op_str);
    }
    /**
     * @param  string $operator
     * @return int
     *
     * @phpstan-param  self::STR_OP_* $operator
     * @phpstan-return self::OP_*
     */
    public static function get_operator_constant($operator)
    {
        return self::$trans_op_str[$operator];
    }
    /**
     * @param string $a
     * @param string $b
     * @param string $operator
     * @param bool   $compareBranches
     *
     * @throws \InvalidArgumentException if invalid operator is given.
     *
     * @return bool
     *
     * @phpstan-param self::STR_OP_* $operator
     */
    public function version_compare($a, $b, $operator, $compare_branches = false)
    {
        if (!isset(self::$trans_op_str[$operator])) {
            throw new \InvalidArgumentException(sprintf('Invalid operator "%s" given, expected one of: %s', $operator, implode(', ', self::get_supported_operators())));
        }
        $a_is_branch = strpos($a, 'dev-') === 0;
        $b_is_branch = strpos($b, 'dev-') === 0;
        if ($operator === '!=' && ($a_is_branch || $b_is_branch)) {
            return $a !== $b;
        }
        if ($a_is_branch && $b_is_branch) {
            return $operator === '==' && $a === $b;
        }
        // when branches are not comparable, we make sure dev branches never match anything
        if (!$compare_branches && ($a_is_branch || $b_is_branch)) {
            return false;
        }
        return \version_compare($a, $b, $operator);
    }
    /**
     * {@inheritDoc}
     */
    public function compile($other_operator)
    {
        if (strpos($this->version, 'dev-') === 0) {
            if (self::OP_EQ === $this->operator) {
                if (self::OP_EQ === $other_operator) {
                    return sprintf('$b && $v === %s', \var_export($this->version, true));
                }
                if (self::OP_NE === $other_operator) {
                    return sprintf('!$b || $v !== %s', \var_export($this->version, true));
                }
                return 'false';
            }
            if (self::OP_NE === $this->operator) {
                if (self::OP_EQ === $other_operator) {
                    return sprintf('!$b || $v !== %s', \var_export($this->version, true));
                }
                if (self::OP_NE === $other_operator) {
                    return 'true';
                }
                return '!$b';
            }
            return 'false';
        }
        if (self::OP_EQ === $this->operator) {
            if (self::OP_EQ === $other_operator) {
                return sprintf('\version_compare($v, %s, \'==\')', \var_export($this->version, true));
            }
            if (self::OP_NE === $other_operator) {
                return sprintf('$b || \version_compare($v, %s, \'!=\')', \var_export($this->version, true));
            }
            return sprintf('!$b && \version_compare(%s, $v, \'%s\')', \var_export($this->version, true), self::$trans_op_int[$other_operator]);
        }
        if (self::OP_NE === $this->operator) {
            if (self::OP_EQ === $other_operator) {
                return sprintf('$b || (!$b && \version_compare($v, %s, \'!=\'))', \var_export($this->version, true));
            }
            if (self::OP_NE === $other_operator) {
                return 'true';
            }
            return '!$b';
        }
        if (self::OP_LT === $this->operator || self::OP_LE === $this->operator) {
            if (self::OP_LT === $other_operator || self::OP_LE === $other_operator) {
                return '!$b';
            }
        } else if (self::OP_GT === $other_operator || self::OP_GE === $other_operator) {
            return '!$b';
        }
        if (self::OP_NE === $other_operator) {
            return 'true';
        }
        $code_comparison = sprintf('\version_compare($v, %s, \'%s\')', \var_export($this->version, true), self::$trans_op_int[$this->operator]);
        if ($this->operator === self::OP_LE) {
            if ($other_operator === self::OP_GT) {
                return sprintf('!$b && \version_compare($v, %s, \'!=\') && ', \var_export($this->version, true)) . $code_comparison;
            }
        } elseif ($this->operator === self::OP_GE) {
            if ($other_operator === self::OP_LT) {
                return sprintf('!$b && \version_compare($v, %s, \'!=\') && ', \var_export($this->version, true)) . $code_comparison;
            }
        }
        return sprintf('!$b && %s', $code_comparison);
    }
    /**
     * @param bool       $compareBranches
     * @return bool
     */
    public function match_specific(Constraint $provider, $compare_branches = false)
    {
        $no_equal_op = str_replace('=', '', self::$trans_op_int[$this->operator]);
        $provider_no_equal_op = str_replace('=', '', self::$trans_op_int[$provider->operator]);
        $is_equal_op = self::OP_EQ === $this->operator;
        $is_non_equal_op = self::OP_NE === $this->operator;
        $is_provider_equal_op = self::OP_EQ === $provider->operator;
        $is_provider_non_equal_op = self::OP_NE === $provider->operator;
        // '!=' operator is match when other operator is not '==' operator or version is not match
        // these kinds of comparisons always have a solution
        if ($is_non_equal_op || $is_provider_non_equal_op) {
            if ($is_non_equal_op && !$is_provider_non_equal_op && !$is_provider_equal_op && strpos($provider->version, 'dev-') === 0) {
                return false;
            }
            if ($is_provider_non_equal_op && !$is_non_equal_op && !$is_equal_op && strpos($this->version, 'dev-') === 0) {
                return false;
            }
            if (!$is_equal_op && !$is_provider_equal_op) {
                return true;
            }
            return $this->version_compare($provider->version, $this->version, '!=', $compare_branches);
        }
        // an example for the condition is <= 2.0 & < 1.0
        // these kinds of comparisons always have a solution
        if ($this->operator !== self::OP_EQ && $no_equal_op === $provider_no_equal_op) {
            return !(strpos($this->version, 'dev-') === 0 || strpos($provider->version, 'dev-') === 0);
        }
        $version1 = $is_equal_op ? $this->version : $provider->version;
        $version2 = $is_equal_op ? $provider->version : $this->version;
        $operator = $is_equal_op ? $provider->operator : $this->operator;
        if ($this->version_compare($version1, $version2, self::$trans_op_int[$operator], $compare_branches)) {
            // special case, e.g. require >= 1.0 and provide < 1.0
            // 1.0 >= 1.0 but 1.0 is outside of the provided interval
            return !(self::$trans_op_int[$provider->operator] === $provider_no_equal_op && self::$trans_op_int[$this->operator] !== $no_equal_op && \version_compare($provider->version, $this->version, '=='));
        }
        return false;
    }
    /**
     * @return string
     */
    public function __toString()
    {
        return self::$trans_op_int[$this->operator] . ' ' . $this->version;
    }
    /**
     * {@inheritDoc}
     */
    public function get_lower_bound()
    {
        $this->extract_bounds();
        return $this->lower_bound;
    }
    /**
     * {@inheritDoc}
     */
    public function get_upper_bound()
    {
        $this->extract_bounds();
        return $this->upper_bound;
    }
    /**
     * @return void
     */
    private function extract_bounds()
    {
        if (null !== $this->lower_bound) {
            return;
        }
        // Branches
        if (strpos($this->version, 'dev-') === 0) {
            $this->lower_bound = Bound::zero();
            $this->upper_bound = Bound::positive_infinity();
            return;
        }
        switch ($this->operator) {
            case self::OP_EQ:
                $this->lower_bound = new Bound($this->version, true);
                $this->upper_bound = new Bound($this->version, true);
                break;
            case self::OP_LT:
                $this->lower_bound = Bound::zero();
                $this->upper_bound = new Bound($this->version, false);
                break;
            case self::OP_LE:
                $this->lower_bound = Bound::zero();
                $this->upper_bound = new Bound($this->version, true);
                break;
            case self::OP_GT:
                $this->lower_bound = new Bound($this->version, false);
                $this->upper_bound = Bound::positive_infinity();
                break;
            case self::OP_GE:
                $this->lower_bound = new Bound($this->version, true);
                $this->upper_bound = Bound::positive_infinity();
                break;
            case self::OP_NE:
                $this->lower_bound = Bound::zero();
                $this->upper_bound = Bound::positive_infinity();
                break;
        }
    }
}