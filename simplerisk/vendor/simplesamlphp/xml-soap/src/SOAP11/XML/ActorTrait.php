<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP11\XML;

use SimpleSAML\SOAP11\Type\ActorValue;

/**
 * @package simplesamlphp/xml-wss-core
 *
 * @phpstan-ignore trait.unused
 */
trait ActorTrait
{
    /** @var \SimpleSAML\SOAP11\Type\ActorValue|null */
    protected ?ActorValue $actor;


    /**
     * @return \SimpleSAML\SOAP11\Type\ActorValue|null
     */
    public function getActor(): ?ActorValue
    {
        return $this->actor;
    }


    /**
     * @param \SimpleSAML\SOAP11\Type\ActorValue $actor|null
     */
    private function setActor(?ActorValue $actor): void
    {
        $this->actor = $actor;
    }
}
