/* Initiate Audits page — flat, filterable list (replaces the framework ->
 * control -> test treegrid). Fetches every eligible test from
 * /api/v2/compliance/audit_initiation/eligible_tests, renders rows
 * client-side, and layers a client-side DataTable (sort/search/paginate) on
 * top of the rendered rows -- same recipe as self-assessment.js's
 * initSrTable()/srSelectable(). "Initiate whole framework/control" is
 * reproduced by typing the framework/control name into the search box,
 * selecting all matching rows via the header checkbox, then "Initiate
 * selected" -- there is no separate framework/control filter control.
 *
 * All user-facing strings come from window._lang / L() (populated by
 * header.php's $localization_required_by_scripts entry for this file). Do
 * not hardcode English text in this file.
 */
(function () {
    "use strict";

    var root = document.getElementById("initiate-audits-app");
    if (!root) {
        return;
    }

    var TABLE_SEL = "#initiate_audits_table";
    var dt = null;
    var selected = new Set();
    var pendingInitiateIds = [];

    // ---- column visibility -- per-user, server-persisted via the shared
    // Columns picker (render_column_selection_widget('initiate_audits'),
    // includes/compliance.php). That widget's own inline <script> already
    // defines window.custom_display_settings_initiate_audits (the resolved
    // saved-or-default field-name array) and owns the modal's Save button
    // (a form POST to /api/v2/ui/column_settings, then a full page reload) --
    // this file only needs to map each toggleable COLUMN INDEX to the FIELD
    // NAME the picker keys visibility by, since DataTables' columnDefs work
    // in indices but the saved selection is a list of field names. ----
    var TOGGLEABLE_COLUMNS = [2, 3, 4, 5, 6, 7]; // Control / Framework / Schedule / Last Test Date / Next Test Date / Test Frequency
    var COLUMN_FIELD_NAMES = {
        2: "control_name",
        3: "framework_name",
        4: "schedule",
        5: "last_test_date",
        6: "next_test_date",
        7: "test_frequency",
    };

    // Narrow-width lockdown (design-system.md 6b): below this breakpoint the
    // Columns picker's saved choice is set aside entirely, same treatment as
    // Manage Audits (display_audits(), includes/compliance.php). An arbitrary
    // combination of Control/Framework/Schedule/dates/Frequency can't be
    // guaranteed to fit a phone-width card, so only Next Test Date -- the
    // field that answers "does it need me today" -- stays on; Test Name (not
    // in TOGGLEABLE_COLUMNS, always visible) and the checkbox/Initiate columns
    // are unaffected. The Columns button itself is hidden at the same width
    // (_compliance.scss) since customizing a selection that's about to be
    // overridden would be confusing.
    //
    // 1250px, not 760px (measured, not predicted -- moved here after the
    // original 760px value left the full 8-column set overflowing its card
    // by up to 231px in the 800-1200px band, hiding the Initiate button
    // entirely: even with the wide-tier width pins above, this table's two
    // comma-joined list columns (Control/Framework Name) plus Test Name need
    // more room than Manage Audits' single framework_name column did, so the
    // matching breakpoint (AUDITS_NARROW_ALLOW, display_audits(),
    // includes/compliance.php) needed there -- 1120px -- isn't enough here
    // either. A first attempt at 1150px still left a residual band (the full
    // set doesn't actually fit until ~1245px card-width-wise) with the
    // button up to 74px past the card edge -- moved to 1250px for a small
    // margin above the measured crossover.
    var INITIATE_NARROW_QUERY = window.matchMedia("(max-width: 1250px)");

    function getSavedColumnVisibility() {
        var savedFields = window.custom_display_settings_initiate_audits || [];
        var narrow = INITIATE_NARROW_QUERY.matches;
        var visibility = {};
        TOGGLEABLE_COLUMNS.forEach(function (idx) {
            visibility[idx] = narrow ? idx === 6 : savedFields.indexOf(COLUMN_FIELD_NAMES[idx]) !== -1;
        });
        return visibility;
    }

    // ---- tiny helpers (mirrors self-assessment.js's esc()/_n()) ----
    function esc(s) {
        var d = document.createElement("div");
        d.textContent = s === null || s === undefined ? "" : String(s);
        return d.innerHTML;
    }
    function _n(tpl, n) {
        return String(tpl).replace("{n}", n);
    }

    function scheduleChip(type) {
        var key =
            type === "interval"
                ? "ScheduleInterval"
                : type === "calendar"
                  ? "ScheduleCalendar"
                  : "ScheduleManual";
        var cls = type === "interval" || type === "calendar" ? type : "manual";
        return (
            '<span class="sr-schedule-chip ' + cls + '">' + esc(L(key)) + "</span>"
        );
    }

    function formatDate(d) {
        if (!d || d === "0000-00-00") return "";
        return d;
    }

    // Mirrors Manage Audits' own 'test_frequency' formatting (get_custom_
    // formatting_data_for_all_audits(), includes/functions.php): a bare
    // integer is a day count, not a self-explanatory value on its own.
    function formatTestFrequency(n) {
        n = parseInt(n, 10);
        if (!n) return "";
        return n + " " + (n > 1 ? L("days") : L("Day"));
    }

    // ---- fetch + render ----
    function fetchEligibleTests() {
        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/compliance/audit_initiation/eligible_tests",
            // The endpoint's response Content-Type isn't application/json, so
            // jQuery's dataType auto-detection guesses "text" -- resp then
            // arrives as the raw JSON string, resp.data is undefined, and
            // rendering silently no-ops. Force json explicitly.
            dataType: "json",
            data: {
                draw: 1,
                start: 0,
                length: -1,
                filter_text: "",
                filter_framework: [],
                filter_control: [],
                // Schedule is filtered CLIENT-side (like Framework/Control/
                // Tester/Team) now that its counts need to reflect every
                // schedule type regardless of which one is currently
                // selected -- an empty array here means "no restriction",
                // so every eligible test is fetched once.
                filter_schedule_type: [],
                show_in_progress: $("#initiate-show-in-progress").prop("checked") ? 1 : 0,
            },
            success: function (resp) {
                renderRows(resp.data || []);
            },
            error: function (xhr, status, error) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    } else {
                        showAlertFromMessage(L("RequestFailed"), false);
                    }
                }
            },
        });
    }

    function rowHtml(row) {
        // NULL schedule_type means "no automatic schedule ever configured" --
        // functionally the same as 'manual' (matches
        // get_initiate_eligible_tests()'s own NULL-as-manual WHERE clause,
        // includes/compliance.php), so the filter must normalize the same
        // way or a NULL-schedule test would never match "Manual".
        var scheduleType = row.schedule_type || "manual";
        var tr = $("<tr>")
            .attr("data-id", row.id)
            .attr("data-control-ids", row.control_ids || "")
            .attr("data-team-ids", row.team_ids || "")
            .attr("data-tester", row.tester || "")
            .attr("data-schedule", scheduleType);
        tr.append(
            $("<td>")
                .addClass("sr-check-col")
                .html(
                    '<input type="checkbox" class="form-check-input sr-row-check" data-id="' +
                        esc(row.id) +
                        '">',
                ),
        );
        // sr-init-name-col/-control-col/-framework-col: styling hooks for
        // three columns that were previously unbounded -- with no width
        // constraint at all, DataTables' scrollX sizes every column to its
        // full natural content width, and (unlike a container too WIDE for
        // its columns) scrollX never compresses to fit one that's too
        // narrow, only ever grows. That pushed this table's own min-content
        // past the card at ordinary laptop widths, putting the single
        // Actions-column Initiate button off-screen entirely -- the same
        // DataTables-scrollX cause already fixed on Manage Audits. Only
        // sr-init-name-col is actually capped via _compliance.scss's
        // max-width (Test Name stays unpinned in the columnDefs below, so it
        // can still shrink in the narrow-width lockdown) -- Control Name and
        // Framework Name are capped via the columnDefs width pins a few
        // lines down instead, since those two ARE safe to pin at a fixed
        // width (see that columnDefs comment for why).
        var $nameTd = $("<td>").addClass("sr-init-name-col").text(row.name || "");
        if (row.has_in_progress_audit) {
            // Only ever rendered when "Show tests with an audit in progress"
            // is checked -- this row is otherwise excluded, so without this
            // badge, initiating it here would silently start a SECOND audit
            // with no visual cue that one is already running.
            $("<span>", { class: "sr-chip", text: L("InProgress") })
                .css("margin-left", "6px")
                .appendTo($nameTd);
        }
        tr.append($nameTd);
        tr.append($("<td>").addClass("sr-init-control-col").text(row.control_names || ""));
        tr.append($("<td>").addClass("sr-init-framework-col").text(row.framework_names || ""));
        tr.append($("<td>").html(scheduleChip(row.schedule_type)));
        tr.append($("<td>").text(formatDate(row.last_date)));
        // sr-init-next-col: like Test Name, Next Test Date is deliberately
        // left unpinned in DataTables' columnDefs (it stays visible in the
        // narrow-width lockdown and must be free to shrink there) -- but
        // with autoWidth:false and no width constraint of its own at all, it
        // was the one column left to absorb whatever leftover space the
        // pinned columns didn't claim. Measured at 375px, wide enough to
        // need capping even there: 139px of colgroup width for a 10-
        // character ISO date ("2026-06-04"), on a card that only had 281px
        // total to give every column.
        tr.append($("<td>").addClass("sr-init-next-col").text(formatDate(row.next_date)));
        tr.append($("<td>").addClass("sr-num").text(formatTestFrequency(row.test_frequency)));
        tr.append(
            $("<td>")
                .addClass("sr-actions-col")
                .html(
                    // .sr-row-action (_tables.scss), not a Bootstrap button: matches
                    // the icon-only ghost-button treatment Manage Audits and Define
                    // Tests use for their own row actions -- a fixed 26x26px box
                    // instead of a variable-width Bootstrap btn-sm, which was
                    // inflating this table's min-content width enough to overflow
                    // at several common widths (the same DataTables-scrollX cause
                    // fixed on Manage Audits). Left permanently visible rather than
                    // wrapped in .sr-row-actions (which is opacity:0 until row
                    // hover) -- Initiate is this page's one primary action per row,
                    // not a secondary cluster that should stay out of the way.
                    '<button type="button" class="sr-row-action btn-initiate-one" data-id="' +
                        esc(row.id) +
                        '" title="' +
                        esc(L("Initiate")) +
                        '"><i class="fa fa-play" aria-hidden="true"></i></button>',
                ),
        );
        return tr;
    }

    // The Framework/Control/Test/Tester filter dropdowns are built from the
    // ELIGIBLE rows actually loaded, never from a global table query. A test
    // already mid-audit (or a framework/control/tester with no currently-
    // eligible test) is correctly excluded from this table -- offering it as
    // a filter option anyway lets someone pick a value that can never match
    // any row, which reads as "the filters are broken" rather than "that
    // option legitimately has nothing eligible right now". Building options
    // from the loaded data makes an unmatchable option structurally
    // impossible, and gives each option a real count for free.
    function buildFilterOptions(rows) {
        var frameworkCounts = {};
        var controlCounts = {};
        var testerCounts = {};
        var teamCounts = {};
        var scheduleCounts = { manual: 0, interval: 0, calendar: 0 };

        rows.forEach(function (row) {
            scheduleCounts[row.schedule_type || "manual"]++;
            (row.framework_names || "")
                .split(",")
                .map(function (s) { return s.trim(); })
                .filter(Boolean)
                .forEach(function (fw) {
                    frameworkCounts[fw] = (frameworkCounts[fw] || 0) + 1;
                });

            var ids = (row.control_ids || "").split(",").filter(Boolean);
            var names = (row.control_names || "").split(",").map(function (s) { return s.trim(); }).filter(Boolean);
            ids.forEach(function (id, i) {
                if (!controlCounts[id]) controlCounts[id] = { name: names[i] || id, count: 0 };
                controlCounts[id].count++;
            });

            if (row.tester) {
                var tid = String(row.tester);
                if (!testerCounts[tid]) testerCounts[tid] = { name: row.tester_name || tid, count: 0 };
                testerCounts[tid].count++;
            }

            var teamIds = (row.team_ids || "").split(",").filter(Boolean);
            var teamNames = (row.team_names || "").split(",").map(function (s) { return s.trim(); }).filter(Boolean);
            teamIds.forEach(function (id, i) {
                if (!teamCounts[id]) teamCounts[id] = { name: teamNames[i] || id, count: 0 };
                teamCounts[id].count++;
            });
        });

        function rebuild($select, placeholderText, entries) {
            var prevVal = $select.val();
            $select.empty();
            if (!$select.prop("multiple")) {
                $("<option>", { value: "" }).text(placeholderText).appendTo($select);
            }
            entries.forEach(function (entry) {
                $("<option>", { value: entry.value })
                    .attr("data-count", entry.count)
                    .text(entry.label)
                    .appendTo($select);
            });
            if (prevVal !== null && prevVal !== undefined) {
                $select.val(prevVal);
            }
            window.srSelectRender($select);
        }

        var frameworkEntries = Object.keys(frameworkCounts)
            .sort()
            .map(function (fw) { return { value: fw, label: fw, count: frameworkCounts[fw] }; });
        rebuild($("#initiate-framework-filter"), L("AllFrameworks"), frameworkEntries);

        var controlEntries = Object.keys(controlCounts)
            .map(function (id) { return { value: id, label: controlCounts[id].name, count: controlCounts[id].count }; })
            .sort(function (a, b) { return a.label.localeCompare(b.label); });
        rebuild($("#initiate-control-filter"), L("AllControls"), controlEntries);

        var testerEntries = Object.keys(testerCounts)
            .map(function (id) { return { value: id, label: testerCounts[id].name, count: testerCounts[id].count }; })
            .sort(function (a, b) { return a.label.localeCompare(b.label); });
        rebuild($("#initiate-tester-filter"), L("AllTesters"), testerEntries);

        var teamEntries = Object.keys(teamCounts)
            .map(function (id) { return { value: id, label: teamCounts[id].name, count: teamCounts[id].count }; })
            .sort(function (a, b) { return a.label.localeCompare(b.label); });
        rebuild($("#initiate-team-filter"), L("AllTeams"), teamEntries);

        var testEntries = rows
            .slice()
            .sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); })
            .map(function (row) { return { value: row.id, label: row.name, count: 1 }; });
        rebuild($("#initiate-test-name-filter"), L("ShowAllTests"), testEntries);

        // Unlike Framework/Control/Tester/Team (built only from values a
        // loaded row actually has, so a zero-count option is structurally
        // impossible), Schedule's 3 values are fixed -- a schedule type with
        // nothing eligible right now must be explicitly dropped here rather
        // than left to show a "0" chip a viewer could still pick.
        //
        // EXCEPT for whichever value is currently selected: rebuild()'s
        // $select.val(prevVal) call below silently no-ops when prevVal isn't
        // among the freshly-built <option>s (no matching option to select),
        // and the native <select> then falls back to its first option -- the
        // "" (Any Schedule) placeholder. Since the default selection is
        // "manual", that meant every time this table refreshed with zero
        // *other* eligible Manual tests (e.g. every earlier scenario's had
        // already been initiated), the "Manual" quickfilter silently turned
        // into "Any Schedule" and every Interval/Calendar test became visible
        // -- reproduced in CI (initiate-audits.spec.ts SCENARIO-1 and
        // SCENARIO-3): the widget read "Any schedule" instead of "Manual".
        // Keeping the active selection out of the drop list, even at count 0,
        // keeps the default filter from silently disappearing.
        var scheduleLabels = { manual: L("ScheduleManual"), interval: L("ScheduleInterval"), calendar: L("ScheduleCalendar") };
        var currentScheduleVal = $("#initiate-schedule-filter").val();
        var scheduleEntries = ["manual", "interval", "calendar"]
            .filter(function (key) { return scheduleCounts[key] > 0 || key === currentScheduleVal; })
            .map(function (key) { return { value: key, label: scheduleLabels[key], count: scheduleCounts[key] }; });
        rebuild($("#initiate-schedule-filter"), L("AnySchedule"), scheduleEntries);
    }

    function renderRows(rows) {
        var tbody = $(TABLE_SEL + " tbody");

        // dt.destroy() removes the dom-string-generated wrapper elements
        // (including '.sr-table-foot-right', which this select was relocated
        // into on the previous render) -- detach it back out first or it's
        // destroyed along with them, leaving nothing for the next render's
        // prependTo() to find.
        $("#initiate-audits-length-wrap").appendTo("#initiate-audits-app");

        if (dt) {
            dt.destroy();
            dt = null;
        }
        tbody.empty();

        rows.forEach(function (row) {
            tbody.append(rowHtml(row));
        });

        $("#initiate-audits-count").text(rows.length + " " + L("Tests"));
        buildFilterOptions(rows);

        dt = $(TABLE_SEL).DataTable({
            // header.php sets an app-wide DataTable.defaults with serverSide:true
            // (every OTHER table on the page fetches its own page of rows via
            // ajax). This table is the opposite: every eligible test is already
            // fetched and rendered into <tbody>, and DataTables only adds
            // client-side sort/search/paginate on top -- so both must be
            // explicitly overridden back to false, or DataTables tries (and
            // fails) to ajax-fetch data itself.
            serverSide: false,
            processing: false,
            scrollX: true,
            // scrollX's own automatic column-width calculation writes an
            // explicit pixel width onto <colgroup>'s own <col> element for
            // every column, sized to fill whatever leftover container space
            // it finds -- and that colgroup width is what actually governs
            // layout, ignoring a <td>/<th>'s own CSS max-width entirely (the
            // same cause Manage Audits' own 'all_audits' datatable_options
            // documents, includes/functions.php). Disabling it is what makes
            // the columnDefs width pins below authoritative; Test Name and
            // Next Test Date are deliberately left unpinned (see their
            // columnDefs comment) and instead rely on autoWidth:false's
            // browser-native table layout respecting .sr-init-name-col's
            // max-width (_compliance.scss).
            autoWidth: false,
            // No 'f' (DataTables' own generated search box) -- the toolbar has
            // its own dedicated #initiate-audits-search input instead, wired
            // below. No 'l' either -- #initiate-audits-length in the
            // quickfilters row replaces it, matching Define Tests' own
            // dedicated Show-N-entries select rather than DataTables' native one.
            // Footer groups the Show-select + row-count text together on the
            // left (sr-table-foot-left) with the pager alone on the right
            // (sr-table-foot-right), matching the audits-redesign mockup.
            dom: 'rt<"sr-table-foot"<"sr-table-foot-left"i><"sr-table-foot-right"p>>',
            // Previous/[page numbers]/Next only -- matches Define Tests' own
            // custom pager and the audits-redesign mockup's footer, neither
            // of which has First/Last. The app-wide DataTable.defaults
            // (header.php) leaves pagingType at the library default
            // (full_numbers, which does show First/Last).
            pagingType: "simple_numbers",
            // Terse "Showing X–Y of Z" wording (no "entries") to match
            // Define Tests' own hand-built footer and the mockup, instead of
            // DataTables' bundled "Showing X to Y of Z entries" default.
            language: {
                infoCallback: function (settings, start, end, max, total, pre) {
                    return String(_lang["ShowingXToYOfZ"] || "")
                        .replace("{$start}", start)
                        .replace("{$end}", end)
                        .replace("{$total}", total);
                },
            },
            // Oldest Next Test Date first (an overdue date sorts ahead of a
            // future one), then oldest Last Test Date as the tiebreaker (long
            // since last tested sorts ahead of recently tested) -- surfaces
            // the tests most in need of an audit at the top by default,
            // rather than an alphabetical Test Name sort with no bearing on
            // urgency. Column indices are fixed regardless of the viewer's
            // Columns picker choices (this table toggles 'visible', it never
            // removes a column from the underlying structure), so hardcoding
            // them here is safe -- see rowHtml() for the column layout.
            order: [
                [6, "asc"],
                [5, "asc"],
            ],
            columnDefs: [
                { orderable: false, targets: [0, 8] },
                // DataTables auto-detects YYYY-MM-DD content as its "date" type
                // and right-aligns it (dt-type-date, the vendor CSS's own
                // numeric/date rule) -- forcing 'string' opts these two columns
                // out of that so they read left-aligned like every other text
                // column. Values are already ISO (formatDate()), so a plain
                // string sort still orders them chronologically.
                { type: "string", targets: [5, 6] },
                // Width pins (see autoWidth:false above), sized to what each
                // column's actual content needs -- mirrors Manage Audits' own
                // per-column pins (includes/functions.php, 'all_audits'
                // datatable_options). Control Name (2) and Framework Name (3)
                // are comma-joined lists, hence the wider pin than a plain
                // chip/date/number column needs. Test Name (1) and Next Test
                // Date (6) are deliberately NOT pinned here: both stay visible
                // in the narrow-width lockdown (INITIATE_NARROW_QUERY below),
                // which needs them free to shrink well below any wide-tier
                // pin to fit a phone-width card -- a fixed pin is a constant
                // regardless of viewport, and autoWidth:false stops DataTables
                // from ever recalculating it, which is exactly what silently
                // broke a narrow tier the first time this was tried on Manage
                // Audits (see that file's own columnDefs comment).
                { targets: 2, width: "170px" },
                { targets: 3, width: "170px" },
                { targets: 4, width: "110px" },
                { targets: 5, width: "90px" },
                { targets: 7, width: "90px" },
                { targets: 8, width: "60px" },
            ].concat(
                TOGGLEABLE_COLUMNS.map(function (idx) {
                    var saved = getSavedColumnVisibility();
                    return { targets: idx, visible: saved[idx] !== false };
                }),
            ),
        });
        dt.on("draw", function () {
            syncChecks();
            updateBulkBar();
            syncSortIcons();
        });
        // The DataTable's own first draw already fires synchronously during
        // construction above, before this .on('draw', ...) listener is
        // attached -- an explicit call here covers that initial render (the
        // one a viewer actually sees on page load) rather than only every
        // draw AFTER the first user interaction.
        syncSortIcons();

        // The dom string above generates '.sr-table-foot-left'/'-right' fresh
        // on every (re)init -- relocate the page's own Show-N-entries select
        // into the left group, ahead of the row-count text, matching the
        // audits-redesign mockup's footer grouping.
        $("#initiate-audits-length-wrap").prependTo(
            $(TABLE_SEL).closest(".dt-container").find(".sr-table-foot-left"),
        );

        // Selection may reference ids no longer present after a refetch.
        var liveIds = rows.map(function (r) {
            return String(r.id);
        });
        selected.forEach(function (id) {
            if (liveIds.indexOf(id) === -1) selected.delete(id);
        });

        syncChecks();
        updateBulkBar();
    }

    // ---- filters: search, framework, control, page size ----
    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function updateFiltersCount() {
        var frameworkCount = ($("#initiate-framework-filter").val() || []).length;
        var controlCount = ($("#initiate-control-filter").val() || []).length;
        var testNameCount = $("#initiate-test-name-filter").val() ? 1 : 0;
        var testerCount = $("#initiate-tester-filter").val() ? 1 : 0;
        var teamCount = ($("#initiate-team-filter").val() || []).length;
        var n = frameworkCount + controlCount + testNameCount + testerCount + teamCount;
        $("#initiate-filters-count").text(n).prop("hidden", n === 0);
    }

    function wireFilters() {
        $("#initiate-audits-search").on(
            "input",
            debounce(function () {
                if (dt) dt.search($(this).val()).draw();
            }, 300),
        );

        $("#initiate-audits-length").on("change", function () {
            if (dt) dt.page.len(parseInt($(this).val(), 10)).draw();
        });

        // .sr-qf-toggle only renders below 1100px (design-system.md 6b) --
        // above that the quickfilters row is always visible inline, and this
        // button stays display:none. Below it, the row starts collapsed and
        // this is what opens it.
        $("#initiate-filters-toggle").on("click", function () {
            var $panel = $("#initiate-quickfilters");
            var expanded = $panel.hasClass("is-open");
            $panel.toggleClass("is-open");
            $(this).attr("aria-expanded", String(!expanded));
        });

        [
            [$("#initiate-framework-filter"), L("AllFrameworks")],
            [$("#initiate-control-filter"), L("AllControls")],
            [$("#initiate-test-name-filter"), L("ShowAllTests")],
            [$("#initiate-tester-filter"), L("AllTesters")],
            [$("#initiate-team-filter"), L("AllTeams")],
            [$("#initiate-schedule-filter"), L("AnySchedule")],
        ].forEach(function (pair) { window.srSelectEnhance(pair[0], pair[1]); });

        // Every row already carries every field a filter here needs, as a
        // data-* attribute set at render time (rowHtml()) -- one combined
        // custom row filter, rather than one dt.column().search() per field,
        // since Framework/Control match ANY of a row's several comma-joined
        // values (a single substring column search can't express "any of").
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            // settings.nTable / settings.aoData are DataTables 1.x/2.x-era
            // internals removed in the datatables.net 3.0.2 this app bundles
            // (package.json) -- settings.nTable is undefined here, so the old
            // `!==` check was always true and this whole predicate returned
            // "include" for every row on every table, silently no-opping
            // every quickfilter (Framework/Control/Tester/Team/Schedule).
            // new $.fn.dataTable.Api(settings) is the version-stable way to
            // reach both the table's node and a row's node by index.
            var api = new $.fn.dataTable.Api(settings);
            if (api.table().node() !== $(TABLE_SEL)[0]) return true;
            var tr = api.row(dataIndex).node();

            var frameworks = $("#initiate-framework-filter").val() || [];
            if (frameworks.length) {
                var cellFrameworks = (data[3] || "").split(",").map(function (s) { return s.trim(); });
                if (!frameworks.some(function (f) { return cellFrameworks.indexOf(f) !== -1; })) return false;
            }

            var controls = $("#initiate-control-filter").val() || [];
            if (controls.length) {
                var rowControlIds = (tr.getAttribute("data-control-ids") || "").split(",");
                if (!controls.some(function (c) { return rowControlIds.indexOf(c) !== -1; })) return false;
            }

            var testName = $("#initiate-test-name-filter").val();
            if (testName && tr.getAttribute("data-id") !== testName) return false;

            var tester = $("#initiate-tester-filter").val();
            if (tester && tr.getAttribute("data-tester") !== tester) return false;

            var teams = $("#initiate-team-filter").val() || [];
            if (teams.length) {
                var rowTeamIds = (tr.getAttribute("data-team-ids") || "").split(",");
                if (!teams.some(function (t) { return rowTeamIds.indexOf(t) !== -1; })) return false;
            }

            var schedule = $("#initiate-schedule-filter").val();
            if (schedule && tr.getAttribute("data-schedule") !== schedule) return false;

            return true;
        });

        $("#initiate-framework-filter, #initiate-control-filter, #initiate-test-name-filter, #initiate-tester-filter, #initiate-team-filter").on("change", function () {
            updateFiltersCount();
            if (dt) dt.draw();
        });

        $("#initiate-schedule-filter").on("change", function () {
            updateFiltersCount();
            if (dt) dt.draw();
        });
    }

    // ---- selection + bulk bar ----
    function filteredIds() {
        if (!dt) return [];
        return dt
            .rows({ search: "applied" })
            .nodes()
            .toArray()
            .map(function (tr) {
                var cb = tr.querySelector(".sr-row-check");
                return cb ? cb.getAttribute("data-id") : null;
            })
            .filter(Boolean);
    }

    function syncChecks() {
        var ids = filteredIds();
        $(TABLE_SEL + " .sr-row-check").each(function () {
            $(this).prop("checked", selected.has($(this).data("id").toString()));
        });
        var selCount = ids.filter(function (id) {
            return selected.has(id);
        }).length;
        // DataTables' scrollX clones the whole <thead> (this table's own
        // select-all checkbox included) into a SEPARATE '.dt-scroll-head'
        // table -- a plain TABLE_SEL-scoped find() only ever reaches the
        // original, invisible copy once the table is wide enough to
        // scroll, never the visible clone. $('.sr-check-all') catches both.
        var all = $(".sr-check-all");
        if (all.length) {
            all.prop("checked", ids.length > 0 && selCount === ids.length);
            all.prop("indeterminate", selCount > 0 && selCount < ids.length);
        }
    }

    // Swaps DataTables' own bundled up/down-caret sort indicator
    // (.dt-column-order's :before/:after pseudo-elements) for the same
    // FontAwesome 'fa-sort'/'fa-arrow-up-short-wide'/
    // 'fa-arrow-down-wide-short' glyph Define Tests' own hand-rolled table
    // uses (compliance-define-tests.js's syncSortHeaders()), so every
    // Compliance page reads the same sort affordance. Targets BOTH the live
    // table and its scrollX header clone (.dt-scroll-head table), same
    // dual-header reasoning as syncChecks()' '.sr-check-all' handling
    // above. The clone carries no id/class of its own linking it back to
    // this table (it's a bare '.dt-scroll-head table' shared by every
    // scrollX-enabled DataTable in the app), so an ID-scoped CSS rule can't
    // reach it to suppress the native caret -- 'sr-sort-native-off' is
    // added here, directly on the SAME elements this function already
    // finds, and _tables.scss keys its suppression off that class instead
    // of an ancestor selector. Without it the clone showed BOTH the native
    // grey caret pair AND this icon at once.
    function syncSortIcons() {
        $(TABLE_SEL + ", .dt-scroll-head table").find("thead th[data-dt-column]").each(function () {
            var $th = $(this);
            if (!$th.is(".dt-orderable-asc, .dt-orderable-desc")) return;
            var $order = $th.find(".dt-column-order").addClass("sr-sort-native-off");
            if (!$order.length) return;
            $th.addClass("sr-sortable");
            var $icon = $order.find(".sr-sort-icon");
            if (!$icon.length) {
                $icon = $("<i>", { class: "fa sr-sort-icon" }).appendTo($order);
            }
            var sort = $th.attr("aria-sort");
            $th.toggleClass("is-sorted", sort === "ascending" || sort === "descending");
            $icon
                .removeClass("fa-arrow-up-short-wide fa-arrow-down-wide-short fa-sort")
                .addClass(sort === "ascending" ? "fa-arrow-up-short-wide" : sort === "descending" ? "fa-arrow-down-wide-short" : "fa-sort");
        });
    }

    function updateBulkBar() {
        var bar = $("#initiate-bulk-bar");
        var n = selected.size;
        if (n > 0) {
            if (!bar.length) {
                bar = buildBulkBar();
            }
            bar.find(".sr-bulk-count").text(_n(L("NSelected"), n));
            bar.show();
            $(".sr-table-toolbar", root).hide();
        } else {
            bar.hide();
            $(".sr-table-toolbar", root).show();
        }
    }

    function buildBulkBar() {
        var bar = $('<div id="initiate-bulk-bar" class="sr-bulk-bar" style="display:none;"></div>');
        var count = $('<span class="sr-bulk-count"></span>');
        var actions = $('<div class="sr-bulk-actions"></div>');
        var initiateBtn = $(
            '<button type="button" class="btn btn-sm btn-primary">' +
                esc(L("InitiateSelected")) +
                "</button>",
        ).on("click", function () {
            openTagsModal(Array.from(selected).map(Number));
        });
        var clear = $('<button type="button" class="sr-bulk-clear" aria-label="' + esc(L("Cancel")) + '">&times;</button>').on(
            "click",
            function () {
                selected.clear();
                syncChecks();
                updateBulkBar();
            },
        );
        actions.append(initiateBtn);
        bar.append(clear).append(count).append(actions);
        $(".sr-table-card#initiate-audits-app").prepend(bar);
        return bar;
    }

    // ---- tags modal + initiate ----
    function openTagsModal(ids) {
        pendingInitiateIds = ids;
        $("#initiate-tags-modal-title").text(_n(L("InitiateNAudits"), ids.length));

        var select = $("#initiate-tags-select");
        if (select[0] && select[0].selectize) {
            select[0].selectize.clear();
        }

        $("#initiate-tags-modal").modal("show");
    }

    function submitInitiate() {
        var tags = $("#initiate-tags-select").val() || [];

        $.ajax({
            type: "POST",
            url: BASE_URL + "/api/v2/compliance/audit_initiation/initiate_bulk",
            data: {
                ids: pendingInitiateIds,
                tags: tags,
            },
            success: function (data) {
                if (data.status_message) {
                    showAlertsFromArray(data.status_message);
                }
                $("#initiate-tags-modal").modal("hide");
                selected.clear();
                fetchEligibleTests();
            },
            error: function (xhr, status, error) {
                if (!retryCSRF(xhr, this)) {
                    if (xhr.responseJSON && xhr.responseJSON.status_message) {
                        showAlertsFromArray(xhr.responseJSON.status_message);
                    }
                }
            },
        });
    }

    // ---- wiring ----
    $(function () {
        fetchEligibleTests();
        wireFilters();

        $(document).on("click", ".btn-initiate-one", function () {
            openTagsModal([parseInt($(this).data("id"), 10)]);
        });

        $("#initiate-tags-confirm").on("click", submitInitiate);

        $(document).on("change", TABLE_SEL + " .sr-row-check", function () {
            var id = $(this).data("id").toString();
            if ($(this).prop("checked")) {
                selected.add(id);
            } else {
                selected.delete(id);
            }
            syncChecks();
            updateBulkBar();
        });

        $(document).on("change", ".sr-check-all", function () {
            var ids = filteredIds();
            var everyOn = ids.every(function (id) {
                return selected.has(id);
            });
            ids.forEach(function (id) {
                if (everyOn) {
                    selected.delete(id);
                } else {
                    selected.add(id);
                }
            });
            syncChecks();
            updateBulkBar();
        });

        $("#initiate-show-in-progress").on("change", function () {
            selected.clear();
            fetchEligibleTests();
        });

        // Columns picker checkbox state, Save submit, and the post-save page
        // reload are all owned by render_column_selection_widget()'s own
        // generated <script> (includes/display.php) -- nothing left to wire
        // up here.

        // Crossing the narrow-width breakpoint doesn't go through
        // renderRows() (no data refetch involved), so getSavedColumnVisibility()
        // picking up the new state isn't enough on its own -- the already-
        // live DataTable's column visibility has to be pushed explicitly.
        INITIATE_NARROW_QUERY.addEventListener("change", function () {
            if (!dt) return;
            var visibility = getSavedColumnVisibility();
            TOGGLEABLE_COLUMNS.forEach(function (idx) {
                dt.column(idx).visible(visibility[idx], false);
            });
            dt.columns.adjust().draw(false);
        });
    });
})();
