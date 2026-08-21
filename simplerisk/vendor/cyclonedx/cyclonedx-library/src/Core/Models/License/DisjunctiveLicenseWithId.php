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

namespace CycloneDX\Core\Models\License;

use CycloneDX\Core\Spdx\License as LicenseValidator;
use DomainException;

/**
 * Disjunctive license with (SPDX-)ID - aka SpdxLicense.
 *
 * @author jkowalleck
 */
class DisjunctiveLicenseWithId extends AbstractDisjunctiveLicense
{
    /**
     * A valid SPDX license ID.
     *
     * @see \CycloneDX\Core\Spdx\License::validate()
     *
     * @var string
     */
    private $id;

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id SPDX-ID of a license
     *
     * @throws DomainException when the SPDX license is invalid
     *
     * @see \CycloneDX\Core\Spdx\License::getLicense()
     * @see \CycloneDX\Core\Spdx\License::validate()
     */
    public static function makeValidated(string $id, LicenseValidator $spdxLicenseValidator): self
    {
        $validId = $spdxLicenseValidator->getLicense($id);
        if (null === $validId) {
            throw new DomainException("Invalid SPDX license: $validId");
        }

        return new self($validId);
    }

    private function __construct(string $id)
    {
        $this->id = $id;
    }
}
