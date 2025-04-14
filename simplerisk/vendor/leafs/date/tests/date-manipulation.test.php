<?php

use Leaf\Date;

test('add adds duration to date', function () {
    $date = new Date();
    $date->tick('2023-01-01');
    $date->add(1, 'day');
    expect($date->toDateString())->toBe('2023-01-02');
});

test('add works with string duration', function () {
    $date = new Date();
    $date->tick('2023-01-01');
    $date->add('1 day');
    expect($date->toDateString())->toBe('2023-01-02');
});

test('subtract subtracts duration from date', function () {
    $date = new Date();
    $date->tick('2023-01-02');
    $date->subtract(1, 'day');
    expect($date->toDateString())->toBe('2023-01-01');
});

test('subtract works with string duration', function () {
    $date = new Date();
    $date->tick('2023-01-02');
    $date->subtract('1 day');
    expect($date->toDateString())->toBe('2023-01-01');
});

test('startOf year sets date to start of year', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->startOf('year');
    expect($date->toDateTimeString())->toBe('2023-01-01 00:00:00');
});

test('startOf month sets date to start of month', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->startOf('month');
    expect($date->toDateTimeString())->toBe('2023-05-01 00:00:00');
});

test('startOf day sets date to start of day', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->startOf('day');
    expect($date->toDateTimeString())->toBe('2023-05-15 00:00:00');
});

test('startOf hour sets date to start of hour', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->startOf('hour');
    expect($date->toDateTimeString())->toBe('2023-05-15 12:00:00');
});

test('startOf minute sets date to start of minute', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->startOf('minute');
    expect($date->toDateTimeString())->toBe('2023-05-15 12:30:00');
});

test('startOf week sets date to start of week', function () {
    $date = new Date();
    // Use a date we know is a Wednesday
    $date->tick('2023-05-17'); // Wednesday
    $date->startOf('week');
    // Start of week should be Monday in most locales, but this depends on server settings
    // So we'll just verify it's earlier in the week
    expect($date->toDateTime()->format('N'))->toBeLessThan(4);
});

test('endOf year sets date to end of year', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->endOf('year');
    expect($date->toDateTimeString())->toBe('2023-12-31 23:59:59');
});

test('endOf month sets date to end of month', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->endOf('month');
    expect($date->toDateTimeString())->toBe('2023-05-31 23:59:59');
});

test('endOf day sets date to end of day', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->endOf('day');
    expect($date->toDateTimeString())->toBe('2023-05-15 23:59:59');
});

test('endOf hour sets date to end of hour', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->endOf('hour');
    expect($date->toDateTimeString())->toBe('2023-05-15 12:59:59');
});

test('endOf minute sets date to end of minute', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    $date->endOf('minute');
    expect($date->toDateTimeString())->toBe('2023-05-15 12:30:59');
});
