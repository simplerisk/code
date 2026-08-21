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
use Composer\Package\RootPackage;
use CycloneDX\Composer\Factories\LicenseFactory;
use CycloneDX\Composer\Factories\PackageUrlFactory;
use CycloneDX\Core\Enums\Classification;
use CycloneDX\Core\Enums\HashAlgorithm;
use CycloneDX\Core\Models\Component;
use CycloneDX\Core\Repositories\HashRepository;

/**
 * @internal
 *
 * @author jkowalleck
 */
class ComponentBuilder
{
    /** @var LicenseFactory */
    private $licenseFactory;

    /** @var PackageUrlFactory */
    private $packageUrlFactory;

    /** @var ExternalReferenceRepositoryBuilder */
    private $externalReferenceRepositoryBuilder;

    /** @var bool */
    private $enableVersionNormalization;

    public function __construct(
        LicenseFactory $licenseFactory,
        PackageUrlFactory $packageUrlFactory,
        ExternalReferenceRepositoryBuilder $externalReferenceRepositoryBuilder,
        bool $enableVersionNormalization = true
    ) {
        $this->licenseFactory = $licenseFactory;
        $this->packageUrlFactory = $packageUrlFactory;
        $this->externalReferenceRepositoryBuilder = $externalReferenceRepositoryBuilder;
        $this->enableVersionNormalization = $enableVersionNormalization;
    }

    public function getLicenseFactory(): LicenseFactory
    {
        return $this->licenseFactory;
    }

    public function getPackageUrlFactory(): PackageUrlFactory
    {
        return $this->packageUrlFactory;
    }

    public function setVersionNormalization(bool $enableVersionNormalization): self
    {
        $this->enableVersionNormalization = $enableVersionNormalization;

        return $this;
    }

    public function getVersionNormalization(): bool
    {
        return $this->enableVersionNormalization;
    }

    /**
     * @throws \UnexpectedValueException if the given package does not provide a name or version
     */
    public function makeFromPackage(PackageInterface $package, ?string $packageVersionOverride = null): Component
    {
        $rawName = $package->getPrettyName();
        if (empty($rawName)) {
            throw new \UnexpectedValueException('Encountered package without name:'.\PHP_EOL.print_r($package, true));
        }

        $version = $packageVersionOverride ?? $this->getPackageVersion($package);
        if ('' === $version) {
            throw new \UnexpectedValueException("Encountered package without version: $rawName");
        }

        $type = $this->getComponentType($package);
        [$name, $vendor] = $this->splitNameAndVendor($rawName);

        /** @psalm-suppress MissingThrowsDocblock since correct $type is asserted */
        $component = new Component($type, $name, $version);

        $component->setGroup($vendor);

        if ($package instanceof CompletePackageInterface) {
            $component->setDescription($package->getDescription());
            $component->setLicense($this->licenseFactory->makeFromPackage($package));
        }

        $sha1sum = $package->getDistSha1Checksum();
        if (false === empty($sha1sum)) {
            $component->setHashRepository(new HashRepository([HashAlgorithm::SHA_1 => $sha1sum]));
        }

        try {
            $purl = $this->packageUrlFactory->makeFromComponent($component);
            if ($this->compatGetDefaultPrettyVersion() === $version) {
                $purl->setVersion(null);
            }
            $component->setPackageUrl($purl);
            $component->setBomRefValue((string) $purl);
        } catch (\DomainException $exception) {
            unset($exception);
        }

        $component->setExternalReferenceRepository(
            $this->externalReferenceRepositoryBuilder->makeFromPackage($package)
        );

        return $component;
    }

    /**
     * Not all versions of composer have {@see RootPackage::DEFAULT_PRETTY_VERSION}.
     * This is a compatibility layer for it.
     */
    private function compatGetDefaultPrettyVersion(): string
    {
        $r = new \ReflectionClass(RootPackage::class);

        return $r->hasConstant('DEFAULT_PRETTY_VERSION')
            ? (string) $r->getConstant('DEFAULT_PRETTY_VERSION')
            : '1.0.0+no-version-set';
    }

    /**
     * @psalm-return array{string, ?string}
     */
    public function splitNameAndVendor(string $packageName): array
    {
        // Composer2 requires published packages to be named like <vendor>/<packageName>.
        // Because this was a loose requirement in composer1 that doesn't apply to "internal" packages,
        // we need to consider that the vendor name may be omitted.
        // See https://getcomposer.org/doc/04-schema.md#name
        // This is still done for backward compatibility reasons.
        if (false === strpos($packageName, '/')) {
            $name = $packageName;
            $vendor = null;
        } else {
            [$vendor, $name] = explode('/', $packageName, 2);
        }

        return [$name, $vendor];
    }

    private function getPackageVersion(PackageInterface $package): string
    {
        $version = $package->getPrettyVersion();
        if ('' === $version) {
            $version = $this->compatGetDefaultPrettyVersion();
        }

        if ($package->isDev()) {
            // package is installed in dev-mode
            // so the version is already final.
            return $version;
        }

        if ($this->enableVersionNormalization) {
            // Versions of Composer packages may be prefixed with "v".
            //     * This prefix appears to be problematic for CPE and PURL matching and thus is removed here.
            //     *
            //     * See for example {@link https://ossindex.sonatype.org/component/pkg:composer/phpmailer/phpmailer@v6.0.7}
            //     * vs {@link https://ossindex.sonatype.org/component/pkg:composer/phpmailer/phpmailer@6.0.7}.
            //
            // A _numeric_ version can be prefixed with 'v'.
            // Strip leading 'v' must not be applied if the "version" is actually a branch name,
            // which is totally fine in the composer ecosystem.
            //
            // will be removed via https://github.com/CycloneDX/cyclonedx-php-composer/issues/102
            // @TODO remove the whole normalizer with next major version
            if (1 === preg_match('/^v\\d/', $version)) {
                return substr($version, 1);
            }
        }

        return $version;
    }

    /**
     * composer has no option to distinguish framework/library, yet.
     * but composer knows projects and plugins, which are equivalent to application.
     *
     * @see https://getcomposer.org/doc/04-schema.md#type
     */
    private function getComponentType(PackageInterface $package): string
    {
        return \in_array($package->getType(), ['project', 'composer-plugin'], true)
            ? Classification::APPLICATION
            : Classification::LIBRARY;
    }
}
