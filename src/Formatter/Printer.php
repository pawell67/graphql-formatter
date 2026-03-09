<?php
declare(strict_types=1);
namespace GraphQLFormatter\Formatter;

use GraphQLFormatter\Config\FormatterConfig;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;

final class Printer
{
    public function __construct(private readonly FormatterConfig $config) {}

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
        return match (true) {
            $node instanceof OperationDefinitionNode => $this->printOperation($node),
            $node instanceof FragmentDefinitionNode  => $this->printFragment($node),
            default => throw new \RuntimeException('Unsupported definition node: ' . $node::class),
        };
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

        return $header . ' ' . $this->printSelectionSet($node->selectionSet, $depth);
    }

    private function printFragment(FragmentDefinitionNode $node): string
    {
        $typeCondition = $node->typeCondition->name->value;
        $name = $node->name->value;
        $header = "fragment {$name} on {$typeCondition}";
        foreach ($node->directives as $directive) {
            $header .= ' @' . $directive->name->value;
        }
        return $header . ' ' . $this->printSelectionSet($node->selectionSet, 0);
    }

    private function printSelectionSet(SelectionSetNode $node, int $depth): string
    {
        $indent = str_repeat($this->config->indent, $depth + 1);
        $closingIndent = str_repeat($this->config->indent, $depth);

        $selections = [];
        foreach ($node->selections as $selection) {
            $selections[] = $indent . $this->printSelectionNode($selection, $depth + 1);
        }

        return "{\n" . implode("\n", $selections) . "\n" . $closingIndent . '}';
    }

    private function printSelectionNode(Node $node, int $depth): string
    {
        return match (true) {
            $node instanceof FieldNode            => $this->printField($node, $depth),
            $node instanceof FragmentSpreadNode   => $this->printFragmentSpread($node),
            $node instanceof InlineFragmentNode   => $this->printInlineFragment($node, $depth),
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

        if ($node->selectionSet !== null) {
            $result .= ' ' . $this->printSelectionSet($node->selectionSet, $depth);
        }

        return $result;
    }

    private function printFragmentSpread(FragmentSpreadNode $node): string
    {
        return '...' . $node->name->value;
    }

    private function printInlineFragment(InlineFragmentNode $node, int $depth): string
    {
        $typeCondition = $node->typeCondition !== null
            ? 'on ' . $node->typeCondition->name->value . ' '
            : '';
        return '... ' . $typeCondition . $this->printSelectionSet($node->selectionSet, $depth);
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
                $rendered[] = $this->printArgument($arg, $depth);
            }
            $inline = '(' . implode(', ', $rendered) . ')';

            // Check total line width: indent + fieldName + inline args
            $currentIndent = str_repeat($this->config->indent, $depth - 1);
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
        $indent = str_repeat($this->config->indent, $depth);
        $closingIndent = str_repeat($this->config->indent, $depth - 1);

        $lines = [];
        foreach ($args as $arg) {
            $lines[] = $indent . $this->printArgument($arg, $depth);
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
            $value instanceof IntValueNode     => $value->value,
            $value instanceof FloatValueNode   => $value->value,
            $value instanceof BooleanValueNode => $value->value ? 'true' : 'false',
            $value instanceof NullValueNode    => 'null',
            $value instanceof EnumValueNode    => $value->value,
            $value instanceof StringValueNode  => '"' . addslashes($value->value) . '"',
            $value instanceof VariableNode     => '$' . $value->name->value,
            $value instanceof ListValueNode    => $this->printListValue($value, $depth),
            $value instanceof ObjectValueNode  => $this->printObjectValue($value, $depth),
            default => throw new \RuntimeException('Unsupported value node: ' . $value::class),
        };
    }

    private function printListValue(ListValueNode $node, int $depth): string
    {
        $values = [];
        foreach ($node->values as $value) {
            $values[] = $this->printValue($value, $depth);
        }
        return '[' . implode(', ', $values) . ']';
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
}
