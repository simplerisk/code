<?php

use Leaf\Date;

test('tick returns the date instance', function () {
    expect((new Date())->tick())->toBeInstanceOf(Date::class);
    expect(tick())->toBeInstanceOf(Date::class);
});

test('tick parses dates and the global helper accepts one directly', function () {
    expect(tick('2026-01-15')->format('YYYY-MM-DD'))->toBe('2026-01-15');
    expect(tick('2026/01/15')->format('YYYY-MM-DD'))->toBe('2026-01-15');
});

test('format speaks dayjs tokens', function () {
    $date = tick('2026-01-15 14:05:09');

    expect($date->format('YYYY-MM-DD HH:mm:ss'))->toBe('2026-01-15 14:05:09');
    expect($date->format('MMM D, YYYY'))->toBe('Jan 15, 2026');
    expect($date->format('dddd'))->toBe('Thursday');
    expect($date->format('hh:mm a'))->toBe('02:05 pm');
});

test('bracketed text in format strings is output literally', function () {
    expect(tick('2026-01-15')->format('[on] YYYY-MM-DD'))->toBe('on 2026-01-15');
});

test('add and subtract move the date', function () {
    expect(tick('2026-01-15')->add(10, 'days')->format('YYYY-MM-DD'))->toBe('2026-01-25');
    expect(tick('2026-01-15')->subtract(1, 'month')->format('YYYY-MM-DD'))->toBe('2025-12-15');
    expect(tick('2026-01-31')->add(1, 'month')->format('YYYY-MM-DD'))->toBe('2026-03-03');
});

test('startOf and endOf snap to unit boundaries', function () {
    expect(tick('2026-01-15 14:05:09')->startOf('day')->format('HH:mm:ss'))->toBe('00:00:00');
    expect(tick('2026-01-15')->startOf('month')->format('YYYY-MM-DD'))->toBe('2026-01-01');
    expect(tick('2026-01-15')->endOf('month')->format('YYYY-MM-DD'))->toBe('2026-01-31');
    expect(tick('2026-02-10')->endOf('month')->format('YYYY-MM-DD'))->toBe('2026-02-28');
});

test('set and get read and write single units', function () {
    $date = tick('2026-01-15');

    expect($date->set('year', 2030)->get('year'))->toBe('2030');
    expect($date->set('month', 6)->format('MM'))->toBe('06');
});

test('unit shorthands read and write', function () {
    $date = tick('2026-01-15 14:05:09');

    expect((int) $date->hour())->toBe(14);
    expect($date->minute(30)->format('HH:mm'))->toBe('14:30');
    expect($date->year(2027)->format('YYYY'))->toBe('2027');
});

test('comparisons behave', function () {
    expect(tick('2026-01-15')->isBefore('2026-02-01'))->toBeTrue();
    expect(tick('2026-01-15')->isAfter('2026-01-01'))->toBeTrue();
    expect(tick('2026-01-15')->isBetween('2026-01-01', '2026-02-01'))->toBeTrue();
    expect(tick('2026-01-15')->isSameDay('2026-01-15 23:00:00'))->toBeTrue();
    expect(tick('2026-01-15')->isSameMonth('2026-01-31'))->toBeTrue();
    expect(tick('2026-01-15')->isSameYear('2026-12-31'))->toBeTrue();
    expect(tick('2024-01-01')->isLeapYear())->toBeTrue();
    expect(tick('2026-01-01')->isLeapYear())->toBeFalse();
});

test('relative time from a reference date', function () {
    expect(tick('2026-01-15')->from('2026-01-18', true))->toBe('3 days');
    expect(tick()->subtract(2, 'hours')->fromNow())->toContain('2 hours')->toContain('ago');
    expect(tick()->add(3, 'days')->add(1, 'minute')->fromNow())->toContain('3 days')->toContain('from now');
});

test('conversions to native types', function () {
    $date = tick('2026-01-15 14:05:09');

    expect($date->toDateTime())->toBeInstanceOf(DateTime::class);
    expect($date->toDateTimeString())->toBe('2026-01-15 14:05:09');
    expect($date->toDateString())->toBe('2026-01-15');
    expect($date->toTimeString())->toBe('14:05:09');
    expect($date->toIsoString())->toContain('2026-01-15T14:05:09');
});

test('timezones shift the wall clock', function () {
    $utc = tick('2026-01-15 12:00:00', 'UTC')->format('HH');
    $tokyo = tick('2026-01-15 12:00:00', 'UTC')->setTimezone('Asia/Tokyo')->format('HH');

    expect($utc)->toBe('12');
    expect($tokyo)->toBe('21');
});

test('tz converts and reports the timezone', function () {
    $date = tick('2026-01-15 12:00:00', 'UTC');

    expect($date->tz())->toBe('UTC');
    expect($date->tz('Asia/Tokyo'))->toBeInstanceOf(Leaf\Date::class);
    expect($date->tz())->toBe('Asia/Tokyo');
    expect($date->format('HH'))->toBe('21');
});

test('utc converts back to UTC', function () {
    $date = tick('2026-01-15 12:00:00', 'UTC')->tz('Asia/Tokyo');

    expect($date->format('HH'))->toBe('21');
    expect($date->utc()->tz())->toBe('UTC');
    expect($date->format('HH'))->toBe('12');
});

test('utcOffset reports minutes from UTC', function () {
    expect(tick('2026-01-15 12:00:00', 'UTC')->utcOffset())->toBe(0);
    expect(tick('2026-01-15 12:00:00', 'UTC')->tz('Asia/Tokyo')->utcOffset())->toBe(540);
    expect(tick('2026-06-15 12:00:00', 'UTC')->tz('America/New_York')->utcOffset())->toBe(-240);
});

test('an invalid timezone throws', function () {
    expect(fn () => tick()->tz('Neverland/Nowhere'))->toThrow(Exception::class);
});

test('a timezone at construction parses the wall clock in that zone (dayjs style)', function () {
    // noon as experienced in Tokyo is 3am UTC
    $meeting = tick('2026-01-15 12:00:00', 'Asia/Tokyo');

    expect($meeting->tz())->toBe('Asia/Tokyo');
    expect($meeting->format('HH:mm'))->toBe('12:00');
    expect($meeting->utc()->format('HH:mm'))->toBe('03:00');
});

test('construction parse-in and tz() conversion compose like dayjs', function () {
    // store a user-entered time, then render it for another user
    $utc = tick('2026-01-15 12:00:00', 'Asia/Tokyo')->utc()->format('YYYY-MM-DD HH:mm:ss');

    expect(tick($utc, 'UTC')->tz('America/New_York')->format('HH:mm'))->toBe('22:00');
});
