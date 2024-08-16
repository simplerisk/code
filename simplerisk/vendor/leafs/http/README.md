<!-- markdownlint-disable no-inline-html -->
<p align="center">
  <br><br>
  <img src="https://leafphp.netlify.app/assets/img/leaf3-logo.png" height="100"/>
  <br>
</p>

<h1 align="center">Leaf Http v2</h1>

<p align="center">
	<a href="https://packagist.org/packages/leafs/http"
		><img
			src="https://poser.pugx.org/leafs/http/v/stable"
			alt="Latest Stable Version"
	/></a>
	<a href="https://packagist.org/packages/leafs/http"
		><img
			src="https://poser.pugx.org/leafs/http/downloads"
			alt="Total Downloads"
	/></a>
	<a href="https://packagist.org/packages/leafs/http"
		><img
			src="https://poser.pugx.org/leafs/http/license"
			alt="License"
	/></a>
</p>
<br />
<br />

Leaf's core http functionality packaged as a serve-yourself module. **Although seperated from Leaf core, it is still part of the default initial installation and doesn't need to be installed manually (unless you want a particular version).**

## Installation

You can easily install Leaf using the [Leaf CLI](https://cli.leafphp.dev)

```sh
leaf install http
```

[Composer](https://getcomposer.org/).

```bash
composer require leafs/http
```

## Basic Usage

The http module comes with a bunch of packages which help you manage the input and output of your app or API. This module comes with the most used Http related functionality like requests, responses, ...

The available classes in the Http module are:

- [`Leaf\Http\Request`](https://leafphp.dev/modules/http/request)
- [`Leaf\Http\Response`](https://leafphp.dev/modules/http/response)
- [`Leaf\Http\Headers`](https://leafphp.dev/modules/http/headers)
- [`Leaf\Http\Cache`](https://leafphp.dev/modules/http/cache)
- [`Leaf\Http\Status`](https://leafphp.dev/modules/http/status)

**The docs for Leaf Http v2 can be found at [https://leafphp.dev/modules/http/v/2/](https://leafphp.dev/modules/http/v/2/).**

## Request

This is a developer friendly interface which allows you to interact with data coming into your application. [Read the docs](https://leafphp.dev/modules/http/request)

## Response

This interface allows you to output data from your application in different forms. [Read the docs](https://leafphp.dev/modules/http/response)

## Headers

This interface allows you to manage headers in your application. [Read the docs](https://leafphp.dev/modules/http/headers)

## Cache

This interface allows you to manage http cache in your app. [Read the docs](https://leafphp.dev/modules/http/cache)

## Status

This interface allows you to manage http status codes in your app. [Read the docs](https://leafphp.dev/modules/http/status)

**There are also some add on classes which can be installed on demand.**

## Session (module)

This module allows you to manage session in your application. [Read the docs](/modules/session/)

## Cookies (module)

This module allows you to manage cookies in your application. [Read the docs](/modules/cookies)

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
    <td align="center">
			<a href="https://github.com/Dreamer0x01">
				<img src="https://avatars.githubusercontent.com/u/12978365?v=4" width="120px" alt=""/>
				<br />
				<sub>
					<b>Dreamer0x01</b>
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
</table>

## 🤯 Links/Projects

- [Aloe CLI](https://leafphp.dev/aloe-cli/)
- [Leaf Docs](https://leafphp.dev)
- [Leaf MVC](https://mvc.leafphp.dev)
- [Leaf API](https://api.leafphp.dev)
- [Leaf CLI](https://cli.leafphp.dev)
