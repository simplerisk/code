<?php

declare(strict_types=1);

namespace SimpleSAML\XML\XML\Trait;

use SimpleSAML\XML\Type\BaseValue;
use SimpleSAML\XML\Type\IDValue;
use SimpleSAML\XML\Type\LangValue;
use SimpleSAML\XML\Type\SpaceValue;

/**
 * Trait grouping common functionality for elements that use the specialAttrs-attributeGroup.
 *
 * @package simplesamlphp/xml-common
 *
 * @phpstan-ignore trait.unused
 */
trait SpecialAttrsTrait
{
    /**
     * The base.
     *
     * @var \SimpleSAML\XML\Type\BaseValue|null
     */
    protected ?BaseValue $base;

    /**
     * The id.
     *
     * @var \SimpleSAML\XML\Type\IDValue|null
     */
    protected ?IDValue $id;

    /**
     * The lang.
     *
     * @var \SimpleSAML\XML\Type\LangValue|null
     */
    protected ?LangValue $lang;

    /**
     * The space.
     *
     * @var \SimpleSAML\XML\Type\SpaceValue|null
     */
    protected ?SpaceValue $space;


    /**
     * Collect the value of the base-property
     *
     * @return \SimpleSAML\XML\Type\BaseValue|null
     */
    public function getBase(): ?BaseValue
    {
        return $this->base;
    }


    /**
     * Set the value of the base-property
     *
     * @param \SimpleSAML\XML\Type\BaseValue|null $base
     */
    protected function setBase(?BaseValue $base): void
    {
        $this->base = $base;
    }


    /**
     * Collect the value of the id-property
     *
     * @return \SimpleSAML\XML\Type\IDValue|null
     */
    public function getId(): ?IDValue
    {
        return $this->id;
    }


    /**
     * Set the value of the id-property
     *
     * @param \SimpleSAML\XML\Type\IDValue|null $id
     */
    protected function setID(?IDValue $id): void
    {
        $this->id = $id;
    }


    /**
     * Collect the value of the lang-property
     *
     * @return \SimpleSAML\XML\Type\LangValue|null
     */
    public function getLang(): ?LangValue
    {
        return $this->lang;
    }


    /**
     * Set the value of the lang-property
     *
     * @param \SimpleSAML\XML\Type\LangValue|null $id
     */
    protected function setLang(?LangValue $lang): void
    {
        $this->lang = $lang;
    }


    /**
     * Collect the value of the space-property
     *
     * @return \SimpleSAML\XML\Type\SpaceValue|null
     */
    public function getSpace(): ?SpaceValue
    {
        return $this->space;
    }


    /**
     * Set the value of the space-property
     *
     * @param \SimpleSAML\XML\Type\SpaceValue|null $id
     */
    protected function setSpace(?SpaceValue $space): void
    {
        $this->space = $space;
    }
}
