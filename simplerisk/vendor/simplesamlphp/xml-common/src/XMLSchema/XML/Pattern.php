<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\XML\Interface\FacetInterface;

/**
 * Class representing the pattern element
 *
 * @package simplesamlphp/xml-common
 */
final class Pattern extends AbstractNoFixedFacet implements SchemaValidatableElementInterface, FacetInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'pattern';
}
