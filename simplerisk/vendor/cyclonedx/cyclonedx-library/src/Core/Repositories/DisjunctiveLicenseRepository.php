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

namespace CycloneDX\Core\Repositories;

use CycloneDX\Core\Models\License\AbstractDisjunctiveLicense;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithId;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithName;

/**
 * @author jkowalleck
 */
class DisjunctiveLicenseRepository implements \Countable
{
    /**
     * @var DisjunctiveLicenseWithId[]|DisjunctiveLicenseWithName[]
     *
     * @psalm-var list<DisjunctiveLicenseWithId|DisjunctiveLicenseWithName>
     */
    private $licenses = [];

    /**
     * Unsupported Licenses are filtered out silently.
     *
     * @param DisjunctiveLicenseWithId[]|DisjunctiveLicenseWithName[] $licenses
     *
     * @psalm-param  list<DisjunctiveLicenseWithId|DisjunctiveLicenseWithName> $licenses
     */
    public function __construct(AbstractDisjunctiveLicense ...$licenses)
    {
        $this->addLicense(...$licenses);
    }

    /**
     * Add supported licenses.
     * Unsupported Licenses are filtered out silently.
     *
     * @param DisjunctiveLicenseWithId[]|DisjunctiveLicenseWithName[] $licenses
     *
     * @psalm-param  list<DisjunctiveLicenseWithId|DisjunctiveLicenseWithName> $licenses
     *
     * @return $this
     */
    public function addLicense(AbstractDisjunctiveLicense ...$licenses): self
    {
        foreach (array_filter($licenses, [$this, 'isSupportedLicense']) as $license) {
            if (\in_array($license, $this->licenses, true)) {
                continue;
            }
            $this->licenses[] = $license;
        }

        return $this;
    }

    /**
     * @return DisjunctiveLicenseWithId[]|DisjunctiveLicenseWithName[]
     *
     * @psalm-return list<DisjunctiveLicenseWithId|DisjunctiveLicenseWithName>
     */
    public function getLicenses(): array
    {
        return $this->licenses;
    }

    public function count(): int
    {
        return \count($this->licenses);
    }

    /**
     * @psalm-assert-if-true DisjunctiveLicenseWithId|DisjunctiveLicenseWithName $license
     */
    private function isSupportedLicense(AbstractDisjunctiveLicense $license): bool
    {
        return $license instanceof DisjunctiveLicenseWithId
            || $license instanceof DisjunctiveLicenseWithName;
    }
}
