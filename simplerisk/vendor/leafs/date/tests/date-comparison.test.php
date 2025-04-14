<?php

use Leaf\Date;

test('isBefore checks if date is before another date', function () {
    $date = new Date();
    $date->tick('2023-01-01');
    
    expect($date->isBefore('2023-01-02'))->toBeTrue();
    expect($date->isBefore('2022-12-31'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isBefore(new DateTime('2023-01-02')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-02');
    expect($date->isBefore($otherDate))->toBeTrue();
});

test('isAfter checks if date is after another date', function () {
    $date = new Date();
    $date->tick('2023-01-02');
    
    expect($date->isAfter('2023-01-01'))->toBeTrue();
    expect($date->isAfter('2023-01-03'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isAfter(new DateTime('2023-01-01')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-01');
    expect($date->isAfter($otherDate))->toBeTrue();
});

test('isBetween checks if date is between two dates', function () {
    $date = new Date();
    $date->tick('2023-01-02');
    
    expect($date->isBetween('2023-01-01', '2023-01-03'))->toBeTrue();
    expect($date->isBetween('2023-01-02', '2023-01-03'))->toBeFalse(); // Equal to first date
    expect($date->isBetween('2023-01-01', '2023-01-02'))->toBeFalse(); // Equal to second date
});

test('isBetweenOrEqual checks if date is between or equal to two dates', function () {
    $date = new Date();
    $date->tick('2023-01-02');
    
    expect($date->isBetweenOrEqual('2023-01-01', '2023-01-03'))->toBeTrue();
    expect($date->isBetweenOrEqual('2023-01-02', '2023-01-03'))->toBeTrue(); // Equal to first date
    expect($date->isBetweenOrEqual('2023-01-01', '2023-01-02'))->toBeTrue(); // Equal to second date
});

test('isSame checks if date is the same as another date', function () {
    $date = new Date();
    $date->tick('2023-01-01 12:00:00');
    
    expect($date->isSame('2023-01-01 12:00:00'))->toBeTrue();
    expect($date->isSame('2023-01-01 12:00:01'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isSame(new DateTime('2023-01-01 12:00:00')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-01 12:00:00');
    expect($date->isSame($otherDate))->toBeTrue();
});

test('isSameDay checks if date is the same day as another date', function () {
    $date = new Date();
    $date->tick('2023-01-01 12:00:00');
    
    expect($date->isSameDay('2023-01-01 15:30:00'))->toBeTrue();
    expect($date->isSameDay('2023-01-02 12:00:00'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isSameDay(new DateTime('2023-01-01 15:30:00')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-01 15:30:00');
    expect($date->isSameDay($otherDate))->toBeTrue();
});

test('isSameMonth checks if date is the same month as another date', function () {
    $date = new Date();
    $date->tick('2023-01-15');
    
    expect($date->isSameMonth('2023-01-01'))->toBeTrue();
    expect($date->isSameMonth('2023-02-15'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isSameMonth(new DateTime('2023-01-01')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-01');
    expect($date->isSameMonth($otherDate))->toBeTrue();
});

test('isSameYear checks if date is the same year as another date', function () {
    $date = new Date();
    $date->tick('2023-06-15');
    
    expect($date->isSameYear('2023-01-01'))->toBeTrue();
    expect($date->isSameYear('2022-06-15'))->toBeFalse();
    
    // Test with DateTime
    expect($date->isSameYear(new DateTime('2023-01-01')))->toBeTrue();
    
    // Test with Date
    $otherDate = new Date();
    $otherDate->tick('2023-01-01');
    expect($date->isSameYear($otherDate))->toBeTrue();
});

test('isLeapYear checks if year is a leap year', function () {
    $date = new Date();
    
    $date->tick('2020-01-01'); // Leap year
    expect($date->isLeapYear())->toBeTrue();
    
    $date->tick('2023-01-01'); // Not a leap year
    expect($date->isLeapYear())->toBeFalse();
});

test('isDateTime checks if value is a DateTime object', function () {
    $date = new Date();
    
    expect($date->isDateTime(new DateTime()))->toBeTrue();
    expect($date->isDateTime('2023-01-01'))->toBeFalse();
    expect($date->isDateTime($date))->toBeFalse();
});
