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

use CycloneDX\Composer\Factories\SpecFactory;
use CycloneDX\Composer\MakeBom\Exceptions\ValueError;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * @internal
 *
 * @author jkowalleck
 */
class Options
{
    private const OPTION_OUTPUT_FORMAT = 'output-format';
    private const OPTION_OUTPUT_FILE = 'output-file';
    private const OPTION_SPEC_VERSION = 'spec-version';
    private const OPTION_MAIN_COMPONENT_VERSION = 'mc-version';

    private const SWITCH_EXCLUDE_DEV = 'exclude-dev';
    private const SWITCH_EXCLUDE_PLUGINS = 'exclude-plugins';
    private const SWITCH_NO_VALIDATE = 'no-validate';

    // added in preparation for https://github.com/CycloneDX/cyclonedx-php-composer/issues/102
    // @TODO remove with next major version
    private const SWITCH_NO_VERSION_NORMALIZATION = 'no-version-normalization';

    private const ARGUMENT_COMPOSER_FILE = 'composer-file';

    public const OUTPUT_FORMAT_XML = 'XML';
    public const OUTPUT_FORMAT_JSON = 'JSON';

    public const OUTPUT_FILE_STDOUT = '-';

    /**
     * @var string[]
     *
     * @psalm-var array<Options::OUTPUT_FORMAT_*, string>
     */
    private const OUTPUT_FILE_DEFAULT = [
        self::OUTPUT_FORMAT_XML => 'bom.xml',
        self::OUTPUT_FORMAT_JSON => 'bom.json',
    ];

    /**
     * @psalm-suppress MissingThrowsDocblock since {@see \Symfony\Component\Console\Command\Command::addOption()} is intended to work this way
     */
    public function configureCommand(Command $command): void
    {
        $command
            ->addOption(
                self::OPTION_OUTPUT_FORMAT,
                null,
                InputOption::VALUE_REQUIRED,
                'Which output format to use.'.\PHP_EOL.
                'Values: "'.self::OUTPUT_FORMAT_XML.'", "'.self::OUTPUT_FORMAT_JSON.'"',
                self::OUTPUT_FORMAT_XML
            )
            ->addOption(
                self::OPTION_OUTPUT_FILE,
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the output file.'.\PHP_EOL.
                'Set to "'.self::OUTPUT_FILE_STDOUT.'" to write to STDOUT.'.\PHP_EOL.
                'Depending on the output-format, default is one of: "'.implode(
                    '", "',
                    array_values(self::OUTPUT_FILE_DEFAULT)
                ).'"'
            )
            ->addOption(
                self::SWITCH_EXCLUDE_DEV,
                null,
                InputOption::VALUE_NONE,
                'Exclude dev dependencies'
            )
            ->addOption(
                self::SWITCH_EXCLUDE_PLUGINS,
                null,
                InputOption::VALUE_NONE,
                'Exclude composer plugins'
            )
            ->addOption(
                self::OPTION_SPEC_VERSION,
                null,
                InputOption::VALUE_REQUIRED,
                'Which version of CycloneDX spec to use.'.\PHP_EOL.
                'Values: "'.implode('", "', array_keys(SpecFactory::SPECS)).'"',
                SpecFactory::VERSION_LATEST
            )
            ->addOption(
                self::SWITCH_NO_VALIDATE,
                null,
                InputOption::VALUE_NONE,
                'Don\'t validate the resulting output'
            )
            ->addOption(
                self::OPTION_MAIN_COMPONENT_VERSION,
                null,
                InputOption::VALUE_REQUIRED,
                'Version of the main component.'.\PHP_EOL.
                'This will override auto-detection.',
                null
            )
            ->addOption(
                self::SWITCH_NO_VERSION_NORMALIZATION,
                null,
                InputOption::VALUE_NONE,
                'Don\'t normalize component version strings.'.\PHP_EOL.
                'Per default this plugin will normalize version strings by stripping leading "v".'.\PHP_EOL.
                'This is a compatibility-switch. The next major-version of this plugin will not modify component versions.'
            )
            ->addArgument(
                self::ARGUMENT_COMPOSER_FILE,
                InputArgument::OPTIONAL,
                'Path to composer config file.'.\PHP_EOL.
                'Defaults to "composer.json" file in working directory.'
            );
    }

    /**
     * @var string
     *
     * @psalm-var \CycloneDX\Core\Spec\Version::V_*
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $specVersion = SpecFactory::VERSION_LATEST;

    /**
     * @var bool
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $excludeDev = false;

    /**
     * @var bool
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $excludePlugins = false;

    /**
     * @var bool
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $omitVersionNormalization = false;

    /**
     * @var string
     *
     * @psalm-var Options::OUTPUT_FORMAT_*
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $outputFormat = self::OUTPUT_FORMAT_XML;

    /**
     * @var bool
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $skipOutputValidation = false;

    /**
     * @var string
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $outputFile = self::OUTPUT_FILE_STDOUT;

    /**
     * @var string|null
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $composerFile;

    /**
     * @var string|null
     *
     * @readonly
     *
     * @psalm-allow-private-mutation
     */
    public $mainComponentVersion;

    /**
     * @throws ValueError
     *
     * @return $this
     *
     * @psalm-suppress MissingThrowsDocblock since {@see \Symfony\Component\Console\Input\InputInterface::getOption()} is intended to work this way
     */
    public function setFromInput(InputInterface $input): self
    {
        // region get from input

        $specVersion = $input->getOption(self::OPTION_SPEC_VERSION);
        \assert(\is_string($specVersion));
        if (false === \array_key_exists($specVersion, SpecFactory::SPECS)) {
            throw new ValueError('Invalid value for option "'.self::OPTION_SPEC_VERSION.'": '.$specVersion);
        }

        $outputFormat = $input->getOption(self::OPTION_OUTPUT_FORMAT);
        \assert(\is_string($outputFormat));
        $outputFormat = strtoupper($outputFormat);
        if (false === \in_array($outputFormat, [self::OUTPUT_FORMAT_XML, self::OUTPUT_FORMAT_JSON], true)) {
            throw new ValueError('Invalid value for option "'.self::OPTION_OUTPUT_FORMAT.'": '.$outputFormat);
        }

        $excludeDev = false !== $input->getOption(self::SWITCH_EXCLUDE_DEV);
        $excludePlugins = false !== $input->getOption(self::SWITCH_EXCLUDE_PLUGINS);
        $skipOutputValidation = false !== $input->getOption(self::SWITCH_NO_VALIDATE);
        $omitVersionNormalization = false !== $input->getOption(self::SWITCH_NO_VERSION_NORMALIZATION);
        $outputFile = $input->getOption(self::OPTION_OUTPUT_FILE);
        \assert(null === $outputFile || \is_string($outputFile));
        $composerFile = $input->getArgument(self::ARGUMENT_COMPOSER_FILE);
        \assert(null === $composerFile || \is_string($composerFile));
        $mainComponentVersion = $input->getOption(self::OPTION_MAIN_COMPONENT_VERSION);
        \assert(null === $mainComponentVersion || \is_string($mainComponentVersion));

        // endregion get from input

        // those regions are split,
        // so the state does not modify unless everything is clear

        // region set state

        $this->specVersion = $specVersion;
        $this->excludeDev = $excludeDev;
        $this->excludePlugins = $excludePlugins;
        $this->outputFormat = $outputFormat;
        $this->skipOutputValidation = $skipOutputValidation;
        $this->omitVersionNormalization = $omitVersionNormalization;
        $this->outputFile = \is_string($outputFile) && '' !== $outputFile
            ? $outputFile
            : self::OUTPUT_FILE_DEFAULT[$outputFormat];
        $this->composerFile = \is_string($composerFile) && '' !== $outputFile
            ? $composerFile
            : null;
        $this->mainComponentVersion = \is_string($mainComponentVersion) && '' !== $mainComponentVersion
            ? $mainComponentVersion
            : null;

        // endregion set state

        return $this;
    }
}
