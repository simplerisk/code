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

namespace CycloneDX\Core\Serialize\DOM\Normalizers;

use CycloneDX\Core\Helpers\SimpleDomTrait;
use CycloneDX\Core\Helpers\XmlTrait;
use CycloneDX\Core\Models\License\AbstractDisjunctiveLicense;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithId;
use CycloneDX\Core\Models\License\DisjunctiveLicenseWithName;
use CycloneDX\Core\Serialize\DOM\AbstractNormalizer;
use DOMElement;
use InvalidArgumentException;

/**
 * @author jkowalleck
 */
class DisjunctiveLicenseNormalizer extends AbstractNormalizer
{
    use SimpleDomTrait;
    use XmlTrait;

    /**
     * @psalm-assert DisjunctiveLicenseWithId|DisjunctiveLicenseWithName $license
     *
     * @throws InvalidArgumentException
     */
    public function normalize(AbstractDisjunctiveLicense $license): DOMElement
    {
        if ($license instanceof DisjunctiveLicenseWithId) {
            $id = $license->getId();
            $name = null;
        } elseif ($license instanceof DisjunctiveLicenseWithName) {
            $id = null;
            $name = $license->getName();
        } else {
            throw new InvalidArgumentException('Unsupported license class: '.\get_class($license));
        }

        $document = $this->getNormalizerFactory()->getDocument();

        return $this->simpleDomAppendChildren(
            $document->createElement('license'),
            [
                $this->simpleDomSafeTextElement($document, 'id', $id),
                $this->simpleDomSafeTextElement($document, 'name', $name),
                $this->simpleDomSafeTextElement($document, 'url', $this->encodeAnyUriBE($license->getUrl())),
            ]
        );
    }
}
