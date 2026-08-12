<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSecurity\Assert\Assert;

use function array_pop;
use function strval;

/**
 * Class representing a ds:Reference element.
 *
 * @package simplesamlphp/xml-security
 */
final class Reference extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize a ds:Reference
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\DigestMethod $digestMethod
     * @param \SimpleSAML\XMLSecurity\XML\ds\DigestValue $digestValue
     * @param \SimpleSAML\XMLSecurity\XML\ds\Transforms|null $transforms
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $Id
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $Type
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue|null $URI
     */
    public function __construct(
        protected DigestMethod $digestMethod,
        protected DigestValue $digestValue,
        protected ?Transforms $transforms = null,
        protected ?IDValue $Id = null,
        protected ?AnyURIValue $Type = null,
        protected ?AnyURIValue $URI = null,
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
     * @return \SimpleSAML\XMLSecurity\XML\ds\DigestMethod
     */
    public function getDigestMethod(): DigestMethod
    {
        return $this->digestMethod;
    }


    /**
     * @return \SimpleSAML\XMLSecurity\XML\ds\DigestValue
     */
    public function getDigestValue(): DigestValue
    {
        return $this->digestValue;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->Id;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getType(): ?AnyURIValue
    {
        return $this->Type;
    }


    /**
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue|null
     */
    public function getURI(): ?AnyURIValue
    {
        return $this->URI;
    }


    /**
     * Determine whether this is an xpointer reference.
     */
    public function isXPointer(): bool
    {
        return !is_null($this->getURI()) && str_starts_with(strval($this->getURI()), '#xpointer');
    }


    /**
     * Convert XML into a Reference element
     *
     * @param \DOMElement $xml The XML element we should load
     *
     * @throws \SimpleSAML\XMLSchema\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Reference', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, Reference::NS, InvalidDOMElementException::class);

        $Id = self::getOptionalAttribute($xml, 'Id', IDValue::class, null);
        $Type = self::getOptionalAttribute($xml, 'Type', AnyURIValue::class, null);
        $URI = self::getOptionalAttribute($xml, 'URI', AnyURIValue::class, null);

        $transforms = Transforms::getChildrenOfClass($xml);
        Assert::maxCount(
            $transforms,
            1,
            'A <ds:Reference> may contain just one <ds:Transforms>.',
            TooManyElementsException::class,
        );

        $digestMethod = DigestMethod::getChildrenOfClass($xml);
        Assert::count(
            $digestMethod,
            1,
            'A <ds:Reference> must contain a <ds:DigestMethod>.',
            MissingElementException::class,
        );

        $digestValue = DigestValue::getChildrenOfClass($xml);
        Assert::count(
            $digestValue,
            1,
            'A <ds:Reference> must contain a <ds:DigestValue>.',
            MissingElementException::class,
        );

        return new static(
            array_pop($digestMethod),
            array_pop($digestValue),
            empty($transforms) ? null : array_pop($transforms),
            $Id,
            $Type,
            $URI,
        );
    }


    /**
     * Convert this Reference element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this Reference element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        if ($this->getId() !== null) {
            $e->setAttribute('Id', strval($this->getId()));
        }
        if ($this->getType() !== null) {
            $e->setAttribute('Type', strval($this->getType()));
        }
        if ($this->getURI() !== null) {
            $e->setAttribute('URI', strval($this->getURI()));
        }

        $this->getTransforms()?->toXML($e);
        $this->getDigestMethod()->toXML($e);
        $this->getDigestValue()->toXML($e);

        return $e;
    }
}
