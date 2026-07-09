/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

(function () {
    'use strict';

    // Localization helper — _lang is populated from required_localization_keys.
    var L = function (k) { return (window._lang && window._lang[k]) || k; };

    var root = document.getElementById('licenses-page');
    if (!root) { return; }

    var ENDPOINT_GET     = BASE_URL + '/api/v2/admin/licenses';
    var ENDPOINT_REFRESH = BASE_URL + '/api/v2/admin/license/refresh';

    // blockUI message styling. Only visual properties — no positioning. blockUI's
    // default centering (centerX/centerY) places the message box in the middle of
    // the blocked element (#licenses-page); overriding position here would break
    // that and leave the message off-center relative to the dimmed card.
    var BLOCK_UI_MESSAGE_CSS = {
        background: '#f9fafb', border: '1px solid #ccc', borderRadius: '6px',
        fontSize: '14px', color: '#000', padding: '12px 18px', textAlign: 'center',
    };
    var BLOCK_UI_OVERLAY_CSS = { backgroundColor: '#000', opacity: 0.25 };
    var _blocked = false;

    function block(message) {
        if (!_blocked) {
            $(root).block({ message: message, css: BLOCK_UI_MESSAGE_CSS, overlayCSS: BLOCK_UI_OVERLAY_CSS });
            _blocked = true;
        } else {
            // message is always a trusted, static L() constant — never response data.
            $(root).find('.blockMsg').text(message);
        }
    }
    function unblock() { if (_blocked) { $(root).unblock(); _blocked = false; } }

    // Explicit re-entrancy guard so a second click can't fire a concurrent request,
    // independent of the blockUI overlay happening to cover the button.
    var _loading = false;

    function showError(msg) {
        var box = document.getElementById('licenses-error');
        if (!box) { return; }
        box.textContent = msg;
        box.classList.remove('d-none');
    }
    function clearError() {
        var box = document.getElementById('licenses-error');
        if (box) { box.classList.add('d-none'); box.textContent = ''; }
    }

    // Surface a load/refresh failure. The initial page load has no data to show,
    // so its failure reveals the persistent inline banner that explains the empty
    // table. A Refresh failure leaves the previously-rendered rows on screen, so
    // it reports through a transient toast — the app-wide convention (alerts.php /
    // assessment.js / risk.js) for "your action didn't take" — rather than a
    // banner. Falls back to the banner if toastr isn't loaded for any reason.
    function reportError(msg, isRefresh) {
        if (isRefresh && typeof toastr !== 'undefined') {
            toastr.error(msg);
        } else {
            showError(msg);
        }
    }

    // ---------------------------------------------------------------------------
    // Rendering. All rows are built with DOM methods (createElement/textContent),
    // never innerHTML, so values from the API response (name, description, dates,
    // status) cannot inject markup. This matches the settings-hub.js convention.
    // ---------------------------------------------------------------------------
    function makeBadge(text, cls) {
        var span = document.createElement('span');
        span.className = 'badge ' + cls;
        span.textContent = text;
        return span;
    }

    function makeCell(text) {
        var td = document.createElement('td');
        td.textContent = (text === null || text === undefined) ? '' : String(text);
        return td;
    }

    // The server sends "YYYY-MM-DD HH:MM:SS"; license validity is a calendar
    // range, so drop the time portion for display.
    function dateOnly(value) {
        return (typeof value === 'string') ? value.split(' ')[0] : value;
    }

    // Append a label cell + value cell to a 2-column grid so the values align.
    function appendLabelValue(grid, label, value) {
        var l = document.createElement('span');
        l.textContent = label;
        var v = document.createElement('span');
        v.textContent = value;
        grid.appendChild(l);
        grid.appendChild(v);
    }

    function licenseBadge(extra) {
        // Free (bundled) Extras are perpetually licensed, so they read 'Active'
        // like paid licenses — the Unlimited validity below conveys they're free.
        if (extra.classification === 'licensed') {
            return makeBadge(L('Active'), 'bg-success');
        }
        if (extra.classification === 'expired') {
            return makeBadge(L('Expired'), 'bg-danger');
        }
        return makeBadge(L('Unlicensed'), 'bg-secondary');
    }

    function licenseCell(extra) {
        var td = document.createElement('td');
        td.className = 'align-middle';

        // Badge with the labeled dates beside it. Left-aligned so the badge sits
        // at the same x in every row (centering the group would offset the badge
        // by each row's date width); vertically centered via the cell's align-middle.
        var wrap = document.createElement('div');
        wrap.className = 'd-flex align-items-center gap-2';
        wrap.appendChild(licenseBadge(extra));

        // Date values: real dates when present (paid licensed / expired); for free
        // licensed Extras the validity is open-ended → 'Unlimited'. Unlicensed
        // Extras show just the badge.
        var startVal = null, endVal = null;
        if (extra.start_date && extra.end_date) {
            startVal = dateOnly(extra.start_date);
            endVal = dateOnly(extra.end_date);
        } else if (extra.is_free && extra.classification === 'licensed') {
            startVal = endVal = L('Unlimited');
        }

        if (startVal !== null) {
            // Labeled dates beside the badge, laid out as a label/value grid so the
            // values align despite 'Start Date:'/'End Date:' differing in width.
            // textContent — values come from the license server; never trust as HTML.
            var dates = document.createElement('div');
            dates.className = 'text-muted small text-nowrap';
            dates.style.display = 'grid';
            dates.style.gridTemplateColumns = 'max-content auto';
            dates.style.columnGap = '0.35rem';
            appendLabelValue(dates, L('StartDate') + ':', startVal);
            appendLabelValue(dates, L('EndDate') + ':', endVal);
            wrap.appendChild(dates);
        }

        td.appendChild(wrap);
        return td;
    }

    function makeRow(extra) {
        var tr = document.createElement('tr');
        tr.appendChild(makeCell(extra.name));
        tr.appendChild(makeCell(extra.description));
        tr.appendChild(licenseCell(extra));
        return tr;
    }

    function emptyRow(messageKey) {
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        td.colSpan = 3;
        td.className = 'text-muted';
        td.textContent = L(messageKey);
        tr.appendChild(td);
        return tr;
    }

    var EMPTY_KEY = {
        licensed:   'NoLicensedExtras',
        expired:    'NoExpiredExtras',
        unlicensed: 'NoUnlicensedExtras',
    };

    function render(extras) {
        var buckets = { licensed: [], expired: [], unlicensed: [] };
        extras.forEach(function (e) {
            (buckets[e.classification] || buckets.unlicensed).push(e);
        });
        Object.keys(buckets).forEach(function (tab) {
            var tbody = root.querySelector('tbody[data-rows="' + tab + '"]');
            var countEl = root.querySelector('[data-count="' + tab + '"]');
            if (countEl) { countEl.textContent = buckets[tab].length; }
            if (!tbody) { return; }
            tbody.replaceChildren();
            if (buckets[tab].length === 0) {
                tbody.appendChild(emptyRow(EMPTY_KEY[tab]));
            } else {
                buckets[tab].forEach(function (extra) { tbody.appendChild(makeRow(extra)); });
            }
        });
    }

    function load(url, method) {
        if (_loading) { return; }
        _loading = true;
        var isRefresh = (method || 'GET').toUpperCase() === 'POST';
        clearError();
        block(L('LoadingLicenseData'));
        var fetchOptions = { method: method || 'GET', credentials: 'same-origin' };
        // csrf-magic requires a token on state-changing requests (POST); GET is
        // exempt (csrf_check skips non-POST). It reads the CSRF-TOKEN header,
        // falling back to $_POST['__csrf_magic']. csrfMagicToken is the global
        // exposed by include_csrf_magic() in head.php.
        if (isRefresh) {
            fetchOptions.headers = {
                'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '',
            };
        }
        // Bound the request so the overlay always clears. The refresh POST runs a
        // single synchronous /license/check on the server (one 5s curl call), so a
        // never-settling fetch would otherwise leave the page blocked forever. On
        // timeout we abort, surface an error, and unblock. (Matches the
        // settings-hub.js fetch pattern.)
        var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var timeoutId = null;
        if (controller) {
            fetchOptions.signal = controller.signal;
            timeoutId = setTimeout(function () { controller.abort(); }, 20000);
        }
        return fetch(url, fetchOptions)
            .then(function (resp) {
                if (resp.status === 401) { window.location.reload(); return null; }
                return resp.json().then(function (json) { return { ok: resp.ok, json: json }; });
            })
            .then(function (res) {
                if (!res) { return; }
                if (res.ok && res.json && res.json.data && Array.isArray(res.json.data.extras)) {
                    render(res.json.data.extras);
                } else {
                    reportError((res.json && res.json.status_message) || L('LicenseStateUnknownRetryShortly'), isRefresh);
                }
            })
            .catch(function () { reportError(L('LicenseStateUnknownRetryShortly'), isRefresh); })
            .then(function () {
                if (timeoutId) { clearTimeout(timeoutId); }
                unblock();
                _loading = false;
            });
    }

    var refreshBtn = document.getElementById('refresh-licenses-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () { load(ENDPOINT_REFRESH, 'POST'); });
    }

    load(ENDPOINT_GET, 'GET');
})();
