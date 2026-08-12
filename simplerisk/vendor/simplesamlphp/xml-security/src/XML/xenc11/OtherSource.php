<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Exception\TooManyElementsException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

use function array_pop;

/**
 * A class implementing the xenc11:OtherSource element.
 *
 * @package simplesamlphp/xml-security
 */
final class OtherSource extends AbstractAlgorithmIdentifierType
{
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

        $parameter = Parameters::getChildrenOfClass($xml);
        Assert::maxCount($parameter, 1, TooManyElementsException::class);

        return new static(
            self::getAttribute($xml, 'Algorithm', AnyURIValue::class),
            array_pop($parameter),
        );
    }
}
