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

use CycloneDX\Core\Spec\Spec11;
use CycloneDX\Core\Spec\Spec12;
use CycloneDX\Core\Spec\Spec13;
use CycloneDX\Core\Spec\SpecInterface;
use CycloneDX\Core\Spec\Version;

/**
 * @internal
 *
 * @author jkowalleck
 */
class SpecFactory
{
    public const VERSION_LATEST = Version::V_1_3;

    /**
     * @var string[]
     *
     * @psalm-var array<Version::V_*, class-string<SpecInterface>>
     */
    public const SPECS = [
        Version::V_1_1 => Spec11::class,
        Version::V_1_2 => Spec12::class,
        Version::V_1_3 => Spec13::class,
    ];

    /**
     * @psalm-assert Version::V_* $version
     *
     * @throws \UnexpectedValueException if version is unknown
     */
    public function make(string $version = self::VERSION_LATEST): SpecInterface
    {
        if (false === \array_key_exists($version, self::SPECS)) {
            throw new \UnexpectedValueException("Unexpected spec-version: $version");
        }
        $class = self::SPECS[$version];

        return new $class();
    }
}
