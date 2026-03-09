<?php
declare(strict_types=1);
namespace GraphQLFormatter\Tests\Unit;
use GraphQLFormatter\Config\FormatterConfig;
use GraphQLFormatter\Formatter\Printer;
use GraphQL\Language\Parser;
use PHPUnit\Framework\TestCase;

class PrinterTest extends TestCase
{
    private Printer $printer;

    protected function setUp(): void
    {
        $this->printer = new Printer(FormatterConfig::fromArray([]));
    }

    public function test_anonymous_query_shorthand_renders_without_query_keyword(): void
    {
        $ast = Parser::parse('{ hero { name } }');
        $this->assertSame("{\n    hero {\n        name\n    }\n}\n", $this->printer->print($ast));
    }

    public function test_named_query_renders_with_query_keyword(): void
    {
        $ast = Parser::parse('query GetHero { hero { name } }');
        $this->assertSame("query GetHero {\n    hero {\n        name\n    }\n}\n", $this->printer->print($ast));
    }

    public function test_mutation_renders_with_mutation_keyword(): void
    {
        $ast = Parser::parse('mutation CreateUser { createUser { id } }');
        $this->assertSame("mutation CreateUser {\n    createUser {\n        id\n    }\n}\n", $this->printer->print($ast));
    }

    public function test_multiple_definitions_separated_by_blank_line(): void
    {
        $output = $this->printer->print(Parser::parse('query A { a } query B { b }'));
        $this->assertStringContainsString("\n\n", $output);
        $this->assertStringContainsString('query A', $output);
        $this->assertStringContainsString('query B', $output);
    }
}
