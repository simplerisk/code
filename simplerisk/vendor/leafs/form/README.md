<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.netlify.app/assets/img/leaf3-logo.png" height="100"/>
  <h1 align="center">Leaf Form Module</h1>
  <br><br>
</p>

[![Latest Stable Version](https://poser.pugx.org/leafs/form/v/stable)](https://packagist.org/packages/leafs/form)
[![Total Downloads](https://poser.pugx.org/leafs/form/downloads)](https://packagist.org/packages/leafs/form)
[![License](https://poser.pugx.org/leafs/form/license)](https://packagist.org/packages/leafs/form)

Leaf's form validation functionality packaged as a serve-yourself module.

## Installation

You can easily install Leaf using the Leaf CLI:

```bash
leaf install form
```

Or via [Composer](https://getcomposer.org/).

```bash
composer require leafs/form
```

## Basic Usage

```php
<?php

$data = [
  'name' => [
    'first' => 'Jane',
    'last' => 'Doe',
  ],
  'items' => ['item1', 'item2'],
  'email' => 'example@example.com',
  'password' => 'password1234',
];

$validated = form()->validate($data, [
  'name' => 'array',
  'name.first' => 'string',
  'name.last' => 'optional|string',
  'items' => 'array<string>',
  'email' => 'email',
  'password' => 'min:8'
]);

if ($validated) {
  // do something
} else {
  // get errors
  $errors = form()->errors();
}
```

## Stay In Touch

- [Twitter](https://twitter.com/leafphp)
- [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
- [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## Learning Leaf PHP

- Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
- You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
- You can also learn from [codelabs](https://leafphp.dev/codelabs/) and contribute as well.

## Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

## Sponsoring Leaf

We are committed to keeping Leaf open-source and free, but maintaining and developing new features now requires significant time and resources. As the project has grown, so have the costs, which have been mostly covered by the team. To sustain and grow Leaf, we need your help to support full-time maintainers.

You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our [existing cash/code contributors](https://leafphp.dev#sponsors), we love you all ❤️
