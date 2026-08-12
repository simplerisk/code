<?php

declare(strict_types=1);

namespace SimpleSAML\XML\Container;

use DOMText;
use SimpleSAML\XML\DOMDocumentFactory;
use SimpleSAML\XMLSchema\Type\AnyURIValue;
use SimpleSAML\XMLSchema\XML\Appinfo;

use function array_key_exists;
use function sprintf;

/**
 * One-time instantiation of common elements in XML, for re-use in unit-tests.
 *
 * @package simplesamlphp\xml-common
 * @phpstan-ignore trait.unused
 */
trait XMLSchemaElementsTrait
{
    protected const string SOURCE = 'urn:x-simplesamlphp:source';


    /** @var array<positive-int, \SimpleSAML\XMLSchema\XML\Appinfo> */
    protected array $appinfo = [];


    /** @param positive-int $x */
    public function getAppinfo(int $x = 1): Appinfo
    {
        if (!array_key_exists($x, $this->appinfo)) {
            $appinfoDocument = DOMDocumentFactory::create();
            $text = new DOMText(sprintf('Application Information (%d)', $x));
            $appinfoDocument->appendChild($text);

            $this->appinfo[$x] = new Appinfo(
                $appinfoDocument->childNodes,
                AnyURIValue::fromString(static::SOURCE),
                [$this->getXMLAttribute($x)],
            );
        }

        return $this->appinfo[$x];
    }
}
