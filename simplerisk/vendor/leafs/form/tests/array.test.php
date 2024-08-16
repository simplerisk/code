<?php

declare(strict_types=1);

use Leaf\Form;

test('special validation rules don\'t throw errors when used', function () {
    $itemsToValidate = ['specialItem' => ['wrong', 'wrong2', 'right@example.com']];

    Form::validate($itemsToValidate, ['specialItem' => 'array(email)']);

    expect(Form::errors())->toHaveKey('specialItem');
});

test('array() can be used to validate arrays', function () {
    $itemsToValidate = ['specialItem2' => 'wrong', 'specialItem3' => ['item here']];

    Form::validate($itemsToValidate, ['specialItem2' => 'array()']);

    expect(Form::errors())->toHaveKey('specialItem2');
    expect(Form::errors())->not()->toHaveKey('specialItem3');
});

test('array() can be used to validate array content', function () {
    $itemsToValidate = ['specialItem3' => ['wrong'], 'specialItem4' => ['mail@example.com']];

    Form::validate($itemsToValidate, [
        'specialItem3' => 'array(email)',
        'specialItem4' => 'array(email)',
    ]);

    expect(Form::errors())->toHaveKey('specialItem3');
    expect(Form::errors())->not()->toHaveKey('specialItem4');
});

test('array() can be used to check if an associative array is an array', function () {
    $itemsToValidate = ['specialItem5' => ['key' => 'value']];

    Form::validate($itemsToValidate, ['specialItem5' => 'array()']);

    expect(Form::errors())->not()->toHaveKey('specialItem5');
});

test('associative arrays can be validated using dot notation', function () {
    $itemsToValidate = [
        'specialItem6' => [
            'key' => 'value',
            'key2' => 'value2',
        ],
    ];

    Form::validate($itemsToValidate, ['specialItem6.key' => 'required']);
    Form::validate($itemsToValidate, ['specialItem6.key2' => 'email']);

    expect(Form::errors())->not()->toHaveKey('specialItem6');
    expect(Form::errors())->toHaveKey('specialItem6.key2');
});
