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
use SimpleSAML\XMLSecurity\Constants as C;
use SimpleSAML\XMLSecurity\XML\ec\InclusiveNamespaces;
use SimpleSAML\XPath\Constants as XPATH_C;

use function array_pop;
use function strval;

/**
 * Class representing transforms.
 *
 * @package simplesamlphp/xml-security
 */
class Transform extends AbstractDsElement implements SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * Initialize the Transform element.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $algorithm
     * @param \SimpleSAML\XMLSecurity\XML\ds\XPath|null $xpath
     * @param \SimpleSAML\XMLSecurity\XML\ec\InclusiveNamespaces|null $inclusiveNamespaces
     */
    final public function __construct(
        protected AnyURIValue $algorithm,
        protected ?XPath $xpath = null,
        protected ?InclusiveNamespaces $inclusiveNamespaces = null,
    ) {
        if ($xpath !== null) {
            Assert::nullOrEq(
                $algorithm->getValue(),
                XPATH_C::XPATH10_URI,
                sprintf('Transform algorithm "%s" required if XPath provided.', XPATH_C::XPATH10_URI),
            );
        }

        if ($inclusiveNamespaces !== null) {
            Assert::oneOf(
                $algorithm->getValue(),
                [
                    C::C14N_INCLUSIVE_WITH_COMMENTS,
                    C::C14N_EXCLUSIVE_WITHOUT_COMMENTS,
                ],
                sprintf(
                    'Transform algorithm "%s" or "%s" required if InclusiveNamespaces provided.',
                    C::C14N_EXCLUSIVE_WITH_COMMENTS,
                    C::C14N_EXCLUSIVE_WITHOUT_COMMENTS,
                ),
            );
        }
    }


    /**
     * Get the algorithm associated with this transform.
     *
     * @return \SimpleSAML\XMLSchema\Type\AnyURIValue
     */
    public function getAlgorithm(): AnyURIValue
    {
        return $this->algorithm;
    }


    /**
     * Get the XPath associated with this transform.
     *
     * @return \SimpleSAML\XMLSecurity\XML\ds\XPath|null
     */
    public function getXPath(): ?XPath
    {
        return $this->xpath;
    }


    /**
     * Get the InclusiveNamespaces associated with this transform.
     *
     * @return \SimpleSAML\XMLSecurity\XML\ec\InclusiveNamespaces|null
     */
    public function getInclusiveNamespaces(): ?InclusiveNamespaces
    {
        return $this->inclusiveNamespaces;
    }


    /**
     * Convert XML into a Transform element.
     *
     * @param \DOMElement $xml The XML element we should load.
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'Transform', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, Transform::NS, InvalidDOMElementException::class);

        $xpath = XPath::getChildrenOfClass($xml);
        Assert::maxCount($xpath, 1, 'Only one XPath element supported per Transform.', TooManyElementsException::class);

        $prefixes = InclusiveNamespaces::getChildrenOfClass($xml);
        Assert::maxCount(
            $prefixes,
            1,
            'Only one InclusiveNamespaces element supported per Transform.',
            TooManyElementsException::class,
        );

        return new static(
            self::getAttribute($xml, 'Algorithm', AnyURIValue::class),
            array_pop($xpath),
            array_pop($prefixes),
        );
    }


    /**
     * Convert this Transform element to XML.
     *
     * @param \DOMElement|null $parent The element we should append this Transform element to.
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = $this->instantiateParentElement($parent);
        $e->setAttribute('Algorithm', strval($this->getAlgorithm()));

        switch ($this->getAlgorithm()) {
            case XPATH_C::XPATH10_URI:
                $this->getXPath()?->toXML($e);
                break;
            case C::C14N_EXCLUSIVE_WITH_COMMENTS:
            case C::C14N_EXCLUSIVE_WITHOUT_COMMENTS:
                $this->getInclusiveNamespaces()?->toXML($e);
                break;
        }

        return $e;
    }
}
