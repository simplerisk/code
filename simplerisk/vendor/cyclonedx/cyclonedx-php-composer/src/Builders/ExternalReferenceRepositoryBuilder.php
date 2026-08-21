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

namespace CycloneDX\Composer\Builders;

use Composer\Package\CompletePackageInterface;
use Composer\Package\PackageInterface;
use CycloneDX\Core\Enums\ExternalReferenceType;
use CycloneDX\Core\Enums\HashAlgorithm;
use CycloneDX\Core\Models\ExternalReference;
use CycloneDX\Core\Repositories\ExternalReferenceRepository;
use CycloneDX\Core\Repositories\HashRepository;
use Generator;

/**
 * @internal
 *
 * @author jkowalleck
 */
class ExternalReferenceRepositoryBuilder
{
    private const MARKER_UNDEFINED = 'UNDEFINED';

    /**
     * Map composer's `support` keys to {@see ExternalReferenceType}.
     * Any non-listed shall default to {@see ExternalReferenceType::SUPPORT}.
     *
     * @psalm-var array<string, ExternalReferenceType::*>
     */
    private const MAP_PackageSupportType_ExtRefType = [
        'source' => ExternalReferenceType::DISTRIBUTION,
        'issues' => ExternalReferenceType::ISSUE_TRACKER,
        'irc' => ExternalReferenceType::CHAT,
        'chat' => ExternalReferenceType::CHAT,
        'docs' => ExternalReferenceType::DOCUMENTATION,
        'wiki' => ExternalReferenceType::DOCUMENTATION,
        'email' => ExternalReferenceType::OTHER, // not sure if mailbox or mailing-list
    ];

    /**
     * Map composer's `support` keys to function the `support` value will be applied to.
     * Any non-listed value shall not be modified.
     *
     * @psalm-var array<string, callable(string):string>
     */
    private const MAP_PackageSupportType_ModifierFunction = [
        'email' => [self::class, 'prefixMailto'],
    ];

    public function makeFromPackage(PackageInterface $package): ExternalReferenceRepository
    {
        $repo = new ExternalReferenceRepository(
            ...iterator_to_array($this->getSource($package), false),
            ...iterator_to_array($this->getDist($package), false)
        );

        if ($package instanceof CompletePackageInterface) {
            $homepage = $this->getHomepage($package);
            if (null !== $homepage) {
                $repo->addExternalReference($homepage);
            }

            $repo->addExternalReference(
                ...iterator_to_array($this->getSupport($package), false),
                ...iterator_to_array($this->getFunding($package), false)
            );
        }

        return $repo;
    }

    /**
     * @see https://getcomposer.org/doc/04-schema.md#repositories
     *
     * @psalm-return Generator<ExternalReference>
     */
    private function getSource(PackageInterface $package): \Generator
    {
        $urls = $package->getSourceUrls();
        if (empty($urls)) {
            // safe: some composer versions may return null or empty list
            return;
        }

        $type = $package->getSourceType() ?: self::MARKER_UNDEFINED;
        $reference = $package->getSourceReference() ?: self::MARKER_UNDEFINED;
        $comment = "As detected by composer's `getSourceUrls()` (type=$type & reference=$reference)";

        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }

            yield (new ExternalReference(ExternalReferenceType::DISTRIBUTION, $url))
                ->setComment($comment);
        }
    }

    /**
     * @see https://getcomposer.org/doc/04-schema.md#repositories
     *
     * @psalm-return Generator<ExternalReference>
     */
    private function getDist(PackageInterface $package): \Generator
    {
        $urls = $package->getDistUrls();
        if (empty($urls)) {
            // safe: some composer versions may return null or empty list
            return;
        }

        $type = $package->getDistType() ?: self::MARKER_UNDEFINED;
        $reference = $package->getDistReference() ?: self::MARKER_UNDEFINED;

        $sha1Checksum = $package->getDistSha1Checksum();
        $hashRepo = empty($sha1Checksum)
            ? null
            : new HashRepository([HashAlgorithm::SHA_1 => $sha1Checksum]);

        $sha1comment = $sha1Checksum ?: self::MARKER_UNDEFINED;
        $comment = "As detected by composer's `getDistUrls()` (type=$type & reference=$reference & sha1=$sha1comment)";

        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }

            yield (new ExternalReference(ExternalReferenceType::DISTRIBUTION, $url))
                ->setComment($comment)
                ->setHashRepository(
                    // $hashRepo is not a clone, since the values are same object for each mirror
                    $hashRepo
                );
        }
    }

    /**
     * @see https://getcomposer.org/doc/04-schema.md#homepage
     */
    private function getHomepage(CompletePackageInterface $package): ?ExternalReference
    {
        $homepage = $package->getHomepage();
        if (empty($homepage)) {
            // safe: some composer versions may return null or empty string
            return null;
        }

        try {
            return (new ExternalReference(ExternalReferenceType::WEBSITE, $homepage))
                ->setComment('As set via `homepage` in composer package definition.');
            // @codeCoverageIgnoreStart
        } catch (\DomainException $ex) {
            // pass - as this case either never happens or can be ignored
            return null;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @see https://getcomposer.org/doc/04-schema.md#support
     *
     * @psalm-return Generator<ExternalReference>
     */
    private function getSupport(CompletePackageInterface $package): \Generator
    {
        $support = $package->getSupport();
        if (empty($support)) {
            // safe: some composer versions may return null or empty list
            return;
        }

        foreach ($support as $supportType => $supportValue) {
            /** @psalm-var ?callable(string):string $modifierFunction */
            $modifierFunction = self::MAP_PackageSupportType_ModifierFunction[$supportType] ?? null;
            $extRefUri = (null === $modifierFunction)
                ? $supportValue
                : $modifierFunction($supportValue);

            if (empty($extRefUri)) {
                continue;
            }

            $extRefType = self::MAP_PackageSupportType_ExtRefType[$supportType] ?? ExternalReferenceType::SUPPORT;

            yield $supportType => (new ExternalReference($extRefType, $extRefUri))
                ->setComment("As set via `support.$supportType` in composer package definition.");
        }
    }

    /**
     * For some entities it is important to keep their dependencies alive via funding.
     *
     * @see https://getcomposer.org/doc/04-schema.md#funding
     *
     * @psalm-return Generator<ExternalReference>
     */
    private function getFunding(CompletePackageInterface $package): \Generator
    {
        $fundings = $package->getFunding();
        if (empty($fundings)) {
            // safe: some composer versions may return null or empty list
            return null;
        }

        foreach ($fundings as $funding) {
            $url = $funding['url'] ?? null;
            if (empty($url)) {
                continue;
            }
            $type = $funding['type'] ?? self::MARKER_UNDEFINED;

            yield (new ExternalReference(ExternalReferenceType::OTHER, $url))
                ->setComment("As set via `funding` in composer package definition. (type=$type)");
        }
    }

    private static function prefixMailto(string $mail): string
    {
        if ('' === $mail) {
            return '';
        }

        return 0 === strpos($mail, 'mailto:')
            ? $mail
            : "mailto:$mail";
    }
}
