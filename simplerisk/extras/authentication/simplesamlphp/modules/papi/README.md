PAPI module
===========

The _papi_ module provides a single authentication module:

* `papi:PAPI`: authenticate using the **PAPI** protocol.

This authentication module makes use of an external library, [_phpPoA_](https://forja.rediris.es/projects/phppoa/), in
order to authenticate users by means of the PAPI protocol. It can therefore be used to bridge between protocols,
behaving like a PAPI _Point of Access_ or as a _Service Provider_.

Installation
------------

Once you have installed SimpleSAMLphp, installing this module is very simple. Just execute the following
command in the root of your SimpleSAMLphp installation:

```
composer.phar require rediris-es/simplesamlphp-module-papi:dev-master
```

where `dev-master` instructs Composer to install the `master` branch from the Git repository. See the
[releases](https://github.com/rediris-es/simplesamlphp-module-papi/releases) available if you
want to use a stable version of the module.

Usage
-----

To use this module, enable it by creating a file named `enable` in the `modules/papi/` directory. Then you need to add
an authentication source which makes use of the `papi:PAPI` module to the `config/authsources.php` file:

```php
    'example-papi' => array(
        'papi:PAPI',

        /*
         * The site identifier that allows the module to determine which
         * configuration of the phpPoA to use.
         */
         'site' => 'example',

         /*
          * The Home Locator Identifier. Use this if your phpPoA configuration
          * points to a GPoA instead of an Authentication Server (AS), and you
          * want to skip the identity provider selection page, by directly
          * selecting one here.
          */
          'hli' => 'exampleAS',
    ),
```

User attributes
---------------

If user attributes were received upon successful authentication, then their exact names and values will be transferred
into the `$state['Attributes']` array. Please note that attribute name mapping could be needed. There's no support for
asking specific attributes during PAPI authentication. Attributes released to a Service Provider must be agreed and
configured on beforehand.
