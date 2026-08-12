/* Self-Assessments page — SCF-based.
 *
 * Hydrates the #self-assessment-app scaffold rendered by
 * simplerisk/assessments/index.php once the two prerequisites (instance
 * registration + SCF installed) are met. Drives the index/history list, the
 * framework picker (enabled/all scope toggle for governance users), the
 * hybrid questionnaire (domain step-chips + Pass/Fail/N-A + Back/progress/Next
 * + Save Progress + Mark Complete), and the Pending Risks tab (failed
 * controls, Push, Delete) entirely against the v2 API.
 *
 * All user-facing strings come from window._lang (populated by SimpleRisk's
 * header.php from $lang via the page's required_localization_keys list).
 * Do not hardcode English text in this file.
 */
(function () {
  "use strict";

  var root = document.getElementById("self-assessment-app");
  if (!root) {
    return;
  }

  var API = (window.BASE_URL || "").replace(/\/$/, "") + "/api/v2";
  var canGovernance = root.dataset.canGovernance === "1";
  // Localization helper L(k) is defined once, globally, in header.php (window.L).

  function csrf() {
    return typeof csrfMagicToken !== "undefined" ? csrfMagicToken : "";
  }

  // -------------------------------------------------------------------
  // apiFetch — thin wrapper around fetch() for the /api/v2 surface.
  // Writes (any method other than GET) get Content-Type + CSRF-TOKEN;
  // GET requests must NOT carry the CSRF header. A 401 means the session
  // has expired/been invalidated — reload so the login page takes over.
  // -------------------------------------------------------------------
  function apiFetch(path, opts) {
    opts = opts || {};
    opts.credentials = "same-origin";
    opts.headers = opts.headers || {};
    if (opts.method && opts.method !== "GET") {
      opts.headers["Content-Type"] = "application/json";
      opts.headers["CSRF-TOKEN"] = csrf();
    }
    return fetch(API + path, opts).then(
      function (r) {
        if (r.status === 401) {
          window.location.reload();
          return { status: 401, body: null };
        }
        return r.json().then(
          function (j) {
            return { status: r.status, body: j };
          },
          function () {
            return { status: r.status, body: null };
          },
        );
      },
      function () {
        return { status: 0, body: null };
      },
    );
  }

  // True when the response matches the expected success code; otherwise shows a
  // generic (non-technical) error banner and returns false. Never surfaces the
  // raw server error to the user.
  function apiOk(res, expected) {
    if (res && res.status === expected) {
      return true;
    }
    if (!res || res.status !== 401) {
      showError();
    }
    return false;
  }

  // Show a dismissable, non-technical error banner at the top of the app.
  function showError(msg) {
    var existing = root.querySelector(".sa-error");
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }
    var banner = document.createElement("div");
    banner.className = "alert alert-danger sa-error";
    banner.setAttribute("role", "alert");
    banner.textContent = msg || L("RequestFailed");
    root.insertBefore(banner, root.firstChild);
    window.setTimeout(function () {
      if (banner.parentNode) {
        banner.parentNode.removeChild(banner);
      }
    }, 6000);
  }

  // Styled confirmation modal following the shared hub modal pattern (replaces
  // native window.confirm). opts: {title, body, primaryLabel, primaryClass, onConfirm}.
  function confirmModal(opts) {
    var previouslyFocused = document.activeElement;
    var overlay = document.createElement("div");
    overlay.className = "hub__modal-overlay";
    var modal = document.createElement("div");
    modal.className = "hub__modal";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    var h = document.createElement("h3");
    h.textContent = opts.title;
    modal.appendChild(h);
    var p = document.createElement("p");
    p.textContent = opts.body;
    modal.appendChild(p);
    var actions = document.createElement("div");
    actions.className = "hub__modal-actions";
    var cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn btn-secondary";
    cancelBtn.textContent = L("Cancel");
    var primaryBtn = document.createElement("button");
    primaryBtn.type = "button";
    primaryBtn.className = "btn " + (opts.primaryClass || "btn-submit");
    primaryBtn.textContent = opts.primaryLabel;
    actions.appendChild(cancelBtn);
    actions.appendChild(primaryBtn);
    modal.appendChild(actions);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    primaryBtn.focus();
    function close() {
      document.removeEventListener("keydown", escHandler);
      if (overlay.parentNode) {
        overlay.parentNode.removeChild(overlay);
      }
      if (previouslyFocused && typeof previouslyFocused.focus === "function") {
        previouslyFocused.focus();
      }
    }
    function escHandler(e) {
      if (e.key === "Escape") {
        close();
      }
    }
    document.addEventListener("keydown", escHandler);
    overlay.addEventListener("click", function (e) {
      if (e.target === overlay) {
        close();
      }
    });
    cancelBtn.onclick = close;
    primaryBtn.onclick = function () {
      close();
      opts.onConfirm();
    };
  }

  // Lay out the page header (the grey `.page-breadcrumb` strip, outside the app
  // root): the "Self-Assessments" title with the breadcrumb LEFT-aligned directly
  // underneath it, and an optional right-aligned page action (e.g. "+ New").
  // items: [{label, action?}] — items with an action render as links; the last is
  // the current page (never a link). action: {html, onClick} or omitted.
  function setBreadcrumb(items, action) {
    var host = document.querySelector(".page-breadcrumb .col-12");
    if (!host) {
      return;
    }
    // The Home-landing header wraps the title in a flex `.page-head`; when that
    // wrapper is present it — not the `.col-12` — is the direct parent of
    // `h4.page-title`, so restructure within it (otherwise insertBefore below
    // throws because h4 is not a direct child of the host).
    var pageHead = host.querySelector(".page-head");
    if (pageHead) {
      host = pageHead;
    }
    // Left block: move the server-rendered title into a column and stack the
    // breadcrumb beneath it.
    var left = host.querySelector("#sa-title-block");
    if (!left) {
      left = document.createElement("div");
      left.id = "sa-title-block";
      var h4 = host.querySelector("h4.page-title");
      if (h4) {
        host.insertBefore(left, h4);
        left.appendChild(h4);
      } else {
        host.insertBefore(left, host.firstChild);
      }
      var s = document.createElement("div");
      s.id = "sa-breadcrumb-slot";
      left.appendChild(s);
    }
    var slot = left.querySelector("#sa-breadcrumb-slot");
    var ol = document.createElement("ol");
    ol.className = "breadcrumb mb-0";
    items.forEach(function (it, i) {
      var isCurrent = i === items.length - 1;
      var li = document.createElement("li");
      li.className = "breadcrumb-item" + (isCurrent ? " active" : "");
      if (isCurrent) {
        li.setAttribute("aria-current", "page");
        li.textContent = it.label;
      } else if (it.action) {
        var a = document.createElement("a");
        a.href = "#";
        a.textContent = it.label;
        a.onclick = function (e) {
          e.preventDefault();
          it.action();
        };
        li.appendChild(a);
      } else {
        li.textContent = it.label;
      }
      ol.appendChild(li);
    });
    var nav = document.createElement("nav");
    nav.setAttribute("aria-label", "breadcrumb");
    nav.appendChild(ol);
    slot.innerHTML = "";
    slot.appendChild(nav);

    // Right-aligned page action (present only on views that pass one).
    var act = host.querySelector("#sa-page-action");
    if (action && action.html) {
      if (!act) {
        act = document.createElement("div");
        act.id = "sa-page-action";
        act.className = "ms-auto";
        host.appendChild(act);
      }
      act.innerHTML = action.html;
      act.style.display = "";
      if (action.onClick) {
        var b = act.querySelector("button");
        if (b) {
          b.onclick = action.onClick;
        }
      }
    } else if (act) {
      act.innerHTML = "";
      act.style.display = "none";
    }
  }

  // Breadcrumb trails per view (Assessments is the non-link menu parent).
  function crumbBase() {
    return [
      { label: L("Assessments") },
      { label: L("SelfAssessments"), action: renderIndex },
    ];
  }

  // The shared 3-tab bar rendered at the top of the index, pending-risks and
  // failed-controls views. `active` is the data-tab key of the current view.
  function tabsHtml(active) {
    function tab(key, label) {
      return (
        '<button class="sa-tab' +
        (active === key ? " sa-tab-active" : "") +
        '" data-tab="' +
        key +
        '">' +
        esc(label) +
        "</button>"
      );
    }
    return (
      '<div class="sa-tabs">' +
      tab("assessments", L("SelfAssessments")) +
      tab("pending", L("PendingRisks")) +
      tab("failed", L("FailedControls")) +
      "</div>"
    );
  }

  // ---- Row-action icons (inline SVG — the FA glyph font isn't loaded on this
  // page; SVGs are self-contained). Each is wrapped in a titled icon button.
  var ICONS = {
    view:
      '<svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M1 8s2.5-4.5 7-4.5S15 8 15 8s-2.5 4.5-7 4.5S1 8 1 8z"/><circle cx="8" cy="8" r="2"/></svg>',
    edit:
      '<svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M11.5 2.5l2 2L6 12l-2.5.5L4 10z"/><path d="M10.5 3.5l2 2"/></svg>',
    del:
      '<svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M2.5 4h11M6 4V2.5h4V4M5 4l.7 9.5h4.6L11 4"/></svg>',
    push:
      '<svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M8 11V3M4.5 6.5L8 3l3.5 3.5M3 13.5h10"/></svg>',
  };

  // Build a titled icon action button. `attrs` is a raw attribute string
  // (e.g. 'data-view-id="12"'); `variant` optionally adds a modifier class.
  function iconBtn(icon, title, attrs, variant) {
    return (
      '<button type="button" class="sa-icon-btn' +
      (variant ? " " + variant : "") +
      '" ' +
      attrs +
      ' title="' +
      esc(title) +
      '" aria-label="' +
      esc(title) +
      '">' +
      ICONS[icon] +
      "</button>"
    );
  }

  // ---- State ----
  var state = {
    view: "index",
    assessment: null,
    controls: [],
    responses: {},
    domainIndex: 0,
    scope: canGovernance ? "enabled" : "all",
    failedStatus: "fail",
  };

  // =================================================================
  // Selectable DataTable infrastructure (design-system §6)
  // =================================================================

  // Build the `.sr-table-card` toolbar: title on the left, right-aligned tools
  // (search is relocated here after the DataTable inits; filters live here too).
  function srToolbar(titleText, toolsHtml) {
    return (
      '<div class="sr-table-toolbar">' +
      '<div class="sr-table-title">' +
      esc(titleText) +
      "</div>" +
      '<div class="sr-table-tools">' +
      (toolsHtml || "") +
      "</div></div>"
    );
  }

  // Header + row checkbox cells for the leftmost selection column.
  function checkTh() {
    return (
      '<th class="sr-check-col"><input type="checkbox" class="form-check-input sr-check-all" aria-label="' +
      esc(L("SelectAll")) +
      '"></th>'
    );
  }
  function checkTd(id) {
    return (
      '<td class="sr-check-col"><input type="checkbox" class="form-check-input sr-row-check" data-id="' +
      esc(id) +
      '" aria-label="' +
      esc(L("Select")) +
      '"></td>'
    );
  }

  // Row filters we registered on DataTables' global ext.search stack, so we can
  // remove them before each re-render (they're global and would otherwise leak).
  var rowFilters = [];
  function clearRowFilters() {
    if (typeof jQuery === "undefined" || !jQuery.fn || !jQuery.fn.dataTable) {
      rowFilters = [];
      return;
    }
    rowFilters.forEach(function (fn) {
      var i = jQuery.fn.dataTable.ext.search.indexOf(fn);
      if (i >= 0) {
        jQuery.fn.dataTable.ext.search.splice(i, 1);
      }
    });
    rowFilters = [];
  }
  // Register a filter scoped to one table element. predicate(tr) -> keep?
  function addRowFilter(tableEl, predicate) {
    var fn = function (settings, data, dataIndex) {
      if (settings.nTable !== tableEl) {
        return true;
      }
      return predicate(settings.aoData[dataIndex].nTr);
    };
    jQuery.fn.dataTable.ext.search.push(fn);
    rowFilters.push(fn);
  }

  // Move DataTables' generated search box up into the card toolbar tools, to the
  // left of any filter already there. Moving the node keeps its event bindings.
  function relocateSearchIntoTools(dt) {
    try {
      var container = dt.table().container();
      var filter =
        container.querySelector(".dt-search") ||
        container.querySelector(".dataTables_filter");
      var tools = root.querySelector(".sr-table-tools");
      if (filter && tools) {
        tools.insertBefore(filter, tools.firstChild);
      }
    } catch (e) {
      /* leave the search where DataTables put it */
    }
  }

  // Initialize a client-side DataTable in the .sr-table-card classic layout.
  // opts: { order:[[col,dir]], noSort:[colIdx,...] }
  function initSrTable(tableEl, opts) {
    opts = opts || {};
    if (typeof jQuery === "undefined" || !jQuery.fn || !jQuery.fn.DataTable) {
      return null;
    }
    if (jQuery.fn.dataTable.isDataTable(tableEl)) {
      jQuery(tableEl).DataTable().destroy();
    }
    var dt = jQuery(tableEl).DataTable({
      serverSide: false,
      processing: false,
      dom: 'rt<"sr-table-foot"i<"sr-table-foot-right"lp>>f',
      order: opts.order || [],
      columnDefs:
        opts.noSort && opts.noSort.length
          ? [{ orderable: false, targets: opts.noSort }]
          : [],
    });
    relocateSearchIntoTools(dt);
    return dt;
  }

  // Lock/unlock an action button while its request is in flight (prevents the
  // no-feedback double-submit). Mirrors the greyed-out/locked affordance used on
  // the SCF admin page.
  function lockBtn(btn) {
    if (!btn) return;
    btn.disabled = true;
    btn.classList.add("is-busy");
  }
  function unlockBtn(btn) {
    if (!btn) return;
    btn.disabled = false;
    btn.classList.remove("is-busy");
  }

  // Wire selection + a contextual bulk bar onto an initialized selectable table.
  // cfg: { dt, tableEl, card, actions:[{key,label,variant,run(ids,ui)}] }
  //   ui passed to run(): { busy(text), reload() }  — run() owns the API calls
  //   and must call ui.reload() (a full re-render) when finished.
  function srSelectable(cfg) {
    var dt = cfg.dt,
      tableEl = cfg.tableEl,
      card = cfg.card;
    var selected = new Set();

    function filteredTrs() {
      return dt.rows({ search: "applied" }).nodes().toArray();
    }
    function idOfTr(tr) {
      var cb = tr.querySelector(".sr-row-check");
      return cb ? cb.getAttribute("data-id") : null;
    }

    // Build the bulk bar once, hidden, as the card's first child.
    var bar = document.createElement("div");
    bar.className = "sr-bulk-bar";
    bar.style.display = "none";
    var count = document.createElement("span");
    count.className = "sr-bulk-count";
    var actionsWrap = document.createElement("div");
    actionsWrap.className = "sr-bulk-actions";
    cfg.actions.forEach(function (a) {
      var b = document.createElement("button");
      b.type = "button";
      b.className = "btn btn-sm " + (a.variant || "btn-secondary");
      b.textContent = a.label;
      b.dataset.bulkKey = a.key;
      b.onclick = function () {
        runAction(a, b);
      };
      actionsWrap.appendChild(b);
    });
    var clear = document.createElement("button");
    clear.type = "button";
    clear.className = "sr-bulk-clear";
    clear.setAttribute("aria-label", L("Cancel"));
    clear.innerHTML = "&times;";
    clear.onclick = function () {
      selected.clear();
      syncChecks();
      updateBar();
    };
    bar.appendChild(clear);
    bar.appendChild(count);
    bar.appendChild(actionsWrap);
    card.insertBefore(bar, card.firstChild);

    function updateBar() {
      var n = selected.size;
      if (n > 0) {
        count.textContent = _n(L("NSelected"), n);
        bar.style.display = "";
        var tb = card.querySelector(".sr-table-toolbar");
        if (tb) tb.style.display = "none";
      } else {
        bar.style.display = "none";
        var tb2 = card.querySelector(".sr-table-toolbar");
        if (tb2) tb2.style.display = "";
      }
    }
    function syncChecks() {
      var frs = filteredTrs();
      // Update every visible checkbox from the selection set.
      tableEl.querySelectorAll(".sr-row-check").forEach(function (cb) {
        cb.checked = selected.has(cb.getAttribute("data-id"));
      });
      var ids = frs.map(idOfTr).filter(Boolean);
      var selCount = ids.filter(function (id) {
        return selected.has(id);
      }).length;
      var all = card.querySelector(".sr-check-all");
      if (all) {
        all.checked = ids.length > 0 && selCount === ids.length;
        all.indeterminate = selCount > 0 && selCount < ids.length;
      }
    }

    function runAction(a, btn) {
      if (!selected.size) return;
      var ids = Array.from(selected);
      // Don't lock anything yet — the action typically shows a confirm modal
      // first (whose overlay already blocks the bar). ui.busy() is what locks,
      // and the action only calls it once the user has confirmed.
      var ui = {
        busy: function (text) {
          actionsWrap.querySelectorAll("button").forEach(function (b) {
            b.disabled = true;
          });
          clear.disabled = true;
          btn.classList.add("is-busy");
          if (text) btn.textContent = text;
        },
        progress: function (text) {
          if (text) btn.textContent = text;
        },
        reload: cfg.reload,
      };
      a.run(ids, ui);
    }

    // Delegated events on the table for row + select-all checkboxes.
    tableEl.addEventListener("change", function (e) {
      var t = e.target;
      if (t.classList.contains("sr-row-check")) {
        var id = t.getAttribute("data-id");
        if (t.checked) selected.add(id);
        else selected.delete(id);
        syncChecks();
        updateBar();
      } else if (t.classList.contains("sr-check-all")) {
        var ids = filteredTrs().map(idOfTr).filter(Boolean);
        var everyOn = ids.every(function (id) {
          return selected.has(id);
        });
        ids.forEach(function (id) {
          if (everyOn) selected.delete(id);
          else selected.add(id);
        });
        syncChecks();
        updateBar();
      }
    });
    // Re-sync checkbox state whenever the table redraws (paging/search/filter).
    dt.on("draw", function () {
      syncChecks();
      updateBar();
    });
    syncChecks();
    updateBar();
  }

  // Tiny "{n}"-substitution for a localized count label ("{n} selected").
  function _n(tpl, n) {
    return String(tpl).replace("{n}", n);
  }

  // Severity chip (design-system §7 "severity — solid") for a risk score:
  // a solid, config-colored pill showing the numeric score and its risk-level
  // name (e.g. "10 · High"). The fill color + text-contrast are applied by
  // paintSevPills() after render — never interpolated into the markup — so an
  // admin-configured color string can't break out of an inline style.
  function sevPill(score, levelName, color) {
    var label = levelName ? esc(score) + " · " + esc(levelName) : esc(score);
    return (
      '<span class="sr-sev-pill sr-sev-pill-sm" data-color="' +
      esc(color || "") +
      '">' +
      label +
      "</span>"
    );
  }

  // Apply each severity pill's configured fill color via the style *property*
  // (the browser validates it — a bad value is a safe no-op) and pick a
  // readable text color from the fill's luminance (pale fills get charcoal
  // text via `.on-light`).
  function paintSevPills() {
    root.querySelectorAll(".sr-sev-pill[data-color]").forEach(function (el) {
      var c = el.getAttribute("data-color");
      if (!c) {
        return;
      }
      el.style.backgroundColor = c;
      var probe = document.createElement("span");
      probe.style.color = c;
      document.body.appendChild(probe);
      var rgb = window.getComputedStyle(probe).color;
      document.body.removeChild(probe);
      var m = rgb.match(/\d+/g);
      if (m && m.length >= 3) {
        var lum = 0.299 * +m[0] + 0.587 * +m[1] + 0.114 * +m[2];
        el.classList.toggle("on-light", lum > 150);
      }
    });
  }

  // ---- Views ----
  function renderIndex() {
    clearRowFilters();
    apiFetch("/self-assessments").then(function (res) {
      if (!apiOk(res, 200)) {
        return;
      }
      setBreadcrumb(
        [{ label: L("Assessments") }, { label: L("SelfAssessments") }],
        {
          html:
            '<button class="btn btn-success" id="sa-new">+ ' +
            esc(L("NewSelfAssessment")) +
            "</button>",
          onClick: renderPicker,
        }
      );
      var runs = (res.body.data && res.body.data.self_assessments) || [];

      if (!runs.length) {
        root.innerHTML =
          tabsHtml("assessments") +
          '<div class="sr-table-card"><p class="sa-empty">' +
          esc(L("NoSelfAssessmentsYet")) +
          "</p></div>";
        wireIndex();
        return;
      }

      var statusFilter =
        '<select class="form-select sr-table-status-filter" id="sa-index-status">' +
        '<option value="all">' +
        esc(L("All")) +
        '</option><option value="completed">' +
        esc(L("Completed")) +
        '</option><option value="in_progress">' +
        esc(L("InProgress")) +
        "</option></select>";

      var body = runs
        .map(function (r) {
          return indexRow(r);
        })
        .join("");

      root.innerHTML =
        tabsHtml("assessments") +
        '<div class="sr-table-card">' +
        srToolbar(L("SelfAssessments"), statusFilter) +
        '<div class="sr-table-scroll">' +
        '<table class="sr-table" id="sa-index-table" style="width:100%"><thead><tr>' +
        checkTh() +
        "<th>" +
        esc(L("Framework")) +
        "</th><th>" +
        esc(L("Date")) +
        "</th><th>" +
        esc(L("Status")) +
        "</th><th>" +
        esc(L("AnsweredOfTotal")) +
        "</th><th>" +
        esc(L("StartedBy")) +
        "</th><th></th></tr></thead><tbody>" +
        body +
        "</tbody></table></div></div>";

      wireIndex();

      var tableEl = document.getElementById("sa-index-table");
      var dt = initSrTable(tableEl, { order: [[2, "desc"]], noSort: [0, 6] });
      if (!dt) {
        return;
      }

      // Status filter (All / Completed / In Progress) over the row's data-status.
      var sel = document.getElementById("sa-index-status");
      addRowFilter(tableEl, function (tr) {
        var want = sel ? sel.value : "all";
        return want === "all" || tr.getAttribute("data-status") === want;
      });
      if (sel) {
        sel.onchange = function () {
          dt.draw();
        };
      }

      srSelectable({
        dt: dt,
        tableEl: tableEl,
        card: root.querySelector(".sr-table-card"),
        reload: renderIndex,
        actions: [
          {
            key: "delete",
            label: L("Delete"),
            variant: "btn-danger",
            run: function (ids, ui) {
              confirmModal({
                title: L("Delete"),
                body: _n(L("ConfirmDeleteSelectedSelfAssessments"), ids.length),
                primaryLabel: L("Delete"),
                primaryClass: "btn-danger",
                onConfirm: function () {
                  ui.busy(L("Deleting"));
                  runSequential(ids, deleteSelfAssessment).then(function (
                    summary,
                  ) {
                    reloadWithBulkSummary(ui.reload, summary);
                  });
                },
              });
            },
          },
        ],
      });
    });
  }

  function indexRow(r) {
    var badge =
      r.status === "completed"
        ? '<span class="sr-state-pill sr-state-success">' +
          esc(L("Completed")) +
          "</span>"
        : '<span class="sr-state-pill sr-state-info">' +
          esc(L("InProgress")) +
          "</span>";
    var action =
      r.status === "completed"
        ? iconBtn("view", L("View"), 'data-view-id="' + esc(r.id) + '"')
        : iconBtn("edit", L("Resume"), 'data-resume-id="' + esc(r.id) + '"');
    var statusKey = r.status === "completed" ? "completed" : "in_progress";
    return (
      '<tr data-status="' +
      esc(statusKey) +
      '">' +
      checkTd(r.id) +
      "<td>" +
      esc(r.framework_name) +
      "</td><td>" +
      esc((r.created_at || "").substring(0, 10)) +
      "</td><td>" +
      badge +
      "</td>" +
      "<td>" +
      esc(r.answered_count) +
      " / " +
      esc(r.total_count) +
      " · " +
      esc(r.failed_count) +
      " " +
      esc(L("FailedSoFar")) +
      "</td>" +
      "<td>" +
      esc(r.started_by_name || "") +
      "</td>" +
      '<td><div class="sa-row-actions">' +
      action +
      iconBtn(
        "del",
        L("Delete"),
        'data-del-id="' + esc(r.id) + '"',
        "sa-icon-danger"
      ) +
      "</div></td></tr>"
    );
  }

  function renderPicker() {
    var q = "/self-assessments/frameworks?scope=" + state.scope;
    apiFetch(q).then(function (res) {
      if (!apiOk(res, 200)) {
        return;
      }
      setBreadcrumb(crumbBase().concat([{ label: L("NewSelfAssessment") }]));
      var fws = (res.body.data && res.body.data.frameworks) || [];
      var toggle = canGovernance
        ? '<div class="sa-toggle"><button data-scope="enabled" class="' +
          (state.scope === "enabled" ? "on" : "") +
          '">' +
          esc(L("EnabledFrameworks")) +
          "</button>" +
          '<button data-scope="all" class="' +
          (state.scope === "all" ? "on" : "") +
          '">' +
          esc(L("AllScfFrameworks")) +
          "</button></div>"
        : "";
      var html =
        '<div class="sa-picker-head"><h3>' +
        esc(L("ChooseAFramework")) +
        "</h3>" +
        toggle +
        "</div>";
      html +=
        '<input class="form-control sa-search" placeholder="' +
        esc(L("Search")) +
        '">';
      html += '<div class="sa-fw-list">';
      fws.forEach(function (f) {
        html +=
          '<div class="sa-fw"><div class="sa-fw-meta"><div class="sa-fw-name">' +
          esc(f.name) +
          (f.enabled
            ? ' <span class="sa-enabled">' +
              esc(L("EnabledFrameworks")) +
              "</span>"
            : "") +
          "</div></div>" +
          '<div class="sa-fw-count"><b>' +
          esc(f.question_count) +
          "</b><span>" +
          esc(L("Questions")) +
          "</span></div>" +
          '<button class="btn btn-success sa-start" data-scf="' +
          esc(f.scf_source_id) +
          '">' +
          esc(L("Start")) +
          " &rsaquo;</button></div>";
      });
      html += "</div>";
      root.innerHTML = html;
      wirePicker();
    });
  }

  function startRun(scfSourceId) {
    apiFetch("/self-assessments", {
      method: "POST",
      body: JSON.stringify({ scf_source_id: scfSourceId }),
    }).then(function (res) {
      if (!apiOk(res, 201)) {
        return;
      }
      loadRun(res.body.data.assessment.id);
    });
  }

  function loadRun(id) {
    apiFetch("/self-assessments/" + id).then(function (res) {
      if (!apiOk(res, 200)) {
        return;
      }
      state.assessment = res.body.data.assessment;
      state.controls = res.body.data.controls;
      state.responses = res.body.data.responses || {};
      state.domainIndex = 0;
      if (state.assessment.status === "completed") {
        renderCompletedView();
      } else {
        renderQuestionnaire();
      }
    });
  }

  // Read-only view for a completed assessment: every domain shown as its own
  // section at once (no domain stepping), each answer shown non-interactively,
  // and no Save Progress / Mark Complete / Back / Next controls.
  function renderCompletedView() {
    var d = domains();
    var answered = Object.keys(state.responses).filter(function (k) {
      return state.responses[k].response;
    }).length;
    var total = state.controls.length;
    var completedOn = (state.assessment.completed_at || "").substring(0, 10);

    var sections = d.order
      .map(function (dom) {
        var rows = (d.map[dom] || [])
          .map(function (c) {
            var resp = (state.responses[c.control_id] || {}).response || "";
            return (
              '<div class="sa-ctl"><div class="sa-ctl-meta">' +
              '<div class="sa-ctl-id"><span class="sa-num">' +
              esc(c.control_number) +
              "</span> " +
              esc(c.short_name) +
              "</div>" +
              '<div class="sa-ctl-q"><span class="sa-qlabel">' +
              esc(L("ControlQuestion")) +
              "</span>" +
              esc(c.question) +
              "</div></div>" +
              readonlyAnswer(resp) +
              "</div>"
            );
          })
          .join("");
        return (
          '<div class="sa-domain"><h3>' +
          esc(dom) +
          "</h3></div>" +
          '<div class="sa-ctl-list">' +
          rows +
          "</div>"
        );
      })
      .join("");

    setBreadcrumb([
      { label: L("Assessments") },
      { label: L("SelfAssessments"), action: renderIndex },
      { label: state.assessment.framework_name },
    ]);
    root.innerHTML =
      '<div class="sa-run-top"><span class="sa-run-title">' +
      esc(state.assessment.framework_name) +
      "</span>" +
      '<span class="sa-run-actions"><span class="sa-count">' +
      esc(L("Completed")) +
      (completedOn ? " · " + esc(completedOn) : "") +
      " · " +
      esc(answered) +
      " / " +
      esc(total) +
      " " +
      esc(L("AnsweredOfTotal")) +
      "</span></span></div>" +
      sections;
  }

  // Non-interactive answer display (mirrors the Pass/Fail/N-A segmented control
  // but read-only, highlighting only the recorded answer).
  function readonlyAnswer(resp) {
    function b(v, label) {
      return (
        '<span class="sa-seg-btn' +
        (resp === v ? " on-" + v : "") +
        '">' +
        esc(label) +
        "</span>"
      );
    }
    return (
      '<div class="sa-seg sa-seg-readonly">' +
      b("pass", L("Yes")) +
      b("fail", L("No")) +
      b("na", L("NotApplicable")) +
      "</div>"
    );
  }

  function domains() {
    var seen = [],
      map = {};
    state.controls.forEach(function (c) {
      if (!map[c.domain]) {
        map[c.domain] = [];
        seen.push(c.domain);
      }
      map[c.domain].push(c);
    });
    return { order: seen, map: map };
  }

  function renderQuestionnaire() {
    var d = domains();
    var currentDomain = d.order[state.domainIndex];
    var controls = d.map[currentDomain] || [];
    var answered = Object.keys(state.responses).filter(function (k) {
      return state.responses[k].response;
    }).length;
    var total = state.controls.length;
    var isLastDomain = state.domainIndex === d.order.length - 1;

    setBreadcrumb(
      crumbBase().concat([{ label: state.assessment.framework_name }]),
    );

    var chips = d.order
      .map(function (dom, i) {
        var done = (d.map[dom] || []).every(function (c) {
          return (
            state.responses[c.control_id] &&
            state.responses[c.control_id].response
          );
        });
        var cls =
          "sa-chip" +
          (i === state.domainIndex ? " on" : "") +
          (done ? " done" : "");
        return (
          '<button class="' +
          cls +
          '" data-domain="' +
          i +
          '">' +
          (done ? "&checkmark; " : "") +
          esc(dom) +
          "</button>"
        );
      })
      .join("");

    var rows = controls
      .map(function (c) {
        var resp = (state.responses[c.control_id] || {}).response || "";
        return (
          '<div class="sa-ctl"><div class="sa-ctl-meta">' +
          '<div class="sa-ctl-id"><span class="sa-num">' +
          esc(c.control_number) +
          "</span> " +
          esc(c.short_name) +
          "</div>" +
          '<div class="sa-ctl-q"><span class="sa-qlabel">' +
          esc(L("ControlQuestion")) +
          "</span>" +
          esc(c.question) +
          "</div></div>" +
          seg(c.control_id, resp) +
          "</div>"
        );
      })
      .join("");

    root.innerHTML =
      '<div class="sa-run-top"><span class="sa-run-title">' +
      esc(state.assessment.framework_name) +
      "</span>" +
      '<span class="sa-run-actions"><span class="sa-count">' +
      esc(answered) +
      " / " +
      esc(total) +
      " " +
      esc(L("AnsweredOfTotal")) +
      "</span>" +
      '<button class="btn btn-secondary" id="sa-save">' +
      esc(L("SaveProgress")) +
      "</button>" +
      '<button class="btn btn-success" id="sa-complete">' +
      esc(L("MarkComplete")) +
      "</button></span></div>" +
      '<div class="sa-chips">' +
      chips +
      "</div>" +
      '<div class="sa-domain"><h3>' +
      esc(currentDomain) +
      "</h3></div>" +
      '<div class="sa-ctl-list">' +
      rows +
      "</div>" +
      '<div class="sa-foot"><button class="btn btn-secondary" id="sa-back">&lsaquo; ' +
      esc(L("Back")) +
      "</button>" +
      '<div class="sa-prog"><div class="sa-prog-bar"><i style="width:' +
      (total ? Math.round((answered / total) * 100) : 0) +
      '%"></i></div></div>' +
      (isLastDomain
        ? '<button class="btn btn-success" id="sa-next">' +
          esc(L("MarkComplete")) +
          "</button>"
        : '<button class="btn btn-secondary" id="sa-next">' +
          esc(L("Next")) +
          " &rsaquo;</button>") +
      "</div>";
    wireQuestionnaire();
  }

  function seg(cid, resp) {
    function b(v, label) {
      return (
        '<button class="sa-seg-btn' +
        (resp === v ? " on-" + v : "") +
        '" data-cid="' +
        esc(cid) +
        '" data-resp="' +
        esc(v) +
        '">' +
        esc(label) +
        "</button>"
      );
    }
    // Internal response values stay 'pass'/'fail'/'na' (risk generation keys on 'fail');
    // only the user-facing labels are Yes/No/N-A, which fit the "Does the org...?" question phrasing.
    return (
      '<div class="sa-seg">' +
      b("pass", L("Yes")) +
      b("fail", L("No")) +
      b("na", L("NotApplicable")) +
      "</div>"
    );
  }

  function collectResponses() {
    return Object.keys(state.responses).map(function (cid) {
      return {
        control_id: parseInt(cid, 10),
        response: state.responses[cid].response || "",
        comment: state.responses[cid].comment || "",
      };
    });
  }

  function saveProgress() {
    var id = state.assessment.id;
    return apiFetch("/self-assessments/" + id + "/responses", {
      method: "PATCH",
      body: JSON.stringify({ responses: collectResponses() }),
    });
  }

  function completeRun() {
    confirmModal({
      title: L("MarkComplete"),
      body: L("ConfirmCompleteSelfAssessment"),
      primaryLabel: L("MarkComplete"),
      primaryClass: "btn-success",
      onConfirm: function () {
        var id = state.assessment.id;
        saveProgress().then(function (res) {
          if (!apiOk(res, 200)) {
            return;
          }
          apiFetch("/self-assessments/" + id + "/complete", {
            method: "POST",
            body: "{}",
          }).then(function (cres) {
            if (!apiOk(cres, 200)) {
              return;
            }
            state.view = "index";
            renderIndex();
          });
        });
      },
    });
  }

  function renderPendingRisks() {
    clearRowFilters();
    apiFetch("/self-assessments/pending-risks").then(function (res) {
      if (!apiOk(res, 200)) {
        return;
      }
      setBreadcrumb(crumbBase().concat([{ label: L("PendingRisks") }]));
      var risks = (res.body.data && res.body.data.pending_risks) || [];

      if (!risks.length) {
        root.innerHTML =
          tabsHtml("pending") +
          '<div class="sr-table-card"><p class="sa-empty">' +
          esc(L("NoPendingRisks")) +
          "</p></div>";
        wirePending();
        return;
      }

      // Build the by-control multi-select options (union of every risk's failed
      // controls, deduped by native id, labelled "<number> — <short name>").
      var ctrlLabels = {};
      risks.forEach(function (p) {
        (p.controls || []).forEach(function (c) {
          if (!ctrlLabels[c.id]) {
            ctrlLabels[c.id] =
              c.number + (c.short_name ? " — " + c.short_name : "");
          }
        });
      });
      var ctrlOptions = Object.keys(ctrlLabels)
        .sort(function (a, b) {
          return ctrlLabels[a].localeCompare(ctrlLabels[b]);
        })
        .map(function (id) {
          return '<option value="' + esc(id) + '">' + esc(ctrlLabels[id]) + "</option>";
        })
        .join("");
      var filterHtml =
        '<select class="form-select sr-table-status-filter sa-pr-control-filter" id="sa-pr-control-filter" multiple placeholder="' +
        esc(L("FilterByControl")) +
        '">' +
        ctrlOptions +
        "</select>";

      var body = risks
        .map(function (p) {
          var ctrlIds = (p.controls || [])
            .map(function (c) {
              return c.id;
            })
            .join(",");
          return (
            '<tr data-controls="' +
            esc(ctrlIds) +
            '">' +
            checkTd(p.id) +
            "<td>" +
            '<div class="sa-pr-subject">' +
            esc(p.subject) +
            "</div>" +
            (p.description
              ? '<div class="sa-pr-desc">' + esc(p.description) + "</div>"
              : "") +
            '</td><td data-order="' +
            esc(p.score) +
            '">' +
            sevPill(p.score, p.score_level, p.score_color) +
            "</td><td>" +
            esc(p.failed_controls) +
            "</td>" +
            '<td><div class="sa-row-actions">' +
            iconBtn("push", L("PushToRisk"), 'data-push-id="' + esc(p.id) + '"') +
            iconBtn(
              "del",
              L("Delete"),
              'data-delpr-id="' + esc(p.id) + '"',
              "sa-icon-danger"
            ) +
            "</div></td></tr>"
          );
        })
        .join("");

      root.innerHTML =
        tabsHtml("pending") +
        '<div class="sr-table-card">' +
        srToolbar(L("PendingRisks"), filterHtml) +
        '<div class="sr-table-scroll">' +
        '<table class="sr-table" id="sa-pr-table" style="width:100%"><thead><tr>' +
        checkTh() +
        "<th>" +
        esc(L("Subject")) +
        "</th><th>" +
        esc(L("Score")) +
        "</th><th>" +
        esc(L("FailedControls")) +
        "</th><th></th></tr></thead><tbody>" +
        body +
        "</tbody></table></div></div>";

      wirePending();

      var tableEl = document.getElementById("sa-pr-table");
      var dt = initSrTable(tableEl, { order: [[1, "asc"]], noSort: [0, 4] });
      if (!dt) {
        paintSevPills();
        return;
      }
      // Color the score severity chips (once now; again on every redraw so
      // paged-in rows are painted too).
      paintSevPills();
      dt.on("draw", paintSevPills);

      // By-control filter: keep a risk if it maps to ANY selected control.
      var selectedControls = new Set();
      addRowFilter(tableEl, function (tr) {
        if (!selectedControls.size) {
          return true;
        }
        var ids = (tr.getAttribute("data-controls") || "").split(",");
        return ids.some(function (id) {
          return selectedControls.has(id);
        });
      });
      wireControlFilter(dt, selectedControls);

      srSelectable({
        dt: dt,
        tableEl: tableEl,
        card: root.querySelector(".sr-table-card"),
        reload: renderPendingRisks,
        actions: [
          {
            key: "push",
            label: L("PushToRisk"),
            variant: "btn-success",
            run: function (ids, ui) {
              confirmModal({
                title: L("PushToRisk"),
                body: _n(L("ConfirmPushSelectedPendingRisks"), ids.length),
                primaryLabel: L("PushToRisk"),
                primaryClass: "btn-success",
                onConfirm: function () {
                  ui.busy(L("Pushing"));
                  runSequential(ids, pushPendingRisk, function (i, n) {
                    ui.progress(L("Pushing") + " " + i + "/" + n);
                  }).then(function (summary) {
                    reloadWithBulkSummary(ui.reload, summary);
                  });
                },
              });
            },
          },
          {
            key: "delete",
            label: L("Delete"),
            variant: "btn-danger",
            run: function (ids, ui) {
              confirmModal({
                title: L("Delete"),
                body: _n(L("ConfirmDeleteSelectedPendingRisks"), ids.length),
                primaryLabel: L("Delete"),
                primaryClass: "btn-danger",
                onConfirm: function () {
                  ui.busy(L("Deleting"));
                  runSequential(ids, deletePendingRisk).then(function (summary) {
                    reloadWithBulkSummary(ui.reload, summary);
                  });
                },
              });
            },
          },
        ],
      });
    });
  }

  // Enhance the control multi-select with selectize (chips) when available,
  // falling back to the native multiple <select>; either way, changes update the
  // selection set and redraw the table.
  function wireControlFilter(dt, selectedControls) {
    var sel = document.getElementById("sa-pr-control-filter");
    if (!sel) {
      return;
    }
    function apply(values) {
      selectedControls.clear();
      (values || []).forEach(function (v) {
        selectedControls.add(String(v));
      });
      dt.draw();
    }
    if (typeof jQuery !== "undefined" && jQuery.fn && jQuery.fn.selectize) {
      var inst = jQuery(sel).selectize({
        plugins: ["remove_button"],
        onChange: function (vals) {
          apply(Array.isArray(vals) ? vals : vals ? [vals] : []);
        },
      });
      void inst;
    } else {
      sel.onchange = function () {
        apply(
          Array.prototype.slice
            .call(sel.selectedOptions)
            .map(function (o) {
              return o.value;
            })
        );
      };
    }
  }

  // Sequentially run an async op over ids (avoids hammering the API with a burst
  // and keeps the "N/M" progress meaningful). doOne(id) -> Promise<bool ok>.
  // Resolves with { ok, fail, total } so a bulk caller can report partial
  // failure rather than swallowing it.
  function runSequential(ids, doOne, onProgress) {
    var i = 0,
      ok = 0,
      fail = 0;
    function next() {
      if (i >= ids.length) {
        return Promise.resolve({ ok: ok, fail: fail, total: ids.length });
      }
      var id = ids[i++];
      if (onProgress) {
        onProgress(i, ids.length);
      }
      return doOne(id).then(function (succeeded) {
        if (succeeded) {
          ok++;
        } else {
          fail++;
        }
        return next();
      });
    }
    return next();
  }

  // Reload the current view, then — if a bulk run had partial failure — surface
  // a persistent summary. Appended to document.body so it survives the reload's
  // re-render of the app root (a banner inside root would be wiped). doOne's
  // per-item error banners are transient; this is the durable roll-up.
  function reloadWithBulkSummary(reload, summary) {
    reload();
    if (!summary || !summary.fail) {
      return;
    }
    var existing = document.getElementById("sa-bulk-summary");
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }
    var el = document.createElement("div");
    el.id = "sa-bulk-summary";
    el.className = "alert alert-warning sa-bulk-summary";
    el.setAttribute("role", "alert");
    el.textContent = _n(L("BulkPartialFailure"), summary.ok).replace(
      "{total}",
      summary.total,
    );
    document.body.appendChild(el);
    window.setTimeout(function () {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, 8000);
  }

  function pushPendingRisk(id) {
    return apiFetch("/self-assessments/pending-risks/" + id + "/push", {
      method: "POST",
      body: JSON.stringify({ owner: 0 }),
    }).then(function (res) {
      return apiOk(res, 200);
    });
  }

  function deletePendingRisk(id) {
    return apiFetch("/self-assessments/pending-risks/" + id, {
      method: "DELETE",
    }).then(function (res) {
      return apiOk(res, 200);
    });
  }

  // ---- Failed Controls tab ----
  // A DataTable of every answered control response across completed
  // assessments, styled with the .sr-table-card design (Task C). A status
  // filter (Fail default / Pass / N-A / All) re-fetches from the API; the
  // table then paginates the returned page client-side.

  // Soft state pill for the recorded Answer (pass/fail/na → Yes/No/N-A).
  function answerPill(ans) {
    var map = {
      pass: ["sr-state-success", L("Yes")],
      fail: ["sr-state-danger", L("No")],
      na: ["sr-state-neutral", L("NotApplicable")],
    };
    var m = map[ans];
    if (!m) {
      return esc(ans || "");
    }
    return '<span class="sr-state-pill ' + m[0] + '">' + esc(m[1]) + "</span>";
  }

  // Soft state pill for the derived control_status (1 → Pass, 0 → Fail,
  // null → em dash).
  function controlStatusPill(cs) {
    if (cs === 1) {
      return (
        '<span class="sr-state-pill sr-state-success">' +
        esc(L("Pass")) +
        "</span>"
      );
    }
    if (cs === 0) {
      return (
        '<span class="sr-state-pill sr-state-danger">' +
        esc(L("Fail")) +
        "</span>"
      );
    }
    return "&mdash;";
  }

  // Initialize the client-side DataTable over the pre-built <tbody>.
  function initFailedTable() {
    var el = document.getElementById("sa-failed-table");
    if (!el) {
      return;
    }
    // Same shared .sr-table-card DataTable as the index / pending tables:
    // initSrTable applies the classic-chrome `dom` and relocates the search box
    // into the toolbar tools (to the left of the #sa-failed-status filter, which
    // is the tools' first child). API already returns date-descending.
    initSrTable(el, { order: [[0, "desc"]] });
  }

  // The API hard-caps per_page at 200 (api_v2_self_assessment_control_results
  // and the underlying query both clamp it). A single fetch therefore
  // silently truncates any status filter that matches more than 200 rows —
  // unacceptable for a compliance view. FAILED_CONTROLS_CAP is a defensive
  // upper bound on how many rows we'll page in client-side so a
  // pathologically large result set can't hang the page.
  var FAILED_CONTROLS_PAGE_SIZE = 200;
  var FAILED_CONTROLS_CAP = 2000;

  // Pages through /self-assessments/control-results for the given status
  // filter, concatenating rows until either every matching row has been
  // fetched (accumulated.length >= total), the defensive cap is hit, or a
  // page comes back empty (guards against a total that never resolves to
  // "done"). If a page request fails, apiOk() has already shown the generic
  // error banner — stop paging and resolve with whatever was accumulated so
  // far. Returns a Promise<{ rows, total, capped }>; `capped` is true
  // whenever the returned rows are known to be incomplete (cap hit or a
  // page failed partway through).
  function fetchAllControlResults(status) {
    var rows = [];
    var total = 0;

    function fetchPage(page) {
      return apiFetch(
        "/self-assessments/control-results?status=" +
          encodeURIComponent(status) +
          "&per_page=" +
          FAILED_CONTROLS_PAGE_SIZE +
          "&page=" +
          page,
      ).then(function (res) {
        if (!apiOk(res, 200)) {
          return { rows: rows, total: total, capped: rows.length < total };
        }
        var data = res.body.data || {};
        var pageRows = data.control_results || [];
        if (typeof data.total === "number") {
          total = data.total;
        }
        rows = rows.concat(pageRows);

        if (
          pageRows.length === 0 ||
          rows.length >= total ||
          rows.length >= FAILED_CONTROLS_CAP
        ) {
          return { rows: rows, total: total, capped: rows.length < total };
        }
        return fetchPage(page + 1);
      });
    }

    return fetchPage(1);
  }

  function renderFailedControls() {
    clearRowFilters();
    var status = state.failedStatus || "fail";

    // Destroy any prior instance before we wipe the DOM node it lives on.
    var prior = document.getElementById("sa-failed-table");
    if (
      prior &&
      typeof jQuery !== "undefined" &&
      jQuery.fn &&
      jQuery.fn.dataTable &&
      jQuery.fn.dataTable.isDataTable(prior)
    ) {
      jQuery(prior).DataTable().destroy();
    }

    // Page through the full result set for this status filter (the API
    // hard-caps per_page at 200), then build the client-side DataTable over
    // everything so search/sort/pagination still work across the complete
    // filtered set rather than just the first page.
    fetchAllControlResults(status).then(function (result) {
      // If the filter changed (or the user left this view) while this paged
      // fetch was in flight, drop the stale result rather than render it.
      if (state.failedStatus !== status) {
        return;
      }
      setBreadcrumb(crumbBase().concat([{ label: L("FailedControls") }]));
      var rows = result.rows || [];
      var capped = !!result.capped;

      var filterOptions = [
        { v: "fail", label: L("Fail") },
        { v: "pass", label: L("Pass") },
        { v: "na", label: L("NotApplicable") },
        { v: "all", label: L("All") },
      ];
      var optionsHtml = filterOptions
        .map(function (o) {
          return (
            '<option value="' +
            esc(o.v) +
            '"' +
            (o.v === status ? " selected" : "") +
            ">" +
            esc(o.label) +
            "</option>"
          );
        })
        .join("");

      var toolbar =
        '<div class="sr-table-toolbar">' +
        '<div class="sr-table-title">' +
        esc(L("FailedControls")) +
        "</div>" +
        '<div class="sr-table-tools">' +
        '<select class="form-select sr-table-status-filter" id="sa-failed-status">' +
        optionsHtml +
        "</select></div></div>";

      // capped is only true when the accumulated rows are known to be
      // incomplete (the defensive fetch cap was hit, or a page failed
      // partway through paging) — never shown for a complete result set.
      var truncatedNotice = capped
        ? '<div class="text-muted small sa-truncated-notice">' +
          esc(L("ControlResultsTruncated")) +
          "</div>"
        : "";

      var html =
        tabsHtml("failed") +
        '<div class="sr-table-card">' +
        toolbar +
        truncatedNotice;

      if (!rows.length) {
        html +=
          '<p class="sa-empty">' + esc(L("NoFailedControls")) + "</p></div>";
        root.innerHTML = html;
        wireFailed();
        return;
      }

      var head =
        "<thead><tr>" +
        "<th>" +
        esc(L("Date")) +
        "</th><th>" +
        esc(L("Framework")) +
        "</th><th>" +
        esc(L("ControlID")) +
        "</th><th>" +
        esc(L("Control")) +
        "</th><th>" +
        esc(L("Question")) +
        "</th><th>" +
        esc(L("Answer")) +
        "</th><th>" +
        esc(L("ControlStatus")) +
        "</th></tr></thead>";

      // Every interpolated server value passes through esc(); the pills wrap
      // esc()'d labels in fixed, trusted markup.
      var body = rows
        .map(function (r) {
          return (
            "<tr><td>" +
            esc(r.assessment_date) +
            "</td><td>" +
            esc(r.framework) +
            "</td><td>" +
            esc(r.control_number) +
            "</td><td>" +
            esc(r.short_name) +
            "</td><td>" +
            esc(r.question) +
            "</td><td>" +
            answerPill(r.answer) +
            "</td><td>" +
            controlStatusPill(r.control_status) +
            "</td></tr>"
          );
        })
        .join("");

      html +=
        '<div class="sr-table-scroll">' +
        '<table class="sr-table" id="sa-failed-table" style="width:100%">' +
        head +
        "<tbody>" +
        body +
        "</tbody></table></div></div>";

      root.innerHTML = html;
      wireFailed();
      initFailedTable();
    });
  }

  // ---- Wiring (event delegation) ----
  // Shared by the index and pending-risks views so both tabs stay clickable
  // regardless of which one is currently rendered.
  function wireTabs() {
    root.querySelectorAll(".sa-tab").forEach(function (t) {
      t.onclick = function () {
        if (t.dataset.tab === "pending") {
          renderPendingRisks();
        } else if (t.dataset.tab === "failed") {
          renderFailedControls();
        } else {
          renderIndex();
        }
      };
    });
  }
  function wireFailed() {
    wireTabs();
    var sel = document.getElementById("sa-failed-status");
    if (sel) {
      sel.onchange = function () {
        state.failedStatus = sel.value;
        renderFailedControls();
      };
    }
  }
  function wireIndex() {
    wireTabs();
    // The "+ New" button lives in the page-header action slot and is wired by
    // setBreadcrumb's onClick.
    root.querySelectorAll("[data-resume-id]").forEach(function (b) {
      b.onclick = function () {
        loadRun(+b.dataset.resumeId);
      };
    });
    root.querySelectorAll("[data-view-id]").forEach(function (b) {
      b.onclick = function () {
        loadRun(+b.dataset.viewId);
      };
    });
    root.querySelectorAll("[data-del-id]").forEach(function (b) {
      b.onclick = function () {
        confirmModal({
          title: L("Delete"),
          body: L("ConfirmDeleteSelfAssessment"),
          primaryLabel: L("Delete"),
          primaryClass: "btn-danger",
          onConfirm: function () {
            lockBtn(b);
            deleteSelfAssessment(b.dataset.delId).then(function (ok) {
              if (ok) {
                renderIndex();
              } else {
                unlockBtn(b);
              }
            });
          },
        });
      };
    });
  }

  function deleteSelfAssessment(id) {
    return apiFetch("/self-assessments/" + id, { method: "DELETE" }).then(
      function (res) {
        return apiOk(res, 200);
      }
    );
  }
  function wirePicker() {
    root.querySelectorAll(".sa-start").forEach(function (b) {
      b.onclick = function () {
        startRun(+b.dataset.scf);
      };
    });
    root.querySelectorAll("[data-scope]").forEach(function (b) {
      b.onclick = function () {
        state.scope = b.dataset.scope;
        renderPicker();
      };
    });
    var s = root.querySelector(".sa-search");
    if (s) {
      s.oninput = function () {
        filterFrameworks(s.value);
      };
    }
  }
  function wireQuestionnaire() {
    root.querySelectorAll(".sa-seg-btn").forEach(function (b) {
      b.onclick = function () {
        var cid = b.dataset.cid;
        state.responses[cid] = state.responses[cid] || { comment: "" };
        state.responses[cid].response = b.dataset.resp;
        renderQuestionnaire();
      };
    });
    document.getElementById("sa-save").onclick = function () {
      saveProgress().then(function (res) {
        if (apiOk(res, 200)) {
          flashSaved();
        }
      });
    };
    document.getElementById("sa-complete").onclick = completeRun;
    document.getElementById("sa-back").onclick = function () {
      if (state.domainIndex > 0) {
        saveProgress().then(function (res) {
          if (apiOk(res, 200)) {
            state.domainIndex--;
            renderQuestionnaire();
          }
        });
      }
    };
    document.getElementById("sa-next").onclick = function () {
      var d = domains();
      if (state.domainIndex < d.order.length - 1) {
        saveProgress().then(function (res) {
          if (apiOk(res, 200)) {
            state.domainIndex++;
            renderQuestionnaire();
          }
        });
      } else {
        // Last domain: the footer button is a green "Mark Complete".
        completeRun();
      }
    };
    root.querySelectorAll(".sa-chip").forEach(function (c) {
      c.onclick = function () {
        saveProgress().then(function (res) {
          if (apiOk(res, 200)) {
            state.domainIndex = +c.dataset.domain;
            renderQuestionnaire();
          }
        });
      };
    });
  }
  function wirePending() {
    wireTabs();
    root.querySelectorAll("[data-delpr-id]").forEach(function (b) {
      b.onclick = function () {
        confirmModal({
          title: L("Delete"),
          body: L("ConfirmDeletePendingRisk"),
          primaryLabel: L("Delete"),
          primaryClass: "btn-danger",
          onConfirm: function () {
            lockBtn(b);
            deletePendingRisk(b.dataset.delprId).then(function (ok) {
              if (ok) {
                renderPendingRisks();
              } else {
                unlockBtn(b);
              }
            });
          },
        });
      };
    });
    root.querySelectorAll("[data-push-id]").forEach(function (b) {
      b.onclick = function () {
        lockBtn(b);
        pushPendingRisk(b.dataset.pushId).then(function (ok) {
          if (ok) {
            renderPendingRisks();
          } else {
            unlockBtn(b);
          }
        });
      };
    });
  }

  function filterFrameworks(term) {
    term = (term || "").toLowerCase();
    root.querySelectorAll(".sa-fw").forEach(function (el) {
      el.style.display =
        el.textContent.toLowerCase().indexOf(term) >= 0 ? "" : "none";
    });
  }
  function flashSaved() {
    /* optional toast; keep minimal */
  }
  function esc(s) {
    var d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  // ---- Boot ----
  renderIndex();
})();
