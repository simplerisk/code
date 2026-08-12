<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use DOMElement;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

use function strval;

/**
 * Class representing <xenc11:AlgorithmIdentifierType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractAlgorithmIdentifierType extends AbstractXenc11Element
{
    /**
     * AlgorithmIdentifierType constructor.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $Algorithm
     * @param \SimpleSAML\XMLSecurity\XML\xenc11\Parameters|null $parameters
     */
    public function __construct(
        protected AnyURIValue $Algorithm,
        protected ?Parameters $parameters = null,
    ) {
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
     * Get the value of the $parameters property.
     *
     * @return \SimpleSAML\XMLSecurity\XML\xenc11\Parameters|null
     */
    public function getParameters(): ?Parameters
    {
        return $this->parameters;
    }


    /**
     * @inheritDoc
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('Algorithm', strval($this->getAlgorithm()));

        if ($this->getParameters() !== null) {
            if (!$this->getParameters()->isEmptyElement()) {
                $this->getParameters()->toXML($e);
            }
        }

        return $e;
    }
}
