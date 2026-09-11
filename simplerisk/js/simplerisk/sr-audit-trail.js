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
            var entityCell = row[entityNameField]
                ? esc(row[entityNameField])
                : '<span class="sr-cell-dash">&mdash;</span>';
            var userLabel = row.user_name ? esc(row.user_name) : esc(L('UnknownUser'));

            return '<tr>' +
                '<td class="sr-audit-timestamp">' + esc(row.timestamp_display) + '</td>' +
                '<td>' + entityCell + '</td>' +
                '<td><span class="sr-state-pill ' + meta.pillClass + '">' + esc(L(meta.labelKey)) + '</span> ' + esc(L('By')) + ' <strong>' + userLabel + '</strong></td>' +
            '</tr>';
        }

        function theadHtml() {
            return '<tr>' +
                '<th>' + esc(L('AuditTrailDateAndTime')) + '</th>' +
                '<th>' + esc(L(entityColumnLabelKey)) + '</th>' +
                '<th>' + esc(L('Activity')) + '</th>' +
            '</tr>';
        }

        // Moves DataTables' own generated search box into the card's INNER
        // toolbar (inside the collapsed region, beside Export) -- not the outer
        // always-visible toolbar, which holds only the toggle/title/Refresh.
        // Same relocation DocumentProgramGrid's relocateSearchIntoTools() (and
        // self-assessment.js's) perform, keeping the node's own event bindings.
        function relocateSearchIntoTools() {
            var $filter = $(id('body') + ' .dt-search, ' + id('body') + ' .dataTables_filter').first();
            var $tools = $(id('inner-toolbar') + ' .sr-table-tools');
            if ($filter.length && $tools.length) {
                $filter.find('input[type="search"]').attr('placeholder', L('SearchAuditTrailPlaceholder')).attr('aria-label', L('SearchAuditTrailPlaceholder'));
                $tools.prepend($filter);
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

        function init() {
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
