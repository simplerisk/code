# MediaWiki Security Check Plugin changelog

## v10.0.0
### Breaking changes
* Dropped support for PHP < 8.3

### New features
* Added support for `array_first`, `array_last`, `array_find`, and `array_find_key`
* (MW) Improved (parser) hook handler analysis to infer callables in more cases.

### Internal changes
* Bumped phan/phan to ~6.0.5, allowing future patch versions to be automatically installed

## v9.1.0
### New features
* Added support for PHP 8.5's pipe operator

### Internal changes
* Bumped phan/phan to 6.0.2

## v9.0.0
### Breaking changes
* Bumped phan/phan to 6.0.1. This is a new major version for phan.

## v8.0.0
### Breaking changes
* (MW) Dropped support for legacy hook handler styles (T401532)

### New features
* Add taintedness info for `mysqli_result` functions and methods
* (MW) Added support for first-class callables and other modern styles in hook handlers.
* (MW) Added support for `HTMLButtonField` and its `buttonlabel-raw` property

### Bug fixes
* (MW) Fix false positives in parser function hooks with `isHTML` set to false
* (MW) Fixed edge case in analysis of hooks with names containing backslashes
* Fixed caused-by lines including unrelated/redundant lines for issues involving SQL_NUMKEY taintedness

### Internal changes
* Bumped phan/phan to 5.5.2

## v7.0.0
### Breaking changes
* Drop support for PHP < 8.1
* (MW) Dropped support for unnamespaced `Parser` and `PPFrame`.

### New features
* (MW) Check SQL `$options` passed to `SelectQueryBuilder::options` and `::option`
* (MW) Check SQL `$join_conds` passed to `SelectQueryBuilder::joinConds`
* (MW) Added support for checking hook interfaces, hooks run via HookRunner classes, and hooks registered via `HookContainer::register()`

### Bug fixes
* (MW) Fix SQLi detection for `$options` and `$join_conds` arguments to `SELECT` DB functions
* (MW) Make special handling of database functions check the newer RDBMS interfaces (`IReadableDatabase` and `ISQLPlatform`)
* (MW) Fix various crashes when using array unpack in database function calls, `getQueryInfo()` methods, or parser function hook returns
* (MW) Restore exclusion of `Status::newGood()`, `Status::getValue()`, and `Status::setResult()` from analysis using the namespaced FQSEN.

### Internal changes
* Bumped phan/phan to 5.5.1

## v6.2.1
### Internal changes
* Bumped phan/phan to 5.5.0

## v6.2.0
### New features
* Support running in PHP 8.4 (fixed spurious errors seen when analyzing `exit`)
* For interface methods returning an instance of a class with a `__toString()` method, make the taintedness returned by `__toString()` propagate to the interface method.

### Bug fixes
* Mark hash and hash_hmac outputs as untainted
* Improve performance when analyzing really large arrays
* (MW) Also check the new `\MediaWiki\Message\Message` FQSEN when trying to determine if a DoubleEscaped issue involving `Message|string` is a false positive.

### Internal changes
* Bumped phan/phan to 5.4.6

## v6.1.0
### New features
* Improved accuracy of error reporting ("caused-by lines") by tracking array shapes in more places.
* Added support for analyzing `call_user_func` and `call_user_func_array`.
* Improved readability of error reporting by reordering the lines to follow parameters from call to sink (i.e. opposite to what they were before).

### Bug fixes
* Fixed a host of bugs affecting backpropagation of taintedness, resulting in anything between false positives, false negatives, inaccurate error reporting, and OOM.
* Made error reporting more accurate for arguments passed by reference.

### Internal changes
* Bumped phan/phan to 5.4.5

## v6.0.0
### Breaking changes
* (MW) Most of the taintedness values hardcoded in MediaWikiSecurityCheckPlugin::getCustomFuncTaints() have been removed, and annotations have been
  added to the relevant methods in MediaWiki itself. Therefore, this version of phan-taint-check-plugin is only compatible with MediaWiki 1.41+.

### New features
* getCustomFuncTaints implementations can now return FunctionTaintedness objects directly, in addition to arrays.
* Array keys (and shapes in general) are now tracked more granularly when backpropagating the effects of a function call.
* (MW) Analyze the `$rows` argument to `Database::insert()` more accurately, and apply similar rules to `InsertQueryBuilder::row()` and `::rows()`.
* Improved shape inference for several built-in array functions.
* (MW) Treat the `help` key in HTMLForm descriptors as an HTML sink.
* (MW) Add compatibility for new Parser FQSEN `\MediaWiki\Parser\Parser`. The non-namespaced version is also still supported.

### Bug fixes
* Fixed a bug where `*-taint` annotations in an interface method were only inherited by the method implementation in children classes.

### Internal changes
* Bumped phan/phan to 5.4.3

## v5.0.0
### Breaking changes
* The raw_param taint flag was removed; error reporting is now sufficiently good that this is no longer needed, and can be treated as normal exec.
* The taint type "misc" was removed. Use the appropriate category instead. This type was originally used for "rce" and "path", so the appropriate replacement could be one of those.
* The `SecurityCheckMulti` issue type was removed. Now, the plugin emits one issue per taint type.
* Dropped support for PHP 7.2 and 7.3.

### New features
* Added support for the effects of `unset( $var['k'] )` on the shape of `$var`.
* The plugin now infers the effect of some array_* functions on the resulting taintedness more accurately.

### Changed
* The `SecurityCheck-RCE` and `SecurityCheck-PathTraversal` issue types now have critical severity.

### Internal changes
* Bumped phan/phan to 5.4.2

## v4.0.0
### Breaking changes
* Global variables and property no longer have EXEC flags if they're later output. Previously, it was supposed to report assigning a tainted value to an object
  that is later output, but didn't work due to a bug. The relevant logic was complicating maintenance. Running phan with `--analyze-twice` will catch this kind of issues.
* The plugin no longer reanalyzes classes when the taintedness of a property is changed. Running phan with `--analyze-twice` will catch this kind of issues.

### New features
* Added a new issue type, `SecurityCheckInvalidAnnotation`, emitted for `-taint` annotations that cannot be parsed, use unknown or forbidden values (e.g. EXEC bits in `return-taint`), document non-existing parameters, or have redundant/missing `...`.

### Bug fixes
* Caused-by lines are now much more accurate for code involving function calls.

### Internal changes
* Bumped phan/phan to 5.4.1

## v3.3.2
### Bug fixes
* Improved caused-by lines for return statements consisting of a function-like call and for inherited methods.
### Internal changes
* Bumped phan/phan to 5.2.0

## v3.3.1
### Internal changes
* Bumped phan/phan to 5.1.0

## v3.3.0
### Breaking changes
* Removed support for standalone install on MediaWiki repos. Generic standalone is still supported, but the script is now called `seccheck`, not `seccheck-generic`.
* `raw_param` is now a modifier for EXEC taintedness, so it must be specified together with EXEC bits, not normal bits.
* The plugin now limits reanalysis of classes to 1 per class when the taintedness of a property is changed. This might hide some issues, but is much faster. Running phan with `--analyze-twice` will help; this might become officially suggested in the future.

### New features
* Added support for the following PHP 7.4 and PHP 8 features: arrow functions, `match`, named arguments, nullsafe method calls and property access, typed properties, constructor property promotion
* Infer array shape mutations for several array-related builtin functions
* Improved taint data for $_FILES
* The plugin now properly infers when a parameter is passed through by a function (even partially or conditionally), and can determine the resulting taintedness of a function call much more accurately
* Improved caused-by lines for setters and some functions that pass their parameters through
* It is now possible to put comments after `@param-taint` and `@return-taint` annotations
* Added taintedness data for PDO functions
* Added partial support for backpropagating NUMKEY taint, in the very few cases where false positives are highly unlikely (this will be improved)
* (MW) Improved hook registration, being now able to infer the callback in more cases
* (MW) Added partial support for HookHandlers in extension.json
* Improved handling of pass-by-reference parameters when the parameter is essentially left unchanged
* The plugin can now track array shapes when backpropagating EXEC taintedness. This brings increased accuracy when analyzing method calls.
* The following hashing functions were annotated as removing taintedness from their arguments: `md5`, `sha1` and `crc32`

### Bug fixes
* Improved merging caused-by lines to avoid duplicates
* Avoid tracking dependencies of functions with hardcoded taintedness, so to keep caused-by lines shorter and more relevant
* Fixed a bug that caused EXEC taints to be backpropagated to local variables, thus creating weird-looking issues
* Fixed some edge cases which would make phan issues disappear with taint-check enabled

### Internal changes
* Bumped phan/phan to 4.0.4
* The plugin now caches taintedness data inside AST nodes. This requires additional memory (300 MB for MW core), but reduces the runtime (30 seconds for MW core)

## v3.2.1
### Bug fixes
* Fixed a crash observed when using the polyfill parser
* Fixed two crashes introduced with the 3.2.0 release

### Internal changes
* Bumped phan/phan to 3.2.6

## v3.2.0
### New features
* Variadic parameters are now properly handled
* Array keys are now tracked separately from values
* (MW) Properly track taintedness of array keys in some HTMLForm specifiers
* Created new taint types and issues for RCE and path traversal: `SecurityCheck-RCE` and `SecurityCheck-PathTraversal`
* Added detection for ReDoS vulnerabilities. New issue: `SecurityCheck-ReDoS`
* The plugin can now properly analyze assignments with an array at the LHS
* Array shapes are now tracked more precisely when a key that cannot be determined statically is found

## v3.1.1
### Internal changes
* Allow installing the plugin in PHP 8. Analyzing code with new PHP 8 features is not supported yet (T269263)

## v3.1.0
### New features
* Increased the length limit for caused-by lines. The new limit is at 12 entries, rather than fixed at 255 characters (it was roughly doubled)
* Caused-by lines are now stored together with a taintedness value, which allows filtering taintedness depending on the sink type
* Handle a few more edge cases in foreach loops. Notably, class properties used as key or value are now
  properly analyzed, and caused-by lines now include sources of taintedness outside the loop.
* The plugin now filters taintedness based on the (real) type of variables using `if` conditions,
  parameters and return type declarations.
* Binops are now properly analysed, removing taintedness if the operation is safe.
* Caused-by lines for function calls now include a code snippet with the argument, together with its ordinal.
* Added an annotation to print the taintedness of a variable (use it with `'@phan-debug-var-taintedness $varname'`)
* Added taint data for a bunch of built-in functions

### Bug fixes
* (MW) Fixed a crash observed when using `$this` as hook handler
* Fixed an edge case that made the plugin crash when attempting to use an undeclared variable as a
  callable
* Fixed a bug that caused the same issue to be reported on multiple lines, hence creating redundant
  warnings, making it difficult to suppress them all.
* (MW) Avoid crash when Hooks::run has no arguments array
* Fixed an edge case where literal integers/strings weren't recognized as integers/strings; this brings improved tracking of SQL_NUMKEY.
* (MW) Fixed incorrect taint data for Sanitizer::removeHTMLtags (T268353)
* Slightly improved performance for recursive methods (analysis is not attempted, rather than letting it reach the recursion limit of 5)

### Internal changes
* Taintedness is now stored in a value object, rather than a plain integer.
* Function taintedness is now stored in a value object, rather than an array of integers.
* Issue descriptions now use phan templates, which notably adds support for selective colorizing.
* (MW) The plugin no longer forces types for MW globals in non-standalone mode. This is now done by mediawiki-phan-config.
* Plugin classes were moved to the `SecurityCheckPlugin` namespace.
* Bumped phan/phan to 3.2.4

## v3.0.4
### New features
* Added explicit taint info for `LinkRenderer::makeBrokenLink`
* Added explicit taint info for `shell_exec` and friends
* The plugin is now able to properly analyze conditionals, and merge the possible taints of each branch
* The plugin can now analyze pass by reference variables better
* Added support for analyzing each array element on its own

### Bug fixes
* Fixed several plugin crashes observed when analyzing weird syntax
* Fixed a crash observed with non-literal keys in getQueryInfo methods (T268055)

### Internal changes
* Bumped phan/phan to 3.0.3
* The plugin is now using PluginV3
* Objects returned by methods are now tracked in-place, and `GetReturnObjVisitor` was deleted

## v3.0.3
* Remove reference to `AST_LIST` (Daimona Eaytoy)
* Avoid shelling out to run tests (Daimona Eaytoy)
* Move hooks-related methods to a new class (Daimona Eaytoy)
* composer: Add Daimona as an author (James D. Forrester)
* Split a long method (Daimona Eaytoy)
* Expand docs for "manual" mode (Daimona Eaytoy)
* Assert that the config options we need are enabled (Daimona Eaytoy)
* Avoid conflating `stdClass` instances (Daimona Eaytoy)
* Cleanup: Various improvements suggested by PHPStorm (Daimona Eaytoy)
* Cleanup: Add return type hints where applicable (Daimona Eaytoy)
* Exclude invalid PHP files from analysis (Daimona Eaytoy)

## v3.0.2
* Fix `PhanTypeComparisonFromArray` edge cases (Daimona Eaytoy)
* Don't check type validity in `nodeIs(String|Int)` (Daimona Eaytoy)
* Optim: Don't reanalyze functions if we already have data (Daimona Eaytoy)
* Fix edge cases with `getOriginalScope` (Daimona Eaytoy)
* Make `handleMethodCall` always require a `FunctionInterface` and a function FQSEN (Daimona Eaytoy)
* Fix bad interaction with phan, part 3 (Daimona Eaytoy)
* Cleanup: Remove unnecessary `try/catch` constructs (Daimona Eaytoy)
* Cleanup: Add method to extract data from exceptions (Daimona Eaytoy)
* Cleanup: Change `taintToIssueAndSeverity` to use a switch (Daimona Eaytoy)
* Fix another edge case interaction with phan (Daimona Eaytoy)
* Fix edge case with prop access confusing other parts of phan (Daimona Eaytoy)

## v3.0.1
* build: Upgrade minus-x from 0.3.2 to 1.1.0 (James D. Forrester)
* Upgrade phan to latest version (Daimona Eaytoy)
* Properly handle `list()` assignments (Daimona Eaytoy)
* Upgrade phan to 2.4.0 (Daimona Eaytoy)

## v3.0.0
* Fix phan crash when analyzing MediaWiki core (Daimona Eaytoy)
* Add `RAW_PARAM` taint type (Daimona Eaytoy)
* build: Upgrade mediawiki-codesniffer from v29.0.0 to v30.0.0 (James D. Forrester)
* Remove outdated config settings (Daimona Eaytoy)
* Add `UnusedSuppressionPlugin` limited to our warnings (Daimona Eaytoy)
* Actually handle binary addition (Daimona Eaytoy)
* Update PHPUnit to 8.5 (Umherirrender)
* build: Upgrade mediawiki-codesniffer to v29.0.0 (James D. Forrester)
* build: Updating composer dependencies (Umherirrender)
* Upgrade phan to 2.2.13 (Daimona Eaytoy)
* Remove hack for OOUI constructors (Daimona Eaytoy)
* Upgrade to phan 2.2.5 (Daimona Eaytoy)
* Further improvements for same var reassignments (Daimona Eaytoy)
* Better handling of reassignments of the same var (Daimona Eaytoy)
* Don't fail hard when core methods cannot be found (Daimona Eaytoy)
* Shrink config files even more (Daimona Eaytoy)
* Remove explicit dependency on `ext-ast` (Daimona Eaytoy)
* Cleanup parent var linking code (Daimona Eaytoy)
* Remove awful hack for var context (Daimona Eaytoy)
* Upgrade to PHPUnit 8.4 (Daimona Eaytoy)
* build: Upgrade MW phpcs to 28.0.0 (Daimona Eaytoy)
* Replace `EXEC_TAINT` with `ALL_EXEC_TAINT` where latter was meant (Brian Wolff)
* Upgrade phan to 2.0.0, ast to 1.0.1 and require PHP72+ (Daimona Eaytoy)

## v2.1.0
* Improve caused-by lines (Daimona Eaytoy)
* Add debug for reaching max analysis depth (Daimona Eaytoy)
* Add some unhandled node kinds (Daimona Eaytoy)
* Visit `AST_EMPTY` (Daimona Eaytoy)
* Further improvements (Daimona Eaytoy)
* Handle closure vars (Daimona Eaytoy)
* Handle closures (Daimona Eaytoy)
* Make CI run phpunit tests (Daimona Eaytoy)
* Make CI run phpunit tests (Daimona Eaytoy)

## v2.0.2
* Fix a crash with the literal '`class`' (Daimona Eaytoy)
* Handle pre/post-increment/decrement operators (Daimona Eaytoy)
* Various code quality improvements (Daimona Eaytoy)
* Restore `TypedElementInterface` typehints (Daimona Eaytoy)
* Fix some FIXMEs (Daimona Eaytoy)
* Fix some issues with CI (Daimona Eaytoy)
* Add missing slashes to `MW_INSTALL_PATH` (Daimona Eaytoy)
* Re-fix failing test (Daimona Eaytoy)
* Fix a failing test (Daimona Eaytoy)

## v2.0.1
* Fix some issues with CI (Daimona Eaytoy)
* Add missing slashes to `MW_INSTALL_PATH` (Daimona Eaytoy)
* Re-fix failing test (Daimona Eaytoy)
* Fix a failing test (Daimona Eaytoy)
* Remove a duplicated method (Daimona Eaytoy)
* When suppressing a warning, also suppress side effects (Brian Wolff)
* Mark `IDatabase::buildLike` as something that escapes SQL (Brian Wolff)
* Special handling for `Linker::makeExternalLink` (Brian Wolff)
* When in MW mode, consider XSS in the maintenance directory to be false positives (Brian Wolff)
* Prevent an `EXEC` variable from tainting itself (Brian Wolff)

## v2.0.0
* Remove wrong `EXEC` bits from MW functions (Daimona Eaytoy)
* Take into account implicit BranchScopes (Daimona Eaytoy)
* Update readme (Daimona Eaytoy)
* Add a file with base config (Daimona Eaytoy)
* Temporarily lower ast requirement (Daimona Eaytoy)
* Hotfix for OOUI exclusion (Daimona Eaytoy)
* Handle nested calls (Daimona Eaytoy)
* Set taintedness to `NO_TAINT` for `class-string` and `callable-string` (Daimona Eaytoy)
* Update integration tests (Daimona Eaytoy)
* Fix global variable handling (Daimona Eaytoy)
* Add checks for `ClosureType` (Daimona Eaytoy)
* Transfer the taintedness from objects to props (Daimona Eaytoy)
* Prevent class props from sending taintedness too far (Daimona Eaytoy)
* Restore code bit for linking var to parentvar (Daimona Eaytoy)
* Make `nodeIsString` work again (Daimona Eaytoy)
* Unbreak `passByReference` parameters handling (Daimona Eaytoy)
* Hack: exclude OOUI constructors from DoubleEscape reporting (Daimona Eaytoy)
* Unbreak handling of `$argc` and `$argv` (Daimona Eaytoy)
* Unbreak docblock parsing (Daimona Eaytoy)
* Fix phan issues (Daimona Eaytoy)
* Upgrade phan to 1.3.2 and php-ast to 1.0.1 (Daimona Eaytoy)
* When suppressing a warning, also suppress side effects (Brian Wolff)
* Mark `IDatabase::buildLike` as something that escapes SQL (Brian Wolff)
* Special handling for `Linker::makeExternalLink` (Brian Wolff)
* When in MW mode, consider XSS in the maintenance directory to be false positives (Brian Wolff)
* Prevent an `EXEC` variable from tainting itself (Brian Wolff)
* Upgrade phan to 1.2.6 (Daimona Eaytoy)
* Minor fixes (Daimona Eaytoy)
* Upgrade phan to 1.0.0 (Daimona Eaytoy)
* Upgrade to PluginV2 (Daimona Eaytoy)
* Turn `TaintednessBaseVisitor` into a trait (Daimona Eaytoy)
* Change inheritance for MW analyzer (Daimona Eaytoy)
* Upgrade phan to 0.9.6 (Daimona Eaytoy)
* Upgrade phan to 0.8.13 (Daimona Eaytoy)
* Move regression test to PHPUnit (Daimona Eaytoy)
* Upgrade phan to 0.8.6 (Daimona Eaytoy)
* Minor fixes (Daimona Eaytoy)
* Remove phpcs bootstrap. (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 24.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 23.0.0 (libraryupgrader)
* Add another test case related to batch insert. (Brian Wolff)

## v1.5.1
* Fix fatal when using global keyword with indirect variable (Brian Wolff)
* Clarify `SECURITY_CHECK_EXT_PATH` documentation (Kunal Mehta)

## v1.5.0
* Avoid false positive related to `getQueryInfo()` methods. (Brian Wolff)
* Include syntax errors in the output of plugin. (Brian Wolff)
* Fix a fatal during a misdetected `HTMLForm` specifier with empty class (Brian Wolff)
* Fix IN list case for db conds when doing `$conds['field'][] = $tainted` (Brian Wolff)
* Fix some confusion over which group of taints to mask out in various places (Brian Wolff)
* Treat `htmlform type=info`'s 'rawrow' option like 'raw' (Brian Wolff)
* Disable `htmlform` detection inside `AuthenticationRequest` (Brian Wolff)
* Better handling of `HTMLForm $options` (Brian Wolff)
* Support custom checking for `IDatabase::makeList` (Brian Wolff)
* Update README expand limitation section (Brian Wolff)
* Link to docker image instructions in README.md (Brian Wolff)
* Make parser hooks work properly even without type hints (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 22.0.0 (libraryupgrader)
* Fix bug in how taint propagation works (Brian Wolff)

## v1.4.0
* Make `seccheck-mwext` and `seccheck-fast-mwext` work with skins (Brian Wolff)
* Make `onlysafefor_html` not mark things as `exec_escaped`. (Brian Wolff)
* Mark `base64_encode` as escaping taint. (Brian Wolff)
* Fix error in argument handling in test script (Brian Wolff)
* Add an indirect test case to taghook test (Brian Wolff)
* Move builtin taints for `Parser` & `ParserOutput` into inline annotations (Brian Wolff)
* Prevent `NO_OVERRIDE` flag from being propagated during assignment (Brian Wolff)
* Add support for reading skin.json in addition to extension.json (Brian Wolff)

## v1.3.1
* Ignore tests/ in mwext-fast (Kunal Mehta)
* Fix markdown syntax in README (Umherirrender)

## v1.3.0
* Refactor docblock taint annotation to support docblocks on interfaces (Brian Wolff)
* Improve tracking of outputting class members (Brian Wolff)
* Standardize casing in error as "Calling method..." (method is lowercase) (Kunal Mehta)
* Fix bug when argument both normal taint and execute taint (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 21.0.0 (libraryupgrader)
* Fix bug where pass by ref causing func to be treated as unknown (Brian Wolff)
* rm the hardcoded OOUI taints. They were wrong. (Brian Wolff)
* Add code to force type for MW globals (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 20.0.0 (libraryupgrader)

## v1.2.0
* Add support for docblock taint annotations (Brian Wolff)
* Fix phan tests (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 18.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 17.0.0 (libraryupgrader)
* build: Updating jakub-onderka/php-parallel-lint to 1.0.0 (libraryupgrader)
* Add support for checking `HTMLForm` specifiers (Brian Wolff)
* Use SPDX 3.0 license identifier (Umherirrender)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.1 (libraryupgrader)
* build: Adding MinusX (Umherirrender)
* Don't mark `\Xml::encodeJsVar` and `encodeJsCall` as double escaping (Brian Wolff)
* Fix missing initial `\` in class name list (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.0 (libraryupgrader)
* Add support for looking at `__toString()` when object in string context (Brian Wolff)
* Improve some of the double escaping checks. (Brian Wolff)
* Depend upon phan/phan instead of deprecated etsy/phan (Kunal Mehta)
* build: Updating mediawiki/mediawiki-codesniffer to 15.0.0 (Kunal Mehta)
* Add `Hooks::runWithoutAbort` support (Phantom42)
* Add double escaping detection (Albert221)
* Appearently this doesn't work with php-ast 0.1.5 (Brian Wolff)

## v1.1.0
* Html escaping functions shouldn't clear non-html taint (Brian Wolff)
* Finish rename to mediawiki/phan-taint-check-plugin (Brian Wolff)
* Add .gitattributes file (Brian Wolff)
* Fix some typos (Kunal Mehta)
* Fix indentation in .phpcs.xml (Kunal Mehta)
* Replace `SecurityCheckPlugin::` with `self::` where possible (Brian Wolff)
* Add a test script for people whose php bin is not 7 (Brian Wolff)
* Disable progress bar in composer test, as ugly on jenkins (Brian Wolff)
* Rename plugin to mediawiki/phan-taint-check-plugin (Brian Wolff)
* Add a note about how it can't validate certain types of SQL (Brian Wolff)
* Version should be php 7.0 (7.1 not supported due to dependency) (Brian Wolff)
* Rename to "mediawiki/phan-security-plugin" (Kunal Mehta)
* Fix test that didn't pass lint (Brian Wolff)
* Follow-up on Ie9106c80 (MarcoAurelio)
* build: update composer.json (MarcoAurelio)
* Add .gitreview (MarcoAurelio)
* Add GPL license headers (Brian Wolff)
* Make README prettier (Bryan Davis)

## v1.0.0
* Update composer.json (Brian Wolff)
* Support installing via composer. (Brian Wolff)
* Update README (Brian Wolff)
* Move plugin entry points to root directory (Brian Wolff)
* Fix some false positives discovered while testing with MW (Brian Wolff)
* Fix various false positives found when testing with MW (Brian Wolff)
* Add a test for `list()` support (Brian Wolff)
* Add test for array addition with `SQL_NUMKEY` (Brian Wolff)
* Minor fixes discovered during testing (Brian Wolff)
* Ensure that errors related to function are per param (Brian Wolff)
* Minor fixes to the eval case (Brian Wolff)
* Some debugging fixes (Brian Wolff)
* Support checking `getQueryInfo()` return; Process `$options` & `$join_conds` (Brian Wolff)
* Fix handling of `IN(...)` lists in db `select` wrapper (Brian Wolff)
* Add support for `IDatabase::select` style arguments (Brian Wolff)
* Fix bug where non-local variables are treated like local (Brian Wolff)
* Add `ARRAY_OK` flag for functions that are safe with arrays (Brian Wolff)
* Make unit tests for extension.json always work (Brian Wolff)
* Make error messages from hooks be in extension instead of core (Brian Wolff)
* Avoid duplication in output (Brian Wolff)
* Fix some minor issues (Brian Wolff)
* Handle dispatching of hooks on `Hooks::run()` (Brian Wolff)
* Support loading hook information from extension.json (Brian Wolff)
* Make more clear error messages, distinguishing different issue types (Brian Wolff)
* Support recognizing `$wgHooks/$_GLOBALS['wgHooks']` (Brian Wolff)
* Keep track of hook registrations (Brian Wolff)
* Add support for parser tag hooks (Brian Wolff)
* Support `ParserFunctions`, and start of work for hooks in general (Brian Wolff)
* Add taint for db related function. Fix handling of subclasses (Brian Wolff)
* Mention phan version requirements (Brian Wolff)
* Fix remaining tests (mostly phpcs) (Brian Wolff)
* Fix various tests (Brian Wolff)
* Add composer and phpcs. (Brian Wolff)
* Use the normal GPL v2 (Kunal Mehta)
* Do not ouput very noisy debug by default (Brian Wolff)
* Initial commit. (Brian Wolff)
