<?php

declare(strict_types=1);

use Leaf\Form;

test('getDotNotatedValue retrieves simple field', function () {
    $array = ['name' => 'Max'];
    expect(Form::getDotNotatedValue($array, 'name'))->toBe('Max');
});

test('getDotNotatedValue returns null for non-existent field', function () {
    $array = ['name' => 'Max'];
    expect(Form::getDotNotatedValue($array, 'age'))->toBeNull();
});

test('getDotNotatedValue retrieves nested field', function () {
    $nestedArray = [
        'customer' => [
            'phone' => '123456789',
            'name' => 'Anna',
        ],
        'order' => [
            'id' => 1,
        ],
    ];

    expect(Form::getDotNotatedValue($nestedArray, 'customer.phone'))->toBe('123456789');
    expect(Form::getDotNotatedValue($nestedArray, 'customer.name'))->toBe('Anna');
    expect(Form::getDotNotatedValue($nestedArray, 'order.id'))->toBe(1);
});

test('getDotNotatedValue retrieves deeply nested field', function () {
    $deepNestedArray = [
        'customer' => [
            'details' => [
                'address' => [
                    'city' => 'Berlin',
                ],
            ],
        ],
    ];

    expect(Form::getDotNotatedValue($deepNestedArray, 'customer.details.address.city'))->toBe('Berlin');
});

test('getDotNotatedValue returns null for non-existent nested field', function () {
    $deepNestedArray = [
        'customer' => [
            'details' => [
                'address' => [
                    'city' => 'Berlin',
                ],
            ],
        ],
    ];

    expect(Form::getDotNotatedValue($deepNestedArray, 'customer.details.phone'))->toBeNull();
});
