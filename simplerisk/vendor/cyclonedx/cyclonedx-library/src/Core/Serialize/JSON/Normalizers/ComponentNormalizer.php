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

use CycloneDX\Core\Factories\LicenseFactory;
use CycloneDX\Core\Helpers\NullAssertionTrait;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Models\License\LicenseExpression;
use CycloneDX\Core\Repositories\DisjunctiveLicenseRepository;
use CycloneDX\Core\Repositories\ExternalReferenceRepository;
use CycloneDX\Core\Repositories\HashRepository;
use CycloneDX\Core\Serialize\JSON\AbstractNormalizer;
use DomainException;
use PackageUrl\PackageUrl;

/**
 * @author jkowalleck
 */
class ComponentNormalizer extends AbstractNormalizer
{
    use NullAssertionTrait;

    /**
     * @throws DomainException
     */
    public function normalize(Component $component): array
    {
        $spec = $this->getNormalizerFactory()->getSpec();

        $name = $component->getName();
        $group = $component->getGroup();
        $version = $component->getVersion();

        $type = $component->getType();
        if (false === $spec->isSupportedComponentType($type)) {
            $reportFQN = "$group/$name@$version";
            throw new DomainException("Component '$reportFQN' has unsupported type: $type");
        }

        $bomRef = $spec->supportsBomRef()
            ? $component->getBomRef()->getValue()
            : null;

        return array_filter(
            [
                'bom-ref' => $bomRef,
                'type' => $type,
                'name' => $name,
                'version' => $version,
                'group' => $group,
                'description' => $component->getDescription(),
                'licenses' => $this->normalizeLicense($component->getLicense()),
                'hashes' => $this->normalizeHashes($component->getHashRepository()),
                'purl' => $this->normalizePurl($component->getPackageUrl()),
                'externalReferences' => $this->normalizeExternalReferences($component->getExternalReferenceRepository()),
            ],
            [$this, 'isNotNull']
        );
    }

    /**
     * @param LicenseExpression|DisjunctiveLicenseRepository|null $license
     */
    private function normalizeLicense($license): ?array
    {
        if ($license instanceof LicenseExpression) {
            return $this->normalizeLicenseExpression($license);
        }

        if ($license instanceof DisjunctiveLicenseRepository) {
            return $this->normalizeDisjunctiveLicenses($license);
        }

        return null;
    }

    private function normalizeLicenseExpression(LicenseExpression $license): ?array
    {
        if ($this->getNormalizerFactory()->getSpec()->supportsLicenseExpression()) {
            return [
                $this->getNormalizerFactory()->makeForLicenseExpression()->normalize($license),
            ];
        }

        return $this->normalizeDisjunctiveLicenses(
            (new LicenseFactory())->makeDisjunctiveFromExpression($license)
        );
    }

    private function normalizeDisjunctiveLicenses(DisjunctiveLicenseRepository $licenses): ?array
    {
        return 0 === \count($licenses)
            ? null
            : $this->getNormalizerFactory()->makeForDisjunctiveLicenseRepository()->normalize($licenses);
    }

    private function normalizeHashes(?HashRepository $hashes): ?array
    {
        return null === $hashes || 0 === \count($hashes)
            ? null
            : $this->getNormalizerFactory()->makeForHashRepository()->normalize($hashes);
    }

    private function normalizePurl(?PackageUrl $purl): ?string
    {
        return null === $purl
            ? null
            : (string) $purl;
    }

    private function normalizeExternalReferences(?ExternalReferenceRepository $externalReferenceRepository): ?array
    {
        return null === $externalReferenceRepository || 0 === \count($externalReferenceRepository)
            ? null
            : $this->getNormalizerFactory()->makeForExternalReferenceRepository()->normalize($externalReferenceRepository);
    }
}
