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

namespace CycloneDX\Core\Serialize;

use CycloneDX\Core\Models\BomRef;

/**
 * @author jkowalleck
 */
class BomRefDiscriminator
{
    private const PREFIX = 'BomRef.';

    /**
     * @var BomRef[]
     *
     * @psalm-var list<BomRef>
     */
    private $bomRefs = [];

    /**
     * @var string[]|null[]
     *
     * @psalm-var list<?string>
     */
    private $originalValues = [];

    public function __construct(BomRef ...$bomRefs)
    {
        foreach ($bomRefs as $bomRef) {
            if (\in_array($bomRef, $this->bomRefs, true)) {
                continue;
            }
            $this->bomRefs[] = $bomRef;
            $this->originalValues[] = $bomRef->getValue();
        }
    }

    public function discriminate(): void
    {
        $values = [];
        foreach ($this->bomRefs as $bomRef) {
            $value = $bomRef->getValue();
            if (null === $value || \in_array($value, $values, true)) {
                $value = $this->makeUniqueId();
                $bomRef->setValue($value);
            }
            $values[] = $value;
        }
    }

    protected function makeUniqueId(): string
    {
        return uniqid(self::PREFIX, true);
    }

    public function reset(): void
    {
        foreach ($this->bomRefs as $i => $bomRef) {
            $bomRef->setValue($this->originalValues[$i]);
        }
    }
}
