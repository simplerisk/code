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
use CycloneDX\Core\Models\ExternalReference;
use CycloneDX\Core\Repositories\HashRepository;
use CycloneDX\Core\Serialize\JSON\AbstractNormalizer;

/**
 * @author jkowalleck
 */
class ExternalReferenceNormalizer extends AbstractNormalizer
{
    use NullAssertionTrait;

    /**
     * @throws \DomainException
     */
    public function normalize(ExternalReference $externalReference): array
    {
        // could throw DomainException if the type was not supported

        return array_filter(
            [
                'type' => $externalReference->getType(),
                'url' => $externalReference->getUrl(),
                'comment' => $externalReference->getComment(),
                'hashes' => $this->normalizeHashes($externalReference->getHashRepository()),
            ],
            [$this, 'isNotNull']
        );
    }

    private function normalizeHashes(?HashRepository $hashes): ?array
    {
        $factory = $this->getNormalizerFactory();

        if (false === $factory->getSpec()->supportsExternalReferenceHashes()) {
            return null;
        }

        return null === $hashes || 0 === \count($hashes)
            ? null
            : $factory->makeForHashRepository()->normalize($hashes);
    }
}
