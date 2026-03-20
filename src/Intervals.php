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
use Composer\Semver\Constraint\Match_All_Constraint;
use Composer\Semver\Constraint\Match_None_Constraint;
use Composer\Semver\Constraint\Multi_Constraint;
/**
 * Helper class generating intervals from constraints
 *
 * This contains utilities for:
 *
 *  - compacting an existing constraint which can be used to combine several into one
 * by creating a MultiConstraint out of the many constraints you have.
 *
 *  - checking whether one subset is a subset of another.
 *
 * Note: You should call clear to free memoization memory  usage when you are done using this class
 */
class Intervals
{
    /**
     * @phpstan-var array<string, array{'numeric': Interval[], 'branches': array{'names': string[], 'exclude': bool}}>
     */
    private static $intervals_cache = [];
    /**
     * @phpstan-var array<string, int>
     */
    private static $op_sort_order = ['>=' => -3, '<' => -2, '>' => 2, '<=' => 3];
    /**
     * Clears the memoization cache once you are done
     *
     * @return void
     */
    public static function clear()
    {
        self::$intervals_cache = [];
    }
    /**
     * Checks whether $candidate is a subset of $constraint
     *
     * @return bool
     */
    public static function is_subset_of(Constraint_Interface $candidate, Constraint_Interface $constraint)
    {
        if ($constraint instanceof Match_All_Constraint) {
            return true;
        }
        if ($candidate instanceof Match_None_Constraint || $constraint instanceof Match_None_Constraint) {
            return false;
        }
        $intersection_intervals = self::get(new Multi_Constraint([$candidate, $constraint], true));
        $candidate_intervals = self::get($candidate);
        if (\count($intersection_intervals['numeric']) !== \count($candidate_intervals['numeric'])) {
            return false;
        }
        foreach ($intersection_intervals['numeric'] as $index => $interval) {
            if (!isset($candidate_intervals['numeric'][$index])) {
                return false;
            }
            if ((string) $candidate_intervals['numeric'][$index]->get_start() !== (string) $interval->get_start()) {
                return false;
            }
            if ((string) $candidate_intervals['numeric'][$index]->get_end() !== (string) $interval->get_end()) {
                return false;
            }
        }
        if ($intersection_intervals['branches']['exclude'] !== $candidate_intervals['branches']['exclude']) {
            return false;
        }
        if (\count($intersection_intervals['branches']['names']) !== \count($candidate_intervals['branches']['names'])) {
            return false;
        }
        foreach ($intersection_intervals['branches']['names'] as $index => $name) {
            if ($name !== $candidate_intervals['branches']['names'][$index]) {
                return false;
            }
        }
        return true;
    }
    /**
     * Checks whether $a and $b have any intersection, equivalent to $a->matches($b)
     *
     * @return bool
     */
    public static function have_intersections(Constraint_Interface $a, Constraint_Interface $b)
    {
        if ($a instanceof Match_All_Constraint || $b instanceof Match_All_Constraint) {
            return true;
        }
        if ($a instanceof Match_None_Constraint || $b instanceof Match_None_Constraint) {
            return false;
        }
        $intersection_intervals = self::generate_intervals(new Multi_Constraint([$a, $b], true), true);
        return \count($intersection_intervals['numeric']) > 0 || $intersection_intervals['branches']['exclude'] || \count($intersection_intervals['branches']['names']) > 0;
    }
    /**
     * Attempts to optimize a MultiConstraint
     *
     * When merging MultiConstraints together they can get very large, this will
     * compact it by looking at the real intervals covered by all the constraints
     * and then creates a new constraint containing only the smallest amount of rules
     * to match the same intervals.
     *
     * @return ConstraintInterface
     */
    public static function compact_constraint(Constraint_Interface $constraint)
    {
        if (!$constraint instanceof Multi_Constraint) {
            return $constraint;
        }
        $intervals = self::generate_intervals($constraint);
        $constraints = [];
        $has_numeric_match_all = false;
        if (\count($intervals['numeric']) === 1 && (string) $intervals['numeric'][0]->get_start() === (string) Interval::from_zero() && (string) $intervals['numeric'][0]->get_end() === (string) Interval::until_positive_infinity()) {
            $constraints[] = $intervals['numeric'][0]->get_start();
            $has_numeric_match_all = true;
        } else {
            $un_equal_constraints = [];
            for ($i = 0, $count = \count($intervals['numeric']); $i < $count; $i++) {
                $interval = $intervals['numeric'][$i];
                // if current interval ends with < N and next interval begins with > N we can swap this out for != N
                // but this needs to happen as a conjunctive expression together with the start of the current interval
                // and end of next interval, so [>=M, <N] || [>N, <P] => [>=M, !=N, <P] but M/P can be skipped if
                // they are zero/+inf
                if ($interval->get_end()->get_operator() === '<' && $i + 1 < $count) {
                    $next_interval = $intervals['numeric'][$i + 1];
                    if ($interval->get_end()->get_version() === $next_interval->get_start()->get_version() && $next_interval->get_start()->get_operator() === '>') {
                        // only add a start if we didn't already do so, can be skipped if we're looking at second
                        // interval in [>=M, <N] || [>N, <P] || [>P, <Q] where unEqualConstraints currently contains
                        // [>=M, !=N] already and we only want to add !=P right now
                        if (\count($un_equal_constraints) === 0 && (string) $interval->get_start() !== (string) Interval::from_zero()) {
                            $un_equal_constraints[] = $interval->get_start();
                        }
                        $un_equal_constraints[] = new Constraint('!=', $interval->get_end()->get_version());
                        continue;
                    }
                }
                if (\count($un_equal_constraints) > 0) {
                    // this is where the end of the following interval of a != constraint is added as explained above
                    if ((string) $interval->get_end() !== (string) Interval::until_positive_infinity()) {
                        $un_equal_constraints[] = $interval->get_end();
                    }
                    // count is 1 if entire constraint is just one != expression
                    if (\count($un_equal_constraints) > 1) {
                        $constraints[] = new Multi_Constraint($un_equal_constraints, true);
                    } else {
                        $constraints[] = $un_equal_constraints[0];
                    }
                    $un_equal_constraints = [];
                    continue;
                }
                // convert back >= x - <= x intervals to == x
                if ($interval->get_start()->get_version() === $interval->get_end()->get_version() && $interval->get_start()->get_operator() === '>=' && $interval->get_end()->get_operator() === '<=') {
                    $constraints[] = new Constraint('==', $interval->get_start()->get_version());
                    continue;
                }
                if ((string) $interval->get_start() === (string) Interval::from_zero()) {
                    $constraints[] = $interval->get_end();
                } elseif ((string) $interval->get_end() === (string) Interval::until_positive_infinity()) {
                    $constraints[] = $interval->get_start();
                } else {
                    $constraints[] = new Multi_Constraint([$interval->get_start(), $interval->get_end()], true);
                }
            }
        }
        $dev_constraints = [];
        if (0 === \count($intervals['branches']['names'])) {
            if ($intervals['branches']['exclude']) {
                if ($has_numeric_match_all) {
                    return new Match_All_Constraint();
                }
                // otherwise constraint should contain a != operator and already cover this
            }
        } else {
            foreach ($intervals['branches']['names'] as $branch_name) {
                if ($intervals['branches']['exclude']) {
                    $dev_constraints[] = new Constraint('!=', $branch_name);
                } else {
                    $dev_constraints[] = new Constraint('==', $branch_name);
                }
            }
            // excluded branches, e.g. != dev-foo are conjunctive with the interval, so
            // > 2.0 != dev-foo must return a conjunctive constraint
            if ($intervals['branches']['exclude']) {
                if (\count($constraints) > 1) {
                    return new Multi_Constraint(array_merge([new Multi_Constraint($constraints, false)], $dev_constraints), true);
                }
                if (\count($constraints) === 1 && (string) $constraints[0] === (string) Interval::from_zero()) {
                    if (\count($dev_constraints) > 1) {
                        return new Multi_Constraint($dev_constraints, true);
                    }
                    return $dev_constraints[0];
                }
                return new Multi_Constraint(array_merge($constraints, $dev_constraints), true);
            }
            // otherwise devConstraints contains a list of == operators for branches which are disjunctive with the
            // rest of the constraint
            $constraints = array_merge($constraints, $dev_constraints);
        }
        if (\count($constraints) > 1) {
            return new Multi_Constraint($constraints, false);
        }
        if (\count($constraints) === 1) {
            return $constraints[0];
        }
        return new Match_None_Constraint();
    }
    /**
     * Creates an array of numeric intervals and branch constraints representing a given constraint
     *
     * if the returned numeric array is empty it means the constraint matches nothing in the numeric range (0 - +inf)
     * if the returned branches array is empty it means no dev-* versions are matched
     * if a constraint matches all possible dev-* versions, branches will contain Interval::anyDev()
     *
     * @return array
     * @phpstan-return array{'numeric': Interval[], 'branches': array{'names': string[], 'exclude': bool}}
     */
    public static function get(Constraint_Interface $constraint)
    {
        $key = (string) $constraint;
        if (!isset(self::$intervals_cache[$key])) {
            self::$intervals_cache[$key] = self::generate_intervals($constraint);
        }
        return self::$intervals_cache[$key];
    }
    /**
     * @param bool $stopOnFirstValidInterval
     *
     * @phpstan-return array{'numeric': Interval[], 'branches': array{'names': string[], 'exclude': bool}}
     */
    private static function generate_intervals(Constraint_Interface $constraint, $stop_on_first_valid_interval = false)
    {
        if ($constraint instanceof Match_All_Constraint) {
            return ['numeric' => [new Interval(Interval::from_zero(), Interval::until_positive_infinity())], 'branches' => Interval::any_dev()];
        }
        if ($constraint instanceof Match_None_Constraint) {
            return ['numeric' => [], 'branches' => ['names' => [], 'exclude' => false]];
        }
        if ($constraint instanceof Constraint) {
            return self::generate_single_constraint_intervals($constraint);
        }
        if (!$constraint instanceof Multi_Constraint) {
            throw new \UnexpectedValueException('The constraint passed in should be an MatchAllConstraint, Constraint or MultiConstraint instance, got ' . \get_class($constraint) . '.');
        }
        $constraints = $constraint->get_constraints();
        $numeric_groups = [];
        $constraint_branches = [];
        foreach ($constraints as $c) {
            $res = self::get($c);
            $numeric_groups[] = $res['numeric'];
            $constraint_branches[] = $res['branches'];
        }
        if ($constraint->is_disjunctive()) {
            $branches = Interval::no_dev();
            foreach ($constraint_branches as $b) {
                if ($b['exclude']) {
                    if ($branches['exclude']) {
                        // disjunctive constraint, so only exclude what's excluded in all constraints
                        // !=a,!=b || !=b,!=c => !=b
                        $branches['names'] = array_intersect($branches['names'], $b['names']);
                    } else {
                        // disjunctive constraint so exclude all names which are not explicitly included in the alternative
                        // (==b || ==c) || !=a,!=b => !=a
                        $branches['exclude'] = true;
                        $branches['names'] = array_diff($b['names'], $branches['names']);
                    }
                } else if ($branches['exclude']) {
                    // disjunctive constraint so exclude all names which are not explicitly included in the alternative
                    // !=a,!=b || (==b || ==c) => !=a
                    $branches['names'] = array_diff($branches['names'], $b['names']);
                } else {
                    // disjunctive constraint, so just add all the other branches
                    // (==a || ==b) || ==c => ==a || ==b || ==c
                    $branches['names'] = array_merge($branches['names'], $b['names']);
                }
            }
        } else {
            $branches = Interval::any_dev();
            foreach ($constraint_branches as $b) {
                if ($b['exclude']) {
                    if ($branches['exclude']) {
                        // conjunctive, so just add all branch names to be excluded
                        // !=a && !=b => !=a,!=b
                        $branches['names'] = array_merge($branches['names'], $b['names']);
                    } else {
                        // conjunctive, so only keep included names which are not excluded
                        // (==a||==c) && !=a,!=b => ==c
                        $branches['names'] = array_diff($branches['names'], $b['names']);
                    }
                } else if ($branches['exclude']) {
                    // conjunctive, so only keep included names which are not excluded
                    // !=a,!=b && (==a||==c) => ==c
                    $branches['names'] = array_diff($b['names'], $branches['names']);
                    $branches['exclude'] = false;
                } else {
                    // conjunctive, so only keep names that are included in both
                    // (==a||==b) && (==a||==c) => ==a
                    $branches['names'] = array_intersect($branches['names'], $b['names']);
                }
            }
        }
        $branches['names'] = array_unique($branches['names']);
        if (\count($numeric_groups) === 1) {
            return ['numeric' => $numeric_groups[0], 'branches' => $branches];
        }
        $borders = [];
        foreach ($numeric_groups as $group) {
            foreach ($group as $interval) {
                $borders[] = ['version' => $interval->get_start()->get_version(), 'operator' => $interval->get_start()->get_operator(), 'side' => 'start'];
                $borders[] = ['version' => $interval->get_end()->get_version(), 'operator' => $interval->get_end()->get_operator(), 'side' => 'end'];
            }
        }
        $op_sort_order = self::$op_sort_order;
        usort($borders, function (array $a, array $b) use ($op_sort_order) {
            $order = version_compare($a['version'], $b['version']);
            if ($order === 0) {
                return $op_sort_order[$a['operator']] - $op_sort_order[$b['operator']];
            }
            return $order;
        });
        $active_intervals = 0;
        $intervals = [];
        $index = 0;
        $activation_threshold = $constraint->is_conjunctive() ? \count($numeric_groups) : 1;
        $start = null;
        foreach ($borders as $border) {
            if ($border['side'] === 'start') {
                $active_intervals++;
            } else {
                $active_intervals--;
            }
            if (!$start && $active_intervals >= $activation_threshold) {
                $start = new Constraint($border['operator'], $border['version']);
            } elseif ($start && $active_intervals < $activation_threshold) {
                // filter out invalid intervals like > x - <= x, or >= x - < x
                if (version_compare($start->get_version(), $border['version'], '=') && ($start->get_operator() === '>' && $border['operator'] === '<=' || $start->get_operator() === '>=' && $border['operator'] === '<')) {
                    unset($intervals[$index]);
                } else {
                    $intervals[$index] = new Interval($start, new Constraint($border['operator'], $border['version']));
                    $index++;
                    if ($stop_on_first_valid_interval) {
                        break;
                    }
                }
                $start = null;
            }
        }
        return ['numeric' => $intervals, 'branches' => $branches];
    }
    /**
     * @phpstan-return array{'numeric': Interval[], 'branches': array{'names': string[], 'exclude': bool}}
     */
    private static function generate_single_constraint_intervals(Constraint $constraint)
    {
        $op = $constraint->get_operator();
        // handle branch constraints first
        if (strpos($constraint->get_version(), 'dev-') === 0) {
            $intervals = [];
            $branches = ['names' => [], 'exclude' => false];
            // != dev-foo means any numeric version may match, we treat >/< like != they are not really defined for branches
            if ($op === '!=') {
                $intervals[] = new Interval(Interval::from_zero(), Interval::until_positive_infinity());
                $branches = ['names' => [$constraint->get_version()], 'exclude' => true];
            } elseif ($op === '==') {
                $branches['names'][] = $constraint->get_version();
            }
            return ['numeric' => $intervals, 'branches' => $branches];
        }
        if ($op[0] === '>') {
            // > & >=
            return ['numeric' => [new Interval($constraint, Interval::until_positive_infinity())], 'branches' => Interval::no_dev()];
        }
        if ($op[0] === '<') {
            // < & <=
            return ['numeric' => [new Interval(Interval::from_zero(), $constraint)], 'branches' => Interval::no_dev()];
        }
        if ($op === '!=') {
            // convert !=x to intervals of 0 - <x && >x - +inf + dev*
            return ['numeric' => [new Interval(Interval::from_zero(), new Constraint('<', $constraint->get_version())), new Interval(new Constraint('>', $constraint->get_version()), Interval::until_positive_infinity())], 'branches' => Interval::any_dev()];
        }
        // convert ==x to an interval of >=x - <=x
        return ['numeric' => [new Interval(new Constraint('>=', $constraint->get_version()), new Constraint('<=', $constraint->get_version()))], 'branches' => Interval::no_dev()];
    }
}