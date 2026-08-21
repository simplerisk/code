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
use CycloneDX\Core\Models\Bom;
use CycloneDX\Core\Models\BomRef;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Serialize\JSON\AbstractNormalizer;

/**
 * @psalm-type Dependency = array{ref: string, dependsOn?: non-empty-list<string>}
 */
class DependenciesNormalizer extends AbstractNormalizer
{
    use NullAssertionTrait;

    /**
     * Only named {@see \CycloneDX\Core\Models\BomRef BomRefs} will be taken into account.
     * Make sure to use the {@see \CycloneDX\Core\Serialize\BomRefDiscriminator} before calling.
     *
     * @return array[]
     *
     * @psalm-return list<Dependency>
     */
    public function normalize(Bom $bom): array
    {
        $allComponents = $bom->getComponentRepository()->getComponents();

        $metadata = $bom->getMetaData();
        if (null !== $metadata) {
            $mainComponent = $metadata->getComponent();
            if (null !== $mainComponent) {
                $allComponents[] = $mainComponent;
            }
        }

        $allComponentRefs = array_map(
            static function (Component $c): BomRef {
                return $c->getBomRef();
            },
            $allComponents
        );
        $isKnownRef = static function (BomRef $r) use ($allComponentRefs): bool {
            return \in_array($r, $allComponentRefs, true);
        };

        $dependencies = [];
        foreach ($allComponents as $component) {
            $componentDependencies = $component->getDependenciesBomRefRepository();
            $dependenciesRefs = null === $componentDependencies
                ? []
                : array_filter($componentDependencies->getBomRefs(), $isKnownRef);

            $dependency = $this->normalizeDependency($component->getBomRef(), ...$dependenciesRefs);
            if (null !== $dependency) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * @psalm-return Dependency|null
     */
    private function normalizeDependency(BomRef $componentRef, BomRef ...$dependencyRefs): ?array
    {
        $componentRefValue = $componentRef->getValue();
        if (null === $componentRefValue) {
            return null;
        }

        $dep = ['ref' => $componentRefValue];

        $deps = [];
        foreach ($dependencyRefs as $dependencyRef) {
            $dependencyRefValue = $dependencyRef->getValue();
            if (null === $dependencyRefValue) {
                continue;
            }
            $deps[] = $dependencyRefValue;
        }
        if (!empty($deps)) {
            $dep['dependsOn'] = $deps;
        }

        return $dep;
    }
}
