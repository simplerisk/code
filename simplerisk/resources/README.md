# `simplerisk/resources/`

Web-servable endpoints that return a **resource for another page to consume** —
bytes with a content type, not a rendered page and not an API payload.

Today that is one file, `custom_logo.php`, which streams the logo an
administrator uploaded through the Customization Extra. The directory exists
rather than the file sitting at the web root because nothing here is a page
anyone navigates to: these URLs are `<img src>` / `<link href>` / download
targets that other pages point at.

## What belongs here

A file belongs in this directory when all of the following hold:

- it is fetched **directly by a client** (a browser, an email client), so it
  must remain web-servable — this is why it is not under `includes/`, whose
  `.htaccess` rewrites every direct request to `/404`;
- it returns **bytes with a resource content type** — an image, a stylesheet, a
  file download — rather than HTML or JSON;
- what it returns is **computed per request** (from configuration, from the
  database, from a permission check), which is why it is a script and not a
  static file under `images/` or `css/`.

Plausible future occupants: a customer-supplied favicon, a stylesheet generated
from configured theme colours, the same logo served to notification-email
clients, and the byte-streaming pages that currently sit in feature directories
(`admin/download_backup.php`, `assessments/download.php`).

## What does not

- **Anything returning data to a caller** — that is the v2 API under `api/v2/`,
  which is JSON-only and authenticated.
- **Static files that never vary per instance** — those are `images/` and
  `css/`.
- **Library code** — `includes/`, which is deliberately not web-servable.
- Note that `assets/` is *not* a home for these: despite the name it is the
  Asset Management feature area.

## Authentication is per-endpoint, not per-directory

Nothing about this directory grants or implies access. `custom_logo.php` is
deliberately unauthenticated because it renders on the login page; a download
endpoint moved here would keep its own permission check. Whatever you add,
state its access model at the top of the file.

## Note for hardening

These are `.php` files in a directory whose contents are otherwise
asset-shaped. A blanket "no PHP execution in asset directories" rule applied
here would break the logo on the login page. If such a rule is introduced,
exempt this directory or move the endpoints somewhere it does not apply.
