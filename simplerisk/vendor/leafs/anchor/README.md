<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.dev/logo-circle.png" height="100"/>
  <br><br>
</p>

# Leaf Anchor

[![Latest Stable Version](https://poser.pugx.org/leafs/anchor/v/stable)](https://packagist.org/packages/leafs/anchor)
[![Total Downloads](https://poser.pugx.org/leafs/anchor/downloads)](https://packagist.org/packages/leafs/anchor)
[![License](https://poser.pugx.org/leafs/anchor/license)](https://packagist.org/packages/leafs/anchor)

This package contains leaf's utils for deep sanitizing of data and basic security provided for your app data. It also serves as the base for security provided in other modules like CSRF.

## Installation

You can easily install Leaf using [Composer](https://getcomposer.org/).

```bash
composer require leafs/anchor
```

## Basic Usage

After [installing](#installation) anchor, create an _index.php_ file.

### Base XSS protection

```php
<?php
require __DIR__ . "vendor/autoload.php";

$data = $_POST["data"];
$data = Leaf\Anchor::sanitize($data);

echo $data;
```

This also works on arrays

```php
<?php
require __DIR__ . "vendor/autoload.php";

$data = Leaf\Anchor::sanitize($_POST);

echo $data["input"];
```

You may quickly test this using the built-in PHP server:

```bash
php -S localhost:8000
```
