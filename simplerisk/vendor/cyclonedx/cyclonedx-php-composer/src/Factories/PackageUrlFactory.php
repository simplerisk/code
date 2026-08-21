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

namespace CycloneDX\Composer\Factories;

use CycloneDX\Core\Enums\HashAlgorithm;
use CycloneDX\Core\Models\Component;
use PackageUrl\PackageUrl;

/**
 * @internal
 *
 * @author jkowalleck
 */
class PackageUrlFactory
{
    /**
     * purl type for composer packages,
     * as defined in {@link https://github.com/package-url/purl-spec/blob/master/PURL-TYPES.rst the PURL specs}.
     */
    private const PURL_TYPE = 'composer';

    /**
     * @throws \DomainException when packageurl could not be constructed
     */
    public function makeFromComponent(Component $component): PackageUrl
    {
        $purl = new PackageUrl(self::PURL_TYPE, $component->getName());

        $hashes = $component->getHashRepository();
        if (null !== $hashes) {
            $sha1sum = $hashes->getHash(HashAlgorithm::SHA_1);
            if (null !== $sha1sum) {
                $purl->setChecksums(["sha1:$sha1sum"]);
            }
        }

        return $purl
            ->setNamespace($component->getGroup())
            ->setVersion($component->getVersion());
    }
}
