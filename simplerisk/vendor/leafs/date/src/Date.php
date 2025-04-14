<?php

declare(strict_types=1);

namespace Leaf;

use DateTime;

/**
 * Leaf Date
 * ----------------------
 * Quick date/time manipulation with Leaf
 *
 * @author Michael Darko
 * @since 1.1.0
 */
class Date
{
    /**PHP datetime instance */
    protected DateTime $date;

    public function __construct()
    {
        $this->tick();
        $this->format();
    }

    /**
     * Get the current date
     */
    public function now(): string
    {
        return $this->format();
    }

    /**
     * Initialize a new date instance
     * @param string|DateTime|Date $userDate The date to initialize with
     * @param string|null $userTimeZone The timezone to initialize with
     * @return Date
     */
    public function tick($userDate = 'now', ?string $userTimeZone = null): Date
    {
        if ($userDate instanceof DateTime) {
            $this->date = $userDate;
        } elseif ($userDate instanceof Date) {
            $this->date = $userDate->toDateTime();
        } else {
            $this->date = new DateTime(str_replace('/', '-', $userDate));
        }

        if ($userTimeZone) {
            $this->setTimezone($userTimeZone);
        }

        return $this;
    }

    /**
     * Set default date timezone
     * @param string $timezone One of the supported timezone names , an offset value (+0200), or a timezone abbreviation (BST)
     * @throws \Exception Invalid timezone selection
     * @return Date
     */
    public function setTimezone(string $timezone = 'Africa/Accra'): Date
    {
        if (!$timezone = new \DateTimeZone($timezone)) {
            throw new \Exception('Invalid timezone selection');
        }

        $this->date->setTimezone($timezone);

        return $this;
    }

    /**
     * Add a duration to the current date
     * @param string|int The duration to add
     * @param string|null The interval to add
     * @return Date
     */
    public function add($duration, ?string $interval = null): Date
    {
        $this->date->modify($interval ? "$duration $interval" : $duration);

        return $this;
    }

    /**
     * Subtract a duration to the current date
     * @param string|int The duration to subtract
     * @param string|null The interval to subtract
     * @return Date
     */
    public function subtract($duration, ?string $interval = null): Date
    {
        return $this->add($interval ? "-$duration $interval" : "-$duration");
    }

    /**
     * Get the start of a time unit
     * @param string $unit The time unit to start
     * @return Date
     * @throws \Exception Invalid time unit
     */
    public function startOf(string $unit): Date
    {
        $units = [
            'year' => 'Y-01-01 00:00:00',
            'month' => 'Y-m-01 00:00:00',
            'week' => 'Y-m-d 00:00:00',
            'day' => 'Y-m-d 00:00:00',
            'hour' => 'Y-m-d H:00:00',
            'minute' => 'Y-m-d H:i:00',
            'second' => 'Y-m-d H:i:s',
        ];

        $this->date->modify(date(
            $units[$unit],
            $unit === 'week' ?
            strtotime('this week', $this->date->getTimestamp()) :
            $this->date->getTimestamp()
        ));

        return $this;
    }

    /**
     * Get the end of a time unit
     * @param string $unit The time unit to end
     * @return Date
     * @throws \Exception Invalid time unit
     */
    public function endOf(string $unit): Date
    {
        $units = [
            'year' => 'Y-12-31 23:59:59',
            'month' => 'Y-m-t 23:59:59',
            'week' => 'Y-m-d 23:59:59',
            'day' => 'Y-m-d 23:59:59',
            'hour' => 'Y-m-d H:59:59',
            'minute' => 'Y-m-d H:i:59',
            'second' => 'Y-m-d H:i:s',
        ];

        $this->date->modify(date(
            $units[$unit],
            $unit === 'week' ?
            date_add(date_create(date('Y-m-d', strtotime('this week', $this->date->getTimestamp()))), date_interval_create_from_date_string('6 days'))->getTimestamp() :
            $this->date->getTimestamp()
        ));

        return $this;
    }

    /**
     * Generic setter, accepting unit as first argument, and value as second, returns a new instance with the applied changes.
     * @param string $unit The time unit to set
     * @param int $value The value to set
     * @return Date
     * @throws \Exception Invalid time unit
     */
    public function set(string $unit, int $value): Date
    {
        if ($unit === 'date' || $unit === 'month' || $unit === 'year') {
            $this->date->setDate(
                $unit === 'year' ? (int) $value : (int) $this->date->format('Y'),
                $unit === 'month' ? (int) $value : (int) $this->date->format('m'),
                $unit === 'day' ? (int) $value : (int) $this->date->format('d')
            );
        }

        if ($unit === 'hour' || $unit === 'minute' || $unit === 'second') {
            $this->date->setTime(
                $unit === 'hour' ? (int) $value : (int) $this->date->format('H'),
                $unit === 'minute' ? (int) $value : (int) $this->date->format('i'),
                $unit === 'second' ? (int) $value : (int) $this->date->format('s')
            );
        }

        return $this;
    }

    /**
     * String getter, returns the corresponding information getting from the date instance.
     */
    public function get(string $unit): string
    {
        $englishUnits = [
            'year' => 'Y',
            'month' => 'M',
            'day' => 'D',
            'hour' => 'H',
            'minute' => 'm',
            'second' => 's',
            'millisecond' => 'u',
        ];

        return $this->format($englishUnits[$unit] ?? $unit);
    }

    /**
     * Gets or sets the millisecond
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function millisecond(?int $value = null)
    {
        return $value ? $this->set('millisecond', $value) : (int) $this->get('millisecond');
    }

    /**
     * Gets or sets the second
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function second(?int $value = null)
    {
        return $value ? $this->set('second', $value) : (int) $this->get('second');
    }

    /**
     * Gets or sets the minute
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function minute(?int $value = null)
    {
        return $value ? $this->set('minute', $value) : (int) $this->get('minute');
    }

    /**
     * Gets or sets the hour
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function hour(?int $value = null)
    {
        return $value ? $this->set('hour', $value) : (int) $this->get('hour');
    }

    /**
     * Gets or sets the day
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function day(?int $value = null)
    {
        return $value ? $this->set('day', $value) : (int) $this->get('day');
    }

    /**
     * Gets or sets the month
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function month(?int $value = null)
    {
        return $value ? $this->set('month', $value) : (int) $this->get('month');
    }

    /**
     * Gets or sets the year
     * @param int|null $value The value to set
     * @return Date|int
     */
    public function year(?int $value = null)
    {
        return $value ? $this->set('year', $value) : (int) $this->get('year');
    }

    /**
     * Get the formatted date according to the string of tokens passed in.
     * @param string $format The format to use
     * @return string
     * @throws \Exception Invalid format
     * @see https://leafphp.dev/docs/utils/date.html#formatting-dates
     */
    public function format(string $format = 'c'): string
    {
        $matches = [
            'YY' => 'y',
            'YYYY' => 'Y',
            'M' => 'n',
            'MM' => 'm',
            'MMM' => 'M',
            'MMMM' => 'F',
            'D' => 'j',
            'DD' => 'd',
            'd' => 'w',
            'dd' => 'D',
            'ddd' => 'D',
            'dddd' => 'l',
            'H' => 'G',
            'HH' => 'H',
            'h' => 'g',
            'hh' => 'h',
            'a' => 'a',
            'A' => 'A',
            'm' => 'i',
            'mm' => 'i',
            's' => 's',
            'ss' => 's',
            'SSS' => 'u',
            'Z' => 'Z',
            'T' => '\T',
        ];

        return $this->date->format(
            preg_replace_callback('/\[([^\]]+)]|Y{1,4}|T|M{1,4}|D{1,2}|d{1,4}|H{1,2}|h{1,2}|a|A|m{1,2}|s{1,2}|Z{1,2}|SSS/', function ($match) use ($matches) {
                if (strpos($match[0], '[') === 0) {
                    return preg_replace_callback('/\[(.*?)\]/', function ($matched) {
                        return preg_replace("/(.)/", "\\\\$1", $matched[1]);
                    }, $match[0]);
                }

                return $matches[$match[0]] ?? $match[0];
            }, $format)
        );
    }

    /**
     * Returns the string of relative time from a date.
     * @param string $date The date to compare to
     * @param bool $valueOnly Whether to return only the value or the full string
     * @return string
     * @throws \Exception Invalid date
     */
    public function from($date = 'now', $valueOnly = false): string
    {
        $interval = $this->date->diff(new DateTime(str_replace('/', '-', $date)));

        $years = $interval->format('%y');
        $months = $interval->format('%m');
        $days = $interval->format('%d');
        $hours = $interval->format('%h');
        $minutes = $interval->format('%i');

        if ($years > 0) {
            $relativeDate = $years . ' year' . ($years === 1 ? '' : 's') . ($valueOnly ? '' : ($years > 0 && $months > 0 ? ' and ' : '') . ($months > 0 ? ($months . ' month' . ($months === 1 ? '' : 's')) : ''));
        } elseif ($months > 0) {
            $relativeDate = $months . ' month' . ($months === 1 ? '' : 's') . ($valueOnly ? '' : ($months > 0 && $days < 20 ? ' and ' : '') . ($days < 20 ? ($days . ' day' . ($days === 1 ? '' : 's')) : ''));
        } elseif ($days > 0) {
            $relativeDate = $days . ' day' . ($days === 1 ? '' : 's') . ($valueOnly ? '' : ($days > 0 && $hours > 0 ? ' and ' : '') . ($hours > 0 ? ($hours . ' hour' . ($hours === 1 ? '' : 's')) : ''));
        } elseif ($hours > 0) {
            $relativeDate = $hours . ' hour' . ($hours === 1 ? '' : 's') . ($valueOnly ? '' : ($hours > 0 && $minutes > 0 ? ' and ' : '') . ($minutes > 0 ? ($minutes . ' minute' . ($minutes === 1 ? '' : 's')) : ''));
        } elseif ($minutes > 0) {
            $relativeDate = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        } else {
            $relativeDate = 'less than a minute';
        }

        if ($valueOnly) {
            return $relativeDate;
        }

        return $relativeDate
            . ($this->date > new DateTime() ? ' from now' : ' ago');
    }

    /**
     * Returns the string of relative time from now.
     * @param bool $valueOnly Whether to return only the value or the full string
     * @return string
     */
    public function fromNow($valueOnly = false): string
    {
        return $this->from('now', $valueOnly);
    }

    /**
     * Returns the string of relative time from now.
     * @param bool $valueOnly Whether to return only the value or the full string
     * @return string
     */
    public function toNow($valueOnly = false): string
    {
        return $this->fromNow($valueOnly);
    }

    /**
     * Return as PHP DateTime object
     * @return DateTime
     */
    public function toDateTime(): DateTime
    {
        return $this->date;
    }

    /**
     * Return as Unix timestamp
     * @return int
     */
    public function toTimestamp(): int
    {
        return $this->date->getTimestamp();
    }

    /**
     * Return as string timestamp
     * @return string
     */
    public function toDateTimeString(): string
    {
        return $this->date->format('Y-m-d H:i:s');
    }

    /**
     * Return as date string
     * @return string
     */
    public function toDateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    /**
     * Return as time string
     * @return string
     */
    public function toTimeString(): string
    {
        return $this->date->format('H:i:s');
    }

    /**
     * Return as ISO string
     * @return string
     */
    public function toIsoString(): string
    {
        return $this->date->format('Y-m-d\TH:i:sO');
    }

    /**
     * This indicates whether the date object is before the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isBefore($date): bool
    {
        if ($date instanceof DateTime) {
            return $this->date < $date;
        }

        if ($date instanceof Date) {
            return $this->date < $date->toDateTime();
        }

        return $this->date < new DateTime(str_replace('/', '-', $date));
    }

    /**
     * This indicates whether the date object is after the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isAfter($date): bool
    {
        return !$this->isBefore($date) && !$this->isSame($date);
    }

    /**
     * This indicates whether the date object is between the other supplied date-time.
     * @param string|DateTime|Date $date1 The first date to compare to
     * @param string|DateTime|Date $date2 The second date to compare to
     * @return bool
     */
    public function isBetween($date1 = 'now', $date2 = 'now'): bool
    {
        return $this->isAfter($date1) && $this->isBefore($date2);
    }

    /**
     * This indicates whether the date object is between the other supplied date-time.
     * @param string|DateTime|Date $date1 The first date to compare to
     * @param string|DateTime|Date $date2 The second date to compare to
     * @return bool
     */
    public function isBetweenOrEqual($date1 = 'now', $date2 = 'now'): bool
    {
        return $this->isAfter($date1) && $this->isBefore($date2) || $this->isSame($date1) || $this->isSame($date2);
    }

    /**
     * This indicates whether the date object is the same as the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isSame($date = 'now'): bool
    {
        if ($date instanceof DateTime) {
            return $this->date == $date;
        }

        if ($date instanceof Date) {
            return $this->date == $date->toDateTime();
        }

        return $this->date == new DateTime(str_replace('/', '-', $date));
    }

    /**
     * This indicates whether the date object is the same as the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isSameDay($date = 'now'): bool
    {
        if ($date instanceof DateTime) {
            return $this->date->format('Y-m-d') === $date->format('Y-m-d');
        }

        if ($date instanceof Date) {
            return $this->date->format('Y-m-d') === $date->toDateTime()->format('Y-m-d');
        }


        return $this->date->format('Y-m-d') === (new DateTime(str_replace('/', '-', $date)))->format('Y-m-d');
    }

    /**
     * This indicates whether the date object is the same as the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isSameMonth($date = 'now'): bool
    {
        if ($date instanceof DateTime) {
            return $this->date->format('Y-m') === $date->format('Y-m');
        }

        if ($date instanceof Date) {
            return $this->date->format('Y-m') === $date->toDateTime()->format('Y-m');
        }

        return $this->date->format('Y-m') === (new DateTime(str_replace('/', '-', $date)))->format('Y-m');
    }

    /**
     * This indicates whether the date object is the same as the other supplied date-time.
     * @param string|DateTime|Date $date The date to compare to
     * @return bool
     */
    public function isSameYear($date = 'now'): bool
    {
        if ($date instanceof DateTime) {
            return $this->date->format('Y') === $date->format('Y');
        }

        if ($date instanceof Date) {
            return $this->date->format('Y') === $date->toDateTime()->format('Y');
        }

        return $this->date->format('Y') === (new DateTime(str_replace('/', '-', $date)))->format('Y');
    }

    /**
     * This indicates whether the date object is a leap year.
     * @return bool
     */
    public function isLeapYear(): bool
    {
        return ((int) $this->date->format('L')) === 1;
    }

    /**
     * This indicates whether the date object is a datetime
     * @param mixed $date The date to compare to
     * @return bool
     */
    public function isDateTime($date): bool
    {
        return $date instanceof DateTime;
    }
}
