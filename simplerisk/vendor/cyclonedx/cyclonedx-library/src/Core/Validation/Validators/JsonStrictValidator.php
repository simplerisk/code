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

namespace CycloneDX\Core\Validation\Validators;

use CycloneDX\Core\Resources;
use CycloneDX\Core\Spec\Version;

/**
 * @author jkowalleck
 */
class JsonStrictValidator extends JsonValidator
{
    /**
     * {@inheritdoc}
     *
     * @internal
     */
    protected static function listSchemaFiles(): array
    {
        return [
            Version::V_1_1 => null, // unsupported version
            Version::V_1_2 => Resources::FILE_CDX_JSON_STRICT_SCHEMA_1_2,
            Version::V_1_3 => Resources::FILE_CDX_JSON_STRICT_SCHEMA_1_3,
        ];
    }
}
