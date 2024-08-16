<?php

declare(strict_types=1);

use Leaf\Form;

test('message can be set for a rule', function () {
    Form::message('number', 'This is a custom message');

    $itemToValidate = ['data4' => 'wrong'];

    expect(Form::validate($itemToValidate, ['data4' => 'number']))->toBe(false);
    expect(Form::errors())->toHaveKey('data4');
    expect(Form::errors()['data4'])->toContain('This is a custom message');
});

test('message can be set for multiple rules', function () {
    Form::message([
        'number' => 'This is a custom message',
        'email' => 'This is another custom message',
    ]);

    $itemToValidate = ['data5' => 'wrong'];

    expect(Form::validate($itemToValidate, ['data5' => 'number|email']))->toBe(false);
    expect(Form::errors())->toHaveKey('data5');
    expect(Form::errors()['data5'])->toContain('This is a custom message');
    expect(Form::errors()['data5'])->toContain('This is another custom message');
});

test('message can be set for a rule with a custom placeholder', function () {
    Form::message('number', 'This is a custom message for {field}');

    $itemToValidate = ['data6' => 'wrong'];

    expect(Form::validate($itemToValidate, ['data6' => 'number']))->toBe(false);
    expect(Form::errors())->toHaveKey('data6');
    expect(Form::errors()['data6'][0] ?? '')->toBe('This is a custom message for data6');
});

test('message can be set for a rule with a custom capitalized placeholder', function () {
    Form::message('number', 'This is a custom message for {Field}');

    $itemToValidate = ['data2' => 'wrong'];

    expect(Form::validate($itemToValidate, ['data2' => 'number']))->toBe(false);
    expect(Form::errors())->toHaveKey('data2');
    expect(Form::errors()['data2'][0] ?? '')->toBe('This is a custom message for Data2');
});

test('message can be set for a rule with a custom placeholder and custom value', function () {
    Form::message('number', 'This is a custom message for {field} with value {value}');

    $itemToValidate = ['data3' => 'wrong'];

    expect(Form::validate($itemToValidate, ['data3' => 'number']))->toBe(false);
    expect(Form::errors())->toHaveKey('data3');
    expect(Form::errors()['data3'][0] ?? '')->toBe('This is a custom message for data3 with value wrong');
});

test('show error if no message is provided', function () {
    Form::message('number');
})->throws(Exception::class, 'Message cannot be empty');
