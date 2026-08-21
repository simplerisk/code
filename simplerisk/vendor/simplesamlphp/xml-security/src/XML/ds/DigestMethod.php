<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\Exception\InvalidArgumentException;

use function strval;

/**
 * Class representing a ds:DigestMethod element.
 *
 * @package simplesamlphp/xml-security
 */
final class DigestMethod extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    /** The namespace-attribute for the xs:any element */
    public const string XS_ANY_ELT_NAMESPACE = NS::OTHER;


    /**
     * Initialize a DigestMethod element.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $Algorithm
     * @param list<\SimpleSAML\XML\SerializableElementInterface> $elements
     */
    public function __construct(
        protected AnyURIValue $Algorithm,
        array $elements = [],
    ) {
        Assert::oneOf(
            $Algorithm->getValue(),
            array_keys(C::$DIGEST_ALGORITHMS),
            'Invalid digest method: %s',
            InvalidArgumentException::class,
        );

        $this->setElements($elements);
    }


    /**
     * Collect the value of the Algorithm-property
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getAlgorithm(): AnyURIValue
    {
        return $this->Algorithm;
    }


    /**
     * Convert XML into a DigestMethod
     *
     * @param \DOMElement $xml The XML element we should load
     * @return static
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'DigestMethod', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, DigestMethod::NS, InvalidDOMElementException::class);

        $Algorithm = self::getAttribute($xml, 'Algorithm', AnyURIValue::class);
        $elements = self::getChildElementsFromXML($xml);

        return new static($Algorithm, $elements);
    }


    /**
     * Convert this DigestMethod element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this DigestMethod element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('Algorithm', strval($this->getAlgorithm()));

        foreach ($this->elements as $elt) {
            if (!$elt->isEmptyElement()) {
                $elt->toXML($e);
            }
        }

        return $e;
    }
}
