<?php
declare(strict_types=1);
namespace GraphQLFormatter\Formatter;

use GraphQLFormatter\Config\FormatterConfig;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;

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
            $node instanceof FieldNode          => $this->printField($node, $depth),
            $node instanceof FragmentSpreadNode => $this->printFragmentSpread($node),
            default => throw new \RuntimeException('Unsupported selection node: ' . $node::class),
        };
    }

    private function printField(FieldNode $node, int $depth): string
    {
        $prefix = $node->alias !== null ? $node->alias->value . ': ' : '';
        $name = $node->name->value;
        $result = $prefix . $name;

        if ($node->selectionSet !== null) {
            $result .= ' ' . $this->printSelectionSet($node->selectionSet, $depth);
        }

        return $result;
    }

    private function printFragmentSpread(FragmentSpreadNode $node): string
    {
        return '...' . $node->name->value;
    }
}
