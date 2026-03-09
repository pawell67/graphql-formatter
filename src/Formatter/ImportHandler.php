<?php
declare(strict_types=1);
namespace GraphQLFormatter\Formatter;

final class ExtractResult
{
    /** @param list<string> $imports */
    public function __construct(
        public readonly array $imports,
        public readonly string $body,
    ) {}
}

final class ImportHandler
{
    public static function extract(string $content): ExtractResult
    {
        $lines = explode("\n", $content);
        $imports = [];
        $bodyLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^#import\s+["\'].+["\']/', $line)) {
                $imports[] = $line;
            } else {
                $bodyLines[] = $line;
            }
        }
        // Strip leading blank lines left after import removal
        while (count($bodyLines) > 0 && trim($bodyLines[0]) === '') {
            array_shift($bodyLines);
        }
        return new ExtractResult(imports: $imports, body: implode("\n", $bodyLines));
    }

    /** @param list<string> $imports */
    public static function prepend(array $imports, string $body): string
    {
        if ($imports === []) {
            return $body;
        }
        return implode("\n", $imports) . "\n\n" . $body;
    }
}
