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

use CycloneDX\Core\Repositories\HashRepository;

/**
 * @author jkowalleck
 */
class Tool
{
    /**
     * The vendor of the tool used to create the BOM.
     *
     * @var string|null
     */
    private $vendor;

    /**
     * The name of the tool used to create the BOM.
     *
     * @var string|null
     */
    private $name;

    /**
     * The version of the tool used to create the BOM.
     *
     * @var string|null
     */
    private $version;

    /**
     * The hashes of the tool (if applicable).
     *
     * @var HashRepository|null
     */
    private $hashRepository;

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    /**
     * @return $this
     */
    public function setVendor(?string $vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return $this
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @return $this
     */
    public function setVersion(?string $version): self
    {
        $this->version = $version;

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
}
