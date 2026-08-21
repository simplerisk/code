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
interface SpecInterface
{
    /**
     * @psalm-return Version::V_*
     */
    public function getVersion(): string;

    /**
     * @return string[]
     *
     * @psalm-return list<Format::*>
     */
    public function getSupportedFormats(): array;

    public function isSupportedFormat(string $format): bool;

    public function isSupportedComponentType(string $classification): bool;

    /**
     * @return string[]
     *
     * @psalm-return list<Classification::*>
     */
    public function getSupportedComponentTypes(): array;

    public function isSupportedHashAlgorithm(string $alg): bool;

    /**
     * @return string[]
     *
     * @psalm-return list<HashAlgorithm::*>
     */
    public function getSupportedHashAlgorithms(): array;

    public function isSupportedHashContent(string $content): bool;

    /**
     * version 1.0 does not support license expressions
     * they must be normalized to disjunctive licenses.
     */
    public function supportsLicenseExpression(): bool;

    /**
     * version < 1.2 does not support MetaData.
     */
    public function supportsMetaData(): bool;

    /**
     * version < 1.2 does not support BomRef.
     */
    public function supportsBomRef(): bool;

    /**
     * version < 1.2 does not support BomRef.
     */
    public function supportsDependencies(): bool;

    /**
     * version < 1.3 does not support hashes in ExternalReference.
     */
    public function supportsExternalReferenceHashes(): bool;
}
