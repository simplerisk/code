<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.dev/logo-circle.png" height="100"/>
  <br>
</p>

<h1 align="center">Tick</h1>

<p align="center">
	<a href="https://packagist.org/packages/leafs/date"
		><img
			src="https://poser.pugx.org/leafs/date/v/stable"
			alt="Latest Stable Version"
	/></a>
	<a href="https://packagist.org/packages/leafs/date"
		><img
			src="https://poser.pugx.org/leafs/date/downloads"
			alt="Total Downloads"
	/></a>
	<a href="https://packagist.org/packages/leafs/date"
		><img
			src="https://poser.pugx.org/leafs/date/license"
			alt="License"
	/></a>
</p>
<br />
<br />

[Tick](https://leafphp.dev/docs/utils/date.html) is a minimalist PHP library that parses, validates, manipulates, and displays dates and times with a largely DayJS/MomentJS-compatible API. If you use DayJS, you already know how to use Tick.

```php
tick()->now(); // get the current timestamp
tick()->format('YYYY-MM-DD'); // format the current timestamp
tick()->startOf('month')->add(1, 'day')->set('year', 2018)->format('YYYY-MM-DD HH:mm:ss');
```

## Installation

You can install Tick using the [Leaf CLI](https://cli.leafphp.dev):

```bash
leaf install date
```

Or with [Composer](https://getcomposer.org/):

```bash
composer require leafs/date
```

## API

It's easy to use Tick's APIs to parse, validate, manipulate, and display dates and times.

- Simple, powerful and intuitive API

```php
tick('2018-08-08') // parse
tick()->format('{YYYY} MM-DDTHH:mm:ss SSS [Z] A') // display
tick()->set('month', 3)->month() // get & set
tick()->add(1, 'year') // manipulate
tick()->isBefore('...') // query
```

- Full support for native PHP DateTime

```php
tick(new DateTime('2018-08-08')) // parse
tick(new DateTime('2018-08-08'))->format('YY-MM-DD') // display
tick(new DateTime('2018-08-08'))->set('month', 3)->month() // get & set
tick(new DateTime('2018-08-08'))->add(1, 'year') // manipulate
tick()->isBefore(new DateTime('2018-08-09')) // query
tick()->toDateTime() // convert to DateTime
```

- Highly recursive API

```php
tick('2018-08-08')->isBefore(
	tick('2018-08-09')->add(1, 'day')->set('year', 2018)
);
```
