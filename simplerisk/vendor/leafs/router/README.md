<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.netlify.app/assets/img/leaf3-logo.png" height="100"/>
  <br>
</p>

<h1 align="center">Leaf Router</h1>

<p align="center">
	<a href="https://packagist.org/packages/leafs/router"
		><img
			src="https://poser.pugx.org/leafs/router/v/stable"
			alt="Latest Stable Version"
	/></a>
	<a href="https://packagist.org/packages/leafs/router"
		><img
			src="https://poser.pugx.org/leafs/router/downloads"
			alt="Total Downloads"
	/></a>
	<a href="https://packagist.org/packages/leafs/router"
		><img
			src="https://poser.pugx.org/leafs/router/license"
			alt="License"
	/></a>
</p>
<br />
<br />

Leaf router is the core routing engine which powers the Leaf PHP framework. Leaf router is now served as a serve-yourself module which can even be used outside the Leaf ecosystem.

**Leaf Router is still built into Leaf Core and doesn't need to be installed separately.**

## Installation

You can easily install Leaf using [Composer](https://getcomposer.org/).

```bash
composer require leafs/router
```

## Basic Usage

If you are using leaf router with Leaf, you can build your leaf apps just as you've always done:

```php
<?php
require __DIR__ . "vendor/autoload.php";

// GET example
app()->get("/", function () {
  response()->json([
    "message" => "Welcome!"
  ]);
});

// MATCH example
app()->match("GET", "/test", function () {
  response()->json([
    "message" => "Test!"
  ]);
});

app()->run();
```

If however, you are using leaf router outside of the leaf framework, you simply need to call these methods on the `Leaf\Router` object:

```php
<?php

use Leaf\Router;

require __DIR__ . "vendor/autoload.php";

// GET example
Router::get("/", function () {
  echo json_encode([
    "message" => "Welcome!"
  ]);
});

// MATCH example
Router::match("GET", "/test", function () {
  echo json_encode([
    "message" => "Test!"
  ]);
});

Router::run();
```

You may quickly test this using the built-in PHP server:

```bash
php -S localhost:8000
```

## 💬 Stay In Touch

- [Twitter](https://twitter.com/leafphp)
- [Join the forum](https://github.com/leafsphp/leaf/discussions/37)
- [Chat on discord](https://discord.com/invite/Pkrm9NJPE3)

## 📓 Learning Leaf 3

- Leaf has a very easy to understand [documentation](https://leafphp.dev) which contains information on all operations in Leaf.
- You can also check out our [youtube channel](https://www.youtube.com/channel/UCllE-GsYy10RkxBUK0HIffw) which has video tutorials on different topics
- We are also working on codelabs which will bring hands-on tutorials you can follow and contribute to.

## 😇 Contributing

We are glad to have you. All contributions are welcome! To get started, familiarize yourself with our [contribution guide](https://leafphp.dev/community/contributing.html) and you'll be ready to make your first pull request 🚀.

To report a security vulnerability, you can reach out to [@mychidarko](https://twitter.com/mychidarko) or [@leafphp](https://twitter.com/leafphp) on twitter. We will coordinate the fix and eventually commit the solution in this project.

### Code contributors

<table>
	<tr>
		<td align="center">
			<a href="https://github.com/mychidarko">
				<img src="https://avatars.githubusercontent.com/u/26604242?v=4" width="120px" alt=""/>
				<br />
				<sub>
					<b>Michael Darko</b>
				</sub>
			</a>
		</td>
	</tr>
</table>

## 🤩 Sponsoring Leaf

Your cash contributions go a long way to help us make Leaf even better for you. You can sponsor Leaf and any of our packages on [open collective](https://opencollective.com/leaf) or check the [contribution page](https://leafphp.dev/support/) for a list of ways to contribute.

And to all our existing cash/code contributors, we love you all ❤️

### Cash contributors

<table>
	<tr>
		<td align="center">
			<a href="https://opencollective.com/aaron-smith3">
				<img src="https://images.opencollective.com/aaron-smith3/08ee620/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Aaron Smith</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="https://opencollective.com/peter-bogner">
				<img src="https://images.opencollective.com/peter-bogner/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Peter Bogner</b></sub>
			</a>
		</td>
		<td align="center">
			<a href="#">
				<img src="https://images.opencollective.com/guest-32634fda/avatar.png" width="120px" alt=""/>
				<br />
				<sub><b>Vano</b></sub>
			</a>
		</td>
    <td align="center">
      <a href="#">
        <img
          src="https://images.opencollective.com/guest-c72a498e/avatar.png"
          width="120px"
          alt=""
        />
        <br />
        <sub><b>Casprine</b></sub>
      </a>
    </td>
	</tr>
  <tr>
    <td align="center">
			<a href="https://github.com/doc-han">
				<img src="https://avatars.githubusercontent.com/u/35382021?v=4" width="120px" alt=""/>
				<br />
				<sub><b>Farhan Yahaya</b></sub>
			</a>
		</td>
    <td align="center">
			<a href="https://www.lucaschaplain.design/">
				<img src="https://images.opencollective.com/sptaule/aa5f956/avatar/256.png" width="120px" alt=""/>
				<br />
				<sub><b>Lucas Chaplain</b></sub>
			</a>
		</td>
  </tr>
</table>

## 🤯 Links/Projects

- [Aloe CLI](https://leafphp.dev/aloe-cli/)
- [Leaf Docs](https://leafphp.dev)
- [Leaf MVC](https://mvc.leafphp.dev)
- [Leaf API](https://api.leafphp.dev)
- [Leaf CLI](https://cli.leafphp.dev)
