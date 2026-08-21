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

namespace CycloneDX\Composer;

use Composer\Package\AliasPackage;
use Composer\Repository\LockArrayRepository;
use Composer\Semver\Constraint\MatchAllConstraint;
use CycloneDX\Composer\Builders\ComponentBuilder;
use CycloneDX\Core\Models\Tool;

/**
 * @internal
 *
 * @author jkowalleck
 */
class ToolUpdater
{
    /**
     * @var ComponentBuilder
     */
    private $componentBuilder;

    public function __construct(ComponentBuilder $componentBuilder)
    {
        $this->componentBuilder = $componentBuilder;
    }

    /**
     * update tool information with data based on composer lock.
     */
    public function updateTool(Tool $tool, LockArrayRepository $lockRepo): bool
    {
        $toolComposerName = sprintf(
            '%s/%s',
            $tool->getVendor() ?? '',
            $tool->getName() ?? '',
        );
        if ('/' === $toolComposerName) {
            return false;
        }

        $packages = array_filter(
            $lockRepo->findPackages($toolComposerName, new MatchAllConstraint()),
            static function ($p): bool {
                return false === $p instanceof AliasPackage;
            }
        );
        if (empty($packages)) {
            return false;
        }

        try {
            $component = $this->componentBuilder->makeFromPackage(reset($packages));
        } catch (\Throwable $exception) {
            return false;
        }

        $tool->setVersion($component->getVersion());
        $tool->setHashRepository($component->getHashRepository());

        return true;
    }
}
