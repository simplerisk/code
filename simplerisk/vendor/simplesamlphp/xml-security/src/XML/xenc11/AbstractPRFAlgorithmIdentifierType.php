<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSecurity\XML\xenc11;

use SimpleSAML\XMLSchema\Type\AnyURIValue;

/**
 * Class representing <xenc11:AbstractPRFAlgorithmIdentifierType>.
 *
 * @package simplesamlphp/xml-security
 */
abstract class AbstractPRFAlgorithmIdentifierType extends AbstractAlgorithmIdentifierType
{
    /**
     * AlgorithmPRFIdentifierType constructor.
     *
     * @param \SimpleSAML\XMLSchema\Type\AnyURIValue $Algorithm
     */
    public function __construct(
        AnyURIValue $Algorithm,
    ) {
        parent::__construct($Algorithm, null);
    }
}
