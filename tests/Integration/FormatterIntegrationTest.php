<?php

declare(strict_types=1);

namespace GraphQLFormatter\Tests\Integration;

use GraphQLFormatter\Config\FormatterConfig;
use GraphQLFormatter\Formatter\GraphQLFormatter;
use PHPUnit\Framework\TestCase;

class FormatterIntegrationTest extends TestCase
{
    private GraphQLFormatter $formatter;
    private string $inputDir;
    private string $expectedDir;

    protected function setUp(): void
    {
        $this->formatter = new GraphQLFormatter(FormatterConfig::fromArray([]));
        $this->inputDir = __DIR__ . '/../fixtures/input';
        $this->expectedDir = __DIR__ . '/../fixtures/expected';
    }

    public function test_simple_query_fixture(): void
    {
        $this->assertFixture('simple-query.gql');
    }

    public function test_simple_query_idempotent(): void
    {
        $this->assertFixtureIdempotent('simple-query.gql');
    }

    public function test_inline_args_fixture(): void
    {
        $this->assertFixture('inline-args.gql');
    }

    public function test_multiline_args_fixture(): void
    {
        $this->assertFixture('multiline-args.gql');
    }

    public function test_fragment_fixture(): void
    {
        $this->assertFixture('fragment.gql');
    }

    public function test_imports_fixture(): void
    {
        $this->assertFixture('imports.gql');
    }

    public function test_nested_objects_fixture(): void
    {
        $this->assertFixture('nested-objects.gql');
    }

    public function test_inline_fragments_fixture(): void
    {
        $this->assertFixture('inline-fragments.gql');
    }

    public function test_complex_fixture(): void
    {
        $this->assertFixture('complex.gql');
    }

    public function test_complex_idempotent(): void
    {
        $this->assertFixtureIdempotent('complex.gql');
    }

    public function test_sdl_types_fixture(): void
    {
        $this->assertFixture('sdl-types.gql');
    }

    public function test_sdl_types_idempotent(): void
    {
        $this->assertFixtureIdempotent('sdl-types.gql');
    }

    public function test_comments_fixture(): void
    {
        $this->assertFixture('comments.gql');
    }

    public function test_comments_idempotent(): void
    {
        $this->assertFixtureIdempotent('comments.gql');
    }

    public function test_already_formatted_produces_no_diff(): void
    {
        $content = file_get_contents("{$this->expectedDir}/simple-query.gql");
        $this->assertSame($content, $this->formatter->format($content));
    }

    private function assertFixture(string $name): void
    {
        $input = file_get_contents("{$this->inputDir}/{$name}");
        $expected = file_get_contents("{$this->expectedDir}/{$name}");
        $this->assertSame($expected, $this->formatter->format($input), "Fixture mismatch: {$name}");
    }

    private function assertFixtureIdempotent(string $name): void
    {
        $content = file_get_contents("{$this->expectedDir}/{$name}");
        $first = $this->formatter->format($content);
        $second = $this->formatter->format($first);
        $this->assertSame($first, $second, "Idempotency failure: {$name}");
    }
}
