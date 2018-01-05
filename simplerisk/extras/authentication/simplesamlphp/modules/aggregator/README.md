Aggregator
==========

This is a module for SimpleSAMLphp that aggregates a set of SAML entities into SAML 2.0 metadata documents.
The resulting metadata documents contain an EntitiesDescriptor element with the multiple entities configured
as sources inside. Multiple aggregates can be configured at the same time.

Please note that **this module has been deprecated** in favour of the more recent [Aggregator2 module](https://github.com/simplesamlphp/simplesamlphp-module-aggregator2).

Configuration
-------------

To configure the aggregator module, add a PHP file named `module_aggregate.php` to the `config` directory in the root of your SimpleSAMLphp installation. Alternatively, you can use the
configuration template provider in the `config-templates` directory of this module.

The configuration file includes an option `aggregators`, which includes a indexed list of different aggregator configurations that all can be accessed independently. The structure is as follows:

```php
	'aggregators' => array(
		'aggr1' => array(
			'sources' => [...]
			[...local params...]
		),
		'aggr2' => ...
	)
	[...global params...]
```

All of the global parameters can be overriden for each aggregator. Here is a list of the available (global) paramters:

* `maxDuration`: Max validity of metadata (duration) in seconds.

* `reconstruct`: Whether simpleSAMLphp should regenerate the metadata XML (TRUE) or pass-through the input metadata XML (FALSE).

* `RegistrationInfo`:   Allows to specify information about the registrar of this metadata. Please refer to the
[MDRPI extension](https://simplesamlphp.org/docs/stable/simplesamlphp-metadata-extensions-rpi) document for further information.

* `set`: By default all SAML types are available, including: `array('saml20-idp-remote', 'saml20-sp-remote', 'shib13-idp-remote', 'shib13-sp-remote')`. This list can be reduced by specifying one of the following values:

    * `saml20-idp-remote`
    * `saml20-sp-remote`
    * `shib13-idp-remote`
    * `shib13-sp-remote`
    * `saml2`
    * `shib13`

* `sign.enable`: Enable signing of metadata document

* `sign.certificate`: Certificate to embed, corresponding to the private key.

* `sign.privatekey`: Private key to use when signing

* `sign.privatekey_pass`: Optionally a passphrase to the private key


Accessing the aggregate
-----------------------

On the SimpleSAMLphp frontpage on the federation tab, there is a link to the aggregator named *Metadata aggregator*.

When accessing the aggregator endpoint without specifying an aggregate ID, a list of available aggregators will be presented, with different options for mime-type presenting the result.

The endpoint supports the following query parameter:

* `id`: The ID of the aggregator (From configuration file)

* `set`: Subset the available types of SAML entities. Similar to the `set` parameter described over in the configuration file description.

* `exclude`: Specify a `tag` that will be excluded from the metadata set. Useful for leaving out your own federation metadata.

* `mimetype`: Select the Mime-Type that will be used. Default is `application/samlmetadata+xml`.

