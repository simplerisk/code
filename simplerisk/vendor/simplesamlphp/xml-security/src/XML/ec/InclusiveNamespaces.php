<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ec;

use DOMElement;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Type\NMTokensValue;

use function strval;

/**
 * Class implementing InclusiveNamespaces
 *
 * @package simplesamlphp/xml-security
 */
class InclusiveNamespaces extends AbstractEcElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize the InclusiveNamespaces element.
     *
     * @param \SimpleSAML\XMLSchema\Type\NMTokensValue|null $prefixes
     */
    final public function __construct(
        protected ?NMTokensValue $prefixes,
    ) {
    }


    /**
     * Get the prefixes specified by this element.
     *
     * @return \SimpleSAML\XMLSchema\Type\NMTokensValue|null
     */
    public function getPrefixes(): ?NMTokensValue
    {
        return $this->prefixes;
    }


    /**
     * Convert XML into an InclusiveNamespaces element.
     *
     * @param \DOMElement $xml The XML element we should load.
     */
    public static function fromXML(DOMElement $xml): static
    {
        return new static(
            self::getOptionalAttribute($xml, 'PrefixList', NMTokensValue::class, null),
        );
    }


    /**
     * Convert this InclusiveNamespaces to XML.
     *
     * @param \DOMElement|null $parent The element we should append this InclusiveNamespaces to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getPrefixes() !== null) {
            $e->setAttribute('PrefixList', strval($this->getPrefixes()));
        }

        return $e;
    }
}
