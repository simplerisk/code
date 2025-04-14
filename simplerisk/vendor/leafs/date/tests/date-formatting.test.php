<?php

use Leaf\Date;

test('format returns formatted date string', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    
    expect($date->format('YYYY-MM-DD'))->toBe('2023-05-15');
    expect($date->format('HH:mm:ss'))->toBe('12:30:45');
    expect($date->format('YYYY-MM-DD HH:mm:ss'))->toBe('2023-05-15 12:30:45');
    expect($date->format('MMM D, YYYY'))->toBe('May 15, 2023');
});

test('toDateTimeString returns formatted datetime string', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    
    expect($date->toDateTimeString())->toBe('2023-05-15 12:30:45');
});

test('toDateString returns formatted date string', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    
    expect($date->toDateString())->toBe('2023-05-15');
});

test('toTimeString returns formatted time string', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    
    expect($date->toTimeString())->toBe('12:30:45');
});

test('toIsoString returns ISO formatted string', function () {
    $date = new Date();
    $date->tick('2023-05-15 12:30:45');
    
    // The timezone offset will depend on the server's timezone, so we'll just check the format
    expect($date->toIsoString())->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{4}$/');
});

test('from returns relative time string', function () {
    $date = new Date();
    $date->tick('2023-01-01');
    
    $fromDate = new Date();
    $fromDate->tick('2022-01-01');
    
    expect($date->from('2022-01-01'))->toContain('year');
    expect($date->from('2022-01-01', true))->not->toContain('ago');
});

test('fromNow returns relative time from now', function () {
    $date = new Date();
    $date->tick('-1 day');
    
    expect($date->fromNow())->toContain('ago');
});

test('toNow is an alias for fromNow', function () {
    $date = new Date();
    $date->tick('-1 day');
    
    expect($date->toNow())->toBe($date->fromNow());
});

test('toDateTime returns DateTime object', function () {
    $date = new Date();
    expect($date->toDateTime())->toBeInstanceOf(DateTime::class);
});

test('toTimestamp returns Unix timestamp', function () {
    $date = new Date();
    $date->tick('2023-01-01 00:00:00');
    
    expect($date->toTimestamp())->toBe(strtotime('2023-01-01 00:00:00'));
});
