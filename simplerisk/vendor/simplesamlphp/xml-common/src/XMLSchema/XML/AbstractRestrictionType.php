<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML;

use DOMElement;
use SimpleSAML\XML\Assert\Assert;
use SimpleSAML\XMLSchema\Exception\SchemaViolationException;
use SimpleSAML\XMLSchema\Type\IDValue;
use SimpleSAML\XMLSchema\Type\QNameValue;
use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;
use SimpleSAML\XMLSchema\XML\Trait\AttrDeclsTrait;
use SimpleSAML\XMLSchema\XML\Trait\SimpleRestrictionModelTrait;
use SimpleSAML\XMLSchema\XML\Trait\TypeDefParticleTrait;

use function strval;

/**
 * Abstract class representing the restrictionType-type.
 *
 * @package simplesamlphp/xml-common
 */
abstract class AbstractRestrictionType extends AbstractAnnotated
{
    use AttrDeclsTrait;
    use SimpleRestrictionModelTrait;
    use TypeDefParticleTrait;


    /**
     * AbstractRestrictionType constructor
     *
     * @param \SimpleSAML\XMLSchema\Type\QNameValue $base
     * @param \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null $particle
     * @param \SimpleSAML\XMLSchema\XML\LocalSimpleType|null $localSimpleType
     * @param array<\SimpleSAML\XMLSchema\XML\Interface\FacetInterface> $facets
     * @param (
     *     \SimpleSAML\XMLSchema\XML\LocalAttribute|
     *     \SimpleSAML\XMLSchema\XML\ReferencedAttributeGroup
     * )[] $attributes
     * @param \SimpleSAML\XMLSchema\XML\AnyAttribute|null $anyAttribute
     * @param \SimpleSAML\XMLSchema\XML\Annotation|null $annotation
     * @param \SimpleSAML\XMLSchema\Type\IDValue|null $id
     * @param array<\SimpleSAML\XML\Attribute> $namespacedAttributes
     */
    public function __construct(
        protected QNameValue $base,
        // xs:typeDefParticle
        ?TypeDefParticleInterface $particle = null,
        // xs:simpleRestrictionModel
        protected ?LocalSimpleType $localSimpleType = null,
        array $facets = [],
        // xs:attrDecls
        array $attributes = [],
        ?AnyAttribute $anyAttribute = null,
        // parent defined
        ?Annotation $annotation = null,
        ?IDValue $id = null,
        array $namespacedAttributes = [],
    ) {
        // The xs:typeDefParticle and xs:simpleRestrictionModel groups are mutually exclusive
        if ($particle !== null) {
            Assert::null($localSimpleType, SchemaViolationException::class);
            Assert::isEmpty($facets, SchemaViolationException::class);
        } elseif ($localSimpleType !== null || $facets !== []) {
            $this->setSimpleType($localSimpleType);
            $this->setFacets($facets);
        }

        parent::__construct($annotation, $id, $namespacedAttributes);

        $this->setAttributes($attributes);
        $this->setAnyAttribute($anyAttribute);
        $this->setParticle($particle);
    }


    /**
     * Collect the value of the localSimpleType-property
     *
     * @return \SimpleSAML\XMLSchema\XML\LocalSimpleType|null
     */
    public function getLocalSimpleType(): ?LocalSimpleType
    {
        return $this->localSimpleType;
    }


    /**
     * Collect the value of the base-property
     *
     * @return \SimpleSAML\XMLSchema\Type\QNameValue
     */
    public function getBase(): ?QNameValue
    {
        return $this->base;
    }


    /**
     * Add this RestrictionType to an XML element.
     *
     * @param \DOMElement|null $parent The element we should append this RestrictionType to.
     * @return \DOMElement
     */
    public function toXML(?DOMElement $parent = null): DOMElement
    {
        $e = parent::toXML($parent);

        if ($this->getParticle() !== null) {
            $this->getParticle()->toXML($e);
        } elseif ($this->getLocalSimpleType() !== null || $this->getFacets() !== []) {
            $this->getLocalSimpleType()?->toXML($e);

            foreach ($this->getFacets() as $facet) {
                $facet->toXML($e);
            }
        }

        foreach ($this->getAttributes() as $attr) {
            $attr->toXML($e);
        }

        $this->getAnyAttribute()?->toXML($e);

        if ($this->getBase() !== null) {
            $e->setAttribute('base', strval($this->getBase()));
        }

        return $e;
    }
}
