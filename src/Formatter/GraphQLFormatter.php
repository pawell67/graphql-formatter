<?php

declare(strict_types=1);

namespace GraphQLFormatter\Formatter;

use GraphQL\Language\Parser;
use GraphQLFormatter\Config\FormatterConfig;

final class GraphQLFormatter
{
    private Printer $printer;

    public function __construct(private readonly FormatterConfig $config)
    {
        $this->printer = new Printer($config);
    }

    public function format(string $rawContent): string
    {
        $extracted = ImportHandler::extract($rawContent);
        $ast = Parser::parse($extracted->body);
        $formatted = $this->printer->print($ast);

        return ImportHandler::prepend($extracted->imports, $formatted);
    }
}
