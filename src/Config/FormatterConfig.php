<?php

declare(strict_types=1);

namespace GraphQLFormatter\Config;

final class FormatterConfig
{
    public readonly string $indent;
    public readonly int $printWidth;
    public readonly int $maxInlineArgs;
    public readonly bool $trailingNewline;
    /** @var list<string> */
    public readonly array $paths;

    private function __construct(
        string $indent,
        int $printWidth,
        int $maxInlineArgs,
        bool $trailingNewline,
        array $paths,
    ) {
        $this->indent = $indent;
        $this->printWidth = $printWidth;
        $this->maxInlineArgs = $maxInlineArgs;
        $this->trailingNewline = $trailingNewline;
        $this->paths = $paths;
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $indentRaw = $config['indent'] ?? '4spaces';
        $indent = match ($indentRaw) {
            '2spaces' => '  ',
            '4spaces' => '    ',
            'tab' => "\t",
            default => throw new \InvalidArgumentException("Invalid indent value: {$indentRaw}"),
        };

        return new self(
            indent: $indent,
            printWidth: (int) ($config['print_width'] ?? 80),
            maxInlineArgs: (int) ($config['max_inline_args'] ?? 2),
            trailingNewline: (bool) ($config['trailing_newline'] ?? true),
            paths: $config['paths'] ?? ['.'],
        );
    }
}
