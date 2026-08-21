<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Container;

use SimpleSAML\XML\Attribute as XMLAttribute;
use SimpleSAML\XML\Chunk;
use SimpleSAML\XML\Type\LangValue;
use SimpleSAML\XMLSchema\Type\LanguageValue;
use SimpleSAML\XMLSchema\XML\Appinfo;

/**
 * Class \SimpleSAML\XML\Container\AbstractTestContainer
 */
abstract class AbstractTestContainer implements TestContainerInterface
{
    abstract public function getAppinfo(int $x = 1): Appinfo;


    abstract public function getChunk(): Chunk;


    abstract public function getLangValue(string $lang = 'en'): LangValue;


    abstract public function getLanguageValue(string $language = 'en'): LanguageValue;


    abstract public function getXMLAttribute(int $x = 1): XMLAttribute;
}
