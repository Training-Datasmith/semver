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
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;

class VersionParserTest extends TestCase
{
    /**
     * @dataProvider numericAliasVersions
     * @param string $input
     * @param string $expected
     */
    public function testParseNumericAliasPrefix($input, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame($expected, $parser->parseNumericAliasPrefix($input));
    }

    /**
     * @return array<mixed>
     */
    public static function numericAliasVersions()
    {
        return [
            ['0.x-dev', '0.'],
            ['1.0.x-dev', '1.0.'],
            ['1.x-dev', '1.'],
            ['1.2.x-dev', '1.2.'],
            ['1.2-dev', '1.2.'],
            ['1-dev', '1.'],
            ['dev-develop', false],
            ['dev-master', false],
        ];
    }

    /**
     * @dataProvider isValidVersions
     * @param string $input
     * @param bool $expected
     */
    public function testIsValid($input, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame($expected, $parser->isValid($input));
    }

    /**
     * @return array<mixed>
     */
    public static function isValidVersions()
    {
        return [
            ['0.x-dev', true],
            ['dev-develop', true],
            ['1.0.2', true],
            ['1.0.2.5', true],
            ['1.0.2.5.5', false],
            ['foo', false],
        ];
    }

    /**
     * @dataProvider successfulNormalizedVersions
     * @param string $input
     * @param string $expected
     */
    public function testNormalizeSucceeds($input, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame($expected, $parser->normalize($input));
    }

    /**
     * @return array<mixed>
     */
    public static function successfulNormalizedVersions()
    {
        return [
            'none' => ['1.0.0', '1.0.0.0'],
            'none/2' => ['1.2.3.4', '1.2.3.4'],
            'parses state' => ['1.0.0RC1dev', '1.0.0.0-RC1-dev'],
            'CI parsing' => ['1.0.0-rC15-dev', '1.0.0.0-RC15-dev'],
            'delimiters' => ['1.0.0.RC.15-dev', '1.0.0.0-RC15-dev'],
            'RC uppercase' => ['1.0.0-rc1', '1.0.0.0-RC1'],
            'patch replace' => ['1.0.0.pl3-dev', '1.0.0.0-patch3-dev'],
            'forces w.x.y.z' => ['1.0-dev', '1.0.0.0-dev'],
            'forces w.x.y.z/2' => ['0', '0.0.0.0'],
            'forces w.x.y.z/maximum major' => ['99999', '99999.0.0.0'],
            'parses long' => ['10.4.13-beta', '10.4.13.0-beta'],
            'parses long/2' => ['10.4.13beta2', '10.4.13.0-beta2'],
            'parses long/semver' => ['10.4.13beta.2', '10.4.13.0-beta2'],
            'parses long/semver2' => ['v1.13.11-beta.0', '1.13.11.0-beta0'],
            'parses long/semver3' => ['1.13.11.0-beta0', '1.13.11.0-beta0'],
            'expand shorthand' => ['10.4.13-b', '10.4.13.0-beta'],
            'expand shorthand/2' => ['10.4.13-b5', '10.4.13.0-beta5'],
            'strips leading v' => ['v1.0.0', '1.0.0.0'],
            'parses dates y-m as classical' => ['2010.01', '2010.01.0.0'],
            'parses dates w/ . as classical' => ['2010.01.02', '2010.01.02.0'],
            'parses dates y.m.Y as classical' => ['2010.1.555', '2010.1.555.0'],
            'parses dates y.m.Y/2 as classical' => ['2010.10.200', '2010.10.200.0'],
            'parses CalVer YYYYMMDD (as MAJOR) versions' => ['20230131.0.0', '20230131.0.0'],
            'parses CalVer YYYYMMDDhhmm (as MAJOR) versions' => ['202301310000.0.0', '202301310000.0.0'],
            'strips v/datetime' => ['v20100102', '20100102'],
            'parses dates no delimiter' => ['20100102', '20100102'],
            'parses dates no delimiter/2' => ['20100102.0', '20100102.0'],
            'parses dates no delimiter/3' => ['20100102.1.0', '20100102.1.0'],
            'parses dates no delimiter/4' => ['20100102.0.3', '20100102.0.3'],
            'parses dates no delimiter/earliest year' => ['100000', '100000'],
            'parses dates w/ - and .' => ['2010-01-02-10-20-30.0.3', '2010.01.02.10.20.30.0.3'],
            'parses dates w/ - and ./2' => ['2010-01-02-10-20-30.5', '2010.01.02.10.20.30.5'],
            'parses dates w/ -' => ['2010-01-02', '2010.01.02'],
            'parses dates w/ .' => ['2012.06.07', '2012.06.07.0'],
            'parses numbers' => ['2010-01-02.5', '2010.01.02.5'],
            'parses dates y.m.Y' => ['2010.1.555', '2010.1.555.0'],
            'parses datetime' => ['20100102-203040', '20100102.203040'],
            'parses date dev' => ['20100102.x-dev', '20100102.9999999.9999999.9999999-dev'],
            'parses datetime dev' => ['20100102.203040.x-dev', '20100102.203040.9999999.9999999-dev'],
            'parses dt+number' => ['20100102203040-10', '20100102203040.10'],
            'parses dt+patch' => ['20100102-203040-p1', '20100102.203040-patch1'],
            'parses dt Ym' => ['201903.0', '201903.0'],
            'parses dt Ym dev' => ['201903.x-dev', '201903.9999999.9999999.9999999-dev'],
            'parses dt Ym+patch' => ['201903.0-p2', '201903.0-patch2'],
            'parses master' => ['dev-master', 'dev-master'],
            'parses master w/o dev' => ['master', 'dev-master'],
            'parses trunk' => ['dev-trunk', 'dev-trunk'],
            'parses branches' => ['1.x-dev', '1.9999999.9999999.9999999-dev'],
            'parses arbitrary' => ['dev-feature-foo', 'dev-feature-foo'],
            'parses arbitrary/2' => ['DEV-FOOBAR', 'dev-FOOBAR'],
            'parses arbitrary/3' => ['dev-feature/foo', 'dev-feature/foo'],
            'parses arbitrary/4' => ['dev-feature+issue-1', 'dev-feature+issue-1'],
            'ignores aliases' => ['dev-master as 1.0.0', 'dev-master'],
            'ignores aliases/2' => ['dev-load-varnish-only-when-used as ^2.0', 'dev-load-varnish-only-when-used'],
            'ignores aliases/3' => ['dev-load-varnish-only-when-used@dev as ^2.0@dev', 'dev-load-varnish-only-when-used'],
            'ignores stability' => ['1.0.0+foo@dev', '1.0.0.0'],
            'ignores stability/2' => ['dev-load-varnish-only-when-used@stable', 'dev-load-varnish-only-when-used'],
            'semver metadata/2' => ['1.0.0-beta.5+foo', '1.0.0.0-beta5'],
            'semver metadata/3' => ['1.0.0+foo', '1.0.0.0'],
            'semver metadata/4' => ['1.0.0-alpha.3.1+foo', '1.0.0.0-alpha3.1'],
            'semver metadata/5' => ['1.0.0-alpha2.1+foo', '1.0.0.0-alpha2.1'],
            'semver metadata/6' => ['1.0.0-alpha-2.1-3+foo', '1.0.0.0-alpha2.1-3'],
            // not supported for BC 'semver metadata/7' => array('1.0.0-0.3.7', '1.0.0.0-0.3.7'),
            // not supported for BC 'semver metadata/8' => array('1.0.0-x.7.z.92', '1.0.0.0-x.7.z.92'),
            'metadata w/ alias' => ['1.0.0+foo as 2.0', '1.0.0.0'],
            'keep zero-padding' => ['00.01.03.04', '00.01.03.04'],
            'keep zero-padding/2' => ['000.001.003.004', '000.001.003.004'],
            'keep zero-padding/3' => ['0.000.103.204', '0.000.103.204'],
            'keep zero-padding/4' => ['0700', '0700.0.0.0'],
            'keep zero-padding/5' => ['041.x-dev', '041.9999999.9999999.9999999-dev'],
            'keep zero-padding/6' => ['dev-041.003', 'dev-041.003'],
            'dev with mad name' => ['dev-1.0.0-dev<1.0.5-dev', 'dev-1.0.0-dev<1.0.5-dev'],
            'dev prefix with spaces' => ['dev-foo bar', 'dev-foo bar'],
            'space padding' => [' 1.0.0', '1.0.0.0'],
            'space padding/2' => ['1.0.0 ', '1.0.0.0'],
        ];
    }

    /**
     * @dataProvider failingNormalizedVersions
     * @param string $input
     */
    public function testNormalizeFails($input)
    {
        $this->doExpectException('UnexpectedValueException');
        $parser = new VersionParser();
        $parser->normalize($input);
    }

    /**
     * @return array<mixed>
     */
    public static function failingNormalizedVersions()
    {
        return [
            'empty ' => [''],
            'invalid chars' => ['a'],
            'invalid type' => ['1.0.0-meh'],
            'too many bits' => ['1.0.0.0.0'],
            'non-dev arbitrary' => ['feature-foo'],
            'metadata w/ space' => ['1.0.0+foo bar'],
            'maven style release' => ['1.0.1-SNAPSHOT'],
            'dev with less than' => ['1.0.0<1.0.5-dev'],
            'dev with less than/2' => ['1.0.0-dev<1.0.5-dev'],
            'dev suffix with spaces' => ['foo bar-dev'],
            'any with spaces' => ['1.0 .2'],
            'no version, no alias' => [' as '],
            'no version, only alias' => [' as 1.2'],
            'just an operator' => ['^'],
            'just an operator/2' => ['^8 || ^'],
            'just an operator/3' => ['~'],
            'just an operator/4' => ['~1 ~'],
            'constraint' => ['~1'],
            'constraint/2' => ['^1'],
            'constraint/3' => ['1.*'],
            'date versions with 4 bits' => ['20100102.0.3.4', '20100102.0.3.4'],
            'date versions with 4 bits/earliest year' => ['100000.0.0.0', '100000.0.0.0'],
            'invalid CalVer (as MAJOR) versions/YYYYMMD' => ['2023013.0.0', '2023013.0.0'],
            'invalid CalVer (as MAJOR) versions/YYYYMMDDh' => ['202301311.0.0', '202301311.0.0'],
            'invalid CalVer (as MAJOR) versions/YYYYMMDDhhm' => ['20230131000.0.0', '20230131000.0.0'],
            'invalid CalVer (as MAJOR) versions/YYYYMMDDhhmmX' => ['2023013100000.0.0', '2023013100000.0.0'],
        ];
    }

    /**
     * @dataProvider failingNormalizedVersionsWithBadAlias
     * @param string $fullInput
     */
    public function testNormalizeFailsAndReportsAliasIssue($fullInput)
    {
        if (!preg_match('{^([^,\s#]+)(?:#[^ ]+)? +as +([^,\s]+)$}', $fullInput, $match)) {
            throw new \RuntimeException($fullInput.' did not match the regex');
        }
        $parser = new VersionParser();
        $parser->normalize($match[1], $fullInput);
        try {
            $parser->normalize($match[2], $fullInput);
        } catch (\UnexpectedValueException $e) {
            $this->assertEquals('Invalid version string "'.$match[2].'" in "'.$fullInput.'", the alias must be an exact version', $e->getMessage());
        }
    }

    /**
     * @return array<mixed>
     */
    public static function failingNormalizedVersionsWithBadAlias()
    {
        return [
            'Alias and caret' => ['1.0.0+foo as ^2.0'],
            'Alias and tilde' => ['1.0.0+foo as  ~2.0'],
            'Alias and greater than' => ['1.0.0+foo  as >2.0'],
            'Alias and less than' => ['1.0.0+foo as <2.0'],
            'Bad alias with stability' => ['1.0.0+foo@dev as <2.0@dev'],
        ];
    }

    /**
     * @dataProvider failingNormalizedVersionsWithBadAliasee
     * @param string $fullInput
     */
    public function testNormalizeFailsAndReportsAliaseeIssue($fullInput)
    {
        if (!preg_match('{^([^,\s#]+)(?:#[^ ]+)? +as +([^,\s]+)$}', $fullInput, $match)) {
            throw new \RuntimeException($fullInput.' did not match the regex');
        }
        $parser = new VersionParser();
        try {
            $parser->normalize($match[1], $fullInput);
        } catch (\UnexpectedValueException $e) {
            $this->assertEquals('Invalid version string "'.$match[1].'" in "'.$fullInput.'", the alias source must be an exact version, if it is a branch name you should prefix it with dev-', $e->getMessage());
        }
        $parser->normalize($match[2], $fullInput);
    }

    /**
     * @return array<mixed>
     */
    public static function failingNormalizedVersionsWithBadAliasee()
    {
        return [
            'Alias and caret' => ['^2.0 as 1.0.0+foo'],
            'Alias and tilde' => ['~2.0 as  1.0.0+foo'],
            'Alias and greater than' => ['>2.0  as 1.0.0+foo'],
            'Alias and less than' => ['<2.0 as 1.0.0+foo'],
            'Bad aliasee with stability' => ['<2.0@dev as 1.2.3@dev'],
        ];
    }

    /**
     * @dataProvider successfulNormalizedBranches
     * @param string $input
     * @param string $expected
     */
    public function testNormalizeBranch($input, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame((string) $expected, (string) $parser->normalizeBranch($input));
    }

    /**
     * @return array<mixed>
     */
    public static function successfulNormalizedBranches()
    {
        return [
            'parses x' => ['v1.x', '1.9999999.9999999.9999999-dev'],
            'parses *' => ['v1.*', '1.9999999.9999999.9999999-dev'],
            'parses digits' => ['v1.0', '1.0.9999999.9999999-dev'],
            'parses digits/2' => ['2.0', '2.0.9999999.9999999-dev'],
            'parses long x' => ['v1.0.x', '1.0.9999999.9999999-dev'],
            'parses long *' => ['v1.0.3.*', '1.0.3.9999999-dev'],
            'parses long digits' => ['v2.4.0', '2.4.0.9999999-dev'],
            'parses long digits/2' => ['2.4.4', '2.4.4.9999999-dev'],
            'parses master' => ['master', 'dev-master'],
            'parses trunk' => ['trunk', 'dev-trunk'],
            'parses arbitrary' => ['feature-a', 'dev-feature-a'],
            'parses arbitrary/2' => ['FOOBAR', 'dev-FOOBAR'],
            'parses arbitrary/3' => ['feature+issue-1', 'dev-feature+issue-1'],
        ];
    }

    public function testParseConstraintsIgnoresStabilityFlag()
    {
        $parser = new VersionParser();

        $this->assertSame((string) new Constraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0@dev'));
        $this->assertSame((string) new Constraint('>=', '1.0.0.0-beta'), (string) $parser->parseConstraints('>=1.0@beta'));
        $this->assertSame((string) new Constraint('=', 'dev-load-varnish-only-when-used'), (string) $parser->parseConstraints('dev-load-varnish-only-when-used as ^2.0@dev'));
        $this->assertSame((string) new Constraint('=', 'dev-load-varnish-only-when-used'), (string) $parser->parseConstraints('dev-load-varnish-only-when-used@dev as ^2.0@dev'));
    }

    public function testParseConstraintsIgnoresReferenceOnDevVersion()
    {
        $parser = new VersionParser();

        $this->assertSame((string) new Constraint('=', '1.0.9999999.9999999-dev'), (string) $parser->parseConstraints('1.0.x-dev#abcd123'));
        $this->assertSame((string) new Constraint('=', '1.0.9999999.9999999-dev'), (string) $parser->parseConstraints('1.0.x-dev#trunk/@123'));
    }

    public function testParseConstraintsFailsOnBadReference()
    {
        $this->doExpectException('UnexpectedValueException');
        $parser = new VersionParser();

        $this->assertSame((string) new Constraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0#abcd123'));
        $this->assertSame((string) new Constraint('=', '1.0.0.0'), (string) $parser->parseConstraints('1.0#trunk/@123'));
    }

    public function testParseConstraintsNudgesRubyDevsTowardsThePathOfRighteousness()
    {
        $this->doExpectException('UnexpectedValueException', 'Invalid operator "~>", you probably meant to use the "~" operator');
        $parser = new VersionParser();
        $parser->parseConstraints('~>1.2');
    }

    /**
     * @dataProvider simpleConstraints
     *
     * @param string     $input
     * @param Constraint $expected
     */
    public function testParseConstraintsSimple($input, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame((string) $expected, (string) $parser->parseConstraints($input));
    }

    /**
     * @return array<mixed>
     */
    public static function simpleConstraints()
    {
        return [
            'match any' => ['*', new MatchAllConstraint()],
            'match any/v' => ['v*', new Constraint('>=', '0.0.0.0-dev')],
            'match any/2' => ['*.*',  new Constraint('>=', '0.0.0.0-dev')],
            'match any/2v' => ['v*.*', new Constraint('>=', '0.0.0.0-dev')],
            'match any/3' => ['*.x.*', new Constraint('>=', '0.0.0.0-dev')],
            'match any/4' => ['x.X.x.*', new Constraint('>=', '0.0.0.0-dev')],
            'not equal' => ['<>1.0.0', new Constraint('<>', '1.0.0.0')],
            'not equal/2' => ['!=1.0.0', new Constraint('!=', '1.0.0.0')],
            'greater than' => ['>1.0.0', new Constraint('>', '1.0.0.0')],
            'lesser than' => ['<1.2.3.4', new Constraint('<', '1.2.3.4-dev')],
            'less/eq than' => ['<=1.2.3', new Constraint('<=', '1.2.3.0')],
            'great/eq than' => ['>=1.2.3', new Constraint('>=', '1.2.3.0-dev')],
            'equals' => ['=1.2.3', new Constraint('=', '1.2.3.0')],
            'double equals' => ['==1.2.3', new Constraint('=', '1.2.3.0')],
            'no op means eq' => ['1.2.3', new Constraint('=', '1.2.3.0')],
            'completes version' => ['=1.0', new Constraint('=', '1.0.0.0')],
            'shorthand beta' => ['1.2.3b5', new Constraint('=', '1.2.3.0-beta5')],
            'shorthand alpha' => ['1.2.3a1', new Constraint('=', '1.2.3.0-alpha1')],
            'shorthand patch' => ['1.2.3p1234', new Constraint('=', '1.2.3.0-patch1234')],
            'shorthand patch/2' => ['1.2.3pl1234', new Constraint('=', '1.2.3.0-patch1234')],
            'accepts spaces' => ['>= 1.2.3', new Constraint('>=', '1.2.3.0-dev')],
            'accepts spaces/2' => ['< 1.2.3', new Constraint('<', '1.2.3.0-dev')],
            'accepts spaces/3' => ['> 1.2.3', new Constraint('>', '1.2.3.0')],
            'accepts master' => ['>=dev-master', new Constraint('>=', 'dev-master')],
            'accepts master/2' => ['dev-master', new Constraint('=', 'dev-master')],
            'accepts arbitrary' => ['dev-feature-a', new Constraint('=', 'dev-feature-a')],
            'regression #550' => ['dev-some-fix', new Constraint('=', 'dev-some-fix')],
            'regression #935' => ['dev-CAPS', new Constraint('=', 'dev-CAPS')],
            'ignores aliases' => ['dev-master as 1.0.0', new Constraint('=', 'dev-master')],
            'lesser than override' => ['<1.2.3.4-stable', new Constraint('<', '1.2.3.4')],
            'great/eq than override' => ['>=1.2.3.4-stable', new Constraint('>=', '1.2.3.4')],
        ];
    }

    /**
     * @dataProvider wildcardConstraints
     *
     * @param string          $input
     * @param Constraint|null $min
     * @param Constraint      $max
     */
    public function testParseConstraintsWildcard($input, $min, $max)
    {
        $parser = new VersionParser();
        if ($min) {
            $expected = new MultiConstraint([$min, $max]);
        } else {
            $expected = $max;
        }

        $this->assertSame((string) $expected, (string) $parser->parseConstraints($input));
    }

    /**
     * @return array<mixed>
     */
    public static function wildcardConstraints()
    {
        return [
            ['v2.*', new Constraint('>=', '2.0.0.0-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['2.*.*', new Constraint('>=', '2.0.0.0-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['20.*', new Constraint('>=', '20.0.0.0-dev'), new Constraint('<', '21.0.0.0-dev')],
            ['20.*.*', new Constraint('>=', '20.0.0.0-dev'), new Constraint('<', '21.0.0.0-dev')],
            ['2.0.*', new Constraint('>=', '2.0.0.0-dev'), new Constraint('<', '2.1.0.0-dev')],
            ['2.x', new Constraint('>=', '2.0.0.0-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['2.x.x', new Constraint('>=', '2.0.0.0-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['2.2.x', new Constraint('>=', '2.2.0.0-dev'), new Constraint('<', '2.3.0.0-dev')],
            ['2.10.X', new Constraint('>=', '2.10.0.0-dev'), new Constraint('<', '2.11.0.0-dev')],
            ['2.1.3.*', new Constraint('>=', '2.1.3.0-dev'), new Constraint('<', '2.1.4.0-dev')],
            ['0.*', null, new Constraint('<', '1.0.0.0-dev')],
            ['0.*.*', null, new Constraint('<', '1.0.0.0-dev')],
            ['0.x', null, new Constraint('<', '1.0.0.0-dev')],
            ['0.x.x', null, new Constraint('<', '1.0.0.0-dev')],
        ];
    }

    /**
     * @dataProvider tildeConstraints
     *
     * @param string          $input
     * @param Constraint|null $min
     * @param Constraint      $max
     */
    public function testParseTildeWildcard($input, $min, $max)
    {
        $parser = new VersionParser();
        if ($min) {
            $expected = new MultiConstraint([$min, $max]);
        } else {
            $expected = $max;
        }

        $this->assertSame((string) $expected, (string) $parser->parseConstraints($input));
    }

    /**
     * @return array<mixed>
     */
    public static function tildeConstraints()
    {
        return [
            ['~v1', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.0', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.0.0', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '1.1.0.0-dev')],
            ['~1.2', new Constraint('>=', '1.2.0.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.2.3', new Constraint('>=', '1.2.3.0-dev'), new Constraint('<', '1.3.0.0-dev')],
            ['~1.2.3.4', new Constraint('>=', '1.2.3.4-dev'), new Constraint('<', '1.2.4.0-dev')],
            ['~1.2-beta',new Constraint('>=', '1.2.0.0-beta'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.2-b2', new Constraint('>=', '1.2.0.0-beta2'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.2-BETA2', new Constraint('>=', '1.2.0.0-beta2'), new Constraint('<', '2.0.0.0-dev')],
            ['~1.2.2-dev', new Constraint('>=', '1.2.2.0-dev'), new Constraint('<', '1.3.0.0-dev')],
            ['~1.2.2-stable', new Constraint('>=', '1.2.2.0'), new Constraint('<', '1.3.0.0-dev')],
            ['~201903.0', new Constraint('>=', '201903.0-dev'), new Constraint('<', '201904.0.0.0-dev')],
            ['~201903.0-beta', new Constraint('>=', '201903.0-beta'), new Constraint('<', '201904.0.0.0-dev')],
            ['~201903.0-stable', new Constraint('>=', '201903.0'), new Constraint('<', '201904.0.0.0-dev')],
            ['~201903.205830.1-stable', new Constraint('>=', '201903.205830.1'), new Constraint('<', '201903.205831.0.0-dev')],
            ['~2.x-dev', new Constraint('>=', '2.9999999.9999999.9999999-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['~2.0.x-dev', new Constraint('>=', '2.0.9999999.9999999-dev'), new Constraint('<', '2.1.0.0-dev')],
            ['~2.0.3.x-dev', new Constraint('>=', '2.0.3.9999999-dev'), new Constraint('<', '2.0.4.0-dev')],
            ['~0.x-dev', new Constraint('>=', '0.9999999.9999999.9999999-dev'), new Constraint('<', '1.0.0.0-dev')],
        ];
    }

    /**
     * @dataProvider caretConstraints
     *
     * @param string          $input
     * @param Constraint|null $min
     * @param Constraint      $max
     */
    public function testParseCaretWildcard($input, $min, $max)
    {
        $parser = new VersionParser();
        if ($min) {
            $expected = new MultiConstraint([$min, $max]);
        } else {
            $expected = $max;
        }

        $this->assertSame((string) $expected, (string) $parser->parseConstraints($input));
    }

    /**
     * @return array<mixed>
     */
    public static function caretConstraints()
    {
        return [
            ['^v1', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['^0', new Constraint('>=', '0.0.0.0-dev'), new Constraint('<', '1.0.0.0-dev')],
            ['^0.0', new Constraint('>=', '0.0.0.0-dev'), new Constraint('<', '0.1.0.0-dev')],
            ['^1.2', new Constraint('>=', '1.2.0.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['^1.2.3-beta.2', new Constraint('>=', '1.2.3.0-beta2'), new Constraint('<', '2.0.0.0-dev')],
            ['^1.2.3.4', new Constraint('>=', '1.2.3.4-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['^1.2.3', new Constraint('>=', '1.2.3.0-dev'), new Constraint('<', '2.0.0.0-dev')],
            ['^0.2.3', new Constraint('>=', '0.2.3.0-dev'), new Constraint('<', '0.3.0.0-dev')],
            ['^0.2', new Constraint('>=', '0.2.0.0-dev'), new Constraint('<', '0.3.0.0-dev')],
            ['^0.2.0', new Constraint('>=', '0.2.0.0-dev'), new Constraint('<', '0.3.0.0-dev')],
            ['^0.0.3', new Constraint('>=', '0.0.3.0-dev'), new Constraint('<', '0.0.4.0-dev')],
            ['^0.0.3-alpha', new Constraint('>=', '0.0.3.0-alpha'), new Constraint('<', '0.0.4.0-dev')],
            ['^0.0.3-dev', new Constraint('>=', '0.0.3.0-dev'), new Constraint('<', '0.0.4.0-dev')],
            ['^0.0.3-stable', new Constraint('>=', '0.0.3.0'), new Constraint('<', '0.0.4.0-dev')],
            ['^201903.0', new Constraint('>=', '201903.0-dev'), new Constraint('<', '201904.0.0.0-dev')],
            ['^201903.0-beta', new Constraint('>=', '201903.0-beta'), new Constraint('<', '201904.0.0.0-dev')],
            ['^201903.205830.1-stable', new Constraint('>=', '201903.205830.1'), new Constraint('<', '201904.0.0.0-dev')],
            ['^2.x-dev', new Constraint('>=', '2.9999999.9999999.9999999-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['^2.0.*-dev', new Constraint('>=', '2.0.9999999.9999999-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['^2.0.x-dev', new Constraint('>=', '2.0.9999999.9999999-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['^2.0.3.x-dev', new Constraint('>=', '2.0.3.9999999-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['^0.x-dev', new Constraint('>=', '0.9999999.9999999.9999999-dev'), new Constraint('<', '1.0.0.0-dev')],
        ];
    }

    /**
     * @dataProvider hyphenConstraints
     *
     * @param string          $input
     * @param Constraint|null $min
     * @param Constraint      $max
     */
    public function testParseHyphen($input, $min, $max)
    {
        $parser = new VersionParser();
        if ($min) {
            $expected = new MultiConstraint([$min, $max]);
        } else {
            $expected = $max;
        }

        $this->assertSame((string) $expected, (string) $parser->parseConstraints($input));
    }

    /**
     * @return array<mixed>
     */
    public static function hyphenConstraints()
    {
        return [
            ['v1 - v2', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '3.0.0.0-dev')],
            ['1.2.3 - 2.3.4.5', new Constraint('>=', '1.2.3.0-dev'), new Constraint('<=', '2.3.4.5')],
            ['1.2-beta - 2.3', new Constraint('>=', '1.2.0.0-beta'), new Constraint('<', '2.4.0.0-dev')],
            ['1.2-beta - 2.3-dev', new Constraint('>=', '1.2.0.0-beta'), new Constraint('<=', '2.3.0.0-dev')],
            ['1.2-RC - 2.3.1', new Constraint('>=', '1.2.0.0-RC'), new Constraint('<=', '2.3.1.0')],
            ['1.2.3-alpha - 2.3-RC', new Constraint('>=', '1.2.3.0-alpha'), new Constraint('<=', '2.3.0.0-RC')],
            ['1 - 2.0', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '2.1.0.0-dev')],
            ['1 - 2.1', new Constraint('>=', '1.0.0.0-dev'), new Constraint('<', '2.2.0.0-dev')],
            ['1.2 - 2.1.0', new Constraint('>=', '1.2.0.0-dev'), new Constraint('<=', '2.1.0.0')],
            ['1.3 - 2.1.3', new Constraint('>=', '1.3.0.0-dev'), new Constraint('<=', '2.1.3.0')],
            ['2.0.3.x-dev - 3.0.3.x-dev', new Constraint('>=', '2.0.3.9999999-dev'), new Constraint('<=', '3.0.3.9999999-dev')],
            ['2.0.x-dev - 3.0.x-dev', new Constraint('>=', '2.0.9999999.9999999-dev'), new Constraint('<=', '3.0.9999999.9999999-dev')],
            ['2.x-dev - 3.x-dev', new Constraint('>=', '2.9999999.9999999.9999999-dev'), new Constraint('<=', '3.9999999.9999999.9999999-dev')],
            ['0.x-dev - 1.x-dev', new Constraint('>=', '0.9999999.9999999.9999999-dev'), new Constraint('<=', '1.9999999.9999999.9999999-dev')],
        ];
    }

    /**
     * @dataProvider constraintProvider
     * @param string $constraint
     * @param string $expected
     */
    public function testParseConstraints($constraint, $expected)
    {
        $parser = new VersionParser();

        $this->assertSame($expected, (string) $parser->parseConstraints($constraint));
    }

    /**
     * @return array<mixed>
     */
    public static function constraintProvider()
    {
        return [
            // numeric branch
            ['3.x-dev', '== 3.9999999.9999999.9999999-dev'],
            ['3-dev', '== 3.0.0.0-dev'],
            // non-numeric branches
            ['dev-3.x', '== dev-3.x'],
            ['xsd2php-dev', '== dev-xsd2php'],
            ['3.next-dev', '== dev-3.next'],
            ['foobar-dev', '== dev-foobar'],
            ['dev-xsd2php', '== dev-xsd2php'],
            ['dev-3.next', '== dev-3.next'],
            ['dev-foobar', '== dev-foobar'],
            ['dev-1.0.0-dev<1.0.5-dev', '== dev-1.0.0-dev<1.0.5-dev'],
            ['dev-1.0.0-dev<1.0.5', '== dev-1.0.0-dev<1.0.5'],
            ['foobar-dev as 2.1.0', '== dev-foobar'],
            ['foobar-dev as 2.1.0 || 3.5', '[== dev-foobar || == 3.5.0.0]'],
            ['foobar-dev as 2.1.0 || 3.5 as 1.5', '[== dev-foobar || == 3.5.0.0]'],
            ['2.1.0 - 2.3-dev', '[>= 2.1.0.0-dev <= 2.3.0.0-dev]'],
            ['1.0 - 2.0.x-dev', '[>= 1.0.0.0-dev <= 2.0.9999999.9999999-dev]'],

            // borked typo constraints but so common historically that we gotta keep them working
            ['^1.', '[>= 1.0.0.0-dev < 2.0.0.0-dev]'],
            ['~1.', '[>= 1.0.0.0-dev < 2.0.0.0-dev]'],
            ['1.2.', '== 1.2.0.0'],
            ['1.2..dev', '== 1.2.0.0-dev'],
            ['1.2-.dev', '== 1.2.0.0-dev'],
            ['1.2_-dev', '== 1.2.0.0-dev'],

            // complex constraints
            ['~2.5.9|~2.6,>=2.6.2', '[[>= 2.5.9.0-dev < 2.6.0.0-dev] || [>= 2.6.0.0-dev < 3.0.0.0-dev >= 2.6.2.0-dev]]'],
        ];
    }

    /**
     * @dataProvider multiConstraintProvider
     * @param string $constraint
     */
    public function testParseConstraintsMulti($constraint)
    {
        $parser = new VersionParser();
        $first = new Constraint('>', '2.0.0.0');
        $second = new Constraint('<=', '3.0.0.0');
        $multi = new MultiConstraint([$first, $second]);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints($constraint));
    }

    /**
     * @return array<mixed>
     */
    public static function multiConstraintProvider()
    {
        return [
            ['>2.0,<=3.0'],
            ['>2.0 <=3.0'],
            ['>2.0  <=3.0'],
            ['>2.0, <=3.0'],
            ['>2.0 ,<=3.0'],
            ['>2.0 , <=3.0'],
            ['>2.0   , <=3.0'],
            ['> 2.0   <=  3.0'],
            ['> 2.0  ,  <=  3.0'],
            ['  > 2.0  ,  <=  3.0 '],
        ];
    }

    public function testParseConstraintsMultiWithStabilitySuffix()
    {
        $parser = new VersionParser();
        $first = new Constraint('>=', '1.1.0.0-alpha4');
        $second = new Constraint('<', '1.2.9999999.9999999-dev');
        $multi = new MultiConstraint([$first, $second]);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints('>=1.1.0-alpha4,<1.2.x-dev'));

        $first = new Constraint('>=', '1.1.0.0-alpha4');
        $second = new Constraint('<', '1.2.0.0-beta2');
        $multi = new MultiConstraint([$first, $second]);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints('>=1.1.0-alpha4,<1.2-beta2'));
    }

    /**
     * @dataProvider multiConstraintProvider2
     *
     * @param string $constraint
     */
    public function testParseConstraintsMultiDisjunctiveHasPrioOverConjuctive($constraint)
    {
        $parser = new VersionParser();
        $first = new Constraint('>', '2.0.0.0');
        $second = new Constraint('<', '2.0.5.0-dev');
        $third = new Constraint('>', '2.0.6.0');
        $multi1 = new MultiConstraint([$first, $second]);
        $multi2 = new MultiConstraint([$multi1, $third], false);

        $this->assertSame((string) $multi2, (string) $parser->parseConstraints($constraint));
    }

    /**
     * @return array<mixed>
     */
    public static function multiConstraintProvider2()
    {
        return [
            ['>2.0,<2.0.5 | >2.0.6'],
            ['>2.0,<2.0.5 || >2.0.6'],
            ['> 2.0 , <2.0.5 | >  2.0.6'],
        ];
    }

    public function testParseConstraintsMultiWithStabilities()
    {
        $parser = new VersionParser();
        $first = new Constraint('>', '2.0.0.0');
        $second = new Constraint('<=', '3.0.0.0-dev');
        $multi = new MultiConstraint([$first, $second]);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints('>2.0@stable,<=3.0@dev'));
    }

    public function testParseConstraintsMultiWithStabilitiesWildcard()
    {
        $parser = new VersionParser();
        $first = new Constraint('>', '2.0.0.0');
        $second = new MatchAllConstraint();
        $multi = new MultiConstraint([$first, $second]);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints('>2.0@stable,@dev'));
    }

    public function testParseConstraintsMultiWithStabilitiesZero()
    {
        $parser = new VersionParser();
        $first = new Constraint('>', '2.0.0.0');
        $second = new Constraint('==', '0.0.0.0');
        $multi = new MultiConstraint([$first, $second], false);

        $this->assertSame((string) $multi, (string) $parser->parseConstraints('>2.0@stable || 0@dev'));
    }

    /**
     * @dataProvider failingConstraints
     *
     * @param string $input
     */
    public function testParseConstraintsFails($input)
    {
        $this->doExpectException('UnexpectedValueException');
        $parser = new VersionParser();
        $parser->parseConstraints($input);
    }

    /**
     * @return array<mixed>
     */
    public static function failingConstraints()
    {
        return [
            'empty ' => [''],
            'invalid version' => ['1.0.0-meh'],
            'operator abuse' => ['>2.0,,<=3.0'],
            'operator abuse/2' => ['>2.0 ,, <=3.0'],
            'operator abuse/3' => ['>2.0 ||| <=3.0'],
            'leading operator' => [',^1@dev || ^4@dev'],
            'leading operator/2' => [',^1@dev'],
            'leading operator/3' => ['|| ^1@dev'],
            'trailing operator' => ['^1@dev ||'],
            'trailing operator/2' => ['^1@dev ,'],
            'caret+wildcard w/o -dev' => ['^2.0.*'],
            'caret+wildcard w/o -dev/2' => ['^2.0.x'],
            'caret+wildcard w/o -dev/3' => ['^2.0.x-beta'],
            'caret+wildcard w/o -dev/4' => ['^2.*'],
            'caret+wildcard w/o -dev/5' => ['^2.x'],
            'caret+wildcard w/o -dev/6' => ['^2.x-beta'],
            'caret+wildcard w/o -dev/7' => ['^2.1.2.*'],
            'caret+wildcard w/o -dev/8' => ['^2.1.2.x'],
            'caret+wildcard w/o -dev/9' => ['^2.1.2.x-beta'],
            'tilde+wildcard w/o -dev' => ['~2.0.*'],
            'tilde+wildcard w/o -dev/2' => ['~2.0.x'],
            'tilde+wildcard w/o -dev/3' => ['~2.0.x-beta'],
            'tilde+wildcard w/o -dev/4' => ['~2.*'],
            'tilde+wildcard w/o -dev/5' => ['~2.x'],
            'tilde+wildcard w/o -dev/6' => ['~2.x-beta'],
            'tilde+wildcard w/o -dev/7' => ['~2.1.2.*'],
            'tilde+wildcard w/o -dev/8' => ['~2.1.2.x'],
            'tilde+wildcard w/o -dev/9' => ['~2.1.2.x-beta'],
            'dash range with wildcard' => ['1.x - 2.*'],
            'dash range with wildcards' => ['2.x.x.x-dev - 3.x.x.x-dev'],
            'broken constraint with dev suffix' => ['^1.*-beta-dev'],
            'broken constraint with dev suffix/2' => ['^1. *-dev'],
            'broken constraint with dev suffix/3' => ['~1.*-beta-dev'],
            'dev suffix conversion only works on simple strings' => ['1.0.0-dev<1.0.5-dev'],
            'dev suffix conversion only works on simple strings/2' => ['*-dev'],
            'just an operator' => ['^'],
            'just an operator/2' => ['^8 || ^'],
            'just an operator/3' => ['~'],
            'just an operator/4' => ['~1 ~'],
        ];
    }

    /**
     * @dataProvider stabilityProvider
     *
     * @param string $expected
     * @param string $version
     */
    public function testParseStability($expected, $version)
    {
        $this->assertSame($expected, VersionParser::parseStability($version));
    }

    /**
     * @return array<mixed>
     */
    public static function stabilityProvider()
    {
        return [
            ['stable', '1'],
            ['stable', '1.0'],
            ['stable', '3.2.1'],
            ['stable', 'v3.2.1'],
            ['dev', 'v2.0.x-dev'],
            ['dev', 'v2.0.x-dev#abc123'],
            ['dev', 'v2.0.x-dev#trunk/@123'],
            ['RC', '3.0-RC2'],
            ['dev', 'dev-master'],
            ['dev', '3.1.2-dev'],
            ['dev', 'dev-feature+issue-1'],
            ['stable', '3.1.2-p1'],
            ['stable', '3.1.2-pl2'],
            ['stable', '3.1.2-patch'],
            ['alpha', '3.1.2-alpha5'],
            ['beta', '3.1.2-beta'],
            ['beta', '2.0B1'],
            ['alpha', '1.2.0a1'],
            ['alpha', '1.2_a1'],
            ['RC', '2.0.0rc1'],
            ['alpha', '1.0.0-alpha11+cs-1.1.0'],
            ['dev', '1-2_dev'],
        ];
    }

    public function testNormalizeStability()
    {
        $parser = new VersionParser();
        $stability = 'rc';
        $expectedValue = 'RC';
        $result = $parser->normalizeStability($stability);

        $this->assertSame($expectedValue, $result);

        $stability = 'BeTa';
        $expectedValue = 'beta';
        $result = $parser->normalizeStability($stability);

        $this->assertSame($expectedValue, $result);
    }

    public function testManipulateVersionStringWithReturnNull()
    {
        $position = 1;
        $increment = 2;
        $matches = [-1, -3, -2, -5, -9];
        $parser = new \ReflectionClass('\Composer\Semver\VersionParser');
        $manipulateVersionStringMethod = $parser->getMethod('manipulateVersionString');
        $manipulateVersionStringMethod->setAccessible(true);
        $result = $manipulateVersionStringMethod->invoke(new VersionParser(), $matches, $position, $increment);

        $this->assertNull($result);
    }

    public function testComplexConjunctive()
    {
        $parser = new VersionParser();
        $version = new Constraint('=', '1.0.1.0');

        $parsed = $parser->parseConstraints('~0.1 || ~1.0 !=1.0.1');

        $this->assertFalse($parsed->matches($version), '"~0.1 || ~1.0 !=1.0.1" should not allow version "1.0.1.0"');
    }

    /**
     * @param class-string<Exception> $class
     * @param string|null $message
     * @return void
     */
    private function doExpectException($class, $message = null)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($class);
            if ($message) {
                $this->expectExceptionMessage($message);
            }
        } elseif (method_exists($this, 'setExpectedException')) {
            $this->setExpectedException($class, $message);
        } else {
            throw new LogicException('Expected method "expectException" or "setExpectedException" to exist.');
        }
    }
}
