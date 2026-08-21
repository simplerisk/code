<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\XML;

use SimpleSAML\SOAP11\Type\MustUnderstandValue;

/**
 * @package simplesamlphp/xml-wss-core
 *
 * @phpstan-ignore trait.unused
 */
trait MustUnderstandTrait
{
    /** @var \SimpleSAML\SOAP11\Type\MustUnderstandValue|null */
    protected ?MustUnderstandValue $mustUnderstand;


    /**
     * @return \SimpleSAML\SOAP11\Type\MustUnderstandValue|null
     */
    public function getMustUnderstand(): ?MustUnderstandValue
    {
        return $this->mustUnderstand;
    }


    /**
     * @param \SimpleSAML\SOAP11\Type\MustUnderstandValue $mustUnderstand|null
     */
    private function setMustUnderstand(?MustUnderstandValue $mustUnderstand): void
    {
        $this->mustUnderstand = $mustUnderstand;
    }
}
