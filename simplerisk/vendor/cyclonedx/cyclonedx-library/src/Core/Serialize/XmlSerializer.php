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

use CycloneDX\Core\Helpers\SimpleDomTrait;
use CycloneDX\Core\Models\Bom;
use DomainException;
use DOMDocument;

/**
 * Transform data models to XML.
 *
 * @author jkowalleck
 */
class XmlSerializer extends BaseSerializer
{
    use SimpleDomTrait;

    private const XML_VERSION = '1.0';
    private const XML_ENCODING = 'UTF-8';

    /**
     * @throws DomainException if something was not supported
     */
    protected function normalize(Bom $bom): string
    {
        $document = new DOMDocument(self::XML_VERSION, self::XML_ENCODING);
        $document->appendChild(
            $document->importNode(
                (new DOM\NormalizerFactory($this->getSpec()))
                    ->makeForBom()
                    ->normalize($bom),
                true
            )
        );

        $document->formatOutput = true;

        // option LIBXML_NOEMPTYTAG might lead to errors in consumers
        $xml = $document->saveXML();
        \assert(false !== $xml);
        \assert('' !== $xml);

        return $xml;
    }
}
