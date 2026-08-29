<?php

declare(strict_types=1);

test('array<> can be used to validate arrays', function () {
    $itemsToValidate = ['specialItem2' => 'wrong', 'specialItem3' => ['item here']];

    $success = validator()->validate($itemsToValidate, ['specialItem2' => 'array']);

    expect($success)->toBe(false);
    expect(validator()->errors())->toHaveKey('specialItem2');
    expect(validator()->errors())->not()->toHaveKey('specialItem3');
});

test('array<> can be used to validate array content', function () {
    $itemsToValidate = ['specialItem3' => ['wrong'], 'specialItem4' => ['mail@example.com']];

    validator()->validate($itemsToValidate, [
        'specialItem3' => 'array<email>',
        'specialItem4' => 'array<email>',
    ]);

    expect(validator()->errors())->toHaveKey('specialItem3');
    expect(validator()->errors())->not()->toHaveKey('specialItem4');
});

test('array<> can be used to check if an associative array is an array', function () {
    $itemsToValidate = ['specialItem5' => ['key' => 'value']];

    validator()->validate($itemsToValidate, ['specialItem5' => 'array<>']);

    expect(validator()->errors())->not()->toHaveKey('specialItem5');
});

test('associative arrays can be validated using dot notation', function () {
    $itemsToValidate = [
        'specialItem6' => [
            'key' => 'value',
            'key2' => 'value2',
        ],
    ];

    validator()->validate($itemsToValidate, ['specialItem6.key' => 'string']);
    validator()->validate($itemsToValidate, ['specialItem6.key2' => 'email']);

    expect(validator()->errors())->toHaveKey('specialItem6.key2');
    expect(validator()->errors())->not()->toHaveKey('specialItem6');
});

test('array can validate associative array', function () {
    $array = ['name' => ['first' => 'Max']];
    $validator = [
        'name' => 'array',
        'name.first' => 'string',
    ];

    $data = validator()->validate($array, $validator);

    if (!$data) {
        $this->fail(json_encode(validator()->errors()));
    }

    expect($data)->toBeArray();
});
