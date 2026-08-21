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

namespace CycloneDX\Core\Enums;

/**
 * See {@link https://cyclonedx.org/schema/bom/1.0 Schema 1.0} for `hashAlg`.
 * See {@link https://cyclonedx.org/schema/bom/1.1 Schema 1.1} for `hashAlg`.
 * See {@link https://cyclonedx.org/schema/bom/1.2 Schema 1.2} for `hashAlg`.
 * See {@link https://cyclonedx.org/schema/bom/1.3 Schema 1.3} for `hashAlg`.
 *
 * @author jkowalleck
 */
abstract class HashAlgorithm
{
    public const MD5 = 'MD5';
    public const SHA_1 = 'SHA-1';
    public const SHA_256 = 'SHA-256';
    public const SHA_384 = 'SHA-384';
    public const SHA_512 = 'SHA-512';
    public const SHA3_256 = 'SHA3-256';
    public const SHA3_384 = 'SHA3-384';
    public const SHA3_512 = 'SHA3-512';
    public const BLAKE2B_256 = 'BLAKE2b-256';
    public const BLAKE2B_384 = 'BLAKE2b-384';
    public const BLAKE2B_512 = 'BLAKE2b-512';
    public const BLAKE3 = 'BLAKE3';

    /**
     * @psalm-assert-if-true self::* $value
     */
    public static function isValidValue(string $value): bool
    {
        $values = (new \ReflectionClass(self::class))->getConstants();

        return \in_array($value, $values, true);
    }
}
