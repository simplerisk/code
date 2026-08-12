<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

use function strval;

/**
 * Class representing a ds:RetrievalMethod element.
 *
 * @package simplesamlphp/xml-security
 */
final class RetrievalMethod extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize a ds:RetrievalMethod
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\Transforms|null $transforms
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $URI
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $Type
     */
    final public function __construct(
        protected ?Transforms $transforms,
        protected AnyURIValue $URI,
        protected ?AnyURIValue $Type = null,
    ) {
    }


    /**
     * @return \SimpleSAML\XMLSecurity\XML\ds\Transforms|null
     */
    public function getTransforms(): ?Transforms
    {
        return $this->transforms;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getURI(): AnyURIValue
    {
        return $this->URI;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getType(): ?AnyURIValue
    {
        return $this->Type;
    }


    /**
     * Convert XML into a RetrievalMethod element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'RetrievalMethod', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, RetrievalMethod::NS, InvalidDOMElementException::class);

        $URI = self::getAttribute($xml, 'URI', AnyURIValue::class);
        $Type = self::getOptionalAttribute($xml, 'Type', AnyURIValue::class, null);

        $transforms = Transforms::getChildrenOfClass($xml);
        Assert::maxCount(
            $transforms,
            1,
            'A <ds:RetrievalMethod> may contain a maximum of one <ds:Transforms>.',
            TooManyElementsException::class,
        );

        return new static(
            array_pop($transforms),
            $URI,
            $Type,
        );
    }


    /**
     * Convert this RetrievalMethod element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this RetrievalMethod element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('URI', strval($this->getURI()));

        if ($this->getType() !== null) {
            $e->setAttribute('Type', strval($this->getType()));
        }

        $this->getTransforms()?->toXML($e);

        return $e;
    }
}
