/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Manage assets, background asset discovery (asset management redesign,
 * Phase A, Task 11). Loaded after manage-assets.js and built on its
 * window.ManageAssets (config, caps, lookups, util helpers, refresh(),
 * rememberOpener()/restoreFocus()).
 *
 *   Modal   #asset-discovery-modal: POST /api/v2/assets/discovery-runs
 *           {range, resolve_names, team_ids, tcp_ports?}. 422 codes from the
 *           server are shown on the field they belong to (DiscoveryRangeInvalid,
 *           DiscoveryRangeReserved, DiscoveryRangeTooLarge, DiscoveryTeamsInvalid,
 *           DiscoveryPortsInvalid). On open, GET .../discovery-runs/capabilities
 *           fills the probe-method line; when only TCP connects are available
 *           the dialog shows the TCP caution and the per-run ports field. It
 *           also lists the ranges the config.php allowlist permits, or, when
 *           none are configured, says so and disables Start (the server
 *           refuses anyway: DiscoveryNotConfigured / DiscoveryRangeNotAllowed).
 *   Runs    #asset-discovery-runs, under the Assets table: GET
 *           /assets/discovery-runs (newest 10), polled every POLL_MS only
 *           while a run is queued or running and the tab is visible. A run
 *           that finishes while the page watches raises a toast and refreshes
 *           the Assets table. Cancel: DELETE /assets/discovery-runs/{id}.
 *
 * Escaping: API values are raw text and go in with jQuery `text:` / .text().
 * Toast text is escaped once with util.esc() (server status messages are
 * escaped at their source).
 */
(function (window, document, $) {
    'use strict';

    var MA = window.ManageAssets;
    if (!MA) { return; }

    var U = MA.util;
    var cfg = MA.config || {};
    var caps = cfg.caps || {};
    var lookups = cfg.lookups || {};
    var POLL_MS = 4000;
    var PER_PAGE = 10;

    // Run status -> design-system §7 state family and label key.
    var FAMILY = { queued: 'neutral', running: 'info', completed: 'success', failed: 'danger', cancelled: 'neutral' };
    var LABEL = { queued: 'DiscoveryStatusQueued', running: 'UpgradeStateRunning', completed: 'Completed', failed: 'Failed', cancelled: 'Canceled' };

    function api(path) { return BASE_URL + '/api/v2' + path; }
    function isActive(run) { return run.status === 'queued' || run.status === 'running'; }
    function num(n) { return Number(n || 0).toLocaleString(); }

    var Discovery = {
        runs: [],
        total: 0,
        seen: {},           // run id -> status last rendered (for finish toasts)
        loaded: false,
        timer: null,
        seq: 0,
        xhr: null,
        busy: false,
        cancelling: {},

        init: function () {
            var self = this;
            if (!cfg.discoveryEnabled || !caps.can_discovery) { return; }

            var $modal = $('#asset-discovery-modal');
            $modal.on('shown.bs.modal', function () { $('#asset-discovery-range').trigger('focus'); });
            $modal.on('hidden.bs.modal', function () { MA.restoreFocus(); });
            $('#asset-discovery-form').on('submit', function (e) { e.preventDefault(); self.start(); });
            $('#asset-discovery-range').on('input', function () { self.setError('range', null); });
            $('#asset-discovery-teams').on('change', function () { self.setError('teams', null); });
            $('#asset-discovery-ports').on('input', function () { self.setError('ports', null); });
            this.fillTeams();

            $('#asset-discovery-runs-toggle').on('click', function () {
                self.expand($(this).attr('aria-expanded') !== 'true');
            });
            $('#asset-discovery-runs-table').on('click', '[data-run-cancel]', function () {
                self.cancel(String($(this).attr('data-run-cancel')), $(this));
            });
            $(document).on('click', '#asset-discovery-runs-retry', function () { self.load(); });

            // Poll only while someone can see it.
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    self.stopPolling();
                } else if (self.hasActive()) {
                    self.load();
                }
            });

            this.load();
        },

        fillTeams: function () {
            var $sel = $('#asset-discovery-teams');
            $sel.empty();
            (lookups.team_options || []).forEach(function (t) { $('<option>', { value: t.id, text: t.name }).appendTo($sel); });
            if (typeof srSelectEnhance === 'function') {
                if ($sel.data('srSelect')) { srSelectRender($sel); } else { srSelectEnhance($sel, $sel.data('placeholder')); }
            }
        },

        /* ---------------- modal ---------------- */
        open: function () {
            if (!cfg.discoveryEnabled) { return; }
            MA.rememberOpener();
            $('#asset-discovery-range').val('');
            $('#asset-discovery-resolve').prop('checked', true);
            var $sel = $('#asset-discovery-teams').val([]);
            if ($sel.data('srSelect')) { srSelectRender($sel); }
            $('#asset-discovery-ports').val('');
            this.setError('range', null);
            this.setError('teams', null);
            this.setError('ports', null);
            this.setBusy(false);
            this.loadProbe();
            $('#asset-discovery-modal').modal('show');
        },

        /* Probe method line, TCP caution and the per-run ports field. */
        probe: null,
        probeXhr: null,

        loadProbe: function () {
            var self = this;
            if (this.probeXhr) { this.probeXhr.abort(); }
            this.probeXhr = $.ajax({ url: api('/assets/discovery-runs/capabilities'), type: 'GET', dataType: 'json' })
                .done(function (json) { self.renderProbe((json && json.data) || null); })
                .fail(function (xhr) {
                    // Informational only: without it the dialog still works.
                    if (!U.isAbort(xhr)) { self.renderProbe(null); }
                })
                .always(function () { self.probeXhr = null; });
        },

        renderProbe: function (caps) {
            this.probe = caps;
            // Allowlist: unknown (request failed) leaves Start alone.
            this.blocked = !!(caps && caps.configured === false);
            $('#asset-discovery-not-configured').prop('hidden', !this.blocked);
            var ranges = (caps && caps.allowed_ranges) || [];
            $('#asset-discovery-allowed').prop('hidden', !ranges.length)
                .text(ranges.length ? U.fmt('DiscoveryAllowedRangesList', { ranges: ranges.join(', ') }) : '');
            this.setBusy(this.busy);
            var tcp = !!(caps && caps.method === 'tcp');
            $('#asset-discovery-probe').prop('hidden', !caps);
            $('#asset-discovery-probe-line').text(caps ? U.fmt('DiscoveryProbeMethod', { method: caps.method_label || caps.method }) : '');
            $('#asset-discovery-probe-source').prop('hidden', !caps || caps.source !== 'web');
            $('#asset-discovery-tcp-warning').prop('hidden', !tcp);
            $('#asset-discovery-ports-field').prop('hidden', !tcp);
            if (tcp) {
                $('#asset-discovery-ports').attr('placeholder', caps.tcp_ports || '');
                $('#asset-discovery-ports-hint').text(U.fmt('DiscoveryTcpPortsHint', { max: caps.tcp_ports_max, ports: caps.tcp_ports || '' }));
            }
        },

        setError: function (field, msg) {
            // field: range | teams | ports (the input ids follow the same names).
            var $input = $('#asset-discovery-' + field);
            $input.toggleClass('is-invalid', !!msg).attr('aria-invalid', msg ? 'true' : 'false');
            $('#asset-discovery-' + field + '-error').text(msg || '');
        },

        blocked: false,     // the allowlist is not configured: Start stays off

        setBusy: function (on) {
            this.busy = on;
            $('#asset-discovery-start').prop('disabled', on || this.blocked).toggleClass('is-busy', on);
        },

        start: function () {
            var self = this;
            if (this.busy || this.blocked) { return; }
            var range = String($('#asset-discovery-range').val() || '').trim();
            if (!range) {
                this.setError('range', L('DiscoveryRangeInvalid'));
                $('#asset-discovery-range').trigger('focus');
                return;
            }
            var body = {
                range: range,
                resolve_names: $('#asset-discovery-resolve').is(':checked'),
                team_ids: ($('#asset-discovery-teams').val() || []).map(Number)
            };
            // Per-run ports only when the field is offered (TCP probing).
            var ports = $('#asset-discovery-ports-field').prop('hidden') ? '' : String($('#asset-discovery-ports').val() || '').trim();
            if (ports) { body.tcp_ports = ports; }
            this.setBusy(true);
            $.ajax({
                url: api('/assets/discovery-runs'), type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(body),
                headers: U.csrfHeaders(), dataType: 'json',
                success: function (json) {
                    self.setBusy(false);
                    showAlertFromMessage(U.esc(L('DiscoveryRunQueued')), true);
                    $('#asset-discovery-modal').modal('hide');
                    var run = json && json.data && json.data.run;
                    if (run) {
                        self.seen[String(run.id)] = run.status;
                        self.runs = [run].concat(self.runs.filter(function (r) { return String(r.id) !== String(run.id); })).slice(0, PER_PAGE);
                        self.total += 1;
                    }
                    self.expand(true);
                    self.render();
                    self.schedule();
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    self.setBusy(false);
                    var data = (xhr && xhr.responseJSON && xhr.responseJSON.data) || {};
                    if (data.error === 'DiscoveryRangeInvalid' || data.error === 'DiscoveryRangeReserved'
                        || data.error === 'DiscoveryRangeNotAllowed' || data.error === 'DiscoveryNotConfigured') {
                        self.setError('range', L(data.error));
                        $('#asset-discovery-range').trigger('focus');
                    } else if (data.error === 'DiscoveryRangeTooLarge') {
                        self.setError('range', U.fmt('DiscoveryRangeTooLarge', { max: num(data.max) }));
                        $('#asset-discovery-range').trigger('focus');
                    } else if (data.error === 'DiscoveryTeamsInvalid') {
                        self.setError('teams', L('DiscoveryTeamsInvalid'));
                    } else if (data.error === 'DiscoveryPortsInvalid') {
                        self.setError('ports', U.fmt('DiscoveryPortsInvalid', { max: num(data.max) }));
                        $('#asset-discovery-ports').trigger('focus');
                    } else {
                        U.reportError(xhr);
                    }
                }
            });
        },

        /* ---------------- runs panel ---------------- */
        hasActive: function () { return this.runs.some(isActive); },

        expand: function (open) {
            $('#asset-discovery-runs-toggle').attr('aria-expanded', open ? 'true' : 'false');
            $('#asset-discovery-runs-body').prop('hidden', !open);
            $('#asset-discovery-runs .sr-assets-runs-caret').toggleClass('fa-chevron-right', !open).toggleClass('fa-chevron-down', open);
        },

        stopPolling: function () {
            clearTimeout(this.timer);
            this.timer = null;
        },

        schedule: function () {
            var self = this;
            this.stopPolling();
            if (this.hasActive() && !document.hidden) {
                this.timer = setTimeout(function () { self.load(); }, POLL_MS);
            }
        },

        load: function () {
            var self = this;
            var seq = ++this.seq;
            this.stopPolling();
            if (this.xhr) { this.xhr.abort(); }
            this.xhr = $.ajax({ url: api('/assets/discovery-runs'), type: 'GET', data: { per_page: PER_PAGE }, dataType: 'json' })
                .done(function (json) {
                    if (seq !== self.seq) { return; }
                    var data = (json && json.data) || {};
                    self.apply(data.runs || [], Number(data.total || 0));
                })
                .fail(function (xhr) {
                    if (seq !== self.seq || U.isAbort(xhr)) { return; }
                    // Keep what is shown; a panel with runs says it could not refresh.
                    if (self.runs.length) { self.renderError(); }
                })
                .always(function () {
                    if (seq !== self.seq) { return; }
                    self.xhr = null;
                    self.schedule();
                });
        },

        apply: function (runs, total) {
            var self = this;
            var first = !this.loaded;
            var finished = false;
            runs.forEach(function (run) {
                var id = String(run.id);
                var before = self.seen[id];
                // Toast only runs this page watched go from active to done
                // (not every finished run on the first load).
                if (!first && (before === 'queued' || before === 'running')) {
                    if (run.status === 'completed') {
                        showAlertFromMessage(U.esc(U.fmt('DiscoveryRunCompleted', { 'new': num(run.new_assets) })), true);
                        finished = true;
                    } else if (run.status === 'failed') {
                        showAlertFromMessage(U.esc(U.fmt('DiscoveryRunFailedToast', { range: run.range })), false);
                        finished = true;
                    } else if (run.status === 'cancelled') {
                        finished = true;
                    }
                }
                self.seen[id] = run.status;
            });
            this.runs = runs;
            this.total = total;
            this.loaded = true;
            if (first) { this.expand(this.hasActive()); }
            this.render();
            // New assets appeared: refresh the table and its segment counts.
            if (finished) { MA.refresh(false); }
        },

        render: function () {
            var self = this;
            var $section = $('#asset-discovery-runs');
            $section.toggleClass('d-none', this.runs.length === 0);
            $('#asset-discovery-runs-count').text(this.total ? num(this.total) : '');
            var $body = $('#asset-discovery-runs-table tbody').empty();
            this.runs.forEach(function (run) { $body.append(self.row(run)); });
        },

        renderError: function () {
            var $body = $('#asset-discovery-runs-table tbody');
            $body.find('.sr-assets-runs-error').remove();
            $body.prepend($('<tr>', { 'class': 'sr-assets-runs-error' }).append(
                $('<td>', { colspan: 7 }).append(
                    $('<span>', { text: L('CouldNotLoadDiscoveryRuns') + ' ' }),
                    $('<button>', { type: 'button', 'class': 'btn btn-link btn-sm', id: 'asset-discovery-runs-retry', text: L('Retry') })
                )
            ));
        },

        pill: function (run) {
            var $pill = $('<span>', {
                'class': 'sr-state-pill sr-state-' + (FAMILY[run.status] || 'neutral'),
                text: L(LABEL[run.status] || 'DiscoveryStatusQueued')
            });
            if (run.status === 'failed' && run.error) { $pill.attr('title', run.error); }
            var $cell = $('<span>', { 'class': 'sr-assets-runs-state' }).append($pill);
            if (run.status === 'running') {
                $cell.append($('<span>', {
                    'class': 'sr-assets-runs-progress',
                    text: U.fmt('DiscoveryProgress', { scanned: num(run.hosts_scanned), total: num(run.total_hosts) })
                }));
            }
            return $cell;
        },

        row: function (run) {
            var dashIfQueued = function (n) { return run.status === 'queued' ? U.dash() : document.createTextNode(num(n)); };
            var mayCancel = isActive(run) && (cfg.discoveryCancelAny || Number(run.created_by) === Number(cfg.discoveryUid));
            var $actions = $('<td>', { 'class': 'sr-actions-col' });
            if (mayCancel) {
                $actions.append($('<button>', {
                    type: 'button', 'class': 'btn btn-sm btn-outline-secondary sr-assets-runs-cancel',
                    'data-run-cancel': run.id, text: L('DiscoveryCancelRun'),
                    disabled: !!this.cancelling[String(run.id)]
                }));
            }
            // The range, with the probe method the worker used underneath.
            var $range = $('<td>', { 'class': 'sr-assets-runs-range' }).append($('<span>', { text: run.range }));
            if (run.probe_method_label) {
                $range.append($('<span>', { 'class': 'd-block small text-muted sr-assets-runs-method', text: run.probe_method_label }));
            }
            return $('<tr>', { 'data-run-id': run.id }).append(
                $range,
                $('<td>').append(this.pill(run)),
                $('<td>', { 'class': 'sr-assets-runs-num' }).append(dashIfQueued(run.live_hosts)),
                $('<td>', { 'class': 'sr-assets-runs-num' }).append(dashIfQueued(run.new_assets)),
                $('<td>', { 'class': 'sr-assets-runs-opt' }).append(run.started_display ? document.createTextNode(run.started_display) : U.dash()),
                $('<td>', { 'class': 'sr-assets-runs-opt', text: run.created_by_name || '' }),
                $actions
            );
        },

        cancel: function (id, $btn) {
            var self = this;
            if (this.cancelling[id]) { return; }
            this.cancelling[id] = true;
            $btn.prop('disabled', true).addClass('is-busy');
            var done = function () { delete self.cancelling[id]; };
            $.ajax({
                url: api('/assets/discovery-runs/' + encodeURIComponent(id)), type: 'DELETE',
                headers: U.csrfHeaders(), dataType: 'json',
                success: function (json) {
                    done();
                    var msg = json && json.status_message;
                    showAlertFromMessage(typeof msg === 'string' && msg ? msg : U.esc(L('DiscoveryRunCancelled')), true);
                    self.load();
                },
                error: function (xhr) {
                    if (U.reissuedByCsrfRetry(xhr, this)) { return; }
                    done();
                    // 409: it finished meanwhile -- say so, then show the real state.
                    U.reportError(xhr);
                    self.load();
                }
            });
        }
    };

    MA.openDiscovery = function () { Discovery.open(); };
    MA.discovery = Discovery;

    $(function () {
        if ($('#manage-assets-card').length && $('#asset-discovery-modal').length) { Discovery.init(); }
    });
})(window, document, jQuery);
