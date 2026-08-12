<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\XML\Interface\FacetInterface;

/**
 * Class representing the minLength element
 *
 * @package simplesamlphp/xml-common
 */
final class MinLength extends AbstractNumFacet implements SchemaValidatableElementInterface, FacetInterface
{
    use SchemaValidatableElementTrait;


    public const string LOCALNAME = 'minLength';
}
