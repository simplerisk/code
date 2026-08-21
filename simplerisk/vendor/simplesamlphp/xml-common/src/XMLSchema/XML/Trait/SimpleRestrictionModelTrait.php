<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XMLSchema\XML\LocalSimpleType;

/**
 * Trait grouping common functionality for elements that are part of the xs:facets group.
 *
 * @package simplesamlphp/xml-common
 */
trait SimpleRestrictionModelTrait
{
    use FacetsTrait;


    /**
     * The simpleType.
     *
     * @var \SimpleSAML\XMLSchema\XML\LocalSimpleType|null
     */
    protected ?LocalSimpleType $simpleType = null;


    /**
     * Collect the value of the simpleType-property
     *
     * @return \SimpleSAML\XMLSchema\XML\LocalSimpleType|null
     */
    public function getSimpleType(): ?LocalSimpleType
    {
        return $this->simpleType;
    }


    /**
     * Set the value of the simpleType-property
     *
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $simpleType
     */
    protected function setSimpleType(?LocalSimpleType $simpleType = null): void
    {
        $this->simpleType = $simpleType;
    }
}
