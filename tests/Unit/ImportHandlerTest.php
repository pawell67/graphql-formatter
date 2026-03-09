<?php
declare(strict_types=1);
namespace GraphQLFormatter\Tests\Unit;
use GraphQLFormatter\Formatter\ImportHandler;
use PHPUnit\Framework\TestCase;

class ImportHandlerTest extends TestCase
{
    public function test_extract_returns_empty_imports_when_none_present(): void
    {
        $content = "query Foo {\n  bar\n}\n";
        $result = ImportHandler::extract($content);
        $this->assertSame([], $result->imports);
        $this->assertSame($content, $result->body);
    }

    public function test_extract_strips_import_lines_and_captures_them(): void
    {
        $content = "#import \"fragments/asset.gql\"\n#import \"fragments/user.gql\"\n\nquery Foo {\n  bar\n}\n";
        $result = ImportHandler::extract($content);
        $this->assertSame(['#import "fragments/asset.gql"', '#import "fragments/user.gql"'], $result->imports);
        $this->assertStringNotContainsString('#import', $result->body);
        $this->assertStringContainsString('query Foo', $result->body);
    }

    public function test_extract_handles_imports_with_single_quotes(): void
    {
        $content = "#import 'fragments/asset.gql'\nquery Foo { bar }\n";
        $result = ImportHandler::extract($content);
        $this->assertSame(["#import 'fragments/asset.gql'"], $result->imports);
    }

    public function test_extract_preserves_non_import_comments(): void
    {
        $content = "# regular comment\nquery Foo { bar }\n";
        $result = ImportHandler::extract($content);
        $this->assertSame([], $result->imports);
        $this->assertStringContainsString('# regular comment', $result->body);
    }

    public function test_prepend_attaches_imports_with_trailing_blank_line(): void
    {
        $imports = ['#import "a.gql"', '#import "b.gql"'];
        $body = "query Foo {\n  bar\n}\n";
        $result = ImportHandler::prepend($imports, $body);
        $this->assertSame("#import \"a.gql\"\n#import \"b.gql\"\n\nquery Foo {\n  bar\n}\n", $result);
    }

    public function test_prepend_returns_body_unchanged_when_no_imports(): void
    {
        $body = "query Foo {\n  bar\n}\n";
        $this->assertSame($body, ImportHandler::prepend([], $body));
    }
}
