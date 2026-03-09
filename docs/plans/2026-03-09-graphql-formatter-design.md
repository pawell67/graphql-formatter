# graphql-formatter Design

**Date:** 2026-03-09
**Status:** Approved

## Problem

Projects using GraphQL with multiple developers have inconsistently formatted `.gql` and `.graphql` files — varying indentation, arguments sometimes inline sometimes multiline, imports scattered. No PHP-native formatter exists for GraphQL files.

## Goal

A PHP Composer package that formats `.gql` and `.graphql` files consistently and automatically, installable via `composer require` and usable as `composer graphql-formatter:fix`.

## Requirements

- PHP 8.4+
- Handles: queries, mutations, subscriptions, fragments, inline fragments, directives
- Handles `#import "..."` directives (preserve at top of file)
- Configurable: indentation (2 spaces / 4 spaces / tab), print width, max inline args
- Two CLI modes: `fix` (writes in place) and `check` (CI-friendly, exits 1 if files need changes)
- File discovery via directories configured in `graphql-formatter.php`
- Config file: PHP array returning file at project root

## Architecture: Parse-to-AST + Custom Printer

Uses `webonyx/graphql-php` for parsing. `#import` lines are pre-processed (stripped before parse, re-prepended after formatting).

### Pipeline (per file)

1. Read raw content
2. `ImportHandler::extract()` — strip `#import "..."` lines, save ordered list
3. `GraphQL\Language\Parser::parse()` — produce AST
4. `Printer::print()` — walk AST, produce formatted string
5. `ImportHandler::prepend()` — reattach imports at top
6. Write (fix) or diff (check)

### Argument expansion rules

- Inline if: arg count ≤ `max_inline_args` AND formatted length ≤ `print_width`
- Otherwise: one arg per line, indented one level
- Object value args `{ ... }` always expand multiline
- Inline fragments always on their own lines

## Package Structure

```
graphql-formatter/
├── bin/graphql-formatter          # CLI entry point
├── src/
│   ├── Command/
│   │   ├── FixCommand.php
│   │   └── CheckCommand.php
│   ├── Config/
│   │   └── FormatterConfig.php
│   ├── Formatter/
│   │   ├── GraphQLFormatter.php   # Orchestrates pipeline
│   │   ├── ImportHandler.php      # #import preprocessing
│   │   └── Printer.php            # AST → formatted string
│   └── Finder/
│       └── FileFinder.php         # Directory scanning for .gql/.graphql
├── graphql-formatter.php.example
├── composer.json
└── tests/
    ├── Unit/
    │   ├── FormatterConfigTest.php
    │   ├── ImportHandlerTest.php
    │   └── PrinterTest.php
    ├── Integration/
    │   └── FixCommandTest.php
    └── fixtures/
        ├── input/
        └── expected/
```

## Config File (`graphql-formatter.php`)

```php
<?php
return [
    'paths'               => ['src/', 'resources/graphql/'],
    'indent'              => '4spaces',   // '2spaces' | '4spaces' | 'tab'
    'print_width'         => 80,
    'max_inline_args'     => 2,
    'trailing_newline'    => true,
    'normalize_keyword_case' => false,
];
```

## Composer Integration

Users add to their `composer.json`:
```json
"scripts": {
    "graphql-formatter:fix": "graphql-formatter fix",
    "graphql-formatter:check": "graphql-formatter check"
}
```

## Testing

- Unit: config defaults, import extract/prepend, printer snapshot tests
- Integration: fixture files input→expected, check mode exit codes
- Idempotency: format twice, assert same result
- Fixtures include real-world complex query with fragments and nested args
