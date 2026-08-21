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

namespace CycloneDX\Core\Factories;

use CycloneDX\Core\Models\License\AbstractDisjunctiveLicense;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithId;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithName;
use CycloneDX\Core\Models\License\LicenseExpression;
use CycloneDX\Core\Repositories\DisjunctiveLicenseRepository;
use CycloneDX\Core\Spdx\License as SpdxLicenseValidator;
use DomainException;
use UnexpectedValueException;

class LicenseFactory
{
    /**
     * @var SpdxLicenseValidator|null
     */
    private $spdxLicenseValidator;

    public function __construct(?SpdxLicenseValidator $spdxLicenseValidator = null)
    {
        $this->spdxLicenseValidator = $spdxLicenseValidator;
    }

    /**
     * @psalm-assert SpdxLicenseValidator $this->spdxLicenseValidator
     *
     * @throws UnexpectedValueException when SpdxLicenseValidator is missing
     */
    public function getSpdxLicenseValidator(): SpdxLicenseValidator
    {
        $validator = $this->spdxLicenseValidator;
        if (null === $validator) {
            throw new UnexpectedValueException('Missing spdxLicenseValidator');
        }

        return $validator;
    }

    public function setSpdxLicenseValidator(SpdxLicenseValidator $spdxLicenseValidator): self
    {
        $this->spdxLicenseValidator = $spdxLicenseValidator;

        return $this;
    }

    /**
     * @return DisjunctiveLicenseWithName|DisjunctiveLicenseWithId|LicenseExpression
     */
    public function makeFromString(string $license)
    {
        try {
            return $this->makeExpression($license);
        } catch (DomainException $exception) {
            return $this->makeDisjunctive($license);
        }
    }

    /**
     * @throws DomainException if the expression was invalid
     */
    public function makeExpression(string $license): LicenseExpression
    {
        return new LicenseExpression($license);
    }

    /**
     * @return DisjunctiveLicenseWithId|DisjunctiveLicenseWithName
     */
    public function makeDisjunctive(string $license): AbstractDisjunctiveLicense
    {
        try {
            return $this->makeDisjunctiveWithId($license);
        } catch (UnexpectedValueException|DomainException $exception) {
            return $this->makeDisjunctiveWithName($license);
        }
    }

    /**
     * @throws DomainException          when the SPDX license is invalid
     * @throws UnexpectedValueException when SpdxLicenseValidator is missing
     */
    public function makeDisjunctiveWithId(string $license): DisjunctiveLicenseWithId
    {
        return DisjunctiveLicenseWithId::makeValidated($license, $this->getSpdxLicenseValidator());
    }

    public function makeDisjunctiveWithName(string $license): DisjunctiveLicenseWithName
    {
        return new DisjunctiveLicenseWithName($license);
    }

    public function makeDisjunctiveFromExpression(LicenseExpression $license): DisjunctiveLicenseRepository
    {
        return new DisjunctiveLicenseRepository(
            $this->makeDisjunctiveWithName(
                $license->getExpression()
            )
        );
    }
}
