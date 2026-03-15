<?php

declare(strict_types=1);

namespace GraphQLFormatter\Tests\Unit;

use GraphQLFormatter\Config\FormatterConfig;
use PHPUnit\Framework\TestCase;

class FormatterConfigTest extends TestCase
{
    public function test_defaults_are_applied_when_array_is_empty(): void
    {
        $config = FormatterConfig::fromArray([]);
        $this->assertSame('    ', $config->indent);
        $this->assertSame(80, $config->printWidth);
        $this->assertSame(2, $config->maxInlineArgs);
        $this->assertTrue($config->trailingNewline);
        $this->assertSame(['.'], $config->paths);
    }

    public function test_two_spaces_indent(): void
    {
        $this->assertSame('  ', FormatterConfig::fromArray(['indent' => '2spaces'])->indent);
    }

    public function test_tab_indent(): void
    {
        $this->assertSame("\t", FormatterConfig::fromArray(['indent' => 'tab'])->indent);
    }

    public function test_four_spaces_indent(): void
    {
        $this->assertSame('    ', FormatterConfig::fromArray(['indent' => '4spaces'])->indent);
    }

    public function test_custom_paths_are_preserved(): void
    {
        $config = FormatterConfig::fromArray(['paths' => ['src/', 'resources/graphql/']]);
        $this->assertSame(['src/', 'resources/graphql/'], $config->paths);
    }

    public function test_invalid_indent_value_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid indent value: badvalue');
        FormatterConfig::fromArray(['indent' => 'badvalue']);
    }
}
