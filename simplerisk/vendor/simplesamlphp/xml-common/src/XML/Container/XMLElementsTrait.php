<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Container;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Attribute as XMLAttribute;
use SimpleSAML\XML\Chunk;
use SimpleSAML\XML\DOMDocumentFactory;
use SimpleSAML\XML\Type\LangValue;
use SimpleSAML\XMLSchema\Type\LanguageValue;
use SimpleSAML\XMLSchema\Type\StringValue;

use function array_key_exists;
use function sprintf;

/**
 * One-time instantiation of common elements in XML, for re-use in unit-tests.
 *
 * @package simplesamlphp\xml-common
 * @phpstan-ignore trait.unused
 */
trait XMLElementsTrait
{
    protected const string NAMESPACE = 'urn:x-simplesamlphp:namespace';


    protected ?Chunk $chunk = null;

    /** @var array<string, \SimpleSAML\XML\Type\LangValue> $lang */
    protected array $lang = [];

    /** @var array<string, \SimpleSAML\XMLSchema\Type\LanguageValue> $language */
    protected array $language = [];

    /** @var array<positive-int, \SimpleSAML\XML\Attribute> $attribute */
    protected array $attribute = [];


    public function getChunk(): Chunk
    {
        if ($this->chunk === null) {
            $this->chunk = new Chunk(DOMDocumentFactory::fromString(
                '<ssp:Chunk xmlns:ssp="urn:x-simplesamlphp:namespace">Some</ssp:Chunk>',
            )->documentElement);
        }

        return $this->chunk;
    }


    public function getLangValue(string $lang = 'en'): LangValue
    {
        if (array_key_exists($lang, $this->lang)) {
            $this->lang[$lang] = LangValue::fromString($lang);
        }

        return $this->lang[$lang];
    }


    public function getLanguageValue(string $language = 'en'): LanguageValue
    {
        if (array_key_exists($language, $this->language)) {
            $this->language[$language] = LanguageValue::fromString($language);
        }

        return $this->language[$language];
    }


    /** @param positive-int $x */
    public function getXMLAttribute(int $x = 1): XMLAttribute
    {
        Assert::positiveInteger($x);

        if (!array_key_exists($x, $this->attribute)) {
            $this->attribute[$x] = new XMLAttribute(
                static::NAMESPACE,
                'ssp',
                sprintf('attr%d', $x),
                StringValue::fromString(sprintf('value%d', $x)),
            );
        }

        return $this->attribute[$x];
    }
}
