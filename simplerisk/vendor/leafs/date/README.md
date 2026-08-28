<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br>
  <img src="https://leafphp.dev/logo-circle.png" height="120"/>
  <br>
</p>

<h1 align="center">⏰ Tick</h1>

<p align="center">
  <b>A powerful, elegant date/time manipulation library for PHP with a familiar JavaScript-like API</b>
</p>

<p align="center">
	<a href="https://packagist.org/packages/leafs/date"><img src="https://poser.pugx.org/leafs/date/v/stable" alt="Latest Stable Version"/></a>
	<a href="https://packagist.org/packages/leafs/date"><img src="https://poser.pugx.org/leafs/date/downloads" alt="Total Downloads"/></a>
	<a href="https://packagist.org/packages/leafs/date"><img src="https://poser.pugx.org/leafs/date/license" alt="License"/></a>
</p>

<!-- <p align="center">
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#features">Features</a> •
  <a href="#api-reference">API Reference</a> •
  <!-- <a href="#examples">Examples</a> •
</p> -->
<br>

## 🌟 Why Tick?

**Tick** is a modern, lightweight PHP date/time library designed to make working with dates and times as painless as possible. If you're familiar with JavaScript libraries like Moment.js or Day.js, you'll feel right at home with Tick.

```php
// Get current date in a specific format
echo tick()->format('MMMM Do, YYYY'); // March 31st, 2025

// Chain methods for complex operations
$nextFriday = tick()->add(1, 'week')->startOf('week')->add(4, 'day');
echo $nextFriday->format('dddd, MMMM D'); // Friday, April 11
```

## 🚀 Installation

**Using [Leaf CLI](https://cli.leafphp.dev) (Recommended)**:

```bash
leaf install date
```

**Using [Composer](https://getcomposer.org/)**:

```bash
composer require leafs/date
```

## 🏁 Quick Start

```php
// Current date and time
echo tick()->now(); // 2025-03-31T12:29:29+00:00

// Parse a specific date
$birthday = tick('1990-05-15');
echo $birthday->format('MMMM D, YYYY'); // May 15, 1990

// Manipulate dates
$futureDate = tick()->add(3, 'months')->subtract(2, 'days');
echo $futureDate->format('YYYY-MM-DD'); // 2025-06-29
```

## ✨ Features

- **🔄 Familiar API** - If you know Day.js or Moment.js, you already know Tick
- **🪶 Lightweight** - No heavy dependencies, just pure PHP goodness
- **🔌 Native Integration** - Seamless integration with PHP's DateTime objects
- **🌐 Timezone Support** - Work with dates across different timezones effortlessly
- **📊 Date Comparison** - Easily compare dates with intuitive methods
- **🧩 Extensible** - Add your own custom functionality when needed
- **🔍 Validation** - Validate dates with built-in methods
- **📝 Formatting** - Format dates in any way you need

## 📚 API Reference

### Creating Dates

```php
// Current date and time
tick();
tick()->now();

// From string
tick('2025-03-31');
tick('2025/03/31');
tick('March 31, 2025');

// From DateTime
tick(new DateTime('2025-03-31'));

// From tick object
$tomorrow = tick('2025-03-31')->add(1, 'day');
tick($tomorrow);
```

### Formatting

```php
$date = tick('2025-03-31');

// Standard formats
$date->format('YYYY-MM-DD');          // 2025-03-31
$date->format('MMMM D, YYYY');        // March 31, 2025
$date->format('ddd, MMM D, YYYY');    // Mon, Mar 31, 2025
$date->format('YYYY-MM-DD HH:mm:ss'); // 2025-03-31 12:29:29

// Predefined formats
$date->toDateString();                // 2025-03-31
$date->toTimeString();                // 12:29:29
$date->toDateTimeString();            // 2025-03-31 12:29:29
$date->toISOString();                 // 2025-03-31T12:29:29.000Z
```

### Manipulating Dates

```php
$date = tick('2025-03-31');

// Add time
$date->add(1, 'day');     // 2025-04-01
$date->add(2, 'months');  // 2025-05-31
$date->add(1, 'year');    // 2026-03-31

// Subtract time
$date->subtract(1, 'week');  // 2025-03-24
$date->subtract(3, 'hours'); // 2025-03-31 09:29:29

// Start/End of time units
$date->startOf('month');    // 2025-03-01 00:00:00
$date->endOf('year');       // 2025-12-31 23:59:59.999999
$date->startOf('day');      // 2025-03-31 00:00:00
```

### Comparing Dates

```php
$date = tick('2025-03-31');

$date->isBefore('2025-04-01');        // true
$date->isAfter('2025-03-30');         // true
$date->isSame('2025-03-31');          // true
$date->isBetween('2025-03-30', '2025-04-01'); // true
```

<!-- ## 🧪 Examples

### Working with Relative Dates

```php
// Get tomorrow's date
$tomorrow = tick()->add(1, 'day');

// Get the first day of next month
$nextMonth = tick()->add(1, 'month')->startOf('month');

// Get last day of current month
$lastDay = tick()->endOf('month');

// Check if a date is on a weekend
$isWeekend = tick('2025-04-05')->day() === 0 || tick('2025-04-05')->day() === 6;
``` -->

<!-- ### Date Calculations

```php
// Calculate age
$birthdate = tick('1990-05-15');
$age = tick()->diff($birthdate, 'years');

// Calculate days until an event
$christmas = tick('2025-12-25');
$daysUntilChristmas = $christmas->diff(tick(), 'days');

// Calculate business days between dates
$workDays = tick('2025-04-01')->diffBusinessDays(tick('2025-04-30'));
``` -->

### Working with Timezones

```php
// Create a date in a specific timezone
$tokyoTime = tick('2025-03-31', 'Asia/Tokyo');

// Convert between timezones
$newYorkTime = $tokyoTime->setTimezone('America/New_York');
```
<!-- 
// Get timezone offset
$offset = tick()->getTimezoneOffset(); // in minutes -->

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

<p align="center">
  Made with ❤️ by <a href="https://leafphp.dev">Leaf PHP</a>
</p>
