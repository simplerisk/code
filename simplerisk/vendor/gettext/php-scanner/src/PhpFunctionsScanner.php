<?php
declare(strict_types = 1);

namespace Gettext\Scanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class PhpFunctionsScanner implements FunctionsScannerInterface
{
    protected Parser $parser;
    protected ?array $validFunctions;

    public function __construct(?array $validFunctions = null, ?Parser $parser = null)
    {
        $this->validFunctions = $validFunctions;
        $this->parser = $parser ?: (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function scan(string $code, string $filename): array
    {
        $ast = $this->parser->parse($code);

        if (empty($ast)) {
            return [];
        }

        $visitor = $this->createNodeVisitor($filename);

        $traverser = new NodeTraverser($visitor);
        $traverser->traverse($ast);

        return $visitor->getFunctions();
    }

    protected function createNodeVisitor(string $filename): PhpNodeVisitor
    {
        return new PhpNodeVisitor($filename, $this->validFunctions);
    }
}
