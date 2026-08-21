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

use CycloneDX\Core\Enums\HashAlgorithm;
use DomainException;

/**
 * Dictionary of {@see \CycloneDX\Core\Enums\HashAlgorithm} => $content.
 *
 * @author jkowalleck
 */
class HashRepository implements \Countable
{
    /**
     * @var string[] dictionary of hashes
     *
     * @psalm-var  array<HashAlgorithm::*, string>
     */
    private $hashDict = [];

    /**
     * Ignores unknown hash algorithms.
     *
     * @param string[] $hashes dictionary of hashes. Valid keys are {@see \CycloneDX\Core\Enums\HashAlgorithm}
     *
     * @psalm-param array<string,string> $hashes
     */
    public function __construct(array $hashes = [])
    {
        $this->setHashes($hashes);
    }

    /**
     * Set the hashes.
     * Ignores unknown hash algorithms.
     *
     * @param string[] $hashes dictionary of hashes. Valid keys are {@see \CycloneDX\Core\Enums\HashAlgorithm}
     *
     * @psalm-param array<string,string> $hashes
     *
     * @return $this
     */
    public function setHashes(array $hashes): self
    {
        foreach ($hashes as $algorithm => $content) {
            try {
                $this->setHash($algorithm, $content);
            } catch (DomainException $exception) {
                unset($exception);
            }
        }

        return $this;
    }

    /**
     * @return string[] dictionary of hashes
     *
     * @psalm-return array<HashAlgorithm::*, string>
     */
    public function getHashes(): array
    {
        return $this->hashDict;
    }

    /**
     * @psalm-assert HashAlgorithm::* $algorithm
     *
     * @throws DomainException if $algorithm is not in {@see \CycloneDX\Core\Enums\HashAlgorithm}'s constants list
     *
     * @return $this
     */
    public function setHash(string $algorithm, ?string $content): self
    {
        if (false === HashAlgorithm::isValidValue($algorithm)) {
            throw new DomainException("Unknown hash algorithm: $algorithm");
        }
        if (null === $content) {
            unset($this->hashDict[$algorithm]);
        } else {
            $this->hashDict[$algorithm] = $content;
        }

        return $this;
    }

    public function getHash(string $algorithm): ?string
    {
        return $this->hashDict[$algorithm] ?? null;
    }

    public function count(): int
    {
        return \count($this->hashDict);
    }
}
