<?php

declare(strict_types=1);

namespace SimpleSAML\Assert;

use BadMethodCallException; // Requires ext-spl
use DateTime; // Requires ext-date
use DateTimeImmutable; // Requires ext-date
use InvalidArgumentException; // Requires ext-spl
use Throwable;
use UnitEnum;
use Webmozart\Assert\Assert as Webmozart;

use function array_pop;
use function array_unshift;
use function call_user_func_array;
use function end;
use function is_array;
use function is_callable;
use function is_object;
use function is_resource;
use function is_string;
use function is_subclass_of;
use function lcfirst;
use function method_exists;
use function preg_match; // Requires ext-pcre
use function reset;
use function sprintf;
use function strval;

/**
 * Webmozart\Assert wrapper class
 *
 * @package simplesamlphp/assert
 *
 * @method static mixed string(mixed $value, string $message = '', string $exception = '')
 * @method static mixed stringNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed integer(mixed $value, string $message = '', string $exception = '')
 * @method static mixed integerish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed positiveInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed notNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed negativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed float(mixed $value, string $message = '', string $exception = '')
 * @method static mixed numeric(mixed $value, string $message = '', string $exception = '')
 * @method static mixed natural(mixed $value, string $message = '', string $exception = '')
 * @method static mixed boolean(mixed $value, string $message = '', string $exception = '')
 * @method static mixed scalar(mixed $value, string $message = '', string $exception = '')
 * @method static mixed object(mixed $value, string $message = '', string $exception = '')
 * @method static mixed objectish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed resource(mixed $value, string|null $type = null, string $message = '', string $exception = '')
 * @method static mixed isInitialized(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isCallable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isArray(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isArrayAccessible(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isCountable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isIterable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed isInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed notInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed isInstanceOfAny(mixed $value, array<object|string> $classes, string $message = '', string $exception = '')
 * @method static mixed isAOf(string|object $value, string $class, string $message = '', string $exception = '')
 * @method static mixed isNotA(string|object $value, string $class, string $message = '', string $exception = '')
 * @method static mixed isAnyOf(string|object $value, string[] $classes, string $message = '', string $exception = '')
 * @method static mixed isEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed notEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed null(mixed $value, string $message = '', string $exception = '')
 * @method static mixed notNull(mixed $value, string $message = '', string $exception = '')
 * @method static mixed true(mixed $value, string $message = '', string $exception = '')
 * @method static mixed false(mixed $value, string $message = '', string $exception = '')
 * @method static mixed notFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed ip(mixed $value, string $message = '', string $exception = '')
 * @method static mixed ipv4(mixed $value, string $message = '', string $exception = '')
 * @method static mixed ipv6(mixed $value, string $message = '', string $exception = '')
 * @method static mixed email(mixed $value, string $message = '', string $exception = '')
 * @method static mixed uniqueValues(mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed eq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed notEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed same(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed notSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed greaterThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed greaterThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed lessThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed lessThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed range(mixed $value, mixed $min, mixed $max, string $message = '', string $exception = '')
 * @method static mixed oneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed inArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed notInArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed notOneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed contains(string $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed notContains(string $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed notWhitespaceOnly($value, string $message = '', string $exception = '')
 * @method static mixed startsWith(string $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed notStartsWith(string $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed startsWithLetter(mixed $value, string $message = '', string $exception = '')
 * @method static mixed endsWith(string $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed notEndsWith(string $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed regex(string $value, string $pattern, string $message = '', string $exception = '')
 * @method static mixed notRegex(string $value, string $pattern, string $message = '', string $exception = '')
 * @method static mixed unicodeLetters(mixed $value, string $message = '', string $exception = '')
 * @method static mixed alpha(mixed $value, string $message = '', string $exception = '')
 * @method static mixed digits(string $value, string $message = '', string $exception = '')
 * @method static mixed alnum(string $value, string $message = '', string $exception = '')
 * @method static mixed lower(string $value, string $message = '', string $exception = '')
 * @method static mixed upper(string $value, string $message = '', string $exception = '')
 * @method static mixed length(string $value, int $length, string $message = '', string $exception = '')
 * @method static mixed minLength(string $value, int|float $min, string $message = '', string $exception = '')
 * @method static mixed maxLength(string $value, int|float $max, string $message = '', string $exception = '')
 * @method static mixed lengthBetween(string $value, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed fileExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed file(mixed $value, string $message = '', string $exception = '')
 * @method static mixed directory(mixed $value, string $message = '', string $exception = '')
 * @method static mixed readable(string $value, string $message = '', string $exception = '')
 * @method static mixed writable(string $value, string $message = '', string $exception = '')
 * @method static mixed classExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed subclassOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed interfaceExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed implementsInterface(mixed $value, mixed $interface, string $message = '', string $exception = '')
 * @method static mixed propertyExists(string|object $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed propertyNotExists(string|object $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed methodExists(string|object $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed methodNotExists(string|object $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed keyExists(mixed[] $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed keyNotExists(mixed[] $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed validArrayKey($value, string $message = '', string $exception = '')
 * @method static mixed count(\Countable|mixed[] $array, int $number, string $message = '', string $exception = '')
 * @method static mixed minCount(\Countable|mixed[] $array, int|float $min, string $message = '', string $exception = '')
 * @method static mixed maxCount(\Countable|mixed[] $array, int|float $max, string $message = '', string $exception = '')
 * @method static mixed countBetween(\Countable|mixed[] $array, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed isList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed isNonEmptyList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed isMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed isStatic(\Closure $callable, string $message = '', string $exception = '')
 * @method static mixed notStatic(\Closure $callable, string $message = '', string $exception = '')
 * @method static mixed isNonEmptyMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed uuid(string $value, string $message = '', string $exception = '')
 * @method static mixed throws(\Closure $expression, string $class = 'Exception', string $message = '', string $exception = '')
 *
 * @method static mixed nullOrString(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrStringNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIntegerish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrPositiveInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNotNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrFloat(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNumeric(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNatural(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrBoolean(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrScalar(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrObject(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrObjectish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrResource(mixed $value, string|null $type = null, string $message = '', string $exception = '')
 * @method static mixed nullOrIsCallable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIsArray(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIsArrayAccessible(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIsCountable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIsIterable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIsInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed nullOrNotInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed nullOrIsInstanceOfAny(mixed $value, array<object|string> $classes, string $message = '', string $exception = '')
 * @method static mixed nullOrIsAOf(object|string|null $value, string $class, string $message = '', string $exception = '')
 * @method static mixed nullOrIsNotA(object|string|null $value, string $class, string $message = '', string $exception = '')
 * @method static mixed nullOrIsAnyOf(object|string|null $value, string[] $classes, string $message = '', string $exception = '')
 * @method static mixed nullOrIsEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrTrue(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrNotFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIp(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIpv4(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrIpv6(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrEmail(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrUniqueValues(mixed[]|null $values, string $message = '', string $exception = '')
 * @method static mixed nullOrEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed nullOrNotEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed nullOrSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed nullOrNotSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed nullOrGreaterThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed nullOrGreaterThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed nullOrLessThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed nullOrLessThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed nullOrRange(mixed $value, mixed $min, mixed $max, string $message = '', string $exception = '')
 * @method static mixed nullOrOneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed nullOrInArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed nullOrNotOneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed nullOrNotInArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed nullOrContains(string|null $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed nullOrNotContains(string|null $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed nullOrNotWhitespaceOnly(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrStartsWith(string|null $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed nullOrNotStartsWith(string|null $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed nullOrStartsWithLetter(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrEndsWith(string|null $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed nullOrNotEndsWith(string|null $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed nullOrRegex(string|null $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed nullOrNotRegex(string|null $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed nullOrUnicodeLetters(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrAlpha(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrDigits(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrAlnum(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrLower(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrUpper(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrLength(string|null $value, int $length, string $message = '', string $exception = '')
 * @method static mixed nullOrMinLength(string|null $value, int|float $min, string $message = '', string $exception = '')
 * @method static mixed nullOrMaxLength(string|null $value, int|float $max, string $message = '', string $exception = '')
 * @method static mixed nullOrLengthBetween(string|null $value, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed nullOrFileExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrFile(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrDirectory(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrReadable(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrWritable(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrClassExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrSubclassOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed nullOrInterfaceExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrImplementsInterface(mixed $value, mixed $interface, string $message = '', string $exception = '')
 * @method static mixed nullOrPropertyExists(string|object|null $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed nullOrPropertyNotExists(string|object|null $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed nullOrMethodExists(string|object|null $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed nullOrMethodNotExists(string|object|null $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed nullOrKeyExists(mixed[]|null $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed nullOrKeyNotExists(mixed[]|null $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed nullOrValidArrayKey(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrCount(\Countable|mixed[]|null $array, int $number, string $message = '', string $exception = '')
 * @method static mixed nullOrMinCount(\Countable|mixed[]|null $array, int|float $min, string $message = '', string $exception = '')
 * @method static mixed nullOrMaxCount(\Countable|mixed[]|null $array, int|float $max, string $message = '', string $exception = '')
 * @method static mixed nullOrCountBetween(\Countable|mixed[]|null $array, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed nullOrIsList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed nullOrIsNonEmptyList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed nullOrIsMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed nullOrIsStatic(\Closure $callable, string $message = '', string $exception = '')
 * @method static mixed nullOrNotStatic(\Closure $callable, string $message = '', string $exception = '')
 * @method static mixed nullOrIsNonEmptyMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed nullOrUuid(string|null $value, string $message = '', string $exception = '')
 * @method static mixed nullOrThrows(\Closure|null $expression, string $class, string $message = '', string $exception = '')
 *
 * @method static mixed allString(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allStringNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIntegerish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allPositiveInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNotNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allFloat(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNumeric(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNatural(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allBoolean(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allScalar(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allObject(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allObjectish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allResource(mixed $value, string|null $type = null, string $message = '', string $exception = '')
 * @method static mixed allIsCallable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIsArray(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIsArrayAccessible(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIsCountable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIsIterable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIsInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allNotInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allIsInstanceOfAny(mixed $value, array<object|string> $classes, string $message = '', string $exception = '')
 * @method static mixed allIsAOf(object|string|null $value, string $class, string $message = '', string $exception = '')
 * @method static mixed allIsNotA(iterable<object|string> $value, string $class, string $message = '', string $exception = '')
 * @method static mixed allIsAnyOf(iterable<object|string> $value, string[] $classes, string $message = '', string $exception = '')
 * @method static mixed allIsEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNotNull(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allTrue(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNotFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIp(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIpv4(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allIpv6(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allEmail(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allUniqueValues(iterable<mixed[]> $values, string $message = '', string $exception = '')
 * @method static mixed allEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNotEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNotSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allGreaterThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allGreaterThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allLessThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allLessThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allRange(mixed $value, mixed $min, mixed $max, string $message = '', string $exception = '')
 * @method static mixed allOneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allInArray(mixed[] $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNotOneOf(mixed[] $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNotInArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allContains(string[] $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed allNotContains(string[] $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed allNotWhitespaceOnly(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allStartsWith(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNotStartsWith(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allStartsWithLetter(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allEndsWith(string[] $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed allNotEndsWith(string[] $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed allRegex(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNotRegex(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allUnicodeLetters(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allAlpha(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allDigits(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allAlnum(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allLower(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allUpper(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allLength(string[] $value, int $length, string $message = '', string $exception = '')
 * @method static mixed allMinLength(string[] $value, int|float $min, string $message = '', string $exception = '')
 * @method static mixed allMaxLength(string[] $value, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allLengthBetween(string[] $value, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allFileExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allFile(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allDirectory(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allReadable(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allWritable(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allClassExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allSubclassOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allInterfaceExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allImplementsInterface(mixed $value, mixed $interface, string $message = '', string $exception = '')
 * @method static mixed allPropertyExists(iterable<string|object> $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed allPropertyNotExists(iterable<string|object> $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed allMethodExists(iterable<string|object> $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed allMethodNotExists(iterable<string|object> $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed allKeyExists(iterable<mixed[]> $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed allKeyNotExists(iterable<mixed[]> $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed allValidArrayKey(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allCount(iterable<\Countable|mixed[]> $array, int $number, string $message = '', string $exception = '')
 * @method static mixed allMinCount(iterable<\Countable|mixed[]> $array, int|float $min, string $message = '', string $exception = '')
 * @method static mixed allMaxCount(iterable<\Countable|mixed[]> $array, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allCountBetween(iterable<\Countable|mixed[]> $array, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allIsList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allIsNonEmptyList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allIsMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allIsStatic(\Closure[] $callable, string $message = '', string $exception = '')
 * @method static mixed allNotStatic(\Closure[] $callable, string $message = '', string $exception = '')
 * @method static mixed allIsNonEmptyMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allUuid(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allThrows(\Closure[] $expression, string $class, string $message = '', string $exception = '')
 *
 * @method static mixed allNullOrString(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrStringNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIntegerish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrPositiveInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNegativeInteger(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrFloat(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNumeric(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNatural(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrBoolean(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrScalar(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrObject(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrObjectish(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrResource(mixed $value, string|null $type = null, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsCallable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsArray(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsArrayAccessible(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsCountable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsIterable(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotInstanceOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsInstanceOfAny(mixed $value, array<object|string> $classes, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsAOf(object|string|null $value, string $class, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsNotA(iterable<object|string> $value, string $class, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsAnyOf(iterable<object|string> $value, string[] $classes, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotEmpty(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNull(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrTrue(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotFalse(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIp(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIpv4(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrIpv6(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrEmail(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrUniqueValues(iterable<mixed[]> $values, string $message = '', string $exception = '')
 * @method static mixed allNullOrEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotEq(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNullOrSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotSame(mixed $value, mixed $expect, string $message = '', string $exception = '')
 * @method static mixed allNullOrGreaterThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allNullOrGreaterThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allNullOrLessThan(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allNullOrLessThanEq(mixed $value, mixed $limit, string $message = '', string $exception = '')
 * @method static mixed allNullOrRange(mixed $value, mixed $min, mixed $max, string $message = '', string $exception = '')
 * @method static mixed allNullOrOneOf(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNullOrInArray(mixed[] $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotOneOf(mixed[] $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotInArray(mixed $value, mixed[] $values, string $message = '', string $exception = '')
 * @method static mixed allNullOrContains(string[] $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotContains(string[] $value, string $subString, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotWhitespaceOnly(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrStartsWith(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotStartsWith(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNullOrStartsWithLetter(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrEndsWith(string[] $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotEndsWith(string[] $value, string $suffix, string $message = '', string $exception = '')
 * @method static mixed allNullOrRegex(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotRegex(string[] $value, string $prefix, string $message = '', string $exception = '')
 * @method static mixed allNullOrUnicodeLetters(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrAlpha(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrDigits(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrAlnum(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrLower(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrUpper(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrLength(string[] $value, int $length, string $message = '', string $exception = '')
 * @method static mixed allNullOrMinLength(string[] $value, int|float $min, string $message = '', string $exception = '')
 * @method static mixed allNullOrMaxLength(string[] $value, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allNullOrLengthBetween(string[] $value, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allNullOrFileExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrFile(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrDirectory(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrReadable(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrWritable(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrClassExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrSubclassOf(mixed $value, string|object $class, string $message = '', string $exception = '')
 * @method static mixed allNullOrInterfaceExists(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrImplementsInterface(mixed $value, mixed $interface, string $message = '', string $exception = '')
 * @method static mixed allNullOrPropertyExists(iterable<string|object> $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed allNullOrPropertyNotExists(iterable<string|object> $classOrObject, mixed $property, string $message = '', string $exception = '')
 * @method static mixed allNullOrMethodExists(iterable<string|object> $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed allNullOrMethodNotExists(iterable<string|object> $classOrObject, mixed $method, string $message = '', string $exception = '')
 * @method static mixed allNullOrKeyExists(iterable<mixed[]> $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed allNullOrKeyNotExists(iterable<mixed[]> $array, string|int $key, string $message = '', string $exception = '')
 * @method static mixed allNullOrValidArrayKey(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrCount(iterable<\Countable|mixed[]> $array, int $number, string $message = '', string $exception = '')
 * @method static mixed allNullOrMinCount(iterable<\Countable|mixed[]> $array, int|float $min, string $message = '', string $exception = '')
 * @method static mixed allNullOrMaxCount(iterable<\Countable|mixed[]> $array, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allNullOrCountBetween(iterable<\Countable|mixed[]> $array, int|float $min, int|float $max, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsNonEmptyList(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsStatic(\Closure[] $callable, string $message = '', string $exception = '')
 * @method static mixed allNullOrNotStatic(\Closure[] $callable, string $message = '', string $exception = '')
 * @method static mixed allNullOrIsNonEmptyMap(mixed $array, string $message = '', string $exception = '')
 * @method static mixed allNullOrUuid(string[] $value, string $message = '', string $exception = '')
 * @method static mixed allNullOrThrows(\Closure[] $expression, string $class, string $message = '', string $exception = '')
 *
 * @method static mixed validBase64(mixed $value, string $message = '', string $exception = '')
 * @method static mixed validURN(mixed $value, string $message = '', string $exception = '')
 * @method static mixed validURI(mixed $value, string $message = '', string $exception = '')
 * @method static mixed validURL(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrValidBase64(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrValidURN(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrValidURI(mixed $value, string $message = '', string $exception = '')
 * @method static mixed nullOrValidURL(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allValidBase64(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allValidURN(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allValidURI(mixed $value, string $message = '', string $exception = '')
 * @method static mixed allValidURL(mixed $value, string $message = '', string $exception = '')
 *
 * @method static mixed getUri()
 */
class Assert
{
    use Base64Trait;
    use URITrait;


    /**
     * @param string $name
     * @param mixed[] $arguments
     */
    public static function __callStatic(string $name, array $arguments): void
    {
        // Handle Exception-parameter
        $exception = AssertionFailedException::class;

        $last = end($arguments);
        if (is_string($last) && class_exists($last) && is_subclass_of($last, Throwable::class)) {
            $exception = $last;
            array_pop($arguments);
        }

        try {
            // Putting Webmozart first, since the most calls will be to their native assertions
            if (method_exists(Webmozart::class, $name)) {
                $callable = [Webmozart::class, $name];
                (is_callable($callable)) && call_user_func_array($callable, $arguments);
                return;
            } elseif (method_exists(static::class, $name)) {
                $callable = [static::class, $name];
                (is_callable($callable)) && call_user_func_array($callable, $arguments);
                return;
            } elseif (preg_match('/^nullOr(.+)$/i', $name, $matches)) {
                $method = lcfirst($matches[1]);
                if (method_exists(Webmozart::class, $method)) {
                    call_user_func_array([static::class, 'nullOr'], [[Webmozart::class, $method], $arguments]);
                } elseif (method_exists(static::class, $method)) {
                    call_user_func_array([static::class, 'nullOr'], [[static::class, $method], $arguments]);
                } else {
                    throw new BadMethodCallException(sprintf("Assertion named `%s` does not exists.", $method));
                }
            } elseif (preg_match('/^all(.+)$/i', $name, $matches)) {
                $method = lcfirst($matches[1]);
                if (method_exists(Webmozart::class, $method)) {
                    call_user_func_array([static::class, 'all'], [[Webmozart::class, $method], $arguments]);
                } elseif (method_exists(static::class, $method)) {
                    call_user_func_array([static::class, 'all'], [[static::class, $method], $arguments]);
                } else {
                    throw new BadMethodCallException(sprintf("Assertion named `%s` does not exists.", $method));
                }
            } else {
                throw new BadMethodCallException(sprintf("Assertion named `%s` does not exists.", $name));
            }
        } catch (InvalidArgumentException $e) {
            throw new $exception($e->getMessage());
        }
    }


    /**
     * Handle nullOr* for either Webmozart or for our custom assertions
     *
     * @param callable $method
     * @param mixed[] $arguments
     */
    private static function nullOr(callable $method, array $arguments): void
    {
        $value = reset($arguments);
        ($value === null) || call_user_func_array($method, $arguments);
    }


    /**
     * all* for our custom assertions
     *
     * @param callable $method
     * @param mixed[] $arguments
     */
    private static function all(callable $method, array $arguments): void
    {
        $values = array_pop($arguments);
        foreach ($values as $value) {
            $tmp = $arguments;
            array_unshift($tmp, $value);
            call_user_func_array($method, $tmp);
        }
    }


    /**
     * @param mixed $value
     *
     * @return string
     */
    protected static function valueToString(mixed $value): string
    {
        if (is_resource($value)) {
            return 'resource';
        }

        if (null === $value) {
            return 'null';
        }

        if (true === $value) {
            return 'true';
        }

        if (false === $value) {
            return 'false';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return $value::class . ': ' . self::valueToString($value->__toString());
            }

            if ($value instanceof DateTime || $value instanceof DateTimeImmutable) {
                return $value::class . ': ' . self::valueToString($value->format('c'));
            }

            if ($value instanceof UnitEnum) {
                return $value::class . '::' . $value->name;
            }

            return $value::class;
        }

        if (is_string($value)) {
            return '"' . $value . '"';
        }

        return strval($value);
    }
}
