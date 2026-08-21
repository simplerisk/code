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

use CycloneDX\Core\Models\Tool;

/**
 * Unique list of {@see \CycloneDX\Core\Models\Tool}.
 *
 * @author jkowalleck
 */
class ToolRepository implements \Countable
{
    /**
     * @var Tool[]
     *
     * @psalm-var list<Tool>
     */
    private $tools = [];

    public function __construct(Tool ...$tools)
    {
        $this->addTool(...$tools);
    }

    /**
     * @return $this
     */
    public function addTool(Tool ...$tools): self
    {
        foreach ($tools as $tool) {
            if (\in_array($tool, $this->tools, true)) {
                continue;
            }
            $this->tools[] = $tool;
        }

        return $this;
    }

    /**
     * @return Tool[]
     *
     * @psalm-return list<Tool>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    public function count(): int
    {
        return \count($this->tools);
    }
}
