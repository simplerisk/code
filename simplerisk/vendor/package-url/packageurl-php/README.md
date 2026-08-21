[![shield_gh-workflow-test]][link_gh-workflow-test]
[![shield_packagist-version]][link_packagist]
[![shield_license]][license_file]

----

# Package URL (purl) for PHP

A parser and builder based on [package url spec],
implemented in PHP.

License: MIT

## Install

```shell
composer require package-url/packageurl-php
```

## Usage

see also [the examples](https://github.com/package-url/packageurl-php/tree/main/examples).

```php
<?php

use PackageUrl\PackageUrl;

$purl = (new PackageUrl('composer', 'console'))
    ->setNamespace('symfony')
    ->setVersion('6.3.8')
    ->setQualifiers([
        PackageUrl::QUALIFIER_VCS_URL => 'git+https://github.com/symfony/console.git@v6.3.8',
    ]);

$purlString = $purl->toString();

// string(96) "pkg:composer/symfony/console@6.3.8?vcs_url=git%2Bhttps://github.com/symfony/console.git%40v6.3.8"
var_dump($purlString);

// string(96) "pkg:composer/symfony/console@6.3.8?vcs_url=git%2Bhttps://github.com/symfony/console.git%40v6.3.8"
var_dump((string) $purl);

$purl2 = PackageUrl::fromString($purlString);
// bool(true)
var_dump($purl == $purl2);
```

## Contributing

Feel free to open pull requests.  
See the [contribution docs][contributing_file] for details.


[package url spec]: https://github.com/package-url/purl-spec/blob/master/PURL-SPECIFICATION.rst

[license_file]: https://github.com/package-url/packageurl-php/blob/main/LICENSE
[contributing_file]: https://github.com/package-url/packageurl-php/blob/main/CONTRIBUTING.md

[shield_gh-workflow-test]: https://img.shields.io/github/actions/workflow/status/package-url/packageurl-php/php.yml?branch=main&?logo=GitHub&logoColor=white "build"
[shield_packagist-version]: https://img.shields.io/packagist/v/package-url/packageurl-php?logo=&logoColor=white "packagist"
[shield_license]: https://img.shields.io/github/license/package-url/packageurl-php "license"
[link_gh-workflow-test]: https://github.com/package-url/packageurl-php/actions?workflow=PHP+CI
[link_packagist]: https://packagist.org/packages/package-url/packageurl-php
