/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Register & Upgrade (admin/register.php): the update channel selector.
 *
 * A thin client of GET/PUT /api/v2/admin/update-channel. The server is the only
 * guard that matters (403 non-admin, 409 locked, 422 invalid); the disabled
 * control is a courtesy. Every switch goes through the sr-modal confirmation,
 * and anything that closes it without confirming (Cancel, x, Esc, backdrop)
 * puts the selector back.
 *
 * Text set here goes in with .text(). Toast messages come pre-escaped from the
 * page (#update-channel-config) because toastr renders HTML and decodes them
 * exactly once; they are never re-escaped here.
 */
(function ($) {
    'use strict';

    var API_URL = BASE_URL + '/api/v2/admin/update-channel';

    $(function () {
        var $select = $('#update-channel-select');
        var $modal = $('#update-channel-modal');
        if (!$select.length || !$modal.length) {
            return;
        }

        var $confirm = $('#update-channel-confirm');
        var $locked = $('#update-channel-locked');
        var $lockedText = $('#update-channel-locked-text');
        var $urls = $('#update-channel-urls');
        var $loadError = $('#update-channel-load-error');

        var toasts = {};
        try {
            toasts = JSON.parse($('#update-channel-config').text() || '{}').toasts || {};
        } catch {
            // A malformed block only costs the toasts; the selector still works.
            toasts = {};
        }

        // The last state the server confirmed. Null until the first GET lands,
        // which is also why the control starts disabled.
        var state = null;
        var pendingChannel = null;
        var confirmed = false;
        var busy = false;

        function channelLabel(channel) {
            return channel === 'rc' ? L('ReleaseCandidate') : L('Production');
        }

        function toast(message, success) {
            if (message) {
                showAlertFromMessage(message, success);
            }
        }

        function applyState(next) {
            state = next;
            var custom = !!next.custom;

            $select.find('option[value="custom"]').prop('hidden', !custom).prop('disabled', !custom);
            $select.val(custom ? 'custom' : next.channel);
            $select.prop('disabled', !!next.locked);

            if (next.locked) {
                if (custom) {
                    $lockedText.text(L('UpdateChannelCustomInConfig'));
                    $urls.empty();
                    $.each(next.urls || {}, function (service, url) {
                        $('<li>').append($('<code>').text(url)).appendTo($urls);
                    });
                    $urls.prop('hidden', false);
                } else {
                    $lockedText.text(L('UpdateChannelPinnedInConfig').replace('{$channel}', channelLabel(next.channel)));
                    $urls.empty().prop('hidden', true);
                }
                $locked.prop('hidden', false);
            } else {
                $locked.prop('hidden', true);
            }

            $loadError.prop('hidden', true);
        }

        function load() {
            $select.prop('disabled', true).attr('aria-busy', 'true');
            $.ajax({
                url: API_URL,
                type: 'GET',
                dataType: 'json',
                success: function (response) {
                    if (response && response.data) {
                        applyState(response.data);
                    } else {
                        $loadError.prop('hidden', false);
                    }
                },
                error: function () {
                    // Leave the control disabled: without the server's answer
                    // there is no honest value to offer a switch from.
                    $loadError.prop('hidden', false);
                },
                complete: function () {
                    $select.removeAttr('aria-busy');
                },
            });
        }

        // The switch cleared settings.latest_version_data, which the "(Latest is
        // ...)" clause and the version pills read. Drop them now, so the page
        // matches what a reload would render until the version check job has
        // fetched the new channel's feed.
        function clearLatestVersionText() {
            $('[data-version-row] .sr-register-latest, [data-version-row] .sr-register-version-state').remove();
            // The "ahead of this channel" / "latest unknown" line described the
            // previous channel; a reload states the new channel's position.
            $('#update-channel-position').prop('hidden', true).empty();
        }

        function showVariant(channel) {
            $modal.find('[data-channel-variant]').each(function () {
                $(this).prop('hidden', $(this).attr('data-channel-variant') !== channel);
            });
        }

        function setBusy(on) {
            busy = on;
            $confirm.prop('disabled', on).attr('aria-busy', on ? 'true' : null);
            $confirm.find('.spinner-border').remove();
            if (on) {
                $confirm.prepend('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>');
            }
        }

        // True when csrf-magic's retryCSRF() re-sent the request with a fresh
        // token: it re-issues $.ajax(settings), so the outcome handlers live in
        // the settings, and the first attempt must not be reported as a failure.
        function reissuedByCsrfRetry(xhr, settings) {
            if (typeof retryCSRF !== 'function') {
                return false;
            }
            var before = typeof retryCSRFCount !== 'undefined' ? retryCSRFCount : null;
            if (!retryCSRF(xhr, settings)) {
                return false;
            }
            return before === null || (typeof retryCSRFCount !== 'undefined' && retryCSRFCount > before);
        }

        function failureToast(status) {
            if (status === 409) {
                return toasts.locked;
            }
            if (status === 422) {
                return toasts.invalid;
            }
            if (status === 500) {
                return toasts.writeFailed;
            }
            return toasts.failed;
        }

        function hideModal() {
            bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
        }

        $select.on('change', function () {
            var target = $select.val();
            if (!state || state.locked || busy || (target !== 'production' && target !== 'rc') || target === state.channel) {
                return;
            }
            pendingChannel = target;
            confirmed = false;
            showVariant(target);
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        });

        // A save in flight cannot be dismissed: a write can't be taken back.
        $modal.on('hide.bs.modal', function (e) {
            if (busy) {
                e.preventDefault();
            }
        });

        $modal.on('hidden.bs.modal', function () {
            if (!confirmed && state) {
                $select.val(state.custom ? 'custom' : state.channel);
            }
            pendingChannel = null;
            confirmed = false;
            // A programmatically opened modal has no trigger for Bootstrap to
            // return focus to.
            if (!$select.prop('disabled')) {
                $select.trigger('focus');
            }
        });

        $confirm.on('click', function () {
            if (busy || !pendingChannel) {
                return;
            }
            var channel = pendingChannel;
            setBusy(true);
            $.ajax({
                url: API_URL,
                type: 'PUT',
                contentType: 'application/json',
                // A JSON body bypasses csrf-magic's automatic token injection.
                headers: { 'CSRF-TOKEN': typeof csrfMagicToken !== 'undefined' ? csrfMagicToken : '' },
                dataType: 'json',
                data: JSON.stringify({ channel: channel }),
                success: function (response) {
                    setBusy(false);
                    confirmed = true;
                    if (response && response.data) {
                        applyState(response.data);
                    }
                    clearLatestVersionText();
                    hideModal();
                    toast((toasts.switched || {})[channel], true);
                },
                error: function (xhr) {
                    if (reissuedByCsrfRetry(xhr, this)) {
                        return;
                    }
                    setBusy(false);
                    hideModal();
                    toast(failureToast(xhr.status), false);
                    // Re-read the server's answer: a 409 means config.php now
                    // pins the channel, and the control has to say so.
                    load();
                },
            });
        });

        load();
    });
})(jQuery);
