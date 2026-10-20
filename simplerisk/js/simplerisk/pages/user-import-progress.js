/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/*
 * Progress view for a background user import (Settings > Import-Export > Users).
 *
 * Polls GET /api/v2/import-export/user-imports/{id} until the import finishes. The temporary
 * passwords are sealed to a one-time key the server gave this page exactly once (the data-import-key
 * attribute of the response to the form post) and does not keep. It is held here, in sessionStorage,
 * and sent back only to reveal the passwords with
 * POST /api/v2/import-export/user-imports/{id}/passwords. That call erases them, so they are shown
 * once. If this tab is closed first the key is gone and they cannot be shown. Every value from the
 * server is written with .text() because usernames come from an uploaded spreadsheet.
 */
(function ($) {
    'use strict';

    var $root = $('#user-import-progress');
    if (!$root.length) {
        return;
    }

    var importId = parseInt($root.data('import-id'), 10);
    var apiBase = BASE_URL + '/api/v2/import-export/user-imports/' + importId;
    var POLL_MS = 2000;
    var MAX_CONSECUTIVE_FAILURES = 5;
    var failures = 0;

    // The one-time key: take it from the page, then remove it from the DOM so it isn't left lying around
    var STORAGE_KEY = 'userImportKey:' + importId;
    var memoryKey = null;

    (function adoptKeyFromPage() {
        var fromPage = $root.attr('data-import-key');
        if (!fromPage) {
            return;
        }
        memoryKey = fromPage;
        try {
            window.sessionStorage.setItem(STORAGE_KEY, fromPage);
        } catch (e) { /* storage blocked: the key lives for this page view only */ }
        $root.removeAttr('data-import-key').removeData('import-key');
    })();

    function getKey() {
        if (memoryKey) {
            return memoryKey;
        }
        try {
            return window.sessionStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function forgetKey() {
        memoryKey = null;
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* nothing to remove */ }
    }

    function L(key) {
        return (window._lang && window._lang[key] !== undefined) ? window._lang[key] : key;
    }

    function fmt(text, values) {
        var out = String(text);
        Object.keys(values).forEach(function (name) {
            out = out.split('{$' + name + '}').join(String(values[name]));
        });
        return out;
    }

    // Keep the address bookmarkable and make a reload re-open this view instead of re-posting the form
    try {
        var url = new URL(window.location.href);
        url.search = '';
        url.searchParams.set('importoption', 'users');
        url.searchParams.set('user_import', importId);
        window.history.replaceState(null, '', url.toString());
    } catch (e) { /* older browsers: the form post URL stays, which is harmless */ }

    function render(p) {
        var pct = p.total > 0 ? Math.min(100, Math.round((p.processed / p.total) * 100)) : 0;
        if (p.status === 'completed') {
            pct = 100;
        }
        $('#user-import-bar')
            .css('width', pct + '%')
            .attr('aria-valuenow', pct)
            .text(pct + '%')
            .toggleClass('bg-success', p.status === 'completed')
            .toggleClass('bg-danger', p.status === 'failed');

        var status;
        if (p.status === 'queued') {
            status = L('UserImportQueued');
        } else if (p.status === 'running') {
            status = fmt(L('UserImportProgressCount'), { processed: p.processed, total: p.total });
        } else if (p.status === 'completed') {
            status = L('UserImportComplete');
        } else {
            status = L('UserImportFailed');
        }
        $('#user-import-status').text(status);

        if (p.status === 'completed' || p.status === 'failed') {
            $('#user-import-summary')
                .text(fmt(L('UserImportSummary'), { created: p.created, updated: p.updated, failed: p.failed }))
                .removeClass('d-none');
        }

        var $problems = $('#user-import-problems').empty();
        (p.problems || []).forEach(function (problem) {
            var key = problem.outcome === 'invalid' ? 'UserImportRowInvalid' : 'UserImportRowError';
            $('<li>').text(fmt(L(key), { row: problem.row, username: problem.username || '' })).appendTo($problems);
        });
        $problems.toggleClass('d-none', !(p.problems && p.problems.length));
    }

    function showPasswordsSection(p) {
        var $section = $('#user-import-passwords-section');
        var $note = $('#user-import-passwords-note');
        var $button = $('#user-import-reveal');

        if (p.passwords_pending && getKey()) {
            $note.text(L('UserImportPasswordsOnce'));
            $button.removeClass('d-none');
            $section.removeClass('d-none');
        } else if (p.created > 0 && !$('#user-import-passwords').is(':visible')) {
            // Created users but no way to show their passwords from here: already shown, expired, or this is not the tab that started the import
            $note.text(L('UserImportPasswordsUnavailable'));
            $button.addClass('d-none');
            $section.removeClass('d-none');
        }
    }

    function poll() {
        $.ajax({ url: apiBase, method: 'GET', dataType: 'json' })
            .done(function (response) {
                failures = 0;
                var p = response.data;
                render(p);

                if (p.status === 'completed' || p.status === 'failed') {
                    showPasswordsSection(p);
                    return;
                }
                window.setTimeout(poll, POLL_MS);
            })
            .fail(function () {
                failures += 1;
                if (failures >= MAX_CONSECUTIVE_FAILURES) {
                    $('#user-import-status').text(L('RequestFailed'));
                    return;
                }
                window.setTimeout(poll, POLL_MS * 2);
            });
    }

    $('#user-import-reveal').on('click', function () {
        var $button = $(this).prop('disabled', true);

        $.ajax({ url: apiBase + '/passwords', method: 'POST', dataType: 'json', data: { key: getKey() } })
            .done(function (response) {
                var $body = $('#user-import-passwords tbody').empty();
                (response.data || []).forEach(function (row) {
                    $('<tr>')
                        .append($('<td>').text(row.username))
                        .append($('<td>').append($('<code>').text(row.password)))
                        .appendTo($body);
                });
                forgetKey();
                $('#user-import-passwords').removeClass('d-none');
                $('#user-import-passwords-note').text(L('UserImportPasswordsShownOnce'));
                $button.addClass('d-none');
            })
            .fail(function () {
                $('#user-import-passwords-note').text(L('UserImportPasswordsUnavailable'));
                $button.addClass('d-none');
            });
    });

    poll();
})(jQuery);
