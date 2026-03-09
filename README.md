# graphql-formatter

An opinionated formatter for `.gql` and `.graphql` files. Written in PHP, powered by `webonyx/graphql-php`.

## Installation

```bash
composer require --dev graphql-formatter/graphql-formatter
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

Copy the example config to your project root:

```bash
cp vendor/graphql-formatter/graphql-formatter/graphql-formatter.php.example graphql-formatter.php
```

Then edit `graphql-formatter.php`:

```php
<?php
return [
    'paths'           => ['src/', 'resources/graphql/'],
    'indent'          => '4spaces',
    'print_width'     => 80,
    'max_inline_args' => 2,
    'trailing_newline'=> true,
];
```

### Config options

| Key | Default | Options | Description |
|-----|---------|---------|-------------|
| `paths` | `['.']` | — | Directories to scan |
| `indent` | `'4spaces'` | `'2spaces'`, `'4spaces'`, `'tab'` | Indentation style |
| `print_width` | `80` | — | Max line width before args expand |
| `max_inline_args` | `2` | — | Max args allowed inline |
| `trailing_newline` | `true` | — | Ensure trailing newline |
| `normalize_keyword_case` | `false` | — | Normalize `query`/`mutation`/etc. to lowercase |

## Formatting rules

- Each definition separated by a blank line
- Selection sets always indented (4 spaces by default)
- Arguments inline if count ≤ `max_inline_args` AND total length ≤ `print_width`
- `ObjectValue` arguments (`{ key: value }`) always expand to multiline
- Inline fragments (`... on Type`) always rendered on their own line
- `#import "..."` directives preserved at top of file
- Formatting is idempotent — running twice gives the same result

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

Output:
```graphql
#import "fragments/user.gql"

query AssetQuery($id: ID!, $locale: String = "en", $preview: Boolean) {
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

## Development

```bash
composer install
./vendor/bin/phpunit tests/
```
