<?php

declare(strict_types=1);

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
use Composer\Semver\Constraint\MatchNoneConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use PHPUnit\Framework\TestCase;

class IntervalsTest extends TestCase
{
    public const INTERVAL_ANY = '*/dev*';
    public const INTERVAL_ANY_NODEV = '*';
    public const INTERVAL_NONE = '';

    public const COMPACT_NONE = '';

    /**
     * @dataProvider compactProvider
     * @param string $expected
     * @param array<string> $toCompact
     * @param bool $conjunctive
     */
    public function testCompactConstraint($expected, $toCompact, $conjunctive)
    {
        $parser = new VersionParser();

        $parts = [];
        foreach ($toCompact as $part) {
            $parts[] = $parser->parseConstraints($part);
        }

        if ($expected === self::COMPACT_NONE) {
            $expected = new MatchNoneConstraint();
        } else {
            $expected = $parser->parseConstraints($expected);
        }

        $new = Intervals::compactConstraint(new MultiConstraint($parts, $conjunctive));
        $this->assertSame((string) $expected, (string) $new);
    }

    public static function compactProvider()
    {
        return [
            'simple disjunctive multi' => [
                '1.0 - 1.2 || ^1.5',
                ['1.0 - 1.2 || ^1.5', '1.8 - 1.9 || ^1.12'],
                false,
            ],
            'simple conjunctive multi' => [
                '1.8 - 1.9 || ^1.12',
                ['1.0 - 1.2 || ^1.5', '1.8 - 1.9 || ^1.12'],
                true,
            ],
            'dev constraints propagate, disjunctive' => [
                '1.8 - 1.9 || ^1.12 || dev-master || dev-foo',
                ['1.8 - 1.9 || ^1.12', 'dev-master', 'dev-foo'],
                false,
            ],
            'dev constraints + numeric constraint, conjunctive results in match-none' => [
                self::COMPACT_NONE,
                ['1.8 - 1.9 || ^1.12', 'dev-master', 'dev-foo'],
                true,
            ],
            'conflicting numeric constraint, conjunctive results in match-none' => [
                self::COMPACT_NONE,
                ['1.0', '2.0'],
                true,
            ],
            'simple disjunctive results in same output' => [
                '1.0 || 2.0',
                ['1.0', '2.0'],
                false,
            ],
            'simple conjunctive results in same output' => [
                '!= 1.2, != 1.6',
                ['!= 1.2', '!= 1.6'],
                true,
            ],
            'simple conjunctive results in same output/2' => [
                '!= 1.0, != 2.0',
                ['!= 1.0', '!= 2.0'],
                true,
            ],
            'switches to conjunctive if more than != x is present' => [
                '>1.5, != 2.0',
                ['!= 2.0', '> 1.5'],
                true,
            ],
            'complex conjunctive with dev' => [
                '!= 1.0, != 2.0',
                ['!= 1.0', '!= 2.0'],
                true,
            ],
            'simple disjunctive with negation' => [
                '!= 1.0',
                ['!= 1.0', '!= 1.0'],
                false,
            ],
            'disjunctive with complex negation' => [
                '*',
                ['!= 1.0', '!= 1.0', '!= dev-foo', '1.0.5.*'],
                false,
            ],
            'conjunctive with complex negation' => [
                '1.0.5.*',
                ['!= 1.0', '!= 1.0', '!= dev-foo', '1.0.5.*'],
                true,
            ],
            'conjunctive with complex negation/2' => [
                '>= 1.0-dev, != 1.2-stable, <2',
                ['!= 1.2', '!= dev-foo', '!= dev-bar', '1.*'],
                true,
            ],
            'conjunctive with complex negation/3' => [
                '!= 1.2, != dev-foo, != dev-bar',
                ['!= 1.2', '!= dev-foo', '!= dev-bar'],
                true,
            ],
            'disjunctive with complex negation/3' => [
                '*',
                ['!= 1.2', '!= dev-foo', '!= dev-bar'],
                false,
            ],
            'conjunctive with complex negation/4' => [
                '== dev-foo',
                ['!= 1.2', '== dev-foo', '!= dev-bar'],
                true,
            ],
            'disjunctive with complex negation and dev ==' => [
                '*',
                ['!= 1.0', '!= 1.0', '!= dev-foo', '1.0.5.*', '== dev-bla'],
                false,
            ],
            'conjunctive with complex negation and dev ==' => [
                'dev-bla',
                ['!= 1.0', '!= 1.0', '!= dev-foo', '== dev-bla'],
                true,
            ],
            'complex conjunctive which can not match anything' => [
                self::COMPACT_NONE,
                ['!= 1.0', '!= 1.0', '!= dev-foo', '1.0.5.*', '== dev-bla'],
                true,
            ],
            'conjunctive with more than one dev negation' => [
                '!= dev-master, != dev-foo',
                ['!= dev-master', '!= dev-foo'],
                true,
            ],
            'conjunctive with mix of devs' => [
                '== dev-foo',
                ['!= dev-master', '== dev-foo'],
                true,
            ],
            'disjunctive with mix of devs' => [
                '!= dev-master',
                ['!= dev-master', '== dev-foo'],
                false,
            ],
            'conjunctive with more than one dev negation, and numeric constraint' => [
                '> 5',
                ['!= dev-master', '!= dev-foo', '> 5'],
                true,
            ],
            'conjunctive with more than one of the same dev negation' => [
                '!= dev-foo',
                ['!= dev-foo', '!= dev-foo'],
                true,
            ],
            'switches to conjunctive when excluding versions and complex' => [
                '!= 3-stable, <5 || >=6, <9',
                ['!= 3, <5', '>=6, <9'],
                false,
            ],
            'conjunctive with multiple numeric negations and a disjunctive exact match for dev versions' => [
                '== dev-foo || == dev-bar',
                ['!= 1.0', '!= 2.0', '==dev-foo || ==dev-bar'],
                true,
            ],
        ];
    }

    /**
     * @dataProvider intervalsProvider
     * @param array<mixed>|self::INTERVAL_* $expected
     * @param string $constraint
     */
    public function testGetIntervals($expected, $constraint)
    {
        if (is_string($constraint)) {
            $parser = new VersionParser();
            $constraint = $parser->parseConstraints($constraint);
        }

        $result = Intervals::get($constraint);
        if (is_array($result)) {
            array_walk_recursive($result, function (&$c) {
                if ($c instanceof Interval) {
                    $c = ['start' => (string) $c->getStart(), 'end' => (string) $c->getEnd()];
                }
            });
        }

        if ($expected === self::INTERVAL_ANY) {
            $expected = ['numeric' => [
                [
                    'start' => '>= 0.0.0.0-dev',
                    'end' => '< '.PHP_INT_MAX.'.0.0.0',
                ],
            ], 'branches' => Interval::anyDev()];
        }

        if ($expected === self::INTERVAL_ANY_NODEV) {
            $expected = ['numeric' => [
                [
                    'start' => '>= 0.0.0.0-dev',
                    'end' => '< '.PHP_INT_MAX.'.0.0.0',
                ],
            ], 'branches' => Interval::noDev()];
        }

        if ($expected === self::INTERVAL_NONE) {
            $expected = ['numeric' => [], 'branches' => Interval::noDev()];
        }

        $this->assertSame($expected, $result);
    }

    public static function intervalsProvider()
    {
        return [
            'simple case' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^1.0',
            ],
            'simple case/2' => [
                ['numeric' => [
                    [
                        'start' => '> 1.0.0.0',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '> 1.0',
            ],
            'intervals should be sorted' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.9.0.0-dev',
                        'end' => '< 1.0.0.0-dev',
                    ],
                    [
                        'start' => '>= 1.2.3.0',
                        'end' => '<= 1.2.3.0',
                    ],
                    [
                        'start' => '>= 1.3.4.0',
                        'end' => '<= 1.3.4.0',
                    ],
                    [
                        'start' => '> 2.3.0.0',
                        'end' => '< 2.5.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '1.3.4 || 1.2.3 || >2.3,<2.5 || <1,>=0.9',
            ],
            'intervals should be sorted and consecutive ones merged' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                    [
                        'start' => '>= 3.0.0.0-dev',
                        'end' => '< 5.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^4.0 || ^1.0 || ^3.0',
            ],
            'consecutive intervals should be merged even if one has no end' => [
                ['numeric' => [
                    [
                        'start' => '>= 4.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '^4.0 || >= 5',
            ],
            'consecutive intervals should be merged even if one has no start' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< 6.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '>= 5,< 6 || < 5',
            ],
            'consecutive intervals representing everything should become *' => [
                self::INTERVAL_ANY_NODEV,
                '>= 5 || < 5',
            ],
            'intervals should be sorted and overlapping ones merged' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.1.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                    [
                        'start' => '>= 3.0.0.0-dev',
                        'end' => '< 5.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^4.0 || ^1.1 || ^3.0 || ^1.2',
            ],
            'intervals should be sorted and overlapping ones merged/2' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 1.5.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '1.2 - 1.4 || 1.0 - 1.3',
            ],
            'overlapping intervals should be merged even if the last has no end' => [
                ['numeric' => [
                    [
                        'start' => '>= 4.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '^4.0 || >= 4.5',
            ],
            'overlapping intervals should be merged even if the first has no start' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< 6.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '>= 5,< 6 || < 5.3',
            ],
            'overlapping intervals representing everything should become *' => [
                self::INTERVAL_ANY_NODEV,
                '>= 5 || <= 5',
            ],
            'equal intervals should be merged' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^1.0 || ^1.0',
            ],
            'weird input order should still be a good result' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '< 2.0 || < 1.2',
            ],
            'weird input order should still be a good result, matches everything' => [
                self::INTERVAL_ANY_NODEV,
                '< 2.0 || >= 1',
            ],
            'weird input order should still be a good result, conjunctive' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '< 2.0, >= 1',
            ],
            'conjunctive constraints result in no interval if conflicting' => [
                self::INTERVAL_NONE,
                '^1.0, ^2.0',
            ],
            'conjunctive constraints result in no interval if conflicting/2' => [
                self::INTERVAL_NONE,
                '^1.0, ^3.0',
            ],
            'conjunctive constraints result in no interval if conflicting/3' => [
                self::INTERVAL_NONE,
                '== 1.0, != 1.0',
            ],
            'conjunctive constraints result in no interval if conflicting/4' => [
                self::INTERVAL_NONE,
                '> 1.0, dev-master',
            ],
            'conjunctive constraints result in no branches interval if numeric is provided' => [
                ['numeric' => [
                    [
                        'start' => '> 5.0.0.0',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '!= dev-master, != dev-foo, > 5',
            ],
            'conjunctive constraints result in no branches interval if numeric is provided, even if one matches dev*' => [
                ['numeric' => [
                    [
                        'start' => '> 5.0.0.0',
                        'end' => '< 6.0.0.0',
                    ],
                    [
                        'start' => '> 6.0.0.0',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '!= 6, > 5',
            ],
            'disjunctive constraints keeps branch intervals if numeric is provided' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-master', 'dev-foo'], 'exclude' => true]],
                '!= dev-master, != dev-foo || > 5',
            ],
            'conjunctive constraints should be intersected' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.2.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^1.0, ^1.2',
            ],
            'conjunctive constraints should be intersected/2' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.5.0.0-dev',
                        'end' => '< 1.7.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^1.0, ^1.2, 1.4 - 1.8, 1.5 - 1.6, 1.5 - 2',
            ],
            'conjunctive constraints should be intersected, not flattened by version parser' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.5.0.0-dev',
                        'end' => '< 1.7.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                new MultiConstraint([
                    new MultiConstraint([
                        new Constraint('>=', '1.0.0.0-dev'),
                        new Constraint('<', '2.0.0.0-dev'),
                    ], true),
                    new MultiConstraint([
                        new Constraint('>=', '1.2.0.0-dev'),
                        new Constraint('<', '2.0.0.0-dev'),
                    ], true),
                    new MultiConstraint([
                        new Constraint('>=', '1.4.0.0-dev'),
                        new Constraint('<', '1.9.0.0-dev'),
                    ], true),
                    new MultiConstraint([
                        new Constraint('>=', '1.5.0.0-dev'),
                        new Constraint('<', '1.7.0.0-dev'),
                    ], true),
                    new MultiConstraint([
                        new Constraint('>=', '1.5.0.0-dev'),
                        new Constraint('<', '3.0.0.0-dev'),
                    ], true),
                ], true),
            ],
            'conjunctive constraints with disjunctive subcomponents should be intersected, not flattened by version parser' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.8.0.0-dev',
                        'end' => '< 1.10.0.0-dev',
                    ],
                    [
                        'start' => '>= 1.12.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                new MultiConstraint([
                    new MultiConstraint([ // 1.0 - 1.2 || ^1.5
                        new MultiConstraint([
                            new Constraint('>=', '1.0.0.0-dev'),
                            new Constraint('<', '1.3.0.0-dev'),
                        ], true),
                        new MultiConstraint([
                            new Constraint('>=', '1.5.0.0-dev'),
                            new Constraint('<', '2.0.0.0-dev'),
                        ], true),
                    ], false),
                    new MultiConstraint([ // 1.8 - 1.9 || ^1.12
                        new MultiConstraint([
                            new Constraint('>=', '1.8.0.0-dev'),
                            new Constraint('<', '1.10.0.0-dev'),
                        ], true),
                        new MultiConstraint([
                            new Constraint('>=', '1.12.0.0-dev'),
                            new Constraint('<', '2.0.0.0-dev'),
                        ], true),
                    ], false),
                ], true),
            ],
            'conjunctive constraints with equal constraints' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.3.2.0-dev',
                        'end' => '<= 1.3.2.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                new MultiConstraint([
                    new MultiConstraint([
                        new Constraint('==', '1.3.1.0-dev'),
                        new Constraint('==', '1.3.2.0-dev'),
                        new Constraint('==', '1.3.3.0-dev'),
                    ], false),
                    new Constraint('==', '1.3.2.0-dev'),
                ], true),
            ],
            'conjunctive constraints simple' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.5.0.0-dev',
                        'end' => '< 3.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '1.5 - 2',
            ],
            'conjunctive constraints with dev exclusions' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 1.2.3.0',
                    ],
                    [
                        'start' => '> 1.2.3.0',
                        'end' => '< 1.4.5.0',
                    ],
                    [
                        'start' => '> 1.4.5.0',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '!= 1.4.5, ^1.0, != 1.2.3, != 2.3, != dev-foo, != dev-master',
            ],
            'conjunctive constraints with dev exact versions suppresses the number scope matches' => [
                self::INTERVAL_NONE,
                '!= 1.4.5, ^1.0, != 1.2.3, != 2.3, == dev-foo, == dev-foo',
            ],
            'conjunctive constraints with dev exact versions suppresses the number scope matches, but keeps dev- match if number constraints allowed dev*' => [
                ['numeric' => [
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => false]],
                '!= 1.2.3, != 2.3, == dev-foo, == dev-foo',
            ],
            'disjunctive constraints with exclusions in dev constraints makes the number scope match *' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => true]],
                '^1.0 || != dev-foo',
            ],
            'disjunctive constraints with exclusions in dev constraints makes number scope match *' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => true]],
                '^1.0 || != dev-foo',
            ],
            'disjunctive constraints with exclusions, if matches * in number scope and dev scope, then * is returned' => [
                self::INTERVAL_ANY,
                '!= 1.4.5 || ^1.0 || != dev-foo || != dev-master || == dev-master',
            ],
            'disjunctive constraints with exclusions, if dev constraints match *, then * is returned for everything' => [
                self::INTERVAL_ANY,
                '^1.0 || != dev-master || == dev-master',
            ],
            'disjunctive constraints with exclusions, if dev constraints match * except in dev scope, then * is returned for number scope' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => true]],
                '^1.0 || != dev-foo || == dev-master',
            ],
            'disjunctive constraints with exact dev matches returns number scope as it should and unique dev constraints' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => ['names' => ['dev-foo', 'dev-master'], 'exclude' => false]],
                '^1.0 || == dev-foo || == dev-master || == dev-master',
            ],
            'conjunctive constraints with exact versions' => [
                self::INTERVAL_NONE,
                'dev-master, ^1.0',
            ],
            'conjunctive constraints with exact versions, dev only, diff version should result in no interval and no constraints' => [
                self::INTERVAL_NONE,
                'dev-master, dev-foo',
            ],
            'conjunctive constraints with exact versions, dev only, same version should pass through' => [
                ['numeric' => [], 'branches' => ['names' => ['dev-master'], 'exclude' => false]],
                'dev-master, dev-master',
            ],
            'conjunctive constraints with same dev exclusion, should result in * with dev exclusion' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-master'], 'exclude' => true]],
                '!= dev-master, != dev-master',
            ],
            'conjunctive constraints with different dev exclusion, should result in * with dev exclusions' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-master', 'dev-foo'], 'exclude' => true]],
                '!= dev-master, != dev-foo',
            ],
            'disjunctive constraints with exact versions' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => ['names' => ['dev-master', 'dev-foo'], 'exclude' => false]],
                'dev-master || ^1.0 || dev-foo || dev-master',
            ],
            'conjunctive constraints with * should skip it' => [
                ['numeric' => [
                    [
                        'start' => '>= 1.0.0.0-dev',
                        'end' => '< 2.0.0.0-dev',
                    ],
                ], 'branches' => Interval::noDev()],
                '^1.0, *',
            ],
            'disjunctive constraints with * should result in *' => [
                self::INTERVAL_ANY,
                '^1.0 || *',
            ],
            'conjunctive constraints with only * should result in *' => [
                self::INTERVAL_ANY,
                '*, *',
            ],
            'conjunctive constraints equivalent of * should result in *' => [
                self::INTERVAL_ANY_NODEV,
                new MultiConstraint([new Constraint('>=', '0.0.0.0-dev'), new Constraint('<', PHP_INT_MAX.'.0.0.0')]),
            ],
            'disjunctive constraints with * and dev exclusion should not return the dev exclusion' => [
                self::INTERVAL_ANY,
                '!= dev-foo || *',
            ],
            'conjunctive constraints with various dev constraints/2' => [
                ['numeric' => [
                    [
                        'start' => '> 5.0.0.0',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '> 5, *',
            ],
            'conjunctive constraints with various dev constraints/3' => [
                ['numeric' => [
                    [
                        'start' => '> 5.0.0.0',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => Interval::noDev()],
                '!= dev-foo, > 5',
            ],
            'conjunctive constraints with various dev constraints/4' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => true]],
                '!= dev-foo, != dev-foo',
            ],
            'conjunctive constraints with various dev constraints/5' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo', 'dev-bar'], 'exclude' => true]],
                '!= dev-foo, != dev-bar',
            ],
            'conjunctive constraints with various dev constraints/6' => [
                ['numeric' => [], 'branches' => ['names' => ['dev-bar'], 'exclude' => false]],
                '!= dev-foo, == dev-bar',
            ],
            'conjunctive constraints with various dev constraints/7' => [
                self::INTERVAL_NONE,
                'dev-foo, > 5',
            ],
            'complex conjunctive which can not match anything' => [
                self::INTERVAL_NONE,
                '!= 1.0, != 1.0, != dev-foo, 1.0.5.*, == dev-bla',
            ],
            'conjunctive with more than one dev negation' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-master', 'dev-foo'], 'exclude' => true]],
                '!= dev-master, != dev-foo',
            ],
            'disjunctive constraints with various dev constraints' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo'], 'exclude' => true]],
                '!= dev-foo, != dev-bar || != dev-foo',
            ],
            'disjunctive constraints with various dev constraints/2' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-foo', 'dev-bar'], 'exclude' => true]],
                '!= dev-foo, != dev-bar || != dev-foo, != dev-bar',
            ],
            'disjunctive constraints with various dev constraints/3' => [
                self::INTERVAL_ANY,
                new MultiConstraint([new Constraint('!=', 'dev-foo'), new Constraint('!=', 'dev-bar')], false),
            ],
            'disjunctive constraints with various dev constraints/4' => [
                ['numeric' => [],
                    'branches' => ['names' => ['dev-foo', 'dev-bar'], 'exclude' => false],
                ],
                '== dev-foo || == dev-bar',
            ],
            'disjunctive constraints with various dev constraints/5' => [
                self::INTERVAL_ANY,
                '== dev-foo || != dev-foo',
            ],
            'disjunctive constraints with various dev constraints/6' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-bar'], 'exclude' => true]],
                '== dev-foo || != dev-bar',
            ],
            'disjunctive constraints with various dev constraints/7' => [
                ['numeric' => [
                    [
                        'start' => '>= 0.0.0.0-dev',
                        'end' => '< '.PHP_INT_MAX.'.0.0.0',
                    ],
                ], 'branches' => ['names' => ['dev-bar'], 'exclude' => true]],
                '== dev-foo || != dev-bar || != dev-bar',
            ],
            'disjunctive constraints with various dev constraints/8' => [
                self::INTERVAL_ANY,
                '== dev-foo || != dev-bar || != dev-foo',
            ],
            'match-none constraints result in no interval' => [
                self::INTERVAL_NONE,
                new MatchNoneConstraint(),
            ],
            'match-none constraint inside conjunctive multi results in no interval' => [
                self::INTERVAL_NONE,
                new MultiConstraint([
                    new MultiConstraint([
                        new Constraint('==', '1.3.1.0-dev'),
                        new Constraint('==', '1.3.2.0-dev'),
                        new Constraint('==', '1.3.3.0-dev'),
                    ], false),
                    new MatchNoneConstraint(),
                ], true),
            ],
        ];
    }
}
