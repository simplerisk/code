<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use DOMElement;
use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\MissingElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\HexBinaryValue;
use SimpleSAML\XMLSecurity\Assert\Assert;
use SimpleSAML\XMLSecurity\XML\ds\DigestMethod;

use function array_pop;
use function strval;

/**
 * Class representing <xenc11:ConcatKDFParamsType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractConcatKDFParamsType extends AbstractXenc11Element implements
    SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * ConcatKDFParams constructor.
     *
     * @param \SimpleSAML\XMLSecurity\XML\ds\DigestMethod $digestMethod
     * @param \SimpleSAML\XMLSchema\Type\HexBinaryValue|null $AlgorithmID
     * @param \SimpleSAML\XMLSchema\Type\HexBinaryValue|null $PartyUInfo
     * @param \SimpleSAML\XMLSchema\Type\HexBinaryValue|null $PartyVInfo
     * @param \SimpleSAML\XMLSchema\Type\HexBinaryValue|null $SuppPubInfo
     * @param \SimpleSAML\XMLSchema\Type\HexBinaryValue|null $SuppPrivInfo
     */
    final public function __construct(
        protected DigestMethod $digestMethod,
        protected ?HexBinaryValue $AlgorithmID = null,
        protected ?HexBinaryValue $PartyUInfo = null,
        protected ?HexBinaryValue $PartyVInfo = null,
        protected ?HexBinaryValue $SuppPubInfo = null,
        protected ?HexBinaryValue $SuppPrivInfo = null,
    ) {
    }


    /**
     * Get the value of the $digestMethod property.
     *
     * @return \SimpleSAML\XMLSecurity\XML\ds\DigestMethod
     */
    public function getDigestMethod(): DigestMethod
    {
        return $this->digestMethod;
    }


    /**
     * Get the value of the $AlgorithmID property.
     *
     * @return \SimpleSAML\XMLSchema\Type\HexBinaryValue|null
     */
    public function getAlgorithmID(): ?HexBinaryValue
    {
        return $this->AlgorithmID;
    }


    /**
     * Get the value of the $PartyUInfo property.
     *
     * @return \SimpleSAML\XMLSchema\Type\HexBinaryValue|null
     */
    public function getPartyUInfo(): ?HexBinaryValue
    {
        return $this->PartyUInfo;
    }


    /**
     * Get the value of the $PartyVInfo property.
     *
     * @return \SimpleSAML\XMLSchema\Type\HexBinaryValue|null
     */
    public function getPartyVInfo(): ?HexBinaryValue
    {
        return $this->PartyVInfo;
    }


    /**
     * Get the value of the $SuppPubInfo property.
     *
     * @return \SimpleSAML\XMLSchema\Type\HexBinaryValue|null
     */
    public function getSuppPubInfo(): ?HexBinaryValue
    {
        return $this->SuppPubInfo;
    }


    /**
     * Get the value of the $SuppPrivInfo property.
     *
     * @return \SimpleSAML\XMLSchema\Type\HexBinaryValue|null
     */
    public function getSuppPrivInfo(): ?HexBinaryValue
    {
        return $this->SuppPrivInfo;
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

        $digestMethod = DigestMethod::getChildrenOfClass($xml);
        Assert::minCount($digestMethod, 1, MissingElementException::class);
        Assert::maxCount($digestMethod, 1, TooManyElementsException::class);

        return new static(
            array_pop($digestMethod),
            self::getOptionalAttribute($xml, 'AlgorithmID', HexBinaryValue::class, null),
            self::getOptionalAttribute($xml, 'PartyUInfo', HexBinaryValue::class, null),
            self::getOptionalAttribute($xml, 'PartyVInfo', HexBinaryValue::class, null),
            self::getOptionalAttribute($xml, 'SuppPubInfo', HexBinaryValue::class, null),
            self::getOptionalAttribute($xml, 'SuppPrivInfo', HexBinaryValue::class, null),
        );
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);

        if ($this->getAlgorithmID() !== null) {
            $e->setAttribute('AlgorithmID', strval($this->getAlgorithmID()));
        }

        if ($this->getPartyUInfo() !== null) {
            $e->setAttribute('PartyUInfo', strval($this->getPartyUInfo()));
        }

        if ($this->getPartyVInfo() !== null) {
            $e->setAttribute('PartyVInfo', strval($this->getPartyVInfo()));
        }

        if ($this->getSuppPubInfo() !== null) {
            $e->setAttribute('SuppPubInfo', strval($this->getSuppPubInfo()));
        }

        if ($this->getSuppPrivInfo() !== null) {
            $e->setAttribute('SuppPrivInfo', strval($this->getSuppPrivInfo()));
        }

        $this->getDigestMethod()->toXML($e);

        return $e;
    }
}
