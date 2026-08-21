<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use SimpleSAML\XML\SchemaValidatableElementInterface;
use SimpleSAML\XML\SchemaValidatableElementTrait;
use SimpleSAML\XMLSchema\Type\AnyURIValue;

/**
 * Class representing <xenc11:AbstractMGFType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractMGFType extends AbstractAlgorithmIdentifierType implements
    SchemaValidatableElementInterface
{
    use SchemaValidatableElementTrait;


    /**
     * MGFType constructor.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $Algorithm
     */
    public function __construct(
        AnyURIValue $Algorithm,
    ) {
        parent::__construct($Algorithm, null);
    }
}
