# Deprecation Notice

Duo Security will deprecate and archive this repository on July 18, 2022. The repository will remain public and visible after that date, and integrations built using this repository’s code will continue to work. You can also continue to fork, clone, or pull from this repository after it is deprecated.

However, Duo will not provide any further releases or enhancements after the deprecation date.

Duo recommends migrating your application to the Duo Universal Prompt. Refer to [our documentation](https://duo.com/docs/universal-prompt-update-guide) for more information on how to update.

For frequently asked questions about the impact of this deprecation, please see the [Repository Deprecation FAQ](https://duosecurity.github.io/faq.html)

----

# Overview

[![Build Status](https://github.com/duosecurity/duo_php/workflows/PHP%20CI/badge.svg?branch=master)](https://github.com/duosecurity/duo_php/actions)
[![Issues](https://img.shields.io/github/issues/duosecurity/duo_php)](https://github.com/duosecurity/duo_php/issues)
[![Forks](https://img.shields.io/github/forks/duosecurity/duo_php)](https://github.com/duosecurity/duo_php/network/members)
[![Stars](https://img.shields.io/github/stars/duosecurity/duo_php)](https://github.com/duosecurity/duo_php/stargazers)
[![License](https://img.shields.io/badge/License-View%20License-orange)](https://github.com/duosecurity/duo_php/blob/master/LICENSE)

**duo_php** - Duo two-factor authentication for PHP web applications: https://duo.com/docs/duoweb-v2

This package allows a web developer to quickly add Duo's interactive, self-service, two-factor authentication to any web login form - without setting up secondary user accounts, directory synchronization, servers, or hardware.

Files located in the `js` directory should be hosted by your webserver for inclusion in web pages.

# Installing

Development:

```
$ git clone https://github.com/duosecurity/duo_php.git
$ cd duo_php
$ composer install
```

System:

```
$ composer global require duosecurity/duo_php:dev-master
```

Or add the following to your project:

```
{
    "require": {
        "duosecurity/duo_php": "dev-master"
    }
}
```

# Using

```
$ php -a -d auto_prepend_file=vendor/autoload.php
Interactive mode enabled

php > var_dump(Duo\Web::signRequest($ikey, $skey, $akey, $username));
string(202) "TX|...TX_SIGNATURE...==|...TX_HASH...:APP|...APP_SIGNATURE...==|...APP_HASH..."
```

# Demo

First add an IKEY, SKEY, and HOST to `demos/simple/index.php`, then run the following:

```
$ php -S localhost:8080 -t demos/simple/
```

# Test

```
$ ./vendor/bin/phpunit -c phpunit.xml
PHPUnit 5.3.2 by Sebastian Bergmann and contributors.

.............                                                     13 / 13 (100%)

Time: 62 ms, Memory: 6.00Mb

OK (13 tests, 13 assertions)
```

# Lint

```
$ ./vendor/bin/phpcs --standard=PSR2 -n src/* tests/*
```

# Support

Report any bugs, feature requests, etc. to us directly: support@duosecurity.com

