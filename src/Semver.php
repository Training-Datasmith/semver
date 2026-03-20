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
class Semver
{
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;
    /** @var VersionParser */
    private static $version_parser;
    /**
     * Determine if given version satisfies given constraints.
     *
     * @param string $version
     * @param string $constraints
     *
     * @return bool
     */
    public static function satisfies($version, $constraints)
    {
        if (null === self::$version_parser) {
            self::$version_parser = new Version_Parser();
        }
        $version_parser = self::$version_parser;
        $provider = new Constraint('==', $version_parser->normalize($version));
        $parsed_constraints = $version_parser->parse_constraints($constraints);
        return $parsed_constraints->matches($provider);
    }
    /**
     * Return all versions that satisfy given constraints.
     *
     * @param string[] $versions
     * @param string   $constraints
     *
     * @return list<string>
     */
    public static function satisfied_by(array $versions, $constraints)
    {
        $versions = array_filter($versions, function ($version) use ($constraints) {
            return Semver::satisfies($version, $constraints);
        });
        return array_values($versions);
    }
    /**
     * Sort given array of versions.
     *
     * @param string[] $versions
     *
     * @return list<string>
     */
    public static function sort(array $versions)
    {
        return self::usort($versions, self::SORT_ASC);
    }
    /**
     * Sort given array of versions in reverse.
     *
     * @param string[] $versions
     *
     * @return list<string>
     */
    public static function rsort(array $versions)
    {
        return self::usort($versions, self::SORT_DESC);
    }
    /**
     * @param string[] $versions
     * @param int      $direction
     *
     * @return list<string>
     */
    private static function usort(array $versions, $direction)
    {
        if (null === self::$version_parser) {
            self::$version_parser = new Version_Parser();
        }
        $version_parser = self::$version_parser;
        $normalized = [];
        // Normalize outside of usort() scope for minor performance increase.
        // Creates an array of arrays: [[normalized, key], ...]
        foreach ($versions as $key => $version) {
            $normalized_version = $version_parser->normalize($version);
            $normalized_version = $version_parser->normalize_default_branch($normalized_version);
            $normalized[] = [$normalized_version, $key];
        }
        usort($normalized, function (array $left, array $right) use ($direction) {
            if ($left[0] === $right[0]) {
                return 0;
            }
            if (Comparator::less_than($left[0], $right[0])) {
                return -$direction;
            }
            return $direction;
        });
        // Recreate input array, using the original indexes which are now in sorted order.
        $sorted = [];
        foreach ($normalized as $item) {
            $sorted[] = $versions[$item[1]];
        }
        return $sorted;
    }
}