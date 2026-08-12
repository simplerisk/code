<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc;

use DOMElement;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XML\ExtendableAttributesTrait;
use SimpleSAML\XML\ExtendableElementTrait;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\XML\Constants\NS;
use SimpleSAML\XMLSecurity\Assert\Assert;

use function strval;

/**
 * Class representing <xenc:EncryptionPropertyType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractEncryptionPropertyType extends AbstractXencElement implements
    SchemaValidatableElementInterface
{
    use ExtendableAttributesTrait;
    use ExtendableElementTrait;
    use SchemaValidatableElementTrait;


    /** The namespace-attribute for the xs:anyAttribute element */
    public const array XS_ANY_ATTR_NAMESPACE = [C::NS_XML];

    /** The namespace-attribute for the xs:any element */
    public const string XS_ANY_ELT_NAMESPACE = NS::OTHER;


    /**
     * EncryptionProperty constructor.
     *
     * @param \SimpleSAML\XML\SerializableElementInterface[] $children
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $Target
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     * @param \SimpleSAML\XML\Attribute[] $namespacedAttributes
     */
    final public function __construct(
        array $children,
        protected ?AnyURIValue $Target = null,
        protected ?IDValue $Id = null,
        array $namespacedAttributes = [],
    ) {
        Assert::minCount($children, 1, MissingElementException::class);

        $this->setElements($children);
        $this->setAttributesNS($namespacedAttributes);
    }


    /**
     * Get the value of the $Target property.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getTarget(): ?AnyURIValue
    {
        return $this->Target;
    }


    /**
     * Get the value of the $Id property.
     *
     * @return \SimpleSAML\XMLSchema\Type\IDValue
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
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
            self::getChildElementsFromXML($xml),
            self::getOptionalAttribute($xml, 'Target', AnyURIValue::class, null),
            self::getOptionalAttribute($xml, 'Id', IDValue::class, null),
            self::getAttributesNSFromXML($xml),
        );
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getTarget() !== null) {
            $e->setAttribute('Target', strval($this->getTarget()));
        }

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        foreach ($this->getAttributesNS() as $attr) {
            $attr->toXML($e);
        }

        foreach ($this->getElements() as $child) {
            if (!$child->isEmptyElement()) {
                $child->toXML($e);
            }
        }

        return $e;
    }
}
