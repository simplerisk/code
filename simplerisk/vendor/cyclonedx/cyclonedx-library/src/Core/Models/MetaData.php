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

namespace CycloneDX\Core\Models;

use CycloneDX\Core\Repositories\ToolRepository;

/**
 * @author jkowalleck
 */
class MetaData
{
    /**
     * The tool(s) used in the creation of the BOM.
     *
     * @var ToolRepository|null
     */
    private $tools;

    /**
     * The component that the BOM describes.
     *
     * @var Component|null
     */
    private $component;

    public function getTools(): ?ToolRepository
    {
        return $this->tools;
    }

    /**
     * @return $this
     */
    public function setTools(?ToolRepository $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function getComponent(): ?Component
    {
        return $this->component;
    }

    /**
     * @return $this
     */
    public function setComponent(?Component $component): self
    {
        $this->component = $component;

        return $this;
    }
}
