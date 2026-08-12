<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XMLSchema\XML\ComplexContent;
use SimpleSAML\XMLSchema\XML\SimpleContent;

/**
 * Trait grouping common functionality for elements that are part of the xs:complexTypeModel group.
 *
 * @package simplesamlphp/xml-common
 */
trait ComplexTypeModelTrait
{
    use AttrDeclsTrait;
    use TypeDefParticleTrait;


    /**
     * The content.
     *
     * @var \SimpleSAML\XMLSchema\XML\SimpleContent|\SimpleSAML\XMLSchema\XML\ComplexContent|null
     */
    protected SimpleContent|ComplexContent|null $content = null;


    /**
     * Collect the value of the content-property
     *
     * @return \SimpleSAML\XMLSchema\XML\SimpleContent|\SimpleSAML\XMLSchema\XML\ComplexContent|null
     */
    public function getContent(): SimpleContent|ComplexContent|null
    {
        return $this->content;
    }


    /**
     * Set the value of the content-property
     *
     * @param \SimpleSAML\XMLSchema\XML\SimpleContent|\SimpleSAML\XMLSchema\XML\ComplexContent|null $content
     */
    protected function setContent(SimpleContent|ComplexContent|null $content = null): void
    {
        $this->content = $content;
    }
}
