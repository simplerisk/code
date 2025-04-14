<?php

use Leaf\Date;

test('Date class can be instantiated', function () {
    $date = new Date();
    expect($date)->toBeInstanceOf(Date::class);
});

test('now returns current date as string', function () {
    $date = new Date();
    expect($date->now())->toBeString();
});

test('tick initializes with current date by default', function () {
    $date = tick();
    expect($date)->toBeInstanceOf(Date::class);
});

test('tick accepts DateTime object', function () {
    $dateTime = new DateTime('2023-01-01');
    $date = tick($dateTime);
    expect($date->toDateString())->toBe('2023-01-01');
});

test('tick accepts Date object', function () {
    $date1 = tick('2023-01-01');
    
    $date2 = tick($date1);
    
    expect($date2->toDateString())->toBe('2023-01-01');
});

test('tick accepts string date', function () {
    $date = tick('2023-01-01');
    expect($date->toDateString())->toBe('2023-01-01');
});

test('tick accepts date with slashes', function () {
    $date = tick('2023/01/01');
    expect($date->toDateString())->toBe('2023-01-01');
});

test('tick accepts timezone', function () {
    $date = tick('2023-01-01', 'America/New_York');
    expect($date->toDateTime()->getTimezone()->getName())->toBe('America/New_York');
});

test('setTimezone changes timezone', function () {
    $date = new Date();
    $date->setTimezone('America/New_York');
    expect($date->toDateTime()->getTimezone()->getName())->toBe('America/New_York');
});

test('setTimezone throws exception for invalid timezone', function () {
    $date = new Date();
    expect(fn() => $date->setTimezone('Invalid/Timezone'))->toThrow(\Exception::class);
});
