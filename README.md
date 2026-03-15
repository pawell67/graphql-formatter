# graphql-formatter

An opinionated formatter for `.gql` and `.graphql` files. Written in PHP, powered by `webonyx/graphql-php`.

## Installation

```bash
composer require --dev pawell67/graphql-formatter
```

## Usage

Add to your `composer.json` scripts:

```json
"scripts": {
    "graphql-formatter:fix": "vendor/bin/graphql-formatter fix",
    "graphql-formatter:check": "vendor/bin/graphql-formatter check"
}
```

Then run:

```bash
composer graphql-formatter:fix    # format files in place
composer graphql-formatter:check  # exit 1 if any files need changes (CI)
```

## Configuration

### Publish config (recommended)

Use the built-in `publish-config` command to copy the example config to your project's `config/` directory:

```bash
vendor/bin/graphql-formatter publish-config
```

This copies `graphql-formatter.php` to `config/graphql-formatter.php` (Laravel convention).

If a config file already exists, you'll get a warning. Use `--force` to overwrite:

```bash
vendor/bin/graphql-formatter publish-config --force
```

To publish to a custom directory:

```bash
vendor/bin/graphql-formatter publish-config --target-dir /path/to/dir
```

### Config file location

The formatter looks for config in this order:

1. `config/graphql-formatter.php` (preferred — Laravel convention)
2. `graphql-formatter.php` in project root (legacy fallback)

If no config file is found, sensible defaults are used.

### Config options

Edit the published `config/graphql-formatter.php`:

```php
<?php
return [
    // Directories to scan for .gql and .graphql files
    'paths' => ['src/', 'resources/graphql/'],

    // Indentation style: '2spaces' | '4spaces' | 'tab'
    'indent' => '4spaces',

    // Maximum line width before arguments expand to multiple lines
    'print_width' => 80,

    // Maximum number of arguments allowed on a single line
    // (still subject to print_width check)
    'max_inline_args' => 2,

    // Whether to ensure a trailing newline at end of file
    'trailing_newline' => true,
];
```

| Key | Default | Options | Description |
|-----|---------|---------|-------------|
| `paths` | `['.']` | — | Directories to scan |
| `indent` | `'4spaces'` | `'2spaces'`, `'4spaces'`, `'tab'` | Indentation style |
| `print_width` | `80` | — | Max line width before args expand to multiline |
| `max_inline_args` | `2` | — | Max args allowed inline (applies to field args and operation variable definitions) |
| `trailing_newline` | `true` | — | Ensure trailing newline at end of file |

## Formatting rules

- Each definition separated by a blank line
- Selection sets always indented (4 spaces by default)
- Arguments inline if count ≤ `max_inline_args` **and** total length ≤ `print_width`
- Operation variable definitions (`$id: ID!`) follow the same inline/multiline rules as field arguments
- `ObjectValue` arguments (`{ key: value }`) always expand to multiline
- List values containing objects expand to one item per line
- Inline fragments (`... on Type`) always rendered on their own line
- `#import "..."` directives preserved at top of file
- SDL types (input, type, enum, scalar, interface, union) are formatted with the same indentation rules
- Formatting is idempotent — running twice gives the same result

## Supported GraphQL constructs

- Queries, mutations, subscriptions (named and anonymous)
- Fragment definitions and spreads
- Inline fragments (`... on Type { ... }`)
- Variable definitions with types and default values
- Field aliases (`alias: fieldName`)
- Directives on fields, operations, and fragments (`@include(if: $val)`)
- `#import "..."` directives (preserved verbatim)
- **Schema Definition Language (SDL):**
  - `type`, `input`, `enum`, `scalar`, `interface`, `union` definitions
  - `extend type`, `extend input`, `extend enum`, etc.
  - `schema { query: Query }` definitions
  - Descriptions (block strings `"""..."""` and inline `"..."`)
  - Directives on type definitions

## Example

Input:
```graphql
#import "fragments/user.gql"

query AssetQuery($id: ID!, $locale: String = "en", $preview: Boolean) {
  asset(id: $id, locale: $locale, preview: $preview) {
    sys { id publishedAt }
    title
    ...AssetFields
  }
}
```

Output (with `max_inline_args: 2`, `indent: 4spaces`):
```graphql
#import "fragments/user.gql"

query AssetQuery(
    $id: ID!
    $locale: String = "en"
    $preview: Boolean
) {
    asset(
        id: $id
        locale: $locale
        preview: $preview
    ) {
        sys {
            id
            publishedAt
        }
        title
        ...AssetFields
    }
}
```

### SDL example

Input:
```graphql
input CreateUserInput { name: String! email: String! role: UserRole = USER }
type User { id: ID! name: String! email: String! role: UserRole! }
enum UserRole { ADMIN USER GUEST }
```

Output:
```graphql
input CreateUserInput {
    name: String!
    email: String!
    role: UserRole = USER
}

type User {
    id: ID!
    name: String!
    email: String!
    role: UserRole!
}

enum UserRole {
    ADMIN
    USER
    GUEST
}
```

## Development

```bash
git clone https://github.com/pawell67/graphql-formatter.git
cd graphql-formatter
composer install
./vendor/bin/phpunit tests/
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full contribution guide.

## License

[MIT](LICENSE) © Pawel Wankiewicz
