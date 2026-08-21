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

namespace CycloneDX\Core\Validation\Helpers;

use CycloneDX\Core\Resources;
use Swaggest\JsonSchema;

/**
 * @internal
 *
 * @author jkowalleck
 */
class JsonSchemaRemoteRefProviderForSnapshotResources implements JsonSchema\RemoteRefProvider
{
    /**
     * {@inheritdoc}
     *
     * @throws \JsonException
     */
    public function getSchemaData($url)
    {
        $path = parse_url($url, \PHP_URL_PATH);
        if (!\is_string($path)) {
            return false;
        }
        $fileName = basename($path);
        if (1 !== preg_match('/\.SNAPSHOT\.schema\.json$/', $fileName)) {
            return false;
        }
        $filePath = Resources::ROOT.$fileName;
        if (!is_file($filePath) || !is_readable($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        \assert(\is_string($content));
        $data = json_decode($content, false, 512, \JSON_THROW_ON_ERROR);
        \assert($data instanceof \stdClass);

        return $data;
    }
}
