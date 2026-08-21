<?php

declare(strict_types=1);

/*
 * This file is part of CycloneDX PHP Composer Plugin.
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

namespace CycloneDX\Composer\Builders;

use Composer\Package\RootPackageInterface;
use Composer\Repository\LockArrayRepository;
use CycloneDX\Core\Models\Bom;
use CycloneDX\Core\Models\BomRef;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Models\MetaData;
use CycloneDX\Core\Models\Tool;
use CycloneDX\Core\Repositories\BomRefRepository;
use CycloneDX\Core\Repositories\ComponentRepository;
use CycloneDX\Core\Repositories\ToolRepository;

/**
 * @internal
 *
 * @author jkowalleck
 */
class BomBuilder
{
    /** @var ComponentBuilder */
    private $componentBuilder;

    /** @var Tool */
    private $tool;

    public function __construct(ComponentBuilder $componentBuilder, Tool $tool)
    {
        $this->componentBuilder = $componentBuilder;
        $this->tool = $tool;
    }

    public function getComponentBuilder(): ComponentBuilder
    {
        return $this->componentBuilder;
    }

    public function getTool(): Tool
    {
        return $this->tool;
    }

    /**
     * Generates BOMs based on Composer's lockData.
     *
     * @throws \UnexpectedValueException if a package does not provide a name or version
     * @throws \DomainException          if the bom structure had unexpected values
     * @throws \RuntimeException
     */
    public function makeForPackageWithRequires(
        RootPackageInterface $rootPackage,
        LockArrayRepository $requires,
        ?string $rootPackageVersionOverride
    ): Bom {
        $rootComponent = $this->componentBuilder->makeFromPackage($rootPackage, $rootPackageVersionOverride);

        $requiresPackageComponent = [];
        foreach ($requires->getPackages() as $requirePackage) {
            $requiresPackageComponent[] = [
                $requirePackage,
                $this->componentBuilder->makeFromPackage($requirePackage),
            ];
        }
        unset($requirePackage);
        $requiresComponentRepo = new ComponentRepository(...array_column($requiresPackageComponent, 1));

        if (\count($requiresPackageComponent) > 0) {
            // the $rootComponent needs to be part of $allComponents so all cyclic dependencies are visible
            $allBomComponents = new ComponentRepository($rootComponent, ...$requiresComponentRepo->getComponents());
            $this->setComponentDependencies(
                $rootComponent,
                $allBomComponents,
                // If dev-requires were found in $requiresComponentRepo then they must be made visible.
                // It is on the outer logic to decide, if dev-requires are included in $requiresComponentRepo
                array_merge($rootPackage->getRequires(), $rootPackage->getDevRequires())
            );
            foreach ($requiresPackageComponent as [$requirePackage, $component]) {
                $this->setComponentDependencies($component, $allBomComponents, $requirePackage->getRequires());
            }
            unset($allBomComponents, $component, $requirePackage);
        }

        return (new Bom($requiresComponentRepo))
            ->setMetaData(
                (new MetaData())
                    ->setComponent($rootComponent)
                    ->setTools(new ToolRepository($this->tool))
            );
    }

    /**
     * @param \Composer\Package\Link[] $requires
     */
    private function setComponentDependencies(
        Component $component,
        ComponentRepository $components,
        array $requires
    ): void {
        $componentGetBomRef = static function (Component $c): BomRef {
            return $c->getBomRef();
        };

        $bomRefs = [];
        foreach ($requires as $require) {
            [$name, $vendor] = $this->componentBuilder->splitNameAndVendor($require->getTarget());
            array_push(
                $bomRefs,
                ...array_map(
                    $componentGetBomRef,
                    $components->findComponents($name, $vendor)
                )
            );
        }

        $component->setDependenciesBomRefRepository(
            empty($bomRefs)
                ? null
                : new BomRefRepository(...$bomRefs)
        );
    }
}
