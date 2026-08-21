<?php

declare(strict_types=1);

/*
 * This file is part of CycloneDX PHP Library.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * SPDX-License-Identifier: Apache-2.0
 * Copyright (c) OWASP Foundation. All Rights Reserved.
 */

namespace CycloneDX\Core\Models;

use CycloneDX\Core\Enums\ExternalReferenceType;
use CycloneDX\Core\Repositories\HashRepository;
use DomainException;

/**
 * External references provide a way to document systems, sites, and information that may be relevant
 * but which are not included with the BOM.
 *
 * @author jkowalleck
 */
class ExternalReference
{
    /**
     * Specifies the type of external reference. There are built-in types to describe common
     * references. If a type does not exist for the reference being referred to, use the "other" type.
     *
     * @var string
     *
     * @psalm-var ExternalReferenceType::*
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $type;

    /**
     * The URL to the external reference.
     *
     * @var string
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $url;

    /**
     * An optional comment describing the external reference.
     *
     * @var string|null
     */
    private $comment;

    /**
     * @var HashRepository|null
     */
    private $hashRepository;

    /**
     * @psalm-return  ExternalReferenceType::*
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type A valid {@see \CycloneDX\Core\Enums\ExternalReferenceType}
     *
     * @psalm-assert ExternalReferenceType::* $type
     *
     * @throws DomainException if value is unknown
     *
     * @return $this
     */
    public function setType(string $type): self
    {
        if (false === ExternalReferenceType::isValidValue($type)) {
            throw new DomainException("Invalid type: $type");
        }
        $this->type = $type;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return $this
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    /**
     * @return $this
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getHashRepository(): ?HashRepository
    {
        return $this->hashRepository;
    }

    /**
     * @return $this
     */
    public function setHashRepository(?HashRepository $hashRepository): self
    {
        $this->hashRepository = $hashRepository;

        return $this;
    }

    /**
     * @psalm-assert ExternalReferenceType::* $type
     *
     * @throws DomainException if type is unknown
     */
    public function __construct(string $type, string $url)
    {
        $this->setType($type);
        $this->setUrl($url);
    }
}
