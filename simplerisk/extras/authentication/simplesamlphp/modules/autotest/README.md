Autotest module
===============

This module provides an interface to do automatic testing of authentication sources.

Installation
------------

Once you have installed SimpleSAMLphp, installing this module is very simple. Just execute the following
command in the root of your SimpleSAMLphp installation:

```
composer.phar require simplesamlphp/simplesamlphp-module-autotest:dev-master
```

where `dev-master` instructs Composer to install the `master` branch from the Git repository. See the
[releases](https://github.com/simplesamlphp/simplesamlphp-module-autotest/releases) available if you
want to use a stable version of the module.

The module is enabled by default. If you want to disable the module once installed, you just need to create a file named
`disable` in the `modules/autotest/` directory inside your SimpleSAMLphp installation.

Usage
-----

This module provides three web pages:

- `SIMPLESAMLPHP_ROOT/module.php/autotest/login.php`
- `SIMPLESAMLPHP_ROOT/module.php/autotest/logout.php`
- `SIMPLESAMLPHP_ROOT/module.php/autotest/attributes.php`

All the web pages have a mandatory parameter 'SourceID', which is the name of the authentication source.

On success, the web pages print a single line with "OK". The attributes page will also list all the attributes of the
user. On error they set the HTTP status code to 500 Internal Server Error, print a line with "ERROR" and then any
information about the error.

Note that you still have to parse the login pages to extract the parameters in the login form.
