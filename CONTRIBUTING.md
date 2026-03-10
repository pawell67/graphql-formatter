# Contributing

Thank you for considering contributing to graphql-formatter!

## Getting started

```bash
git clone https://github.com/pawell67/graphql-formatter.git
cd graphql-formatter
composer install
```

## Running tests

```bash
./vendor/bin/phpunit tests/
```

Run a specific test suite:

```bash
./vendor/bin/phpunit tests/Unit/
./vendor/bin/phpunit tests/Integration/
```

## Development workflow

This project follows **test-driven development**. Before adding a feature or fixing a bug:

1. Write a failing test that captures the expected behaviour
2. Implement the minimal code to make it pass
3. Ensure all existing tests still pass

## Submitting changes

1. Fork the repository
2. Create a feature branch (`git checkout -b feat/my-feature`)
3. Write tests for your change
4. Implement the change
5. Run the full test suite and make sure everything passes
6. Open a pull request with a clear description of what was changed and why

## Code style

- PHP 8.4, strict types (`declare(strict_types=1)` in every file)
- PSR-4 autoloading under the `GraphQLFormatter\` namespace
- `final` classes wherever inheritance isn't needed
- Constructor property promotion and readonly properties preferred

## Reporting bugs

Open a [GitHub issue](https://github.com/pawell67/graphql-formatter/issues) with:

- PHP version
- Package version
- The `.gql` / `.graphql` input that triggers the bug
- The output you got vs. the output you expected

## License

By contributing you agree that your contributions will be licensed under the [MIT License](LICENSE).
