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

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\IO\IOInterface;
use CycloneDX\Composer\Builders\BomBuilder;
use CycloneDX\Composer\MakeBom\Exceptions\ValueError;
use CycloneDX\Composer\ToolUpdater;
use CycloneDX\Core\Models\Bom;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @author jkowalleck
 */
class Command extends BaseCommand
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var \CycloneDX\Composer\Builders\BomBuilder
     */
    private $bomBuilder;

    /**
     * @var ToolUpdater|null
     */
    private $toolUpdater;

    /**
     * alternative command, if this one os deprecated.
     *
     * @var string|null
     */
    private $deprecatedAlternative;

    /**
     * @throws \LogicException When the command name is empty
     */
    public function __construct(
        Options $options,
        Factory $factory,
        BomBuilder $bomFactory,
        ?ToolUpdater $toolUpdater,
        ?string $name = null
    ) {
        $this->options = $options;
        $this->factory = $factory;
        $this->bomBuilder = $bomFactory;
        $this->toolUpdater = $toolUpdater;
        parent::__construct($name);
    }

    /**
     * @return $this
     */
    public function setDeprecated(?string $alternative): self
    {
        $this->deprecatedAlternative = $alternative;

        return $this;
    }

    protected function configure(): void
    {
        $this->options->configureCommand($this);
        $this->setDescription('Generate a CycloneDX Bill of Materials');
    }

    /*
     * ALL LOG OUTPUT MUST BE WRITTEN AS ERROR, SO OUTPUT REDIRECT/PIPE OF RESULT WORKS PROPERLY
     */

    /**
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \DomainException
     * @throws \RuntimeException
     *
     * @psalm-return self::SUCCESS|self::FAILURE|self::INVALID
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();

        if (!empty($this->deprecatedAlternative)) {
            $io->writeError([
                ' ', // leading blank line
                sprintf(
                    '<warning>This command is deprecated; use "%s" instead.</warning>',
                    $this->deprecatedAlternative
                ),
                ' ', // trailing blank line
            ]);
        }

        try {
            $this->options->setFromInput($input);
        } catch (ValueError $valueError) {
            $io->writeErrorRaw((string) $valueError, true, IOInterface::DEBUG);
            $io->writeError(
                sprintf(
                    '<error>Option Error: %s</error>',
                    OutputFormatter::escape($valueError->getMessage())
                )
            );

            return self::INVALID;
        }
        $io->writeErrorRaw(__METHOD__.' Options: '.print_r($this->options, true), true, IOInterface::DEBUG);

        $this->bomBuilder->getComponentBuilder()->setVersionNormalization(
            false === $this->options->omitVersionNormalization
        );

        $this->updateTool();

        $bomString = $this->makeBomString(
            $this->makeBom(
                $this->factory->makeComposer($this->options, $io)
            )
        );

        if (false === $this->validateBomString($bomString)) {
            $io->writeError(
                [
                    '<error>Failed to generate valid output.</error>',
                    '<warning>Please report the issue and provide the composer lock file of the current project to:</warning>',
                    '<warning>https://github.com/CycloneDX/cyclonedx-php-composer/issues/new?template=ValidationError-report.md&labels=ValidationError&title=%5BValidationError%5D</warning>',
                ]
            );

            return self::FAILURE;
        }

        $io->writeError(
            sprintf(
                '<info>Write output to: %s</info>',
                OutputFormatter::escape($this->options->outputFile)
            ),
            true,
            IOInterface::NORMAL
        );
        ($this->factory->makeBomOutput($this->options) ?? $output)
            ->write($bomString, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_NORMAL);

        return self::SUCCESS;
    }

    /**
     * @throws \RuntimeException
     * @throws \DomainException
     */
    private function makeBom(Composer $composer): Bom
    {
        $io = $this->getIO();
        $io->writeError('<info>Generate BOM</info>', true, IOInterface::VERBOSE);

        $rootPackage = $composer->getPackage();
        $components = $this->factory->makeLockerFromComposerForOptions($composer, $this->options);
        $rootComponentVersionOverride = $this->options->mainComponentVersion;

        $bom = $this->bomBuilder->makeForPackageWithRequires($rootPackage, $components, $rootComponentVersionOverride);

        $io->writeErrorRaw('Bom: '.print_r($bom, true), true, IOInterface::DEBUG);

        return $bom;
    }

    /**
     * @throws \UnexpectedValueException if SPEC version is unknown
     *
     * @psalm-return non-empty-string
     */
    private function makeBomString(Bom $bom): string
    {
        $io = $this->getIO();
        $io->writeError('<info>Generate BomString</info>', true, IOInterface::VERBOSE);

        $bomWriter = $this->factory->makeSerializerFromOptions($this->options);
        $io->writeError(
            sprintf(
                '<info>Serialize BOM with %s</info>',
                OutputFormatter::escape(\get_class($bomWriter))
            ),
            true,
            IOInterface::VERY_VERBOSE
        );

        return $bomWriter->serialize($bom);
    }

    /**
     * @psalm-param  non-empty-string $bom
     *
     * @throws \UnexpectedValueException if SPEC version is unknown
     */
    private function validateBomString(string $bom): ?bool
    {
        $io = $this->getIO();

        $validator = $this->factory->makeValidatorFromOptions($this->options);
        if (null === $validator) {
            $io->writeError('<info>Skip BomString validation</info>', true, IOInterface::VERY_VERBOSE);

            return null;
        }
        $io->writeError('<info>Validate BomString</info>', true, IOInterface::VERBOSE);

        $io->writeError(
            sprintf(
                '<info>Validate BOM with %s for %s</info>',
                OutputFormatter::escape(\get_class($validator)),
                OutputFormatter::escape($validator->getSpec()->getVersion())
            ),
            true,
            IOInterface::VERY_VERBOSE
        );

        $validationError = $validator->validateString($bom);
        if (null === $validationError) {
            return true;
        }

        $io->writeErrorRaw('ValidationError: '.print_r($validationError->getError(), true), true, IOInterface::DEBUG);
        $io->writeError(
            sprintf(
                '<error>ValidationError: %s</error>',
                OutputFormatter::escape($validationError->getMessage())
            ),
            true,
            IOInterface::VERY_VERBOSE
        );

        return false;
    }

    private function updateTool(): ?bool
    {
        $updater = $this->toolUpdater;
        if (null === $updater) {
            return null;
        }

        try {
            $composer = $this->compat_getComposer();

            /**
             * Composer <  2.1.7 -> nullable, but type hint was wrong
             * Composer >= 2.1.7 -> nullable.
             * Composer >= 2.3   -> not nullable.
             *
             * @var \Composer\Package\Locker|null $locker
             *
             * @psalm-suppress UnnecessaryVarAnnotation
             */
            $locker = $composer->getLocker();
            if (null === $locker) {
                throw new \UnexpectedValueException('empty locker');
            }

            $withDevReqs = isset($locker->getLockData()['packages-dev']);
            $lockerRepo = $locker->getLockedRepository($withDevReqs);

            // @TODO better use the installed-repo than the lockerRepo - as of milestone v4
            return $updater->updateTool($this->bomBuilder->getTool(), $lockerRepo);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * @throws \UnexpectedValueException if composer could not be fetched
     */
    private function compat_getComposer(): Composer
    {
        $err = null;
        try {
            /**
             * @psalm-suppress DeprecatedMethod as it was handled
             *
             * @var mixed $composer
             */
            $composer = method_exists($this, 'requireComposer')
                ? $this->requireComposer()
                : ( // method was marked as deprecated in Composer 2.3
                    method_exists($this, 'getComposer')
                    ? $this->getComposer()
                    : null);
        } catch (\Exception $err) {
            $composer = null;
        }
        if ($composer instanceof Composer) {
            return $composer;
        }
        throw new \UnexpectedValueException('empty composer', 0, $err);
    }
}
