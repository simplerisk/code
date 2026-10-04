/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

window.SRAccountProfile = (function () {
    'use strict';

    var currentBundle = null;

    function fetchBundle() {
        return $.ajax({
            type: 'GET',
            url: BASE_URL + '/api/v2/account/profile',
            dataType: 'json'
        }).then(function (response) {
            return response.data;
        });
    }

    function renderRecordHeader(data) {
        var initials = (data.name || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(function (part) { return part.charAt(0).toUpperCase(); })
            .join('');

        $('#profile-avatar').text(initials);
        $('#profile-name').text(data.name);
        $('#profile-email').text(data.email);
        $('#profile-role-pill').toggle(!!data.admin).find('.sr-pill-label').text(L('Administrator'));
    }

    function renderAccountDetailsRail(data) {
        $('#profile-username').text(data.username);
        $('#profile-manager').text(data.manager || '—');
        $('#profile-role').text(data.role);

        var $teams = $('#profile-teams').empty();
        var teams = data.teams || [];
        var limit = 3;

        teams.slice(0, limit).forEach(function (team) {
            $('<span>', { class: 'sr-chip' }).text(team).appendTo($teams);
        });

        if (teams.length > limit) {
            var $more = $('<button>', { type: 'button', class: 'sr-linkbtn', text: L('ShowMore') });
            var $less = $('<button>', { type: 'button', class: 'sr-linkbtn d-none', text: L('ShowLess') });

            $more.on('click', function () {
                teams.slice(limit).forEach(function (team) {
                    $('<span>', { class: 'sr-chip' }).text(team).insertBefore($more);
                });
                $more.addClass('d-none');
                $less.removeClass('d-none');
            });
            $less.on('click', function () {
                $teams.find('.sr-chip').slice(limit).remove();
                $less.addClass('d-none');
                $more.removeClass('d-none');
            });

            $teams.append($more).append($less);
        }
    }

    function renderBundle(data) {
        currentBundle = data;
        renderRecordHeader(data);
        renderAccountDetailsRail(data);
        // Security/Preferences/Permissions/API-key rendering: Tasks 9-11.
        if (window.SRAccountProfileSecurity) { window.SRAccountProfileSecurity.render(data); }
        if (window.SRAccountProfilePreferences) { window.SRAccountProfilePreferences.render(data); }
        if (window.SRAccountProfileApiKey) { window.SRAccountProfileApiKey.render(data); }
    }

    function init() {
        fetchBundle().done(renderBundle).fail(function (xhr) {
            if (!retryCSRF(xhr, this)) {
                if (xhr.responseJSON && xhr.responseJSON.status_message) {
                    showAlertsFromArray(xhr.responseJSON.status_message);
                } else {
                    showAlertFromMessage(L('RequestFailed'), false);
                }
            }
        });
    }

    return { init: init, renderBundle: renderBundle, getBundle: function () { return currentBundle; } };
})();

window.SRAccountProfileSecurity = (function () {
    'use strict';

    var checkers = {
        min_chars: function (pw, req) { return pw.length >= req.min_chars; },
        alpha: function (pw) { return /[A-Za-z]/.test(pw); },
        upper: function (pw) { return /[A-Z]/.test(pw); },
        lower: function (pw) { return /[a-z]/.test(pw); },
        digit: function (pw) { return /[0-9]/.test(pw); },
        special: function (pw) { return /[^A-Za-z0-9]/.test(pw); }
        // 'min_age' has no client-side check -- it depends on password
        // history the client doesn't have; the server is authoritative for it.
    };

    function renderRequirementList(requirements) {
        var $list = $('#profile-password-requirements').empty();
        requirements.forEach(function (req) {
            if (!checkers[req.type]) {
                return; // min_age: shown nowhere client-side, server-checked only.
            }
            var $item = $('<div>', { class: 'req-item pending', 'data-req-type': req.type });
            $item.append($('<svg>', { class: 'req-icon' })); // markup filled by CSS/sprite per design-system §5
            $item.append($('<span>').text(req.message));
            $list.append($item);
        });
    }

    function updateLiveChecklist(requirements, password) {
        requirements.forEach(function (req) {
            var checker = checkers[req.type];
            if (!checker) { return; }
            var met = checker(password, req);
            $('#profile-password-requirements [data-req-type="' + req.type + '"]')
                .toggleClass('met', met)
                .toggleClass('pending', !met);
        });
    }

    function render(data) {
        var mfaPillText = data.mfa_enabled ? L('Enabled') : L('Disabled');
        $('#profile-mfa-status').text(mfaPillText).toggleClass('sr-pill-warning', !data.mfa_enabled).toggleClass('sr-pill-success', data.mfa_enabled);
        $('#profile-mfa-enable-btn').toggle(!data.mfa_enabled);
        $('#profile-mfa-disable-btn').toggle(data.mfa_enabled && !data.mfa_required);

        // LDAP/SAML accounts have no local password to change -- the API
        // rejects a change attempt server-side either way (see
        // api/v2/includes/profile.php's `user_type !== 'simplerisk'` gate),
        // but showing a form that can never succeed is a real UX regression,
        // not just a missing security check. Hide the whole card body rather
        // than only disabling the button, matching the legacy page's
        // `$_SESSION['user_type'] != "ldap"` gate.
        var canChangePassword = data.user_type === 'simplerisk';
        $('#profile-password-card-body').toggle(canChangePassword);
        if (!canChangePassword) {
            return;
        }

        renderRequirementList(data.password_policy.requirements);
        $('#profile-mfa-code-field').toggle(!!data.mfa_enabled);

        var $newPassword = $('#profile-new-password').off('input').on('input', function () {
            updateLiveChecklist(data.password_policy.requirements, $(this).val());
        });

        $('#profile-password-form').off('submit').on('submit', function (e) {
            e.preventDefault();
            var payload = {
                current_password: $('#profile-current-password').val(),
                new_password: $newPassword.val(),
                confirm_password: $('#profile-confirm-password').val()
            };
            if (data.mfa_enabled) {
                payload.mfa_code = $('#profile-mfa-code').val();
            }

            $.ajax({
                type: 'PUT',
                url: BASE_URL + '/api/v2/account/password',
                data: payload,
                dataType: 'json'
            }).done(function (response) {
                showAlertFromMessage(L('PasswordUpdated'), true);
                // kill_other_sessions_of_current_user() can fail to clear every
                // other session; its docblock requires callers to surface that
                // rather than report unconditional success. The legacy page did
                // this via alert_if_sessions_not_cleared(); this is the API/JS
                // equivalent, using the sessions_cleared flag this endpoint
                // already returns.
                if (response.data && response.data.sessions_cleared === false) {
                    showAlertFromMessage(L('OtherSessionsNotCleared'), false);
                }
                $('#profile-password-form')[0].reset();
                renderRequirementList(data.password_policy.requirements);
            }).fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    } else {
                        showAlertFromMessage(L('RequestFailed'), false);
                    }
                }
            });
        });
    }

    return { render: render };
})();

window.SRAccountProfilePreferences = (function () {
    'use strict';

    function renderLanguageSelect(data) {
        var $select = $('#profile-language-select').empty();
        (data.languages || []).forEach(function (lang) {
            $('<option>', { value: lang.id, selected: lang.id === data.language_id }).text(lang.name).appendTo($select);
        });
    }

    function renderPermissions(data) {
        var $container = $('#profile-permissions').empty();

        (data.permission_groups || []).forEach(function (group) {
            var $row = $('<button>', { type: 'button', class: 'sr-perm-row' });
            $row.append($('<svg>', { class: 'sr-perm-caret' }));
            $row.append($('<span>', { class: 'sr-perm-name' }).text(group.name));
            $row.append($('<span>', { class: 'sr-perm-count' }).text(L('PermissionsCountLabel').replace('$count', String(group.permissions.length))));

            // Only claim "All granted" when every permission in the group is
            // actually selected -- a partially-granted category shows no
            // pill rather than falsely claiming full access.
            var allGranted = group.permissions.length > 0 && group.permissions.every(function (perm) { return perm.selected; });
            if (allGranted) {
                $row.append($('<span>', { class: 'sr-pill sr-pill-success' }).text(L('AllGranted')));
            }

            var $list = $('<div>', { class: 'sr-perm-list d-none' });
            group.permissions.forEach(function (perm) {
                if (!perm.selected) { return; }
                var $item = $('<div>', { class: 'sr-perm-item' });
                $item.append($('<svg>', { class: 'sr-perm-check' }));
                $item.append($('<span>').text(perm.name));
                $list.append($item);
            });

            $row.on('click', function () {
                $(this).find('.sr-perm-caret').toggleClass('open');
                $list.toggleClass('d-none');
            });

            $container.append($row).append($list);
        });
    }

    function render(data) {
        renderLanguageSelect(data);
        renderPermissions(data);

        $('#profile-language-form').off('submit').on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                type: 'PATCH',
                url: BASE_URL + '/api/v2/account/profile',
                data: { language: $('#profile-language-select').val() },
                dataType: 'json'
            }).done(function () {
                showAlertFromMessage(L('LanguageUpdated'), true);
                // Reload so the whole page re-renders in the newly selected
                // language -- the server session already reflects the change,
                // but this request's own rendered markup does not.
                window.location.reload();
            }).fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    } else {
                        showAlertFromMessage(L('RequestFailed'), false);
                    }
                }
            });
        });

        $('#profile-reset-display-btn').off('click').on('click', function () {
            $.ajax({
                type: 'POST',
                url: BASE_URL + '/api/v2/account/reset-display-settings',
                dataType: 'json'
            }).done(function () {
                showAlertFromMessage(L('CustomResetSuccessMessage'), true);
            }).fail(function (xhr) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    } else {
                        showAlertFromMessage(L('RequestFailed'), false);
                    }
                }
            });
        });
    }

    return { render: render };
})();

window.SRAccountProfileApiKey = (function () {
    'use strict';

    function promptForMfaCodeIfNeeded(data) {
        // A minimal inline prompt -- reuses the same #profile-mfa-code-modal
        // markup the page shell defines once (Task 12), never a bare
        // window.prompt(), so styling and localization stay consistent.
        if (!data.mfa_enabled) {
            return $.Deferred().resolve(null).promise();
        }
        var deferred = $.Deferred();
        var $modal = $('#profile-mfa-code-modal');
        $modal.find('input').val('');
        $modal.modal('show');
        $modal.find('.sr-qsave').off('click').on('click', function () {
            deferred.resolve($modal.find('input').val());
            $modal.modal('hide');
        });
        $modal.find('.sr-qcancel').off('click').on('click', function () {
            deferred.reject();
            $modal.modal('hide');
        });
        return deferred.promise();
    }

    function callApiKeyEndpoint(method, data, mfaCode) {
        var payload = mfaCode ? { mfa_code: mfaCode } : {};
        return $.ajax({
            type: method,
            url: BASE_URL + '/api/v2/account/api-key',
            data: payload,
            dataType: 'json'
        });
    }

    function render(data) {
        // Defensive reset: render() must never leave (or re-display) a raw key
        // that isn't the direct result of the generate success handler below.
        // Every render() call clears/hides the reveal element up front; the
        // generate success handler below re-renders FIRST (to update the
        // button label / revoke visibility for the new api_key_exists state)
        // and only populates the reveal element AFTER that render() call
        // returns, so the freshly-revealed key is never wiped by this reset.
        $('#profile-api-key-reveal').addClass('d-none').text('');

        $('#profile-api-key-card').toggle(!!data.api_extra_active);
        if (!data.api_extra_active) { return; }

        $('#profile-api-key-generate-btn').text(data.api_key_exists ? L('RotateAPIKey') : L('GenerateAPIKey'));
        $('#profile-api-key-revoke-btn').toggle(!!data.api_key_exists);

        $('#profile-api-key-generate-btn').off('click').on('click', function () {
            promptForMfaCodeIfNeeded(data).then(function (mfaCode) {
                callApiKeyEndpoint('POST', data, mfaCode).done(function (response) {
                    window.SRAccountProfile.getBundle().api_key_exists = true;
                    render(window.SRAccountProfile.getBundle());
                    $('#profile-api-key-reveal').text(response.data.api_key).removeClass('d-none');
                    showAlertFromMessage(L('APIKey'), true);
                }).fail(function (xhr) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        } else {
                            showAlertFromMessage(L('RequestFailed'), false);
                        }
                    }
                });
            });
        });

        $('#profile-api-key-revoke-btn').off('click').on('click', function () {
            promptForMfaCodeIfNeeded(data).then(function (mfaCode) {
                callApiKeyEndpoint('DELETE', data, mfaCode).done(function () {
                    $('#profile-api-key-reveal').addClass('d-none').text('');
                    showAlertFromMessage(L('InvalidateAPIKey'), true);
                    window.SRAccountProfile.getBundle().api_key_exists = false;
                    render(window.SRAccountProfile.getBundle());
                }).fail(function (xhr) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        } else {
                            showAlertFromMessage(L('RequestFailed'), false);
                        }
                    }
                });
            });
        });
    }

    return { render: render };
})();
