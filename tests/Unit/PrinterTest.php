<?php

declare(strict_types=1);

namespace GraphQLFormatter\Tests\Unit;

use GraphQL\Language\Parser;
use GraphQLFormatter\Config\FormatterConfig;
use GraphQLFormatter\Formatter\Printer;
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

    // Task 05: fragments
    public function test_fragment_definition_renders_correctly(): void
    {
        $ast = Parser::parse('fragment HeroFields on Hero { name age }');
        $this->assertSame("fragment HeroFields on Hero {\n    name\n    age\n}\n", $this->printer->print($ast));
    }

    public function test_fragment_spread_renders_with_ellipsis(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { hero { ...HeroFields } }'));
        $this->assertStringContainsString('...HeroFields', $output);
    }

    public function test_fragment_spread_directives_are_preserved(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { hero { ...HeroFields @include(if: true) } }'));
        $this->assertStringContainsString('...HeroFields @include(if: true)', $output);
    }

    // Task 06: arguments
    public function test_single_arg_renders_inline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { user(id: 1) { name } }'));
        $this->assertStringContainsString('user(id: 1)', $output);
    }

    public function test_two_args_within_max_inline_renders_inline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { user(id: 1, active: true) { name } }'));
        $this->assertStringContainsString('user(id: 1, active: true)', $output);
    }

    public function test_three_args_exceeds_max_inline_renders_multiline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { user(id: 1, name: "Bob", active: true) { name } }'));
        $this->assertStringNotContainsString('user(id: 1, name: "Bob", active: true)', $output);
        $this->assertStringContainsString('id: 1', $output);
        $this->assertStringContainsString('name: "Bob"', $output);
    }

    public function test_args_exceeding_print_width_expand_to_multiline(): void
    {
        $printer = new Printer(FormatterConfig::fromArray(['print_width' => 30]));
        $output = $printer->print(Parser::parse('query Q { findUser(id: "very-long-identifier-string") { name } }'));
        $this->assertStringNotContainsString('findUser(id: "very-long-identifier-string")', $output);
    }

    // Task 07: object value args
    public function test_object_value_arg_always_expands_multiline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { createUser(input: { name: "Bob", age: 30 }) { id } }'));
        $this->assertStringContainsString("createUser(\n", $output);
        $this->assertStringContainsString('name: "Bob"', $output);
    }

    public function test_list_of_scalars_renders_inline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { field(ids: ["a", "b", "c"]) { id } }'));
        $this->assertStringContainsString('ids: ["a", "b", "c"]', $output);
    }

    public function test_list_of_objects_renders_one_per_line(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { field(filter: [{ active: true }, { id: 1 }]) { id } }'));
        // Each object on its own line inside [...]
        $this->assertStringContainsString("[\n", $output);
        $this->assertStringContainsString('active: true', $output);
        $this->assertStringContainsString('id: 1', $output);
        // NOT all on one line
        $this->assertStringNotContainsString('[{ active: true }, { id: 1 }]', $output);
    }

    // Task 08: inline fragments
    public function test_inline_fragment_renders_on_own_line(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { node { ... on User { name } } }'));
        $this->assertStringContainsString('... on User', $output);
    }

    public function test_inline_fragment_directives_are_preserved(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { node { ... on User @skip(if: true) { name } } }'));
        $this->assertStringContainsString('... on User @skip(if: true)', $output);
    }

    public function test_inline_fragment_without_type_condition(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { node { ... { name } } }'));
        $this->assertStringContainsString("... {\n", $output);
    }

    // Task 09: variable definitions and directives
    public function test_operation_with_variable_definitions_within_max_renders_inline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q($id: ID!, $limit: Int) { users(id: $id) { name } }'));
        $this->assertStringContainsString('query Q($id: ID!, $limit: Int)', $output);
    }

    public function test_operation_with_more_than_max_inline_vars_renders_multiline(): void
    {
        // 3 vars exceeds default maxInlineArgs=2 → should expand to multiline
        $output = $this->printer->print(Parser::parse('query Q($a: String!, $b: Int!, $c: Boolean!) { field { id } }'));
        $this->assertStringNotContainsString('query Q($a: String!, $b: Int!, $c: Boolean!)', $output);
        $this->assertStringContainsString('$a: String!', $output);
        $this->assertStringContainsString('$b: Int!', $output);
        $this->assertStringContainsString('$c: Boolean!', $output);
    }

    public function test_operation_vars_exceeding_print_width_renders_multiline(): void
    {
        $printer = new Printer(FormatterConfig::fromArray(['print_width' => 30]));
        $output = $printer->print(Parser::parse('query Q($longVarName: VeryLongTypeName!) { field { id } }'));
        $this->assertStringNotContainsString('query Q($longVarName: VeryLongTypeName!)', $output);
        $this->assertStringContainsString('$longVarName: VeryLongTypeName!', $output);
    }

    public function test_non_null_type_renders_with_exclamation(): void
    {
        $output = $this->printer->print(Parser::parse('query Q($id: ID!) { user(id: $id) { name } }'));
        $this->assertStringContainsString('$id: ID!', $output);
    }

    public function test_list_type_renders_with_brackets(): void
    {
        $output = $this->printer->print(Parser::parse('query Q($ids: [ID!]!) { users(ids: $ids) { name } }'));
        $this->assertStringContainsString('$ids: [ID!]!', $output);
    }

    public function test_field_directive_renders_inline(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { user { name @include(if: true) } }'));
        $this->assertStringContainsString('@include(if: true)', $output);
    }

    public function test_variable_with_default_value(): void
    {
        $output = $this->printer->print(Parser::parse('query Q($limit: Int = 10) { users { name } }'));
        $this->assertStringContainsString('$limit: Int = 10', $output);
    }

    public function test_block_string_value_is_preserved(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { field(text: """hello""") { id } }'));
        $this->assertStringContainsString('text: """hello"""', $output);
    }

    // Task 10: alias and fragment directives
    public function test_field_alias_renders_correctly(): void
    {
        $output = $this->printer->print(Parser::parse('query Q { myAlias: user { name } }'));
        $this->assertStringContainsString('myAlias: user', $output);
    }

    public function test_fragment_with_directive(): void
    {
        $output = $this->printer->print(Parser::parse('fragment F on User @deprecated { name }'));
        $this->assertStringContainsString('fragment F on User @deprecated', $output);
    }

    // Task 11: idempotency
    public function test_formatting_is_idempotent_simple(): void
    {
        $gql = 'query GetUser($id: ID!) { user(id: $id) { name email } }';
        $first = $this->printer->print(Parser::parse($gql));
        $second = $this->printer->print(Parser::parse($first));
        $this->assertSame($first, $second);
    }

    public function test_formatting_is_idempotent_with_fragments(): void
    {
        $gql = 'fragment F on User { name } query Q { user { ...F } }';
        $first = $this->printer->print(Parser::parse($gql));
        $second = $this->printer->print(Parser::parse($first));
        $this->assertSame($first, $second);
    }

    public function test_formatting_is_idempotent_with_args(): void
    {
        $gql = 'query Q($a: String, $b: Int, $c: Boolean) { field(x: $a, y: $b, z: $c) { id } }';
        $first = $this->printer->print(Parser::parse($gql));
        $second = $this->printer->print(Parser::parse($first));
        $this->assertSame($first, $second);
    }

    // SDL: input object type
    public function test_input_object_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('input CreateUserInput { name: String! email: String! age: Int }');
        $expected = "input CreateUserInput {\n    name: String!\n    email: String!\n    age: Int\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    public function test_input_object_type_with_default_value(): void
    {
        $ast = Parser::parse('input SearchInput { query: String limit: Int = 10 }');
        $expected = "input SearchInput {\n    query: String\n    limit: Int = 10\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL: object type definition
    public function test_object_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('type User { id: ID! name: String! email: String }');
        $expected = "type User {\n    id: ID!\n    name: String!\n    email: String\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    public function test_object_type_with_implements(): void
    {
        $ast = Parser::parse('type User implements Node & Entity { id: ID! name: String! }');
        $expected = "type User implements Node & Entity {\n    id: ID!\n    name: String!\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL: enum type definition
    public function test_enum_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('enum Status { ACTIVE INACTIVE PENDING }');
        $expected = "enum Status {\n    ACTIVE\n    INACTIVE\n    PENDING\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL: scalar type definition
    public function test_scalar_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('scalar DateTime');
        $expected = "scalar DateTime\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL: interface type definition
    public function test_interface_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('interface Node { id: ID! }');
        $expected = "interface Node {\n    id: ID!\n}\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL: union type definition
    public function test_union_type_definition_renders_correctly(): void
    {
        $ast = Parser::parse('union SearchResult = User | Post | Comment');
        $expected = "union SearchResult = User | Post | Comment\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    public function test_directive_definition_renders_correctly(): void
    {
        $ast = Parser::parse('directive @auth(role: String = "admin") repeatable on FIELD_DEFINITION | OBJECT');
        $expected = "directive @auth(role: String = \"admin\") repeatable on FIELD_DEFINITION | OBJECT\n";
        $this->assertSame($expected, $this->printer->print($ast));
    }

    // SDL idempotency
    public function test_input_type_formatting_is_idempotent(): void
    {
        $gql = 'input CreateUserInput { name: String! email: String! age: Int = 0 }';
        $first = $this->printer->print(Parser::parse($gql));
        $second = $this->printer->print(Parser::parse($first));
        $this->assertSame($first, $second);
    }

    public function test_mixed_query_and_input_types_separated_by_blank_line(): void
    {
        $gql = 'input Foo { bar: String } query Q { field { id } }';
        $output = $this->printer->print(Parser::parse($gql));
        $this->assertStringContainsString('input Foo', $output);
        $this->assertStringContainsString('query Q', $output);
        $this->assertStringContainsString("\n\n", $output);
    }

    // Comments
    public function test_leading_comment_before_definition_is_preserved(): void
    {
        $ast = Parser::parse("# file header\nquery Q { field { id } }");
        $output = $this->printer->print($ast);
        $this->assertStringStartsWith("# file header\n", $output);
        $this->assertStringContainsString('query Q', $output);
    }

    public function test_multiple_leading_comments_before_definition_are_preserved(): void
    {
        $ast = Parser::parse("# line 1\n# line 2\nquery Q { field { id } }");
        $output = $this->printer->print($ast);
        $this->assertStringStartsWith("# line 1\n# line 2\n", $output);
    }

    public function test_leading_comment_before_second_definition_is_preserved(): void
    {
        $ast = Parser::parse("query A { a }\n# comment for B\nquery B { b }");
        $output = $this->printer->print($ast);
        $this->assertStringContainsString("# comment for B\nquery B", $output);
    }

    public function test_comment_inside_selection_set_is_preserved(): void
    {
        $ast = Parser::parse("query Q {\n# before field\nfield { id } }");
        $output = $this->printer->print($ast);
        $this->assertStringContainsString("# before field\n", $output);
        $this->assertStringContainsString('field', $output);
    }

    public function test_trailing_inline_comment_on_field_is_preserved(): void
    {
        $ast = Parser::parse("query Q { field { name # inline\n } }");
        $output = $this->printer->print($ast);
        $this->assertStringContainsString('name # inline', $output);
    }

    public function test_trailing_comment_is_not_duplicated_as_leading_comment(): void
    {
        $ast = Parser::parse("query Q { field { name # trailing\nemail } }");
        $output = $this->printer->print($ast);
        $this->assertStringContainsString('name # trailing', $output);
        // The comment should appear exactly once, not also as a leading comment of email
        $this->assertSame(1, substr_count($output, '# trailing'));
    }

    public function test_comments_in_sdl_type_are_preserved(): void
    {
        $ast = Parser::parse("# before type\ntype User {\n# before id\nid: ID! name: String! # trailing\n}");
        $output = $this->printer->print($ast);
        $this->assertStringStartsWith("# before type\n", $output);
        $this->assertStringContainsString("# before id\n", $output);
        $this->assertStringContainsString('name: String! # trailing', $output);
    }

    public function test_comment_formatting_is_idempotent(): void
    {
        $gql = "# file comment\nquery GetUser(\$id: ID!) { # before user\nuser(id: \$id) { name # inline\nemail } }";
        $first = $this->printer->print(Parser::parse($gql));
        $second = $this->printer->print(Parser::parse($first));
        $this->assertSame($first, $second);
    }
}
