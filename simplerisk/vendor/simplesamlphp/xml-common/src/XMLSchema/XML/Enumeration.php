<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\XML\Interface\FacetInterface;

/**
 * Class representing the enumeration element
 *
 * @package simplesamlphp/xml-common
 */
final class Enumeration extends AbstractNoFixedFacet implements SchemaValidatableElementInterface, FacetInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'enumeration';
}
