// ====================================================================
// Shared factory behind the Document Program / Define Exceptions audit
// trails (design-system.md §6/§7) -- a collapsible .sr-table-card below a
// grid, backed by a structured GET .../audit_log endpoint
// (get_documents_audit_log_api() / get_exceptions_audit_log_api() in
// includes/api.php) with Timestamp/<Entity>/Activity columns, an
// entity/activity/user filter row, and the DataTables shell's usual
// relocated search box + sort-icon swap.
//
// governance-document-audit-trail.js and governance-exception-audit-trail.js
// were previously near-verbatim duplicates of this whole module, differing
// only in id prefix, the entity field name (document_id/document_name vs
// exception_id/exception_name), the API path, the activity-pill map (a
// strict subset for exceptions -- no delete_version/download/upload), and a
// couple of lang keys. createAuditTrail(options) takes all of that as
// config; each page file is now a thin `var XAuditTrail = createAuditTrail({...})`.
// ====================================================================
function createAuditTrail(options) {
    return (function ($) {
        'use strict';

        var idPrefix = options.idPrefix;
        var entityKey = options.entityKey;
        var entityIdField = entityKey + '_id';
        var entityNameField = entityKey + '_name';
        var apiPath = options.apiPath;
        var allEntitiesLabelKey = options.allEntitiesLabelKey;
        var entityColumnLabelKey = options.entityColumnLabelKey;
        var activityMeta = options.activityMeta;
        // Opt-in: renders `row.detail` (when present) as a second line under
        // the Activity cell. Off by default -- Document Program/Define
        // Exceptions' own messages are one-line sentences with nothing
        // beyond what the activity pill + actor already say, so showing an
        // empty/redundant second line for every row would be new visual
        // noise those two pages never asked for. Risk view's audit trail
        // (risk-audit-trail.js) turns this on: several of its messages
        // (Risk/Mitigation details updated) carry a real field-by-field
        // diff the OLD get_audit_trail_html() dump showed and a bare
        // activity pill alone would otherwise silently drop.
        var showMessageDetail = !!options.showMessageDetail;
        // Opt-in: relocates the search box beside the filter selects
        // (.sr-qf-selects, as that row's own last flex item) instead of the
        // card's separate inner toolbar row. Document Program/Define
        // Exceptions' inner toolbar also holds their Export button, so
        // search reads as "the other tool up there" on its own row; risk-
        // audit-trail.js has no Export button, and a lone right-aligned
        // search control on an otherwise-empty row read as a mistake, not a
        // deliberate two-row layout -- placing it after the filters lets it
        // wrap onto its own line, left-aligned like the filters above it,
        // when the row runs out of width, rather than staying pinned right.
        var searchBesideFilters = !!options.searchBesideFilters;

        function id(suffix) {
            return '#' + idPrefix + '-' + suffix;
        }

        // The card's own root element is id="<idPrefix>" with no suffix --
        // every other id in this module is "<idPrefix>-<suffix>".
        function root() {
            return '#' + idPrefix;
        }

        var dt = null;
        var expanded = false;
        var loaded = false;

        // The full, unfiltered row set from the last fetch for the currently
        // selected date range. Entity/User filters narrow this client-side,
        // matching DocumentProgramGrid's own "rebuild fresh on every filter
        // change" shape (js/simplerisk/pages/governance-documents.js) --
        // free-text search stays DataTables' own relocated search box instead.
        var allRows = [];

        // "All <entities>"/"All users" real-option multi-select state (mirrors
        // governance-documents.js's normalizeMultiValue()/previousTypeValue
        // exactly -- see that file for the full explanation of why this has to
        // be change-event-based rather than intercepting the option click:
        // sr-select.js's own click handler detaches the clicked option node
        // before a delegated handler could ever see it).
        var previousEntityValue = [''];
        var previousActivityValue = [''];
        var previousUserValue = [''];

        function esc(value) {
            return escapeHtml(value === undefined || value === null ? '' : String(value));
        }

        function decorateOptionCounts($select, counts) {
            $select.find('option').each(function () {
                var value = $(this).attr('value');
                if (value === '') {
                    return;
                }
                var count = counts[value] || 0;
                $(this).attr('data-count', count);
            });
            if (window.srSelectRender) {
                window.srSelectRender($select);
            }
        }

        // Real "All ..." option mutual-exclusion: picking "All" clears every
        // other checked value; picking a specific value drops "All"; nothing
        // checked falls back to ['']. See governance-documents.js's identical
        // helper for the sr-select.js DOM-detach gotcha this works around.
        function normalizeMultiValue($select, previous) {
            var current = $select.val() || [];
            var hadAll = previous.indexOf('') !== -1;
            var hasAll = current.indexOf('') !== -1;
            var result;
            if (hasAll && !hadAll) {
                result = [''];
            } else if (current.length > 1 && hasAll) {
                result = current.filter(function (v) { return v !== ''; });
            } else if (current.length === 0) {
                result = [''];
            } else {
                result = current;
            }
            $select.val(result);
            if (window.srSelectRender) {
                window.srSelectRender($select);
            }
            previous.length = 0;
            Array.prototype.push.apply(previous, result);
            return result;
        }

        function currentFilters() {
            var entities = normalizeMultiValue($(id('entity-filter')), previousEntityValue).filter(function (v) { return v !== ''; });
            var activities = normalizeMultiValue($(id('activity-filter')), previousActivityValue).filter(function (v) { return v !== ''; });
            var users = normalizeMultiValue($(id('user-filter')), previousUserValue).filter(function (v) { return v !== ''; });
            return { entities: entities, activities: activities, users: users };
        }

        function matchesFilters(row, filters) {
            if (filters.entities.length && filters.entities.indexOf(String(row[entityIdField])) === -1) {
                return false;
            }
            if (filters.activities.length && filters.activities.indexOf(row.activity) === -1) {
                return false;
            }
            if (filters.users.length && filters.users.indexOf(String(row.user_id)) === -1) {
                return false;
            }
            return true;
        }

        function renderFilterOptions() {
            var entityCounts = {};
            var entityLabels = {};
            var activityCounts = {};
            var userCounts = {};
            var userLabels = {};

            allRows.forEach(function (row) {
                if (row[entityIdField] && row[entityNameField]) {
                    entityCounts[row[entityIdField]] = (entityCounts[row[entityIdField]] || 0) + 1;
                    entityLabels[row[entityIdField]] = row[entityNameField];
                }
                if (row.activity) {
                    activityCounts[row.activity] = (activityCounts[row.activity] || 0) + 1;
                }
                if (row.user_id && row.user_name) {
                    userCounts[row.user_id] = (userCounts[row.user_id] || 0) + 1;
                    userLabels[row.user_id] = row.user_name;
                }
            });

            var $entitySelect = $(id('entity-filter'));
            var entityHtml = '<option value="">' + esc(L(allEntitiesLabelKey)) + '</option>';
            Object.keys(entityLabels).sort(function (a, b) {
                return entityLabels[a].localeCompare(entityLabels[b]);
            }).forEach(function (rowId) {
                entityHtml += '<option value="' + esc(rowId) + '">' + esc(entityLabels[rowId]) + '</option>';
            });
            $entitySelect.html(entityHtml);
            $entitySelect.val(previousEntityValue.length ? previousEntityValue : ['']);
            decorateOptionCounts($entitySelect, entityCounts);

            // Fixed, ordered set (activityMeta's own key order) with zero-count
            // entries dropped -- same "don't offer a filter value nothing
            // matches" rule DocumentProgramGrid's Status/Review filters use
            // (governance-documents.js), rather than every option always
            // present with a "0" chip.
            var $activitySelect = $(id('activity-filter'));
            var activityHtml = '<option value="">' + esc(L('AllActivities')) + '</option>';
            Object.keys(activityMeta).forEach(function (key) {
                if (key === 'other' || !activityCounts[key]) {
                    return;
                }
                activityHtml += '<option value="' + esc(key) + '">' + esc(L(activityMeta[key].labelKey)) + '</option>';
            });
            $activitySelect.html(activityHtml);
            $activitySelect.val(previousActivityValue.length ? previousActivityValue : ['']);
            decorateOptionCounts($activitySelect, activityCounts);

            var $userSelect = $(id('user-filter'));
            var userHtml = '<option value="">' + esc(L('AllUsers')) + '</option>';
            Object.keys(userLabels).sort(function (a, b) {
                return userLabels[a].localeCompare(userLabels[b]);
            }).forEach(function (rowId) {
                userHtml += '<option value="' + esc(rowId) + '">' + esc(userLabels[rowId]) + '</option>';
            });
            $userSelect.html(userHtml);
            $userSelect.val(previousUserValue.length ? previousUserValue : ['']);
            decorateOptionCounts($userSelect, userCounts);
        }

        function rowHtml(row) {
            var meta = activityMeta[row.activity] || activityMeta.other;
            var userLabel = row.user_name ? esc(row.user_name) : esc(L('UnknownUser'));
            var entityCellHtml = '';
            if (entityKey) {
                var entityCell = row[entityNameField]
                    ? esc(row[entityNameField])
                    : '<span class="sr-cell-dash">&mdash;</span>';
                entityCellHtml = '<td>' + entityCell + '</td>';
            }

            var detailHtml = (showMessageDetail && row.detail) ? '<div class="sr-qhint sr-audit-detail">' + esc(row.detail) + '</div>' : '';

            // data-order pins DataTables' sort to the RAW, lexically-sortable
            // 'YYYY-MM-DD HH:MM:SS' value (row.timestamp -- the same column
            // get_audit_trail()'s own ORDER BY timestamp DESC, includes/
            // functions.php, already sorts by server-side) instead of letting
            // it fall back to this cell's own text content. Without it,
            // DataTables sorts column 0 by row.timestamp_display -- a locale-
            // formatted string built with PHP's unpadded 'g' hour ("3:39 PM",
            // not "03:39 PM") -- so a STRING comparison reads any single-
            // digit-hour time as less than every double-digit-hour time
            // regardless of which is actually later: "3:39 PM" sorts before
            // "10:15 AM" because '3' < '1' is false but the comparison never
            // gets that far -- it's '3' vs '1' at the very first differing
            // character, and '1' < '3'. Confirmed live: a risk's audit trail
            // showed a 9:xx AM entry above a 3:xx PM entry from the same
            // later day. Shared by every createAuditTrail() page (Document
            // Program, Define Exceptions, Risk view), so this one fix covers
            // all three.
            return '<tr>' +
                '<td class="sr-audit-timestamp" data-order="' + esc(row.timestamp) + '">' + esc(row.timestamp_display) + '</td>' +
                entityCellHtml +
                '<td><span class="sr-state-pill ' + meta.pillClass + '">' + esc(L(meta.labelKey)) + '</span> ' + esc(L('By')) + ' <strong>' + userLabel + '</strong>' + detailHtml + '</td>' +
            '</tr>';
        }

        // entityKey is optional -- a page scoped to a single record already
        // (management/view.php's own Audit Trail, risk-audit-trail.js) has
        // no need for an Entity column repeating that one record's name on
        // every row the way a list-page audit trail (Define Exceptions/
        // Document Program, spanning many records) does. Every OTHER
        // entity-driven code path in this module (currentFilters(),
        // matchesFilters(), renderFilterOptions()) already degrades safely
        // on its own when entityKey's DOM elements are simply absent from
        // the page's markup -- see this file's own header comment -- so
        // only the two HTML-generating functions need an explicit guard.
        function theadHtml() {
            return '<tr>' +
                '<th>' + esc(L('AuditTrailDateAndTime')) + '</th>' +
                (entityKey ? '<th>' + esc(L(entityColumnLabelKey)) + '</th>' : '') +
                '<th>' + esc(L('Activity')) + '</th>' +
            '</tr>';
        }

        // Moves DataTables' own generated search box into the card's INNER
        // toolbar (inside the collapsed region, beside Export) -- not the outer
        // always-visible toolbar, which holds only the toggle/title/Refresh.
        // Same relocation DocumentProgramGrid's relocateSearchIntoTools() (and
        // self-assessment.js's) perform, keeping the node's own event bindings.
        //
        // renderBody() (below) replaces #<idPrefix>-body's WHOLE innerHTML on
        // every fetch/filter change, tearing down that DataTable and building
        // a fresh one with its OWN freshly generated search box -- but the
        // PREVIOUSLY relocated search box no longer lives inside #body (this
        // function already moved it out, into $tools), so it was never torn
        // down with the rest of the old table and silently stayed put. Each
        // subsequent call then relocated a second box in alongside it,
        // accumulating one stale, unbound search input per refetch -- the
        // visible one after a couple of filter changes was often a dead
        // leftover wired to an already-destroyed DataTable instance. Removing
        // any previously relocated box first keeps exactly one live.
        function relocateSearchIntoTools() {
            var $filter = $(id('body') + ' .dt-search, ' + id('body') + ' .dataTables_filter').first();
            var $tools = searchBesideFilters ? $(id('filters')) : $(id('inner-toolbar') + ' .sr-table-tools');
            if ($filter.length && $tools.length) {
                $tools.find('.dt-search, .dataTables_filter').remove();
                $filter.find('input[type="search"]').attr('placeholder', L('SearchAuditTrailPlaceholder')).attr('aria-label', L('SearchAuditTrailPlaceholder'));
                if (searchBesideFilters) {
                    $tools.append($filter);
                } else {
                    $tools.prepend($filter);
                }
            }
        }

        // Same FontAwesome sort-icon swap DocumentProgramGrid's syncSortIcons()
        // performs (design-system.md §6) -- suppresses DataTables' own bundled
        // caret pair in favor of fa-sort/fa-arrow-up-short-wide/
        // fa-arrow-down-wide-short on a real <i>, keyed off the class this same
        // function adds (sr-sort-native-off), not an ID-scoped ancestor
        // selector (this table has no scrollX header clone to worry about).
        function syncSortIcons() {
            $(id('table') + ' thead th[data-dt-column]').each(function () {
                var $th = $(this);
                if (!$th.is('.dt-orderable-asc, .dt-orderable-desc')) { return; }
                var $order = $th.find('.dt-column-order').addClass('sr-sort-native-off');
                if (!$order.length) { return; }
                $th.addClass('sr-sortable');
                var $icon = $order.find('.sr-sort-icon');
                if (!$icon.length) {
                    $icon = $('<i>', { 'class': 'fa sr-sort-icon', 'aria-hidden': 'true' }).appendTo($order);
                }
                var sort = $th.attr('aria-sort');
                $th.toggleClass('is-sorted', sort === 'ascending' || sort === 'descending');
                $icon
                    .removeClass('fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort')
                    .addClass(sort === 'ascending' ? 'fa-arrow-up-short-wide' : sort === 'descending' ? 'fa-arrow-down-wide-short' : 'fa-sort');
            });
        }

        function showEmptyState(kind) {
            $(id('empty-nodata') + ', ' + id('empty-noresults')).addClass('d-none');
            if (kind) {
                $(id('empty-' + kind)).removeClass('d-none');
            }
        }

        function renderBody(rows) {
            var html =
                '<div class="sr-table-scroll">' +
                    '<table class="sr-table" id="' + idPrefix + '-table">' +
                        '<thead>' + theadHtml() + '</thead>' +
                        '<tbody>' + rows.map(rowHtml).join('') + '</tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="sr-table-empty d-none" id="' + idPrefix + '-empty-nodata">' +
                    '<div class="sr-table-empty-icon"><i class="fa fa-clock-rotate-left" aria-hidden="true"></i></div>' +
                    '<div class="sr-table-empty-title">' + esc(L('NoAuditLogEntries')) + '</div>' +
                    '<div class="sr-table-empty-body">' + esc(L('NoAuditLogEntriesBody')) + '</div>' +
                '</div>' +
                '<div class="sr-table-empty d-none" id="' + idPrefix + '-empty-noresults">' +
                    '<div class="sr-table-empty-icon"><i class="fa fa-search" aria-hidden="true"></i></div>' +
                    '<div class="sr-table-empty-title">' + esc(L('NoAuditLogEntriesMatchFilters')) + '</div>' +
                    '<div class="sr-table-empty-body">' + esc(L('NoAuditLogEntriesMatchFiltersBody')) + '</div>' +
                    '<div class="sr-table-empty-action"><button type="button" class="btn btn-outline-secondary btn-sm" id="' + idPrefix + '-clear-search">' + esc(L('ClearFilters')) + '</button></div>' +
                '</div>';

            $(id('body')).html(html);

            var $count = $(id('count'));
            if (allRows.length > 0) {
                $count.text(rows.length).removeClass('d-none');
            } else {
                $count.addClass('d-none');
            }

            dt = null;
            if (!rows.length) {
                showEmptyState(allRows.length ? 'noresults' : 'nodata');
                return;
            }
            showEmptyState(null);

            if (!$.fn.DataTable) {
                return;
            }
            var tableEl = document.getElementById(idPrefix + '-table');
            dt = $(tableEl).DataTable({
                serverSide: false,
                processing: false,
                dom: 'rt<"sr-table-foot"<"sr-table-foot-left"li><"sr-table-foot-right"p>>f',
                pagingType: 'simple_numbers',
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, L('All')]],
                // Matches DocumentProgramGrid's identical override
                // (governance-documents.js) -- without it DataTables falls back
                // to its own bundled "_MENU_ entries per page" template with the
                // <select> and <label> in the opposite order, which is what made
                // this table's page-size control look different from the grid's.
                language: {
                    lengthMenu: L('Show') + ' _MENU_'
                },
                order: [[0, 'desc']]
            });
            relocateSearchIntoTools();
            syncSortIcons();
            dt.on('draw', syncSortIcons);
        }

        function applyFiltersAndRender() {
            var filters = currentFilters();
            var rows = allRows.filter(function (row) { return matchesFilters(row, filters); });
            renderFilterOptions();
            renderBody(rows);
        }

        function fetchAndRender() {
            var days = $(id('range-filter')).val();
            $.ajax({
                type: 'GET',
                url: apiPath,
                data: { days: days },
                async: true,
                cache: false,
                success: function (data) {
                    allRows = data.data || [];
                    loaded = true;
                    applyFiltersAndRender();
                },
                error: function (xhr) {
                    if (!retryCSRF(xhr, this)) {
                        if (xhr.responseJSON && xhr.responseJSON.status_message) {
                            showAlertsFromArray(xhr.responseJSON.status_message);
                        } else {
                            showAlertsFromArray([{ type: 'bad', text: L('RequestFailed') }]);
                        }
                    }
                }
            });
        }

        function setExpanded(next) {
            expanded = next;
            $(id('toggle')).attr('aria-expanded', expanded ? 'true' : 'false');
            $(id('collapse')).toggleClass('d-none', !expanded);
            if (expanded && !loaded) {
                fetchAndRender();
            }
        }

        // Namespaced so the two $(document)-delegated bindings below can be
        // safely torn down before re-registering (see init()'s own comment) --
        // scoped to idPrefix so two different createAuditTrail instances on
        // the same page (unlikely on this view, real on a page embedding more
        // than one) never touch each other's handlers.
        var eventNamespace = '.' + idPrefix + '-audit-trail';

        function init() {
            // init() is called more than once on this same, persistent
            // closure -- once at initial page load (management/view.php's
            // $(document).ready), and again after every AJAX-driven full-tab
            // refresh (Close Risk/Reopen/Change Status all replace management/
            // partials/viewhtml.php's entire markup, including this card, via
            // getTabHtml()'s $isAjax-gated re-init script -- the exact
            // mechanism management/partials/details.php's own Cards mount
            // already uses). Reset the transient widget state to match the
            // FRESH markup's own server-rendered default (collapsed, nothing
            // fetched yet) -- without this, a widget left expanded before a
            // swap kept `expanded`/`loaded` = true internally even though the
            // new DOM came back collapsed, so the first click after a swap
            // called setExpanded(false) (a no-op) instead of opening it.
            // Matches fetchAndRender()'s own existing convention below
            // (`dt = null;` with no explicit .destroy()) -- the table's
            // markup is always fully replaced (fresh <table> element) rather
            // than reused, so DataTables never operates on stale nodes
            // either way.
            dt = null;
            expanded = false;
            loaded = false;
            allRows = [];
            previousEntityValue = [''];
            previousActivityValue = [''];
            previousUserValue = [''];

            // The two $(document)-delegated bindings below accumulate across
            // repeated init() calls (jQuery's event delegation doesn't
            // deduplicate identical selector/event/namespace registrations
            // the way a direct binding harmlessly orphans with its old,
            // destroyed node) -- without this, a second Close Risk/Reopen/
            // Change Status in the same page session double-fires
            // applyFiltersAndRender()/the filters-clear proxy, a third
            // triple-fires it, and so on.
            $(document).off(eventNamespace);

            $(id('toggle')).on('click', function () {
                setExpanded(!expanded);
            });

            $(id('refresh')).on('click', function () {
                if (!expanded) {
                    setExpanded(true);
                } else {
                    fetchAndRender();
                }
            });

            $(id('range-filter')).on('change', fetchAndRender);

            // Narrow-width filter sheet (design-system.md §6b) -- same
            // pattern the main grids' own #<x>-filters-toggle uses
            // (governance-exceptions.js/governance-documents.js), generic
            // here so every page using this factory gets it for free rather
            // than each page wiring an identical handler itself. A harmless
            // no-op if a page's markup has no #<idPrefix>-filters-toggle
            // (jQuery's .on() over an empty selection registers nothing).
            $(id('filters-toggle')).on('click', function () {
                var $toggle = $(this);
                var open = $(id('quickfilters')).toggleClass('is-open').hasClass('is-open');
                $toggle
                    .attr('aria-expanded', open ? 'true' : 'false')
                    .attr('title', open ? L('HideFilters') : L('ShowFilters'));
            });

            $(document).on('change', id('entity-filter') + ', ' + id('activity-filter') + ', ' + id('user-filter'), applyFiltersAndRender);

            $(id('filters-clear')).on('click', function () {
                previousEntityValue = [''];
                previousActivityValue = [''];
                previousUserValue = [''];
                $(id('entity-filter')).val(['']);
                $(id('activity-filter')).val(['']);
                $(id('user-filter')).val(['']);
                if (window.srSelectRender) {
                    window.srSelectRender($(id('entity-filter')));
                    window.srSelectRender($(id('activity-filter')));
                    window.srSelectRender($(id('user-filter')));
                }
                applyFiltersAndRender();
            });

            $(document).on('click', id('clear-search'), function () {
                $(id('filters-clear')).trigger('click');
            });

            // Proxies a click onto display_audit_download_btn()'s own hidden
            // trigger (see the markup comment in the page's own PHP) -- the
            // real submission logic (CSRF token, days field, form target) is
            // untouched; only the visible, clickable element differs.
            $(id('export')).on('click', function () {
                $(root() + ' .audit-download-folder .download-btn').trigger('click');
            });

            if (window.srSelectEnhance) {
                window.srSelectEnhance($(id('range-filter')));
                window.srSelectEnhance($(id('entity-filter')), L(allEntitiesLabelKey));
                window.srSelectEnhance($(id('activity-filter')), L('AllActivities'));
                window.srSelectEnhance($(id('user-filter')), L('AllUsers'));
            }
        }

        return { init: init };
    })(jQuery);
}
