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
 * @author jkowalleck
 */
final class Spec11 implements SpecInterface
{
    use SpecTrait;

    private const VERSION = Version::V_1_1;

    private const FORMATS = [
        Format::XML,
    ];

    private const COMPONENT_TYPES = [
        Classification::APPLICATION,
        Classification::FRAMEWORK,
        Classification::LIBRARY,
        Classification::OPERATING_SYSTEMS,
        Classification::DEVICE,
        Classification::FILE,
    ];

    private const HASH_ALGORITHMS = [
        HashAlgorithm::MD5,
        HashAlgorithm::SHA_1,
        HashAlgorithm::SHA_256,
        HashAlgorithm::SHA_384,
        HashAlgorithm::SHA_512,
        HashAlgorithm::SHA3_256,
        HashAlgorithm::SHA3_512,
    ];

    private const HASH_CONTENT_REGEX = '/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{40}|[a-fA-F0-9]{64}|[a-fA-F0-9]{96}|[a-fA-F0-9]{128})$/';

    public function supportsLicenseExpression(): bool
    {
        return true;
    }

    public function supportsMetaData(): bool
    {
        return false;
    }

    public function supportsBomRef(): bool
    {
        return false;
    }

    public function supportsDependencies(): bool
    {
        return false;
    }

    public function supportsExternalReferenceHashes(): bool
    {
        return false;
    }
}
