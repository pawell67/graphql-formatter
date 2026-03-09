<?php

declare(strict_types=1);

namespace GraphQLFormatter\Tests\Unit;

use GraphQLFormatter\Finder\FileFinder;
use PHPUnit\Framework\TestCase;

class FileFinderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/graphql-finder-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        mkdir($this->tmpDir . '/sub', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function test_finds_gql_files(): void
    {
        file_put_contents($this->tmpDir . '/query.gql', 'query Q { a }');
        file_put_contents($this->tmpDir . '/schema.graphql', 'type Query { a: String }');
        $finder = new FileFinder([$this->tmpDir]);
        $files = $finder->find();
        $this->assertCount(2, $files);
        $paths = array_map(fn ($f) => basename($f), $files);
        $this->assertContains('query.gql', $paths);
        $this->assertContains('schema.graphql', $paths);
    }

    public function test_finds_files_recursively(): void
    {
        file_put_contents($this->tmpDir . '/root.gql', 'query Q { a }');
        file_put_contents($this->tmpDir . '/sub/nested.gql', 'query Q { b }');
        $finder = new FileFinder([$this->tmpDir]);
        $files = $finder->find();
        $this->assertCount(2, $files);
    }

    public function test_ignores_non_graphql_files(): void
    {
        file_put_contents($this->tmpDir . '/query.gql', 'query Q { a }');
        file_put_contents($this->tmpDir . '/readme.md', '# ignore me');
        file_put_contents($this->tmpDir . '/schema.json', '{}');
        $finder = new FileFinder([$this->tmpDir]);
        $files = $finder->find();
        $this->assertCount(1, $files);
    }

    public function test_returns_empty_array_for_empty_directory(): void
    {
        $finder = new FileFinder([$this->tmpDir]);
        $files = $finder->find();
        $this->assertSame([], $files);
    }

    public function test_searches_multiple_paths(): void
    {
        $dir2 = $this->tmpDir . '/dir2';
        mkdir($dir2);
        file_put_contents($this->tmpDir . '/a.gql', 'query A { a }');
        file_put_contents($dir2 . '/b.gql', 'query B { b }');
        $finder = new FileFinder([$this->tmpDir, $dir2]);
        $files = $finder->find();
        $paths = array_map(fn ($f) => basename($f), $files);
        $this->assertContains('a.gql', $paths);
        $this->assertContains('b.gql', $paths);
    }

    private function removeDir(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
