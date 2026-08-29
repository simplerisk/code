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

test('diff returns signed whole units in dayjs style', function () {
    // the booking case: nights between check-in and check-out
    expect(tick('2026-09-14')->diff('2026-09-10', 'days'))->toBe(4)
        ->and(tick('2026-09-10')->diff('2026-09-14', 'days'))->toBe(-4)
        ->and(tick('2026-09-14')->diff('2026-09-14', 'days'))->toBe(0);

    expect(tick('2026-09-14 12:00:00')->diff('2026-09-14 09:30:00', 'hours'))->toBe(2)
        ->and(tick('2026-09-14 12:00:00')->diff('2026-09-14 11:58:30', 'minutes'))->toBe(1)
        ->and(tick('2026-09-14 12:00:05')->diff('2026-09-14 12:00:00'))->toBe(5);

    expect(tick('2028-03-01')->diff('2026-01-15', 'months'))->toBe(25)
        ->and(tick('2028-03-01')->diff('2026-01-15', 'years'))->toBe(2);
});

test('diff in days is calendar-aware across DST boundaries', function () {
    $previousTz = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    try {
        // spring forward 2026-03-08: the interval is 95 wall-clock hours,
        // but a stay is 4 nights, not 3.958 — timestamp math gets this wrong
        expect(tick('2026-03-11')->diff('2026-03-07', 'days'))->toBe(4);
    } finally {
        date_default_timezone_set($previousTz);
    }
});

test('diff accepts Date and DateTime instances', function () {
    expect(tick('2026-09-14')->diff(tick('2026-09-10'), 'days'))->toBe(4)
        ->and(tick('2026-09-14')->diff(new DateTime('2026-09-10'), 'days'))->toBe(4);
});
