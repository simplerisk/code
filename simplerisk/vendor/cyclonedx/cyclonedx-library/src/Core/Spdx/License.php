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

namespace CycloneDX\Core\Spdx;

use CycloneDX\Core\Resources;
use JsonException;
use RuntimeException;

/**
 * Work with known SPDX licences.
 *
 * @author jkowalleck
 */
class License
{
    /**
     * @var string[]
     *
     * @psalm-var array<string, string>
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $licenses;

    /**
     * @return string[]
     *
     * @psalm-return array<string, string>
     */
    public function getLicenses(): array
    {
        return $this->licenses;
    }

    public function getResourcesFile(): string
    {
        return realpath(Resources::FILE_SPDX_JSON_SCHEMA);
    }

    /**
     * @throws RuntimeException if loading licenses failed
     */
    public function __construct()
    {
        $this->loadLicenses();
    }

    public function validate(string $identifier): bool
    {
        return isset($this->licenses[strtolower($identifier)]);
    }

    public function getLicense(string $identifier): ?string
    {
        return $this->licenses[strtolower($identifier)] ?? null;
    }

    /**
     * @throws RuntimeException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function loadLicenses(): void
    {
        if (null !== $this->licenses) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        $file = $this->getResourcesFile();
        $json = file_exists($file) ? file_get_contents($file) : false;
        if (false === $json) {
            throw new RuntimeException("Missing licenses file: $file");
        }

        try {
            /**
             * list of strings, as asserted by an integration test:
             * {@see \CycloneDX\Tests\unit\Core\Spdx\LicenseTest::testShippedLicensesFile()}.
             *
             * @var string[] $licenses
             *
             * @psalm-suppress MixedArrayAccess
             * @psalm-suppress MixedAssignment
             */
            ['enum' => $licenses] = json_decode($json, true, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Malformed licenses file: $file", 0, $exception);
        }

        $this->licenses = [];
        foreach ($licenses as $license) {
            $this->licenses[strtolower($license)] = $license;
        }
    }
}
