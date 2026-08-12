<?php

declare(strict_types=1);

namespace SimpleSAML\XMLSchema\XML\Trait;

use SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface;

/**
 * Trait grouping common functionality for elements that are part of the xs:typeDefParticle group.
 *
 * @package simplesamlphp/xml-common
 */
trait TypeDefParticleTrait
{
    /**
     * The particle.
     *
     * @var \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null
     */
    protected ?TypeDefParticleInterface $particle = null;


    /**
     * Collect the value of the particle-property
     *
     * @return \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null
     */
    public function getParticle(): ?TypeDefParticleInterface
    {
        return $this->particle;
    }


    /**
     * Set the value of the particle-property
     *
     * @param \SimpleSAML\XMLSchema\XML\Interface\TypeDefParticleInterface|null $particle
     */
    protected function setParticle(?TypeDefParticleInterface $particle = null): void
    {
        $this->particle = $particle;
    }
}
