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
 * Classification - aka ComponentType.
 *
 * See {@link https://cyclonedx.org/schema/bom/1.0 Schema 1.0} for `classification`.
 * See {@link https://cyclonedx.org/schema/bom/1.1 Schema 1.1} for `classification`.
 * See {@link https://cyclonedx.org/schema/bom/1.2 Schema 1.2} for `classification`.
 * See {@link https://cyclonedx.org/schema/bom/1.3 Schema 1.3} for `classification`.
 *
 * @author jkowalleck
 */
abstract class Classification
{
    public const APPLICATION = 'application';
    public const FRAMEWORK = 'framework';
    public const LIBRARY = 'library';
    public const OPERATING_SYSTEMS = 'operating-system';
    public const DEVICE = 'device';
    public const FILE = 'file';
    public const CONTAINER = 'container';
    public const FIRMWARE = 'firmware';

    /**
     * @psalm-assert-if-true self::* $value
     */
    public static function isValidValue(string $value): bool
    {
        $values = (new \ReflectionClass(self::class))->getConstants();

        return \in_array($value, $values, true);
    }
}
