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
use CycloneDX\Core\Validation\BaseValidator;
use CycloneDX\Core\Validation\Errors\JsonValidationError;
use CycloneDX\Core\Validation\Exceptions\FailedLoadingSchemaException;
use CycloneDX\Core\Validation\Helpers\JsonSchemaRemoteRefProviderForSnapshotResources;
use CycloneDX\Core\Validation\ValidationError;
use Exception;
use JsonException;
use Swaggest\JsonSchema;

/**
 * @author jkowalleck
 */
class JsonValidator extends BaseValidator
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
            Version::V_1_2 => Resources::FILE_CDX_JSON_SCHEMA_1_2,
            Version::V_1_3 => Resources::FILE_CDX_JSON_SCHEMA_1_3,
        ];
    }

    /**
     * @psalm-param non-empty-string $string
     *
     * @throws FailedLoadingSchemaException if schema file unknown or not readable
     * @throws JsonException                if loading the JSON failed
     *
     * @return JsonValidationError|null
     */
    public function validateString(string $string): ?ValidationError
    {
        return $this->validateData(
            $this->loadDataFromJson($string)
        );
    }

    /**
     * @throws FailedLoadingSchemaException
     */
    public function validateData(\stdClass $data): ?JsonValidationError
    {
        $contract = $this->getSchemaContract();
        try {
            $contract->in($data);
        } catch (JsonSchema\InvalidValue $error) {
            return JsonValidationError::fromJsonSchemaInvalidValue($error);
        }

        return null;
    }

    /**
     * @throws FailedLoadingSchemaException
     */
    private function getSchemaContract(): JsonSchema\SchemaContract
    {
        $schemaFile = $this->getSchemaFile();
        try {
            return JsonSchema\Schema::import(
                $schemaFile,
                new JsonSchema\Context(new JsonSchemaRemoteRefProviderForSnapshotResources())
            );
            // @codeCoverageIgnoreStart
        } catch (Exception $exception) {
            throw new FailedLoadingSchemaException('import schema data failed', 0, $exception);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws JsonException if loading the JSON failed
     */
    private function loadDataFromJson(string $json): \stdClass
    {
        try {
            $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            throw new JsonException('loading failed', 0, $exception);
        }
        \assert($data instanceof \stdClass);

        return $data;
    }
}
