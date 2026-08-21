<?php
declare(strict_types = 1);

namespace Gettext\Scanner;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeVisitor;

class PhpNodeVisitor implements NodeVisitor
{
    protected ?array $validFunctions;
    protected string $filename;
    protected array $functions = [];

    /** @var Comment[] */
    protected array $bufferComments = [];

    public function __construct(string $filename, ?array $validFunctions = null)
    {
        $this->filename = $filename;
        $this->validFunctions = $validFunctions;
    }

    public function beforeTraverse(array $nodes)
    {
        return null;
    }

    public function enterNode(Node $node)
    {
        switch ($node->getType()) {
            case 'Expr_MethodCall':
            case 'Expr_FuncCall':
            case 'Expr_StaticCall':
                $name = static::getName($node);

                if ($name && ($this->validFunctions === null || in_array($name, $this->validFunctions))) {
                    $this->functions[] = $this->createFunction($node);
                } elseif ($node->getComments()) {
                    $this->bufferComments[] = $node;
                }
                break;

            case 'Stmt_Expression':
            case 'Stmt_Echo':
            case 'Stmt_Return':
            case 'Expr_Print':
            case 'Expr_Assign':
                $this->bufferComments[] = $node;
                break;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        return null;
    }

    public function afterTraverse(array $nodes)
    {
        return null;
    }

    public function getFunctions(): array
    {
        return $this->functions;
    }

    /**
     * @param FuncCall|MethodCall $node
     */
    protected function createFunction(Expr $node): ParsedFunction
    {
        $function = new ParsedFunction(
            static::getName($node),
            $this->filename,
            $node->getStartLine(),
            $node->getEndLine()
        );

        foreach ($node->getComments() as $comment) {
            $function->addComment(static::getComment($comment));
        }

        if ($this->bufferComments) {
            foreach ($this->bufferComments as $bufferComment) {
                if ($bufferComment->getStartLine() === $node->getStartLine()) {
                    foreach ($bufferComment->getComments() as $comment) {
                        $function->addComment(static::getComment($comment));
                    }
                }
            }
        }

        $this->bufferComments = [];

        foreach ($node->args as $argument) {
            $value = $argument->value;

            foreach ($argument->getComments() as $comment) {
                $function->addComment(static::getComment($comment));
            }

            $function->addArgument(static::getValue($value));
        }

        return $function;
    }

    protected static function getComment(Comment $comment): string
    {
        $text = $comment->getReformattedText();

        $lines = array_map(function ($line) {
            $line = ltrim($line, "#*/ \t");
            $line = rtrim($line, "#*/ \t");
            return trim($line);
        }, explode("\n", $text));

        return trim(implode("\n", $lines));
    }

    protected static function getName(Node $node): ?string
    {
        $name = $node->name;

        if ($name instanceof Name) {
            return $name->getLast();
        }

        if ($name instanceof Identifier) {
            return (string) $name;
        }

        return null;
    }

    protected static function getValue(Expr $value)
    {
        $type = $value->getType();

        switch ($type) {
            case 'Scalar_String':
            case 'Scalar_Int':
            case 'Scalar_Float':
                return $value->value;
            case 'Expr_BinaryOp_Concat':
                $values = [];
                foreach ($value->getSubNodeNames() as $name) {
                    $values[] = static::getValue($value->$name);
                }
                return implode('', $values);
            case 'Expr_Array':
                $arr = [];

                foreach ($value->items as $item) {
                    $value = static::getValue($item->value);

                    if ($item->key === null) {
                        $arr[] = $value;
                    } else {
                        $key = static::getValue($item->key);
                        $arr[$key] = $value;
                    }
                }

                return $arr;
        }
    }
}
