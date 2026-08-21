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

namespace CycloneDX\Core;

/**
 * @internal
 *
 * @author jkowalleck
 */
final class Resources
{
    // paths are a concat of `__DIR__` and a relative path,
    // so IDEs are able to refactor paths easily.

    public const ROOT = __DIR__.'/../../res/';

    public const FILE_SPDX_XML_SCHEMA = __DIR__.'/../../res/spdx.SNAPSHOT.xsd';
    public const FILE_SPDX_JSON_SCHEMA = __DIR__.'/../../res/spdx.SNAPSHOT.schema.json';

    public const FILE_CDX_XML_SCHEMA_1_0 = __DIR__.'/../../res/bom-1.0.SNAPSHOT.xsd';
    public const FILE_CDX_XML_SCHEMA_1_1 = __DIR__.'/../../res/bom-1.1.SNAPSHOT.xsd';
    public const FILE_CDX_XML_SCHEMA_1_2 = __DIR__.'/../../res/bom-1.2.SNAPSHOT.xsd';
    public const FILE_CDX_XML_SCHEMA_1_3 = __DIR__.'/../../res/bom-1.3.SNAPSHOT.xsd';

    public const FILE_CDX_JSON_SCHEMA_1_2 = __DIR__.'/../../res/bom-1.2.SNAPSHOT.schema.json';
    public const FILE_CDX_JSON_SCHEMA_1_3 = __DIR__.'/../../res/bom-1.3.SNAPSHOT.schema.json';

    public const FILE_CDX_JSON_STRICT_SCHEMA_1_2 = __DIR__.'/../../res/bom-1.2-strict.SNAPSHOT.schema.json';
    public const FILE_CDX_JSON_STRICT_SCHEMA_1_3 = __DIR__.'/../../res/bom-1.3-strict.SNAPSHOT.schema.json';
}
