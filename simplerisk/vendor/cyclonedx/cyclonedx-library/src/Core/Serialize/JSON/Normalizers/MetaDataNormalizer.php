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

namespace CycloneDX\Core\Serialize\JSON\Normalizers;

use CycloneDX\Core\Helpers\NullAssertionTrait;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Models\MetaData;
use CycloneDX\Core\Repositories\ToolRepository;
use CycloneDX\Core\Serialize\JSON\AbstractNormalizer;

/**
 * @author jkowalleck
 */
class MetaDataNormalizer extends AbstractNormalizer
{
    use NullAssertionTrait;

    public function normalize(MetaData $metaData): array
    {
        return array_filter(
            [
                // timestamp
                'tools' => $this->normalizeTools($metaData->getTools()),
                // authors
                'component' => $this->normalizeComponent($metaData->getComponent()),
                // manufacture
                // supplier
            ],
            [$this, 'isNotNull']
        );
    }

    private function normalizeTools(?ToolRepository $tools): ?array
    {
        return null === $tools || 0 === \count($tools)
            ? null
            : $this->getNormalizerFactory()->makeForToolRepository()->normalize($tools);
    }

    private function normalizeComponent(?Component $component): ?array
    {
        if (null === $component) {
            return null;
        }

        try {
            return $this->getNormalizerFactory()->makeForComponent()->normalize($component);
        } catch (\DomainException $exception) {
            return null;
        }
    }
}
