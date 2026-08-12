<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSecurity\Assert\Assert;

use function strval;

/**
 * Class representing a ds:Manifest element.
 *
 * @package simplesamlphp/xml-security
 */
final class Manifest extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize a ds:Manifest
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\Reference[] $references
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     */
    public function __construct(
        protected array $references,
        protected ?IDValue $Id = null,
    ) {
        Assert::maxCount($references, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOf($references, Reference::class);
    }


    /**
     * @return \SimpleSAML\XMLSecurity\XML\ds\Reference[]
     */
    public function getReferences(): array
    {
        return $this->references;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
    }


    /**
     * Convert XML into a Manifest element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Manifest', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, Manifest::NS, InvalidDOMElementException::class);

        $Id = self::getOptionalAttribute($xml, 'Id', IDValue::class, null);

        $references = Reference::getChildrenOfClass($xml);
        Assert::minCount(
            $references,
            1,
            'A <ds:Manifest> must contain at least one <ds:Reference>.',
            MissingElementException::class,
        );

        return new static(
            $references,
            $Id,
        );
    }


    /**
     * Convert this Manifest element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this Manifest element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }

        foreach ($this->getReferences() as $reference) {
            $reference->toXML($e);
        }

        return $e;
    }
}
