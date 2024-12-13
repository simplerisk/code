<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\ds;

use DOMElement;
use SimpleSAML\Assert\Assert;
use SimpleSAML\XML\Exception\InvalidDOMElementException;

use function array_merge;

/**
 * Class representing a ds:KeyInfo element.
 *
 * @package simplesamlphp/xml-security
 */
final class KeyInfo extends AbstractKeyInfoType
{
    /**
     * Convert XML into a KeyInfo
     *
     * @param \DOMElement $xml The XML element we should load
     * @return static
     *
     * @throws \SimpleSAML\XML\Exception\InvalidDOMElementException
     *   If the qualified name of the supplied element is wrong
     */
    public static function fromXML(DOMElement $xml): static
    {
        Assert::same($xml->localName, 'KeyInfo', InvalidDOMElementException::class);
        Assert::same($xml->namespaceURI, KeyInfo::NS, InvalidDOMElementException::class);

        $Id = self::getOptionalAttribute($xml, 'Id', null);

        $keyName = KeyName::getChildrenOfClass($xml);
        $keyValue = KeyValue::getChildrenOfClass($xml);
        $retrievalMethod = RetrievalMethod::getChildrenOfClass($xml);
        $x509Data = X509Data::getChildrenOfClass($xml);
        //$pgpData = PGPData::getChildrenOfClass($xml);
        //$spkiData = SPKIData::getChildrenOfClass($xml);
        //$mgmtData = MgmtData::getChildrenOfClass($xml);
        $other = self::getChildElementsFromXML($xml);

        $info = array_merge(
            $keyName,
            $keyValue,
            $retrievalMethod,
            $x509Data,
            //$pgpdata,
            //$spkidata,
            //$mgmtdata,
            $other,
        );

        return new static($info, $Id);
    }
}
