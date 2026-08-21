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

namespace CycloneDX\Core\Repositories;

use CycloneDX\Core\Models\Component;

/**
 * Unique list of {@see \CycloneDX\Core\Models\Component}.
 *
 * @author jkowalleck
 */
class ComponentRepository implements \Countable
{
    /**
     * @var Component[]
     *
     * @psalm-var list<Component>
     */
    private $components = [];

    public function __construct(Component ...$components)
    {
        $this->addComponent(...$components);
    }

    /**
     * @return $this
     */
    public function addComponent(Component ...$components): self
    {
        foreach ($components as $component) {
            if (\in_array($component, $this->components, true)) {
                continue;
            }
            $this->components[] = $component;
        }

        return $this;
    }

    /**
     * @return Component[]
     *
     * @psalm-return list<Component>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    public function count(): int
    {
        return \count($this->components);
    }

    /**
     * @return Component[]
     *
     * @psalm-return list<Component>
     */
    public function findComponents(string $name, ?string $group): array
    {
        if ('' === $group) {
            $group = null;
        }

        return array_values(
            array_filter(
                $this->components,
                static function (Component $component) use ($name, $group): bool {
                    return $component->getName() === $name
                        && $component->getGroup() === $group;
                }
            )
        );
    }
}
