[![shield_gh-workflow-test]][link_gh-workflow-test]
[![shield_packagist-version]][link_packagist]
[![shield_license]][license_file]  
[![shield_website]][link_website]
[![shield_slack]][link_slack]
[![shield_groups]][link_discussion]
[![shield_twitter-follow]][link_twitter]

----

# CycloneDX PHP Library

Work with Software Bill of Materials (SBoM)
in [CycloneDX] format.

## Responsibilities

* Provide a general purpose _php_-implementation of [_CycloneDX_][CycloneDX].
* Provide [_phpDoc_](https://phpdoc.org/)- & [_psalm_](https://psalm.dev/)-typing for said implementation, so developers and dev-tools can rely on it.
* Provide data models to work with _CycloneDX_.
* Provide a JSON- and an XML-normalizer, that...
  * supports all shipped data models.
  * respects any injected [_CycloneDX_ Specification][CycloneDX-spec] and generates valid output according to it.
  * can prepare data structures for JSON- and XML-serialization.
* Serialization:
  * Provide a JSON-serializer.
  * Provide a  XML-serializer.
* Validation against _CycloneDX_ Specification:
  * Provide a JSON-serializer.
  * Provide a  XML-validator.
* Provide [_composer_-based autoloading](https://getcomposer.org/doc/01-basic-usage.md#autoloading) for downstream usage.

## Capabilities

* Enums for the following use cases:
  * `Classification` aka ComponentType
  * `ExternalReferenceType`
  * `HashAlgorithm`
* Data models for the following use cases:
  * `Bom`
  * `BomRef`, `BomRefRepository`
  * `Component`, `ComponentRepository`
  * `ExternalReference`, `ExternalReferenceRepository`
  * `HashRepository`
  * `LicenseExpression`, 
    `DisjunctiveLicenseWithName` aka NamedLicense, `DisjunctiveLicenseWithId` aka SpdxLicense,
    `DisjunctiveLicenseRepository`
  * `Metadata`
  * `Tool`, `ToolRepository`
* Factories for the following use cases:
  * Create data models from any license descriptor string
* Implementation of the [_CycloneDX_ Specification][CycloneDX-spec] for the following versions:
  * `1.3`
  * `1.2`
  * `1.1`
* Normalizers that convert data models to JSON structures
* Normalizers that convert data models to  XML structures
* Serializer that converts `Bom` data models to JSON string
* Serializer that converts `Bom` data models to  XML string
* Validator that checks JSON against _CycloneDX_ Specification
* Validator that checks  XML against _CycloneDX_ Specification

## Installation

Install via composer:

```shell
composer require cyclonedx/cyclonedx-library
```

## Usage

See extended [examples].

```php
$bom = new \CycloneDX\Core\Models\Bom();
$bom->getComponentRepository()->addComponent(
    new \CycloneDX\Core\Models\Component(
        \CycloneDX\Core\Enums\Classification::LIBRARY,
        'myComponent',
        '1.33.7'
    )
);
```

## Conflicts

Due to the fact that this library was split out of [`/src/Core` of cyclonedx-php-composer (346e6200fb2f5086061b15c2ee44f540893ce97d)](https://github.com/CycloneDX/cyclonedx-php-composer/tree/346e6200fb2f5086061b15c2ee44f540893ce97d/src/Core)  
it will conflict with its original source: `cyclonedx/cyclonedx-php-composer:<3.5`.

## Contributing

Feel free to open issues, bugreports or pull requests.  
See the [CONTRIBUTING][contributing_file] file for details.

## License

Permission to modify and redistribute is granted under the terms of the Apache 2.0 license.  
See the [LICENSE][license_file] file for the full license.

[CycloneDX]: https://cyclonedx.org/
[CycloneDX-spec]: https://github.com/CycloneDX/specification/tree/main/schema

[license_file]: https://github.com/CycloneDX/cyclonedx-php-library/blob/master/LICENSE
[contributing_file]: https://github.com/CycloneDX/cyclonedx-php-library/blob/master/CONTRIBUTING.md
[examples]: https://github.com/CycloneDX/cyclonedx-php-library/tree/master/examples

[shield_gh-workflow-test]: https://img.shields.io/github/workflow/status/CycloneDX/cyclonedx-php-library/PHP%20CI/master?logo=GitHub&logoColor=white "build"
[shield_packagist-version]: https://img.shields.io/packagist/v/cyclonedx/cyclonedx-library?logo=Packagist&logoColor=white "packagist"
[shield_license]: https://img.shields.io/github/license/CycloneDX/cyclonedx-php-library?logo=open%20source%20initiative&logoColor=white "license"
[shield_website]: https://img.shields.io/badge/https://-cyclonedx.org-blue.svg "homepage"
[shield_slack]: https://img.shields.io/badge/slack-join-blue?logo=Slack&logoColor=white "slack join"
[shield_groups]: https://img.shields.io/badge/discussion-groups.io-blue.svg "groups discussion"
[shield_twitter-follow]: https://img.shields.io/badge/Twitter-follow-blue?logo=Twitter&logoColor=white "twitter follow"
[link_gh-workflow-test]: https://github.com/CycloneDX/cyclonedx-php-library/actions/workflows/php.yml?query=branch%3Amaster
[link_packagist]: https://packagist.org/packages/cyclonedx/cyclonedx-library
[link_website]: https://cyclonedx.org/
[link_slack]: https://cyclonedx.org/slack/invite
[link_discussion]: https://groups.io/g/CycloneDX
[link_twitter]: https://twitter.com/CycloneDX_Spec
