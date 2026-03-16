<?php

declare(strict_types=1);

namespace GraphQLFormatter\Formatter;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaExtensionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\Token;
use GraphQLFormatter\Config\FormatterConfig;

final class Printer
{
    public function __construct(private readonly FormatterConfig $config)
    {
    }

    public function print(DocumentNode $document): string
    {
        $definitions = [];
        foreach ($document->definitions as $definition) {
            $definitions[] = $this->printDefinition($definition);
        }

        $output = implode("\n\n", $definitions);

        if ($this->config->trailingNewline) {
            $output .= "\n";
        }

        return $output;
    }

    private function printDefinition(Node $node): string
    {
        $definition = match (true) {
            // Executable definitions
            $node instanceof OperationDefinitionNode => $this->printOperation($node),
            $node instanceof FragmentDefinitionNode => $this->printFragment($node),
            // SDL type definitions
            $node instanceof DirectiveDefinitionNode => $this->printDirectiveDefinition($node),
            $node instanceof InputObjectTypeDefinitionNode => $this->printInputObjectTypeDefinition($node),
            $node instanceof InputObjectTypeExtensionNode => $this->printInputObjectTypeExtension($node),
            $node instanceof ObjectTypeDefinitionNode => $this->printObjectTypeDefinition($node),
            $node instanceof ObjectTypeExtensionNode => $this->printObjectTypeExtension($node),
            $node instanceof InterfaceTypeDefinitionNode => $this->printInterfaceTypeDefinition($node),
            $node instanceof InterfaceTypeExtensionNode => $this->printInterfaceTypeExtension($node),
            $node instanceof EnumTypeDefinitionNode => $this->printEnumTypeDefinition($node),
            $node instanceof EnumTypeExtensionNode => $this->printEnumTypeExtension($node),
            $node instanceof UnionTypeDefinitionNode => $this->printUnionTypeDefinition($node),
            $node instanceof UnionTypeExtensionNode => $this->printUnionTypeExtension($node),
            $node instanceof ScalarTypeDefinitionNode => $this->printScalarTypeDefinition($node),
            $node instanceof ScalarTypeExtensionNode => $this->printScalarTypeExtension($node),
            $node instanceof SchemaDefinitionNode => $this->printSchemaDefinition($node),
            $node instanceof SchemaExtensionNode => $this->printSchemaExtension($node),
            default => throw new \RuntimeException('Unsupported definition node: ' . $node::class),
        };

        $leadingComments = $this->getLeadingComments($node);
        if ($leadingComments !== []) {
            return implode("\n", $leadingComments) . "\n" . $definition;
        }

        return $definition;
    }

    private function printOperation(OperationDefinitionNode $node, int $depth = 0): string
    {
        $isShorthand = $node->name === null
            && $node->operation === 'query'
            && count($node->variableDefinitions) === 0
            && count($node->directives) === 0;

        if ($isShorthand) {
            return $this->printSelectionSet($node->selectionSet, $depth);
        }

        $header = $node->operation;

        if ($node->name !== null) {
            $header .= ' ' . $node->name->value;
        }

        if (count($node->variableDefinitions) > 0) {
            $vars = [];
            foreach ($node->variableDefinitions as $varDef) {
                $vars[] = $this->printVariableDefinition($varDef);
            }
            $header .= $this->formatVarDefinitions($vars, $header);
        }

        foreach ($node->directives as $directive) {
            $header .= ' ' . $this->printDirective($directive, $depth);
        }

        return $header . ' ' . $this->printSelectionSet($node->selectionSet, $depth);
    }

    /** @param list<string> $vars */
    private function formatVarDefinitions(array $vars, string $operationKeyword): string
    {
        $inline = '(' . implode(', ', $vars) . ')';

        if (count($vars) <= $this->config->maxInlineArgs) {
            $lineLength = strlen($operationKeyword . $inline);
            if ($lineLength <= $this->config->printWidth) {
                return $inline;
            }
        }

        $indent = $this->config->indent;
        $lines = array_map(fn (string $v) => $indent . $v, $vars);

        return "(\n" . implode("\n", $lines) . "\n)";
    }

    private function printFragment(FragmentDefinitionNode $node): string
    {
        $typeCondition = $node->typeCondition->name->value;
        $name = $node->name->value;
        $header = "fragment {$name} on {$typeCondition}";
        foreach ($node->directives as $directive) {
            $header .= ' ' . $this->printDirective($directive, 0);
        }

        return $header . ' ' . $this->printSelectionSet($node->selectionSet, 0);
    }

    private function printSelectionSet(SelectionSetNode $node, int $depth): string
    {
        $indent = str_repeat($this->config->indent, $depth + 1);
        $closingIndent = str_repeat($this->config->indent, $depth);

        $selections = [];
        foreach ($node->selections as $selection) {
            foreach ($this->getLeadingComments($selection) as $comment) {
                $selections[] = $indent . $comment;
            }
            $line = $indent . $this->printSelectionNode($selection, $depth + 1);
            $trailingComment = $this->getTrailingComment($selection);
            $selections[] = $line . $trailingComment;
        }

        return "{\n" . implode("\n", $selections) . "\n" . $closingIndent . '}';
    }

    private function printSelectionNode(Node $node, int $depth): string
    {
        return match (true) {
            $node instanceof FieldNode => $this->printField($node, $depth),
            $node instanceof FragmentSpreadNode => $this->printFragmentSpread($node, $depth),
            $node instanceof InlineFragmentNode => $this->printInlineFragment($node, $depth),
            default => throw new \RuntimeException('Unsupported selection node: ' . $node::class),
        };
    }

    private function printField(FieldNode $node, int $depth): string
    {
        $prefix = $node->alias !== null ? $node->alias->value . ': ' : '';
        $name = $node->name->value;
        $result = $prefix . $name;

        if (count($node->arguments) > 0) {
            $result .= $this->printArguments($node->arguments, $depth, $prefix . $name);
        }

        foreach ($node->directives as $directive) {
            $result .= ' ' . $this->printDirective($directive, $depth);
        }

        if ($node->selectionSet !== null) {
            $result .= ' ' . $this->printSelectionSet($node->selectionSet, $depth);
        }

        return $result;
    }

    private function printFragmentSpread(FragmentSpreadNode $node, int $depth): string
    {
        $result = '...' . $node->name->value;

        foreach ($node->directives as $directive) {
            $result .= ' ' . $this->printDirective($directive, $depth);
        }

        return $result;
    }

    private function printInlineFragment(InlineFragmentNode $node, int $depth): string
    {
        $typeCondition = $node->typeCondition !== null
            ? ' on ' . $node->typeCondition->name->value
            : '';

        $result = '...' . $typeCondition;

        foreach ($node->directives as $directive) {
            $result .= ' ' . $this->printDirective($directive, $depth);
        }

        return $result . ' ' . $this->printSelectionSet($node->selectionSet, $depth);
    }

    /** @param NodeList<ArgumentNode> $args */
    private function printArguments(NodeList $args, int $depth, string $fieldName = ''): string
    {
        if (count($args) === 0) {
            return '';
        }

        // ObjectValueNode args always force multiline
        foreach ($args as $arg) {
            if ($arg->value instanceof ObjectValueNode) {
                return $this->printArgumentsMultiline($args, $depth);
            }
        }

        // Try inline if within count threshold
        if (count($args) <= $this->config->maxInlineArgs) {
            $rendered = [];
            foreach ($args as $arg) {
                $renderedArg = $this->printArgument($arg, $depth);
                // If any argument value expands to multiple lines (e.g. long lists), force multiline args.
                if (str_contains($renderedArg, "\n")) {
                    return $this->printArgumentsMultiline($args, $depth);
                }
                $rendered[] = $renderedArg;
            }
            $inline = '(' . implode(', ', $rendered) . ')';

            // Check total line width: indent + fieldName + inline args
            $currentIndent = str_repeat($this->config->indent, $depth);
            $lineLength = strlen($currentIndent . $fieldName . $inline);
            if ($lineLength <= $this->config->printWidth) {
                return $inline;
            }
        }

        return $this->printArgumentsMultiline($args, $depth);
    }

    /** @param NodeList<ArgumentNode> $args */
    private function printArgumentsMultiline(NodeList $args, int $depth): string
    {
        $indent = str_repeat($this->config->indent, $depth + 1);
        $closingIndent = str_repeat($this->config->indent, $depth);

        $lines = [];
        foreach ($args as $arg) {
            $lines[] = $indent . $this->printArgument($arg, $depth + 1);
        }

        return "(\n" . implode("\n", $lines) . "\n" . $closingIndent . ')';
    }

    private function printArgument(ArgumentNode $arg, int $depth): string
    {
        return $arg->name->value . ': ' . $this->printValue($arg->value, $depth);
    }

    private function printValue(Node $value, int $depth): string
    {
        return match (true) {
            $value instanceof IntValueNode => $value->value,
            $value instanceof FloatValueNode => $value->value,
            $value instanceof BooleanValueNode => $value->value ? 'true' : 'false',
            $value instanceof NullValueNode => 'null',
            $value instanceof EnumValueNode => $value->value,
            $value instanceof StringValueNode => $this->printStringValue($value),
            $value instanceof VariableNode => '$' . $value->name->value,
            $value instanceof ListValueNode => $this->printListValue($value, $depth),
            $value instanceof ObjectValueNode => $this->printObjectValue($value, $depth),
            default => throw new \RuntimeException('Unsupported value node: ' . $value::class),
        };
    }

    private function printStringValue(StringValueNode $node): string
    {
        if ($node->block) {
            // Block strings are triple-quoted and should not be escaped like normal strings.
            return '"""' . $node->value . '"""';
        }

        return '"' . addslashes($node->value) . '"';
    }

    private function printListValue(ListValueNode $node, int $depth): string
    {
        if (count($node->values) === 0) {
            return '[]';
        }

        // If any item is an object, render one per line
        foreach ($node->values as $value) {
            if ($value instanceof ObjectValueNode) {
                $indent = str_repeat($this->config->indent, $depth + 1);
                $closingIndent = str_repeat($this->config->indent, $depth);
                $lines = [];
                foreach ($node->values as $v) {
                    $lines[] = $indent . $this->printValue($v, $depth + 1);
                }

                return "[\n" . implode("\n", $lines) . "\n" . $closingIndent . ']';
            }
        }

        $values = [];
        foreach ($node->values as $value) {
            $values[] = $this->printValue($value, $depth);
        }

        $inline = '[' . implode(', ', $values) . ']';

        // Prefer multiline for longer scalar lists, or when the list itself exceeds print width.
        // This keeps "where" filters and other long IN-lists readable.
        $currentIndent = str_repeat($this->config->indent, $depth);
        $inlineLength = strlen($currentIndent . $inline);
        if (count($node->values) <= 4 && $inlineLength <= $this->config->printWidth) {
            return $inline;
        }

        $indent = str_repeat($this->config->indent, $depth + 1);
        $closingIndent = str_repeat($this->config->indent, $depth);
        $lines = [];
        foreach ($node->values as $v) {
            $lines[] = $indent . $this->printValue($v, $depth + 1);
        }

        return "[\n" . implode("\n", $lines) . "\n" . $closingIndent . ']';
    }

    private function printObjectValue(ObjectValueNode $node, int $depth): string
    {
        if (count($node->fields) === 0) {
            return '{}';
        }
        $indent = str_repeat($this->config->indent, $depth + 1);
        $closingIndent = str_repeat($this->config->indent, $depth);
        $fields = [];
        foreach ($node->fields as $field) {
            $fields[] = $indent . $field->name->value . ': ' . $this->printValue($field->value, $depth + 1);
        }

        return "{\n" . implode("\n", $fields) . "\n" . $closingIndent . '}';
    }

    private function printVariableDefinition(VariableDefinitionNode $node): string
    {
        $result = '$' . $node->variable->name->value . ': ' . $this->printType($node->type);
        if ($node->defaultValue !== null) {
            $result .= ' = ' . $this->printValue($node->defaultValue, 0);
        }

        return $result;
    }

    private function printType(Node $typeNode): string
    {
        return match (true) {
            $typeNode instanceof NamedTypeNode => $typeNode->name->value,
            $typeNode instanceof ListTypeNode => '[' . $this->printType($typeNode->type) . ']',
            $typeNode instanceof NonNullTypeNode => $this->printType($typeNode->type) . '!',
            default => throw new \RuntimeException('Unsupported type node: ' . $typeNode::class),
        };
    }

    private function printDirective(DirectiveNode $node, int $depth): string
    {
        $result = '@' . $node->name->value;
        if (count($node->arguments) > 0) {
            $result .= $this->printArguments($node->arguments, $depth + 1, '@' . $node->name->value);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // SDL (Schema Definition Language) printers
    // -------------------------------------------------------------------------

    private function printSdlDescription(?StringValueNode $description): string
    {
        if ($description === null || $description->value === '') {
            return '';
        }

        // Block strings as triple-quoted descriptions
        $value = $description->value;

        return '"""' . "\n" . $value . "\n" . '"""' . "\n";
    }

    private function printInputValueDefinitionInline(InputValueDefinitionNode $node): string
    {
        $result = $node->name->value . ': ' . $this->printType($node->type);
        if ($node->defaultValue !== null) {
            $result .= ' = ' . $this->printValue($node->defaultValue, 0);
        }
        $result .= $this->printSdlDirectives($node->directives);

        return $result;
    }

    /** @param NodeList<DirectiveNode> $directives */
    private function printSdlDirectives(NodeList $directives): string
    {
        if (count($directives) === 0) {
            return '';
        }
        $parts = [];
        foreach ($directives as $directive) {
            $parts[] = $this->printDirective($directive, 0);
        }

        return ' ' . implode(' ', $parts);
    }

    /**
     * Print a block of SDL field/value definitions with configurable indentation.
     *
     * @template TNode of Node
     * @param NodeList<TNode> $items
     */
    private function printSdlBlock(NodeList $items, callable $printer): string
    {
        if (count($items) === 0) {
            return '{}';
        }
        $indent = $this->config->indent;
        $lines = [];
        foreach ($items as $item) {
            foreach ($this->getLeadingComments($item) as $comment) {
                $lines[] = $indent . $comment;
            }
            $line = $indent . $printer($item);
            $trailingComment = $this->getTrailingComment($item);
            $lines[] = $line . $trailingComment;
        }

        return "{\n" . implode("\n", $lines) . "\n}";
    }

    private function printInputValueDefinition(InputValueDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $result = $node->name->value . ': ' . $this->printType($node->type);
        if ($node->defaultValue !== null) {
            $result .= ' = ' . $this->printValue($node->defaultValue, 0);
        }
        $result .= $this->printSdlDirectives($node->directives);

        return $desc . $result;
    }

    private function printFieldDefinition(FieldDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $result = $node->name->value;

        // Field arguments (rare in SDL but valid)
        if (count($node->arguments) > 0) {
            $argParts = [];
            foreach ($node->arguments as $arg) {
                $argParts[] = $this->printInputValueDefinition($arg);
            }
            $result .= '(' . implode(', ', $argParts) . ')';
        }

        $result .= ': ' . $this->printType($node->type);
        $result .= $this->printSdlDirectives($node->directives);

        return $desc . $result;
    }

    private function printInputObjectTypeDefinition(InputObjectTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $header = 'input ' . $node->name->value . $this->printSdlDirectives($node->directives);
        $block = $this->printSdlBlock($node->fields, fn (InputValueDefinitionNode $f) => $this->printInputValueDefinition($f));

        return $desc . $header . ' ' . $block;
    }

    private function printInputObjectTypeExtension(InputObjectTypeExtensionNode $node): string
    {
        $header = 'extend input ' . $node->name->value . $this->printSdlDirectives($node->directives);
        if (count($node->fields) === 0) {
            return $header;
        }
        $block = $this->printSdlBlock($node->fields, fn (InputValueDefinitionNode $f) => $this->printInputValueDefinition($f));

        return $header . ' ' . $block;
    }

    private function printObjectTypeDefinition(ObjectTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $header = 'type ' . $node->name->value;

        if (count($node->interfaces) > 0) {
            $ifaces = [];
            foreach ($node->interfaces as $iface) {
                $ifaces[] = $iface->name->value;
            }
            $header .= ' implements ' . implode(' & ', $ifaces);
        }

        $header .= $this->printSdlDirectives($node->directives);
        $block = $this->printSdlBlock($node->fields, fn (FieldDefinitionNode $f) => $this->printFieldDefinition($f));

        return $desc . $header . ' ' . $block;
    }

    private function printObjectTypeExtension(ObjectTypeExtensionNode $node): string
    {
        $header = 'extend type ' . $node->name->value;

        if (count($node->interfaces) > 0) {
            $ifaces = [];
            foreach ($node->interfaces as $iface) {
                $ifaces[] = $iface->name->value;
            }
            $header .= ' implements ' . implode(' & ', $ifaces);
        }

        $header .= $this->printSdlDirectives($node->directives);

        if (count($node->fields) === 0) {
            return $header;
        }

        $block = $this->printSdlBlock($node->fields, fn (FieldDefinitionNode $f) => $this->printFieldDefinition($f));

        return $header . ' ' . $block;
    }

    private function printInterfaceTypeDefinition(InterfaceTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $header = 'interface ' . $node->name->value;

        if (count($node->interfaces) > 0) {
            $ifaces = [];
            foreach ($node->interfaces as $iface) {
                $ifaces[] = $iface->name->value;
            }
            $header .= ' implements ' . implode(' & ', $ifaces);
        }

        $header .= $this->printSdlDirectives($node->directives);
        $block = $this->printSdlBlock($node->fields, fn (FieldDefinitionNode $f) => $this->printFieldDefinition($f));

        return $desc . $header . ' ' . $block;
    }

    private function printInterfaceTypeExtension(InterfaceTypeExtensionNode $node): string
    {
        $header = 'extend interface ' . $node->name->value;

        if (count($node->interfaces) > 0) {
            $ifaces = [];
            foreach ($node->interfaces as $iface) {
                $ifaces[] = $iface->name->value;
            }
            $header .= ' implements ' . implode(' & ', $ifaces);
        }

        $header .= $this->printSdlDirectives($node->directives);

        if (count($node->fields) === 0) {
            return $header;
        }

        $block = $this->printSdlBlock($node->fields, fn (FieldDefinitionNode $f) => $this->printFieldDefinition($f));

        return $header . ' ' . $block;
    }

    private function printEnumTypeDefinition(EnumTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $header = 'enum ' . $node->name->value . $this->printSdlDirectives($node->directives);
        $block = $this->printSdlBlock(
            $node->values,
            fn (EnumValueDefinitionNode $v) => $v->name->value . $this->printSdlDirectives($v->directives)
        );

        return $desc . $header . ' ' . $block;
    }

    private function printEnumTypeExtension(EnumTypeExtensionNode $node): string
    {
        $header = 'extend enum ' . $node->name->value . $this->printSdlDirectives($node->directives);

        if (count($node->values) === 0) {
            return $header;
        }

        $block = $this->printSdlBlock(
            $node->values,
            fn (EnumValueDefinitionNode $v) => $v->name->value . $this->printSdlDirectives($v->directives)
        );

        return $header . ' ' . $block;
    }

    private function printUnionTypeDefinition(UnionTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $header = 'union ' . $node->name->value . $this->printSdlDirectives($node->directives);

        if (count($node->types) === 0) {
            return $desc . $header;
        }

        $types = [];
        foreach ($node->types as $type) {
            $types[] = $type->name->value;
        }

        return $desc . $header . ' = ' . implode(' | ', $types);
    }

    private function printUnionTypeExtension(UnionTypeExtensionNode $node): string
    {
        $header = 'extend union ' . $node->name->value . $this->printSdlDirectives($node->directives);

        if (count($node->types) === 0) {
            return $header;
        }

        $types = [];
        foreach ($node->types as $type) {
            $types[] = $type->name->value;
        }

        return $header . ' = ' . implode(' | ', $types);
    }

    private function printScalarTypeDefinition(ScalarTypeDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);

        return $desc . 'scalar ' . $node->name->value . $this->printSdlDirectives($node->directives);
    }

    private function printScalarTypeExtension(ScalarTypeExtensionNode $node): string
    {
        return 'extend scalar ' . $node->name->value . $this->printSdlDirectives($node->directives);
    }

    private function printSchemaDefinition(SchemaDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $directives = $this->printSdlDirectives($node->directives);
        $indent = $this->config->indent;
        $opTypes = [];
        foreach ($node->operationTypes as $opType) {
            $opTypes[] = $indent . $opType->operation . ': ' . $opType->type->name->value;
        }
        $block = "{\n" . implode("\n", $opTypes) . "\n}";

        return $desc . 'schema' . $directives . ' ' . $block;
    }

    private function printSchemaExtension(SchemaExtensionNode $node): string
    {
        $directives = $this->printSdlDirectives($node->directives);

        if (count($node->operationTypes) === 0) {
            return 'extend schema' . $directives;
        }

        $indent = $this->config->indent;
        $opTypes = [];
        foreach ($node->operationTypes as $opType) {
            $opTypes[] = $indent . $opType->operation . ': ' . $opType->type->name->value;
        }
        $block = "{\n" . implode("\n", $opTypes) . "\n}";

        return 'extend schema' . $directives . ' ' . $block;
    }

    private function printDirectiveDefinition(DirectiveDefinitionNode $node): string
    {
        $desc = $this->printSdlDescription($node->description);
        $result = 'directive @' . $node->name->value;

        if (count($node->arguments) > 0) {
            $args = [];
            foreach ($node->arguments as $arg) {
                $args[] = $this->printInputValueDefinitionInline($arg);
            }
            $result .= '(' . implode(', ', $args) . ')';
        }

        if ($node->repeatable) {
            $result .= ' repeatable';
        }

        $locations = [];
        foreach ($node->locations as $location) {
            $locations[] = $location->value;
        }

        $result .= ' on ' . implode(' | ', $locations);

        return $desc . $result;
    }

    // -------------------------------------------------------------------------
    // Comment helpers
    // -------------------------------------------------------------------------

    /**
     * Collect leading comment lines immediately preceding this node's start token.
     * Returns them in top-to-bottom order without indentation.
     * Stops collecting when a comment is found on the same line as the non-comment
     * token before it (meaning it is a trailing inline comment of that token's node).
     *
     * @return list<string>
     */
    private function getLeadingComments(Node $node): array
    {
        if ($node->loc === null) {
            return [];
        }

        $comments = [];
        $token = $node->loc->startToken->prev;
        while ($token !== null && $token->kind === Token::COMMENT) {
            $prevToken = $token->prev;
            // If the token before this comment is a non-comment on the same line,
            // this comment is a trailing inline comment of that node — stop here.
            if ($prevToken !== null && $prevToken->kind !== Token::COMMENT && $prevToken->line === $token->line) {
                break;
            }
            array_unshift($comments, '#' . rtrim($token->value, "\n\r"));
            $token = $prevToken;
        }

        return $comments;
    }

    /**
     * Get the trailing inline comment on the same line as this node's end token.
     * Returns the comment string (prefixed with ' #') or empty string.
     */
    private function getTrailingComment(Node $node): string
    {
        if ($node->loc === null) {
            return '';
        }

        $nextToken = $node->loc->endToken->next;
        if (
            $nextToken !== null
            && $nextToken->kind === Token::COMMENT
            && $nextToken->line === $node->loc->endToken->line
        ) {
            return ' #' . rtrim($nextToken->value, "\n\r");
        }

        return '';
    }
}
