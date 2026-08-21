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

namespace CycloneDX\Core\Spec;

use CycloneDX\Core\Enums\Classification;
use CycloneDX\Core\Enums\HashAlgorithm;

/**
 * @internal
 *
 * @author jkowalleck
 */
trait SpecTrait
{
    /**
     * @psalm-return Version::V_*
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * @return string[]
     *
     * @psalm-return list<Format::*>
     */
    public function getSupportedFormats(): array
    {
        return self::FORMATS;
    }

    public function isSupportedFormat(string $format): bool
    {
        return \in_array($format, self::FORMATS, true);
    }

    public function isSupportedComponentType(string $classification): bool
    {
        return \in_array($classification, self::COMPONENT_TYPES, true);
    }

    /**
     * @return string[]
     *
     * @psalm-return list<Classification::*>
     */
    public function getSupportedComponentTypes(): array
    {
        return self::COMPONENT_TYPES;
    }

    public function isSupportedHashAlgorithm(string $alg): bool
    {
        return \in_array($alg, self::HASH_ALGORITHMS, true);
    }

    /**
     * @return string[]
     *
     * @psalm-return list<HashAlgorithm::*>
     */
    public function getSupportedHashAlgorithms(): array
    {
        return self::HASH_ALGORITHMS;
    }

    public function isSupportedHashContent(string $content): bool
    {
        return 1 === preg_match(self::HASH_CONTENT_REGEX, $content);
    }
}
