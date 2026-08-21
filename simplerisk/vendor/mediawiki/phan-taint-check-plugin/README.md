Phan Security Check Plugin
===============================

This is a plugin to [Phan] to try and detect security issues
(such as [XSS]). It keeps track of any time a user can modify
a variable, and checks to see that such variables are
escaped before being output as html or used as an sql query, etc.

It supports generic PHP projects, and it also has a dedicated mode
for MediaWiki code (analyzes hooks, HTMLForms and Database methods).

A [web demo] is available.

Usage
-----

### Install

    $ composer require --dev mediawiki/phan-taint-check-plugin

### Usage
The plugin can be used in both "manual" and "standalone" mode. The former is the best
choice if your project is already running phan, and almost no configuration is needed.
The latter should only be used if you don't want to add phan to your project, and is not
supported for MediaWiki-related code. For more information about Wikimedia's use of this
plugin see https://www.mediawiki.org/wiki/Phan-taint-check-plugin.

#### Manual
You simply have to add taint-check to the `plugins` section of your phan config. Assuming
that taint-check is in the standard vendor location, e.g.
` $seccheckPath = 'vendor/mediawiki/phan-taint-check-plugin/';`, the file to include is
`"$seccheckPath/GenericSecurityCheckPlugin.php"` for a generic project, and
`"$seccheckPath/MediaWikiSecurityCheckPlugin.php"` for a MediaWiki project.

Also, make sure that quick mode is disabled, or the plugin won't work:
```php
   'quick_mode' => false
```

You should also add `SecurityCheck-LikelyFalsePositive` and
`SecurityCheck-PHPSerializeInjection` to `suppress_issue_types` (the latter
has a high rate of false positives).

Then run phan as you normally would:

    $ vendor/bin/phan -d . --long-progress-bar

Running phan with `--analyze-twice` will catch additional security issues that
might go unnoticed in the normal analysis phase. A known limitation of this is that
the same issue might be reported more than once with different caused-by lines.

#### Standalone
You can run taint-check via:

    $ ./vendor/bin/seccheck

You might want to add a composer script alias for that:

```json
  "scripts": {
     "seccheck": "seccheck"
  }
```

Note that false positives are disabled by default.


Plugin output
-------------

The plugin will output various issue types depending on what it
detects. The issue types it outputs are:

* `SecurityCheck-XSS`
* `SecurityCheck-SQLInjection`
* `SecurityCheck-ShellInjection`
* `SecurityCheck-PHPSerializeInjection` - For when someone does `unserialize( $_GET['d'] );`
  This issue type seems to have a high false positive rate currently.
* `SecurityCheck-CUSTOM1` - To allow people to have custom taint types
* `SecurityCheck-CUSTOM2` - ditto
* `SecurityCheck-DoubleEscaped` - Detecting that HTML is being double escaped
* `SecurityCheck-RCE` - Remote code execution, e.g. `eval( $_GET['foo'] )`
* `SecurityCheck-PathTraversal` - Path traversal, e.g. `require $_GET['foo']`
* `SecurityCheck-ReDoS` - Regular expression denial of service (ReDoS), e.g. `preg_match( $_GET['foo'], 'foo')`
* `SecurityCheck-LikelyFalsePositive` - A potential issue, but probably not.
  Mostly happens when the plugin gets confused.

The severity field is usually marked as `Issue::SEVERITY_NORMAL (5)`. False
positives get `Issue::SEVERITY_LOW (0)`. Issues that may result in server
compromise (as opposed to just end user compromise) such as shell or sql
injection are marked as `Issue::SEVERITY_CRITICAL (10)`.
SerializationInjection would normally be "critical" but its currently denoted
as a severity of NORMAL because the check seems to have a high false positive
rate at the moment.

You can use the `-y` command line option of Phan to filter by severity.

How to avoid false positives
----------------------------

If you need to suppress a false positive, you can put `@suppress NAME-OF-WARNING`
in the docblock for a function/method. Alternatively, you can use other types of
suppression, like `@phan-suppress-next-line`. See phan's readme for a complete
list.
The `@param-taint` and `@return-taint` (see "Customizing" section) are also very useful
with dealing with false positives.

Note that the plugin will report possible XSS vulnerabilities in CLI context. To avoid them,
you can suppress `SecurityCheck-XSS` file-wide with `@phan-file-suppress` in CLI scripts, or
for the whole application (using the `suppress_issue_types` config option) if the application only
consists of CLI scripts. Alternatively, if all outputting happens from an internal function, you
can use `@param-taint` as follows:
```php
  /**
   * @param-taint $stuffToPrint none
   */
  public function printMyStuff( string $stuffToPrint ) {
    echo $stuffToPrint;
  }
```

When debugging security issues, you can use:
```
'@phan-debug-var-taintedness $varname';
```
this will emit a `SecurityCheckDebugTaintedness` issue containing the taintedness of `$varname`
at the line where the annotation is found. Note that you have to insert the annotation in a string
literal; comments will not work. See also phan's `@phan-debug-var` annotation.

Notable limitations
-------------------
### General limitations

* When an issue is output, the plugin tries to include details about what line
  originally caused the issue. Usually it works, but sometimes it gives
  misleading/wrong information
* The plugin won't recognize things that do custom escaping. If you have
  custom escaping methods, you must add annotations to its docblock so
  that the plugin can recognize it. See the Customizing section.
* Phan does not currently have an API for accessing subclasses for a given class.
  Therefore the SecurityCheckPlugin cannot accommodate certain data flows for
  subclasses that should obviously be considered tainted. The workaround for this
  is to mark any relevant subclass functions as `@return-taint html`.

### MediaWiki specific limitations
* With pass by reference parameters to MediaWiki hooks,
  sometimes the line number is the hook call in MediaWiki core, instead of
  the hook subscriber in the extension that caused the issue.
* The plugin can only validate the fifth (`$options`) and sixth (`$join_cond`)
  of MediaWiki's `IDatabase::select()` if its provided directly as an array
  literal, or directly returned as an array literal from a `getQueryInfo()`
  method.

Customizing
-----------
The plugin supports being customized, by subclassing the [SecurityCheckPlugin]
class. For a complex example of doing so, see [MediaWikiSecurityCheckPlugin].

Sometimes you have methods in your codebase that alter the taint of a
variable. For example, a custom html escaping function should clear the
html taint bit. Similarly, sometimes phan-taint-check can get confused and
you want to override the taint calculated for a specific function.

You can do this by adding a taint directive in a docblock comment. For example:

```php
/**
 * My function description
 *
 * @param string $html the text to be escaped
 * @param-taint $html escapes_html
 */
function escapeHtml( $html ) {
}
```

Methods also inherit these directives from abstract definitions in ancestor interfaces, but not from concrete implementations in ancestor classes.

Taint directives are prefixed with either `@param-taint $parametername` or `@return-taint`. If there are multiple directives they can be separated by a comma. `@param-taint` is used for either marking how taint is transmitted from the parameter to the methods return value, or when used with `exec_` directives, to mark places where parameters are outputted/executed. `@return-taint` is used to adjust the return value's taint regardless of the input parameters.

The type of directives include:
* `exec_$TYPE` - If a parameter is marked as `exec_$TYPE` then feeding that parameter a value with `$TYPE` taint will result in a warning triggered. Typically you would use this when a function that outputs or executes its parameter
* `escapes_$TYPE` - Used for parameters where the function escapes and then returns the parameter. So `escapes_sql` would clear the sql taint bit, but leave other taint bits alone.
* `onlysafefor_$TYPE` - For use in `@return-taint`, marks the return type as safe for a specific `$TYPE` but unsafe for the other types.
* `$TYPE` - if just the type is specified in a parameter, it is bitwised AND with the input variable's taint. Normally you wouldn't want to do this, but can be useful when `$TYPE` is `none` to specify that the parameter is not used to generate the return value. In an `@return` this could be used to enumerate which taint flags the return value has, which is usually only useful when specified as `tainted` to say it has all flags.
* `array_ok` - special purpose flag to say ignore tainted arguments if they are in an array.
* `allow_override` - Special purpose flag to specify that that taint annotation should be overridden by phan-taint-check if it can detect a specific taint.

The value for `$TYPE` can be one of `htmlnoent`, `html`, `sql`, `shell`, `serialize`, `custom1`, `custom2`, `code`, `path`, `regex`, `sql_numkey`, `escaped`, `none`, `tainted`. Most of these are taint categories, except:
* `htmlnoent` - like `html` but disable double escaping detection that gets used with `html`. When `escapes_html` is specified, escaped automatically gets added to `@return`, and `exec_escaped` is added to `@param`. Similarly `onlysafefor_html` is equivalent to `onlysafefor_htmlnoent,escaped`.
* `none` - Means no taint
* `tainted` - Means all taint categories except special categories (equivalent to `SecurityCheckPlugin::YES_TAINT`)
* `escaped` - Is used to mean the value is already escaped (To track double escaping)
* `sql_numkey` - Is fairly special purpose for MediaWiki. It ignores taint in arrays if they are for associative keys.

The default value for `@param-taint` is `tainted` if it's a string (or other dangerous type), and `none` if it's something like an integer. The default value for `@return-taint` is `allow_override` (Which is equivalent to `none` unless something better can be autodetected).

Instead of annotating methods in your codebase, you can also customize
phan-taint-check to have builtin knowledge of method taints. In addition
you can extend the plugin to have fairly arbitrary behaviour.

To do this, you override the `getCustomFuncTaints()` method. This method
returns an associative array of fully qualified method names to an array
describing how the taint of the return value of the function in terms of its
arguments. The numeric keys correspond to the number of an argument, and an
'overall' key adds taint that is not present in any of the arguments.
Basically for each argument, the plugin takes the taint of the argument,
bitwise AND's it to its entry in the array, and then bitwise OR's the overall
key. If any of the keys in the array have an EXEC flags, then an issue is
immediately raised if the corresponding taint is fed the function (For
example, an output function). The EXEC flags don't work in the 'overall' key.

For example, [htmlspecialchars] which removes html taint, escapes its argument and returns the
escaped value would look like:

```php
'htmlspecialchars' => [
	( self::YES_TAINT & ~self::HTML_TAINT ) | self::ESCAPED_EXEC_TAINT,
	'overall' => self::ESCAPED,
];
```

Environment variables
---------------------

The following environment variables affect the plugin. Normally you would not
have to adjust these.

* `SECURITY_CHECK_EXT_PATH` - Path to directory containing
  `extension.json`/`skin.json` when in MediaWiki mode.
  If not set assumes the project root directory.
* `SECCHECK_DEBUG` - File to output extra debug information (If running from
  `shell`, `/dev/stderr` is convenient)

License
-------

[GNU General Public License, version 2 or later]

[web demo]: https://doc.wikimedia.org/mediawiki-tools-phan-SecurityCheckPlugin/master/demos/
[Phan]: https://github.com/phan/phan
[XSS]: https://en.wikipedia.org/wiki/Cross-site_scripting
[SecurityCheckPlugin]: src/SecurityCheckPlugin.php
[MediaWikiSecurityCheckPlugin]: MediaWikiSecurityCheckPlugin.php
[htmlspecialchars]: https://secure.php.net/htmlspecialchars
[GNU General Public License, version 2 or later]: COPYING
