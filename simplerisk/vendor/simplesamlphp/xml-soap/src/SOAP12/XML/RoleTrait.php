<?php

declare(strict_types=1);

namespace SimpleSAML\SOAP12\XML;

use SimpleSAML\SOAP12\Type\RoleValue;

/**
 * @package simplesamlphp/xml-wss-core
 *
 * @phpstan-ignore trait.unused
 */
trait RoleTrait
{
    /** @var \SimpleSAML\SOAP12\Type\RoleValue|null */
    protected ?RoleValue $role;


    /**
     * @return \SimpleSAML\SOAP12\Type\RoleValue|null
     */
    public function getRole(): ?RoleValue
    {
        return $this->role;
    }


    /**
     * @param \SimpleSAML\SOAP12\Type\RoleValue $role|null
     */
    private function setRole(?RoleValue $role): void
    {
        $this->role = $role;
    }
}
