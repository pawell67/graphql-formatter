<?php

declare(strict_types=1);

/**
 * GraphQL Formatter configuration.
 * Copy this file to graphql-formatter.php in your project config directory and adjust as needed.
 */
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
