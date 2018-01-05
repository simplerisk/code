SAML 2.0 debugger module
========================

This module allows you to debug SAML 2.0 messages by decoding or encoding them according to the binding they are using,
supporting both the HTTP-Redirect and HTTP-POST bindings.

Installation
------------

Once you have installed SimpleSAMLphp, installing this module is very simple. Just execute the following
command in the root of your SimpleSAMLphp installation:

```
composer.phar require simplesamlphp/simplesamlphp-module-saml2debug:dev-master
```

where `dev-master` instructs Composer to install the `master` branch from the Git repository. See the
[releases](https://github.com/simplesamlphp/simplesamlphp-module-saml2debug/releases) available if you
want to use a stable version of the module.

The module is enabled by default. If you want to disable the module once installed, you just need to create a file named
`disable` in the `modules/saml2debug/` directory inside your SimpleSAMLphp installation.

Usage
-----

Once installed, you can use the module by going to the *federation* tab in the web interface of SimpleSAMLphp. You will
see a link there to the debugger, allowing you to encode and decode SAML 2.0 messages, selecting the binding to use.
