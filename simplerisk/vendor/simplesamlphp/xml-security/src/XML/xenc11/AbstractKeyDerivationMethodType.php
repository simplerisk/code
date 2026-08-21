<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;

use function strval;

/**
 * Class representing <xenc11:KeyDerivationMethodType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractKeyDerivationMethodType extends AbstractXenc11Element implements
    SchemaValidatableElementInterface
{
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    /** The namespace-attribute for the xs:any element */
    public const string XS_ANY_ELT_NAMESPACE = NS::ANY;


    /**
     * KeyDerivationMethod constructor.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $Algorithm
     * @param \SimpleSAML\XML\SerializableElementInterface[] $children
     */
    final public function __construct(
        protected AnyURIValue $Algorithm,
        array $children,
    ) {
        $this->setElements($children);
    }


    /**
     * Get the value of the $Algorithm property.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getAlgorithm(): AnyURIValue
    {
        return $this->Algorithm;
    }


    /**
     * @inheritDoc
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, static::getLocalName(), InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, static::getNamespaceURI(), InvalidDOMElementException::class);

        return new static(
            self::getAttribute($xml, 'Algorithm', AnyURIValue::class),
            self::getChildElementsFromXML($xml),
        );
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('Algorithm', strval($this->getAlgorithm()));

        foreach ($this->getElements() as $child) {
            if (!$child->isEmptyElement()) {
                $child->toXML($e);
            }
        }

        return $e;
    }
}
