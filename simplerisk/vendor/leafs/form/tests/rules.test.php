<?php

declare(strict_types=1);

use Leaf\Form;

$defaultRules = [
    'required',
    'email',
    'alpha',
    'text',
    'textonly',
    'alphanum',
    'alphadash',
    'username',
    'number',
    'float',
    'date',
    'min',
    'max',
    'between',
    'match',
    'contains',
    'boolean',
    'in',
    'ip',
    'ipv4',
    'ipv6',
    'url',
    'domain',
    'creditcard',
    'phone',
    'uuid',
    'slug',
    'json',
    'regex',
];

test('has some known default validation rules', function () use ($defaultRules) {
    expect(Form::supportedRules())->toBe($defaultRules);
});

test('rules are case-insensitive', function () {
    $value = 'leafphp';
    expect(Form::test('alpha', $value))->toBe(true);
    expect(Form::test('ALPHA', $value))->toBe(true);
    expect(Form::test('Alpha', $value))->toBe(true);
});

test('throws error for unsupported rules', function () {
    $value = '';
    expect(Form::test('rule-does-not-exist', $value))->toBe(false);
})->throws(Exception::class, 'Rule rule-does-not-exist does not exist');

test('can add custom validation rules', function () {
    Form::addRule('customRule10110', '/^custom$/');
    expect(Form::supportedRules())->toContain('customrule10110');
});

test('can add custom validation rules with closure', function () {
    Form::addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    });

    expect(Form::test('anotherCustomRule', 'custom'))->toBe(true);
    expect(Form::test('anotherCustomRule', 'not custom'))->toBe(false);
});

test('can add custom validation rules with closure and custom message', function () {
    Form::addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message');

    $dataToValidate1 = ['item1' => 'not custom'];

    expect(Form::validate($dataToValidate1, ['item1' => 'anotherCustomRule']))->toBe(false);

    expect(Form::errors())->toHaveKey('item1');
    expect(Form::errors()['item1'][0] ?? '')->toBe('This is a custom message');
});

test('can add custom validation rules with closure and custom message with placeholder', function () {
    Form::addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message for {field}');

    $dataToValidate1 = ['item2' => 'not custom'];

    expect(Form::validate($dataToValidate1, ['item2' => 'anotherCustomRule']))->toBe(false);

    expect(Form::errors())->toHaveKey('item2');
    expect(Form::errors()['item2'][0] ?? '')->toBe('This is a custom message for item2');
});

test('can add custom validation rules with closure and custom message with placeholder and custom value', function () {
    Form::addRule('anotherCustomRule', function ($value) {
        return $value === 'custom';
    }, 'This is a custom message for {field} with value {value}');

    $dataToValidate1 = ['item3' => 'not custom'];

    expect(Form::validate($dataToValidate1, ['item3' => 'anotherCustomRule']))->toBe(false);

    expect(Form::errors())->toHaveKey('item3');
    expect(Form::errors()['item3'][0] ?? '')->toBe('This is a custom message for item3 with value not custom');
});

test('can add custom validation rules with regex', function () {
    Form::addRule('mustHaveTheWordAvailable', '/available/');
    expect(Form::test('mustHaveTheWordAvailable', 'available'))->toBe(true);
    expect(Form::test('mustHaveTheWordAvailable', 'not in here'))->toBe(false);
});

test('can add custom validation rules with regex and custom message', function () {
    Form::addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message');
    $dataToValidate1 = ['item4' => 'not in here'];

    expect(Form::validate($dataToValidate1, ['item4' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(Form::errors())->toHaveKey('item4');
    expect(Form::errors()['item4'][0] ?? '')->toBe('This is a custom message');
});

test('can add custom validation rules with regex and custom message with placeholder', function () {
    Form::addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message for {field}');
    $dataToValidate1 = ['item5' => 'not in here'];

    expect(Form::validate($dataToValidate1, ['item5' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(Form::errors())->toHaveKey('item5');
    expect(Form::errors()['item5'][0] ?? '')->toBe('This is a custom message for item5');
});

test('can add custom validation rules with regex and custom message with placeholder and custom value', function () {
    Form::addRule('mustHaveTheWordAvailable', '/available/', 'This is a custom message for {field} with value {value}');
    $dataToValidate1 = ['item6' => 'not in here'];

    expect(Form::validate($dataToValidate1, ['item6' => 'mustHaveTheWordAvailable']))->toBe(false);

    expect(Form::errors())->toHaveKey('item6');
    expect(Form::errors()['item6'][0] ?? '')->toBe('This is a custom message for item6 with value not in here');
});
