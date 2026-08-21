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

namespace CycloneDX\Core\Enums;

/**
 * See {@link https://cyclonedx.org/schema/bom/1.1 Schema 1.1} for `externalReferenceType`.
 * See {@link https://cyclonedx.org/schema/bom/1.2 Schema 1.2} for `externalReferenceType`.
 * See {@link https://cyclonedx.org/schema/bom/1.3 Schema 1.3} for `externalReferenceType`.
 *
 * @author jkowalleck
 */
abstract class ExternalReferenceType
{
    /** Version Control System */
    public const VCS = 'vcs';
    /** Issue or defect tracking system, or an Application Lifecycle Management (ALM) system */
    public const ISSUE_TRACKER = 'issue-tracker';
    /** Website */
    public const WEBSITE = 'website';
    /** Security advisories */
    public const ADVISORIES = 'advisories';
    /** Bill-of-material document (CycloneDX, SPDX, SWID, etc) */
    public const BOM = 'bom';
    /** Mailing list or discussion group */
    public const MAILING_LIST = 'mailing-list';
    /** Social media account */
    public const SOCIAL = 'social';
    /** Real-time chat platform */
    public const CHAT = 'chat';
    /** Documentation, guides, or how-to instructions */
    public const DOCUMENTATION = 'documentation';
    /** Community or commercial support */
    public const SUPPORT = 'support';
    /*** Direct or repository download location.*/
    public const DISTRIBUTION = 'distribution';
    /** The URL to the license file. If a license URL has been defined in the licensenode, it should also be defined as an external reference for completeness. */
    public const LICENSE = 'license';
    /** Build-system specific meta file (i.e. pom.xml, package.json, .nuspec, etc). */
    public const BUILD_META = 'build-meta';
    /** URL to an automated build system. */
    public const BUILD_SYSTEM = 'build-system';
    /** Use this if no other types accurately describe the purpose of the external reference. */
    public const OTHER = 'other';

    /**
     * @psalm-assert-if-true self::* $value
     */
    public static function isValidValue(string $value): bool
    {
        $values = (new \ReflectionClass(self::class))->getConstants();

        return \in_array($value, $values, true);
    }
}
