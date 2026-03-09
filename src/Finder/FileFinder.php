<?php
declare(strict_types=1);
namespace GraphQLFormatter\Finder;

final class FileFinder
{
    private const EXTENSIONS = ['gql', 'graphql'];

    /** @param list<string> $paths */
    public function __construct(private readonly array $paths) {}

    /** @return list<string> */
    public function find(): array
    {
        $files = [];
        foreach ($this->paths as $path) {
            $realPath = realpath($path);
            if ($realPath === false) continue;
            if (is_file($realPath)) {
                if ($this->isGraphQLFile($realPath)) {
                    $files[] = $realPath;
                }
                continue;
            }
            if (is_dir($realPath)) {
                $files = array_merge($files, $this->scanDirectory($realPath));
            }
        }
        return array_values(array_unique($files));
    }

    /** @return list<string> */
    private function scanDirectory(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            assert($file instanceof \SplFileInfo);
            if ($file->isFile() && $this->isGraphQLFile($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function isGraphQLFile(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::EXTENSIONS, true);
    }
}
