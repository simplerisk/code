<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XML\Constants as C;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\XML\Interface\FacetInterface;

/**
 * Trait grouping common functionality for elements that are part of the xs:facets group.
 *
 * @package simplesamlphp/xml-common
 */
trait FacetsTrait
{
    /**
     * The facets.
     *
     * @var \SimpleSAML\XMLSchema\XML\Interface\FacetInterface[]
     */
    protected array $facets;


    /**
     * Collect the value of the facets-property
     *
     * @return \SimpleSAML\XMLSchema\XML\Interface\FacetInterface[]
     */
    public function getFacets(): array
    {
        return $this->facets;
    }


    /**
     * Set the value of the facets-property
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\FacetInterface[] $facets
     */
    protected function setFacets(array $facets): void
    {
        Assert::maxCount($facets, C::UNBOUNDED_LIMIT);
        Assert::allIsInstanceOf($facets, FacetInterface::class, SchemaViolationException::class);
        $this->facets = $facets;
    }
}
