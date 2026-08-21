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

namespace CycloneDX\Core\Models\License;

use DomainException;

/**
 * @author jkowalleck
 */
class LicenseExpression
{
    /**
     * @var string
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $expression;

    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @throws DomainException if the expression was invalid
     *
     * @return $this
     */
    public function setExpression(string $expression): self
    {
        if (false === self::isValid($expression)) {
            throw new DomainException("Invalid expression: $expression");
        }
        $this->expression = $expression;

        return $this;
    }

    /**
     * @throws DomainException if the expression was invalid
     */
    public function __construct(string $expression)
    {
        $this->setExpression($expression);
    }

    public static function isValid(string $expression): bool
    {
        // smallest known: (A or B)
        return \strlen($expression) >= 8
            && '(' === $expression[0]
            && ')' === $expression[-1];
    }
}
