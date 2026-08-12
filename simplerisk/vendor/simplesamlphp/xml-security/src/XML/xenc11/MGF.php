<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\InvalidDOMElementException;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

/**
 * A class implementing the xenc11:MGF element.
 *
 * @package simplesamlphp/xml-security
 */
final class MGF extends AbstractMGFType
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

        return new static(
            self::getAttribute($xml, 'Algorithm', AnyURIValue::class),
        );
    }
}
