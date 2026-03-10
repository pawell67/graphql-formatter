<?php

declare(strict_types=1);

namespace GraphQLFormatter\Tests\Unit;

use GraphQLFormatter\Command\PublishConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class PublishConfigCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/graphql-publish-test-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_copies_config_file_to_target_directory(): void
    {
        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--target-dir' => $this->tmpDir]);

        $this->assertFileExists($this->tmpDir . '/graphql-formatter.php');
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_published_config_contains_paths_key(): void
    {
        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir]);

        $content = file_get_contents($this->tmpDir . '/graphql-formatter.php');
        $this->assertStringContainsString("'paths'", $content);
    }

    public function test_published_config_is_valid_php_returning_array(): void
    {
        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir]);

        $result = require $this->tmpDir . '/graphql-formatter.php';
        $this->assertIsArray($result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('indent', $result);
        $this->assertArrayHasKey('print_width', $result);
        $this->assertArrayHasKey('max_inline_args', $result);
    }

    public function test_refuses_to_overwrite_existing_config_without_force(): void
    {
        file_put_contents($this->tmpDir . '/graphql-formatter.php', '<?php return ["existing" => true];');

        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir]);

        $this->assertSame(1, $tester->getStatusCode());
        // Original file must be untouched
        $result = require $this->tmpDir . '/graphql-formatter.php';
        $this->assertArrayHasKey('existing', $result);
    }

    public function test_overwrites_existing_config_with_force_flag(): void
    {
        file_put_contents($this->tmpDir . '/graphql-formatter.php', '<?php return ["existing" => true];');

        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir, '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $result = require $this->tmpDir . '/graphql-formatter.php';
        $this->assertArrayNotHasKey('existing', $result);
        $this->assertArrayHasKey('paths', $result);
    }

    public function test_output_uses_laravel_info_style_on_success(): void
    {
        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir]);

        $display = $tester->getDisplay();
        // Laravel style: INFO  Copying file [src] to [dest]
        $this->assertStringContainsString('INFO', $display);
        $this->assertStringContainsString('graphql-formatter.php', $display);
    }

    public function test_output_uses_laravel_warn_style_when_file_exists(): void
    {
        file_put_contents($this->tmpDir . '/graphql-formatter.php', '<?php return [];');

        $command = new PublishConfigCommand();
        $tester = new CommandTester($command);
        $tester->execute(['--target-dir' => $this->tmpDir]);

        $display = $tester->getDisplay();
        // Laravel style: WARN  File [x] already exists
        $this->assertStringContainsString('WARN', $display);
        $this->assertStringContainsString('graphql-formatter.php', $display);
    }

    public function test_default_target_is_config_subdirectory(): void
    {
        // Without --target-dir, publishes to config/graphql-formatter.php
        $command = new PublishConfigCommand($this->tmpDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertFileExists($this->tmpDir . '/config/graphql-formatter.php');
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_creates_config_directory_if_missing(): void
    {
        $command = new PublishConfigCommand($this->tmpDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertDirectoryExists($this->tmpDir . '/config');
    }
}
