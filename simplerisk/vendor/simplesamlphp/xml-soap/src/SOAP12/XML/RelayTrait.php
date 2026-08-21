<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use SimpleSAML\SOAP12\Type\RelayValue;

/**
 * @package simplesamlphp/xml-wss-core
 *
 * @phpstan-ignore trait.unused
 */
trait RelayTrait
{
    /** @var \SimpleSAML\SOAP12\Type\RelayValue|null */
    protected ?RelayValue $relay;


    /**
     * @return \SimpleSAML\SOAP12\Type\RelayValue|null
     */
    public function getRelay(): ?RelayValue
    {
        return $this->relay;
    }


    /**
     * @param \SimpleSAML\SOAP12\Type\RelayValue $relay|null
     */
    private function setRelay(?RelayValue $relay): void
    {
        $this->relay = $relay;
    }
}
