<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;

use function strval;

/**
 * Class representing the Annotation-element.
 *
 * @package simplesamlphp/xml-common
 */
final class Annotation extends AbstractOpenAttrs implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'annotation';


    /**
     * Annotation constructor
     *
     * @param array<\SimpleSAML\XMLSchema\XML\Appinfo> $appinfo
     * @param array<\SimpleSAML\XMLSchema\XML\Documentation> $documentation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected array $appinfo,
        protected array $documentation,
        protected ?IDValue $id,
        array $namespacedAttributes = [],
    ) {
        Assert::allIsInstanceOf($appinfo, Appinfo::class, SchemaViolationException::class);
        Assert::allIsInstanceOf($documentation, Documentation::class, SchemaViolationException::class);

        parent::__construct($namespacedAttributes);
    }


    /**
     * Collect the value of the appinfo-property
     *
     * @return \SimpleSAML\XMLSchema\XML\Appinfo[]
     */
    public function getAppinfo(): array
    {
        return $this->appinfo;
    }


    /**
     * Collect the value of the documentation-property
     *
     * @return \SimpleSAML\XMLSchema\XML\Documentation[]
     */
    public function getDocumentation(): array
    {
        return $this->documentation;
    }


    /**
     * Collect the value of the id-property
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->id;
    }


    /**
     * Test if an object, at the state it's in, would produce an empty XML-element
     *
     * @return bool
     */
    public function isEmptyElement(): bool
    {
        return parent::isEmptyElement() &&
            empty($this->getAppinfo()) &&
            empty($this->getDocumentation()) &&
            empty($this->id);
    }


    /**
     * Create a class from XML
     *
     * @param \DOMElement $xml
     * @return static
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getLocalName(), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::NS, InvalidDOMElementException::class);

        return new static(
            Appinfo::getChildrenOfClass($xml),
            Documentation::getChildrenOfClass($xml),
            self::getOptionalAttribute($xml, 'id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }


    /**
     * Add this Annotation to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this Annotation to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getId() !== null) {
            $e->setAttribute('id', strval($this->getId()));
        }

        foreach ($this->getAppinfo() as $appinfo) {
            $appinfo->toXML($e);
        }

        foreach ($this->getDocumentation() as $documentation) {
            $documentation->toXML($e);
        }

        return $e;
    }
}
