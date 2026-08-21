<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\dsig11;

use DOMElement;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

use function strval;

/**
 * Abstract class representing a dsig11:ECValidationDataType
 *
 * @package simplesaml/xml-security
 */
abstract class AbstractECValidationDataType extends AbstractDsig11Element
{
    /**
     * Initialize a ECValidationDataType element.
     *
     * @param \SimpleSAML\XMLSecurity\XML\dsig11\Seed $seed
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $hashAlgorithm
     */
    public function __construct(
        protected Seed $seed,
        protected AnyURIValue $hashAlgorithm,
    ) {
    }


    /**
     * Collect the value of the seed-property
     *
     * @return \SimpleSAML\XMLSecurity\XML\dsig11\Seed
     */
    public function getSeed(): Seed
    {
        return $this->seed;
    }


    /**
     * Collect the value of the hashAlgorithm-property
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getHashAlgorithm(): AnyURIValue
    {
        return $this->hashAlgorithm;
    }


    /**
     * Convert this ECValidationDataType element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this ECValidationDataType element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('hashAlgorithm', strval($this->getHashAlgorithm()));

        $this->getSeed()->toXML($e);

        return $e;
    }
}
