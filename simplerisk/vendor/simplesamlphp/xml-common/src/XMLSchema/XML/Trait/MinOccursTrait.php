<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XMLSchema\Type\Schema\MinOccursValue;

/**
 * Trait grouping common functionality for elements that can hold a MinOccurs attribute.
 *
 * @package simplesamlphp/xml-common
 */
trait MinOccursTrait
{
    /**
     * The minOccurs.
     *
     * @var \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null
     */
    protected ?MinOccursValue $minOccurs = null;


    /**
     * Collect the value of the minOccurs-property
     *
     * @return \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null
     */
    public function getMinOccurs(): ?MinOccursValue
    {
        return $this->minOccurs;
    }


    /**
     * Set the value of the minOccurs-property
     *
     * @param \SimpleSAML\XMLSchema\Type\Schema\MinOccursValue|null $minOccurs
     */
    protected function setMinOccurs(?MinOccursValue $minOccurs): void
    {
        $this->minOccurs = $minOccurs;
    }
}
