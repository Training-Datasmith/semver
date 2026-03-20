# Architecture: semver

## Purpose

A Composer library implementing semantic versioning (SemVer 2.0) parsing, comparison, and constraint matching. Used internally by Composer to resolve package version constraints.

## Directory Structure

```
src/
  Semver.php              - Public API: satisfies(), satisfied_by(), sort(), rsort()
  Version_Parser.php      - Parses version strings and constraints into structured form
  Comparator.php          - Static comparison methods: greaterThan(), lessThan(), equalTo(), etc.
  Compiling_Matcher.php   - Pre-compiles constraint strings into optimised PHP closures
  Interval.php            - Represents a version interval (lower/upper bounds)
  Intervals.php           - Utility: converts constraint trees into interval sets
  Constraint/
    Constraint_Interface.php  - Contract all constraint objects must satisfy
    Constraint.php            - Single version constraint (e.g., ">=1.2.3")
    Multi_Constraint.php      - Composite AND/OR constraint
    Match_All_Constraint.php  - Matches any version (wildcard "*")
    Match_None_Constraint.php - Matches no version (impossible constraint)
    Bound.php                 - Represents an open or closed interval bound
tests/                    - PHPUnit tests for each class
```

## Key Design Decisions

- **Normalised version strings**: All versions are normalised to `major.minor.patch.build` form internally for consistent comparison — `Version_Parser::normalize()` is the gateway.
- **Constraint compilation**: `Compiling_Matcher` pre-compiles frequently-used constraints into PHP closures for performance in large dependency graphs.
- **Interval arithmetic**: `Intervals` converts constraint trees into union-of-intervals for efficient intersection checks used by `satisfied_by()`.
- **Stable static API**: The `Semver` facade is deliberately static and stateless to minimise coupling.

## Extension Points

- Implement `Constraint_Interface` to add custom constraint types.
- Extend `Version_Parser` to handle non-standard version aliases.

## Dependency Flow

```
Semver (static facade)
  └─> Version_Parser::normalize()        — normalises input version strings
  └─> Version_Parser::parseConstraints() — parses constraint string into Constraint tree
  └─> Constraint::matches()              — evaluates a version against the constraint
Comparator
  └─> Version_Parser::normalize()        — normalises before comparison
```
