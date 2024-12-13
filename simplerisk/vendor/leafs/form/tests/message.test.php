<?php

declare(strict_types=1);


test('message can be set for a rule', function () {
    validator()->message('number', 'This is a custom message');

    $itemToValidate = ['data4' => 'wrong'];

    expect(validator()->validate($itemToValidate, ['data4' => 'number']))->toBe(false);
    expect(validator()->errors())->toHaveKey('data4');
    expect(validator()->errors()['data4'])->toContain('This is a custom message');
});

test('message can be set for multiple rules', function () {
    validator()->message([
        'number' => 'This is a custom message',
        'email' => 'This is another custom message',
    ]);

    $itemToValidate = ['data5' => 'wrong'];

    expect(validator()->validate($itemToValidate, ['data5' => 'expanded|number|email']))->toBe(false);
    expect(validator()->errors())->toHaveKey('data5');
    expect(validator()->errors()['data5'])->toContain('This is a custom message');
    expect(validator()->errors()['data5'])->toContain('This is another custom message');
});

test('message can be set for a rule with a custom placeholder', function () {
    validator()->message('number', 'This is a custom message for {field}');

    $itemToValidate = ['data6' => 'wrong'];

    expect(validator()->validate($itemToValidate, ['data6' => 'number|expanded']))->toBe(false);
    expect(validator()->errors())->toHaveKey('data6');
    expect(validator()->errors()['data6'][0] ?? '')->toBe('This is a custom message for data6');
});

test('message can be set for a rule with a custom capitalized placeholder', function () {
    validator()->message('number', 'This is a custom message for {Field}');

    $itemToValidate = ['data2' => 'wrong'];

    expect(validator()->validate($itemToValidate, ['data2' => 'number']))->toBe(false);
    expect(validator()->errors())->toHaveKey('data2');
    expect(validator()->errors()['data2'] ?? '')->toBe('This is a custom message for Data2');
});

test('message can be set for a rule with a custom placeholder and custom value', function () {
    validator()->message('number', 'This is a custom message for {field} with value {value}');

    $itemToValidate = ['data3' => 'wrong'];

    expect(validator()->validate($itemToValidate, ['data3' => 'number']))->toBe(false);
    expect(validator()->errors())->toHaveKey('data3');
    expect(validator()->errors()['data3'] ?? '')->toBe('This is a custom message for data3 with value wrong');
});

test('show error if no message is provided', function () {
    validator()->message('number');
})->throws(Exception::class, 'Message cannot be empty');
