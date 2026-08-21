[![shield_gh-workflow-test]][link_gh-workflow-test]
[![shield_packagist-version]][link_packagist]
[![shield_license]][license_file]  
[![shield_website]][link_website]
[![shield_slack]][link_slack]
[![shield_groups]][link_discussion]
[![shield_twitter-follow]][link_twitter]

----

# CycloneDX PHP Composer Plugin

A plugin for PHP's [Composer](https://getcomposer.org/)
that generates Software Bill of Materials (SBoM) in [CycloneDX](https://cyclonedx.org/) format.

## Requirements

The latest version of this plugin
supports PHP `^7.3||^8.0`
with Composer `^2.0`
.

There are older versions of this plugin available, which
support PHP `^5.5||^7.0||^8.0`
with Composer `^1.0||^2.0`
.

## Installation

As a global composer plugin:

```shell
composer global require cyclonedx/cyclonedx-php-composer
```

As a development dependency of the current project:

```shell
composer require --dev cyclonedx/cyclonedx-php-composer
```

## Usage

After successful installation, the composer command `CycloneDX:make-sbom` is available.

```text
$ composer CycloneDX:make-sbom -h
Usage:
  CycloneDX:make-sbom [options] [--] [<composer-file>]

Arguments:
  composer-file                      Path to composer config file.
                                     Defaults to "composer.json" file in working directory.

Options:
      --output-format=OUTPUT-FORMAT  Which output format to use.
                                     Values: "XML", "JSON" [default: "XML"]
      --output-file=OUTPUT-FILE      Path to the output file.
                                     Set to "-" to write to STDOUT.
                                     Depending on the output-format, default is one of: "bom.xml", "bom.json"
      --exclude-dev                  Exclude dev dependencies
      --exclude-plugins              Exclude composer plugins
      --spec-version=SPEC-VERSION    Which version of CycloneDX spec to use.
                                     Values: "1.1", "1.2", "1.3" [default: "1.3"]
      --no-validate                  Don't validate the resulting output
      --mc-version=MC-VERSION        Version of the main component.
                                     This will override auto-detection.
      --no-version-normalization     Don't normalize component version strings.
                                     Per default this plugin will normalize version strings by stripping leading "v".
                                     This is a compatibility-switch. The next major-version of this plugin will not modify component versions.
  -h, --help                         Display this help message
  -q, --quiet                        Do not output any message
  -V, --version                      Display this application version
      --ansi                         Force ANSI output
      --no-ansi                      Disable ANSI output
  -n, --no-interaction               Do not ask any interactive question
      --profile                      Display timing and memory usage information
      --no-plugins                   Whether to disable plugins.
  -d, --working-dir=WORKING-DIR      If specified, use the given directory as working directory.
      --no-cache                     Prevent use of the cache
  -v|vv|vvv, --verbose               Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Generate a CycloneDX Bill of Materials
```

## Demo

For a demo of _cyclonedx-php-composer_ see the [demo project][demo_readme].

## Internals

This Composer-Plugin utilizes the [CycloneDX library][cyclonedx-library] to generate the actual data structures.

This Composer-Plugin does **not** expose any additional _public_ api or classes - all code is marked as `@internal` and might change without any notice during version upgrades.

## Contributing

Feel free to open issues, bugreports or pull requests.  
See the [CONTRIBUTING][contributing_file] file for details.

## License

Permission to modify and redistribute is granted under the terms of the Apache 2.0 license.  
See the [LICENSE][license_file] file for the full license.

[license_file]: https://github.com/CycloneDX/cyclonedx-php-composer/blob/master/LICENSE
[contributing_file]: https://github.com/CycloneDX/cyclonedx-php-composer/blob/master/CONTRIBUTING.md
[demo_readme]: https://github.com/CycloneDX/cyclonedx-php-composer/blob/master/demo/README.md

[cyclonedx-library]: https://packagist.org/packages/cyclonedx/cyclonedx-library

[shield_gh-workflow-test]: https://img.shields.io/github/actions/workflow/status/CycloneDX/cyclonedx-php-composer/php.yml?branch=master&logo=GitHub&logoColor=white "build"
[shield_packagist-version]: https://img.shields.io/packagist/v/cyclonedx/cyclonedx-php-composer?logo=Packagist&logoColor=white "packagist"
[shield_license]: https://img.shields.io/github/license/CycloneDX/cyclonedx-php-composer?logo=open%20source%20initiative&logoColor=white "license"
[shield_website]: https://img.shields.io/badge/https://-cyclonedx.org-blue.svg "homepage"
[shield_slack]: https://img.shields.io/badge/slack-join-blue?logo=Slack&logoColor=white "slack join"
[shield_groups]: https://img.shields.io/badge/discussion-groups.io-blue.svg "groups discussion"
[shield_twitter-follow]: https://img.shields.io/badge/Twitter-follow-blue?logo=Twitter&logoColor=white "twitter follow"
[link_gh-workflow-test]: https://github.com/CycloneDX/cyclonedx-php-composer/actions/workflows/php.yml?query=branch%3Amaster
[link_packagist]: https://packagist.org/packages/cyclonedx/cyclonedx-php-composer
[link_website]: https://cyclonedx.org/
[link_slack]: https://cyclonedx.org/slack/invite
[link_discussion]: https://groups.io/g/CycloneDX
[link_twitter]: https://twitter.com/CycloneDX_Spec
