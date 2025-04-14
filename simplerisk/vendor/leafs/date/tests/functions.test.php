<?php

test('global tick function returns a Date instance', function () {
    expect(tick())->toBeInstanceOf(\Leaf\Date::class);
});

test('global tick function accepts date string', function () {
    $date = tick('2023-01-01');
    expect($date->toDateString())->toBe('2023-01-01');
});

test('global tick function accepts DateTime object', function () {
    $dateTime = new DateTime('2023-01-01');
    $date = tick($dateTime);
    expect($date->toDateString())->toBe('2023-01-01');
});

test('global tick function accepts timezone', function () {
    $date = tick('2023-01-01', 'America/New_York');
    expect($date->toDateTime()->getTimezone()->getName())->toBe('America/New_York');
});
