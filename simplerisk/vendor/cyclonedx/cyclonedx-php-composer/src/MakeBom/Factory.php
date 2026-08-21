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

namespace CycloneDX\Composer\MakeBom;

use Composer\Composer;
use Composer\Factory as ComposerFactory;
use Composer\Package\AliasPackage;
use Composer\Repository\LockArrayRepository;
use CycloneDX\Composer\Factories\SpecFactory;
use CycloneDX\Core\Serialize\JsonSerializer;
use CycloneDX\Core\Serialize\SerializerInterface;
use CycloneDX\Core\Serialize\XmlSerializer;
use CycloneDX\Core\Spec\SpecInterface;
use CycloneDX\Core\Validation\ValidatorInterface;
use CycloneDX\Core\Validation\Validators\JsonStrictValidator;
use CycloneDX\Core\Validation\Validators\XmlValidator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * @internal
 *
 * @author jkowalleck
 */
class Factory
{
    /**
     * @var string[]
     *
     * @psalm-var array<Options::OUTPUT_FORMAT_*, class-string<\CycloneDX\Core\Serialize\BaseSerializer>>
     */
    private const SERIALIZERS = [
        Options::OUTPUT_FORMAT_XML => XmlSerializer::class,
        Options::OUTPUT_FORMAT_JSON => JsonSerializer::class,
    ];

    /**
     * @var string[]
     *
     * @psalm-var array<Options::OUTPUT_FORMAT_*, class-string<ValidatorInterface>>
     */
    private const VALIDATORS = [
        Options::OUTPUT_FORMAT_XML => XmlValidator::class,
        Options::OUTPUT_FORMAT_JSON => JsonStrictValidator::class,
    ];

    /**
     * @var ComposerFactory
     */
    private $composerFactory;

    /**
     * @var SpecFactory
     */
    private $specFactory;

    public function __construct(
        ComposerFactory $composerFactory,
        SpecFactory $specFactory
    ) {
        $this->composerFactory = $composerFactory;
        $this->specFactory = $specFactory;
    }

    /**
     * @throws \UnexpectedValueException if version is unknown
     */
    public function makeSpecFromOptions(Options $options): SpecInterface
    {
        return $this->specFactory->make($options->specVersion);
    }

    /**
     * @throws \UnexpectedValueException if SPEC version is unknown
     */
    public function makeValidatorFromOptions(Options $options): ?ValidatorInterface
    {
        if ($options->skipOutputValidation) {
            return null;
        }

        $validator = self::VALIDATORS[$options->outputFormat];

        return new $validator($this->makeSpecFromOptions($options));
    }

    /**
     * @throws \UnexpectedValueException if SPEC version is unknown
     */
    public function makeSerializerFromOptions(Options $options): SerializerInterface
    {
        $serializer = self::SERIALIZERS[$options->outputFormat];

        return new $serializer(
            $this->makeSpecFromOptions($options)
        );
    }

    /**
     * @throws Exceptions\LockerIsOutdatedError
     * @throws \RuntimeException
     */
    public function makeLockerFromComposerForOptions(
        Composer $composer,
        Options $options
    ): LockArrayRepository {
        /**
         * Composer <  2.1.7 -> nullable, but type hint was wrong
         * Composer >= 2.1.7 -> nullable.
         *
         * @var \Composer\Package\Locker|null
         *
         * @psalm-suppress UnnecessaryVarAnnotation
         */
        $locker = $composer->getLocker();

        if (null === $locker || !$locker->isLocked() || !$locker->isFresh()) {
            throw new Exceptions\LockerIsOutdatedError('The lock file is missing or not up to date with composer config');
        }

        $withDevReqs = false === $options->excludeDev
            && isset($locker->getLockData()['packages-dev']); // prevent a possible throw in `getLockedRepository()`
        $repo = $locker->getLockedRepository($withDevReqs);

        foreach ($repo->getPackages() as $package) {
            if ($package instanceof AliasPackage) {
                $repo->removePackage($package);
            }
        }

        if ($options->excludePlugins) {
            foreach ($repo->getPackages() as $package) {
                if ('composer-plugin' === $package->getType()) {
                    $repo->removePackage($package);
                }
            }
        }

        return $repo;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function makeComposer(Options $options, \Composer\IO\IOInterface $io): Composer
    {
        /**
         * Composer 2.3 introduced PartialComposer, and may return one here.
         *
         * @var Composer|mixed
         */
        $composer = $this->composerFactory->createComposer(
            $io,
            $options->composerFile,
            true // not needed for analysis
        );
        if ($composer instanceof Composer) {
            return $composer;
        }
        throw new \UnexpectedValueException('Expected Composer');
    }

    public function makeBomOutput(Options $options): ?OutputInterface
    {
        return Options::OUTPUT_FILE_STDOUT === $options->outputFile
            ? null
            : new StreamOutput(
                fopen($options->outputFile, 'wb'),
                StreamOutput::VERBOSITY_NORMAL,
                false
            );
    }
}
