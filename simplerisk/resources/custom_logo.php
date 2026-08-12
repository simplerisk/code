<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/******************************************************************************
 * Serves the Customization Extra's custom logo to UNAUTHENTICATED visitors.
 *
 * Lives under resources/ rather than the web root because nothing navigates to
 * it -- it is only ever an <img src> target -- and NOT under includes/, whose
 * .htaccess rewrites every direct request to /404 precisely because nothing
 * there is meant to be fetched over HTTP. This still has to be servable.
 *
 * THIS ENDPOINT TAKES NO INPUT. Not an id, not a filename, not a size. It
 * reads the single `custom_logo` row and streams it, which is the entire
 * security design: there is no parameter to traverse with, no identifier to
 * enumerate, and no branch a request can steer. A caller can ask for exactly
 * one thing -- the logo this instance is configured with -- or nothing.
 *
 * A `?v=` cache-buster appears in the <img> the login panel emits. It is
 * ignored here on purpose: it exists so a browser re-fetches after a re-upload,
 * and reading it would give the URL an input where the point is that it has
 * none.
 *
 * Unauthenticated by design -- it renders on the login page, which nobody has
 * authenticated to yet. That is why the response is deliberately dull: one
 * allowlisted image type, nosniff, no cookies consulted, and nothing about the
 * instance disclosed beyond a logo the operator chose to publish there.
 *****************************************************************************/

require_once(realpath(__DIR__ . '/../includes/bootstrap.php'));
require_once(realpath(__DIR__ . '/../includes/functions.php'));

// The MIME types an upload may claim, re-checked HERE rather than trusted from
// storage. If a row ever arrives by another route -- a restore, a direct
// database write -- it still cannot make this endpoint emit an arbitrary
// content type, which is what would turn an image URL into an HTML or script
// delivery vector on the application's own origin.
//
// SVG is excluded deliberately, and it is the one customers ask for. An SVG can
// carry script; inside an <img> that script is inert, but a visitor who opens
// this URL directly gets it rendered as a DOCUMENT, and the script then runs
// same-origin on the application. A crisp logo is not worth a stored XSS
// reachable without logging in.
$allowed_custom_logo_types = get_custom_logo_extensions();

// Branding is a Customization Extra capability. With the Extra inactive the
// logo stops being served, matching the login panel, which reverts to the
// SimpleRisk mark rather than merely refusing to let the value be edited.
if (!customization_extra()) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// The table arrives with the branding upgrade. A request that reaches here
// before that -- an instance mid-upgrade, or one where the setting survived a
// partial restore that the table did not -- would otherwise raise a PDO error
// and answer a 500. This endpoint is unauthenticated and requested by every
// login page load, so a missing table should be a quiet 404, not a stack trace
// in the log on every visit.
if (!table_exists('custom_logo')) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

$db = db_open();
$stmt = $db->prepare("SELECT `filename`, `mime_type`, `content`, `updated_at` FROM `custom_logo` WHERE `id` = 1;");
$stmt->execute();
$logo = $stmt->fetch(PDO::FETCH_ASSOC);
db_close($db);

// No logo configured, or a row whose type is not one this endpoint will emit.
if (empty($logo) || !isset($allowed_custom_logo_types[$logo['mime_type']])) {
    header("HTTP/1.1 404 Not Found");
    exit();
}

// Strong validator built from the stored bytes, so a re-upload of a
// same-named file still busts the cache.
$etag = '"' . md5($logo['mime_type'] . '|' . $logo['updated_at'] . '|' . strlen($logo['content'])) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header("HTTP/1.1 304 Not Modified");
    header("ETag: " . $etag);
    exit();
}

header("Content-Type: " . $logo['mime_type']);
header("Content-Length: " . strlen($logo['content']));
// `inline` with a fixed, generated name: the stored filename is operator input
// and has no business reaching a response header.
header("Content-Disposition: inline; filename=\"login-logo." . $allowed_custom_logo_types[$logo['mime_type']] . "\"");
// Belt and braces against content sniffing, so a file whose bytes disagree with
// its declared type cannot be re-interpreted as something executable.
header("X-Content-Type-Options: nosniff");
header("ETag: " . $etag);
// Private rather than public: this is a per-instance asset and the login page
// is the only thing that requests it. The cache-buster in the panel's <img>
// makes a long max-age safe.
header("Cache-Control: private, max-age=86400");

echo $logo['content'];
