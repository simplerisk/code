<?php

declare(strict_types=1);

/*
 * This file is part of CycloneDX PHP Composer Plugin.
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

namespace CycloneDX\Composer\Factories;

use Composer\Package\CompletePackageInterface;
use CycloneDX\Core\Models\License\LicenseExpression;
use CycloneDX\Core\Repositories\DisjunctiveLicenseRepository;

/**
 * @internal
 *
 * @author jkowalleck
 */
class LicenseFactory extends \CycloneDX\Core\Factories\LicenseFactory
{
    /**
     * @return LicenseExpression|DisjunctiveLicenseRepository
     */
    public function makeFromPackage(CompletePackageInterface $package)
    {
        $licenses = $package->getLicense();
        if (1 === \count($licenses)) {
            // exactly one license - this COULD be an expression
            try {
                return $this->makeExpression(reset($licenses));
            } catch (\DomainException $exception) {
                unset($exception);
            }
        }

        return $this->makeDisjunctiveLicenseRepository(...array_values($licenses));
    }

    protected function makeDisjunctiveLicenseRepository(string ...$licenses): DisjunctiveLicenseRepository
    {
        return new DisjunctiveLicenseRepository(
            ...array_map(
                [$this, 'makeDisjunctive'],
                $licenses
            )
        );
    }
}
