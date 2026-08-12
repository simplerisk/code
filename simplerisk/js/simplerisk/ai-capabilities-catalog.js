/* AI Capabilities Catalog — client-rendered from the v2 API. */
(function () {
  "use strict";
  var mount = document.getElementById("ai-capabilities-catalog");
  if (!mount) return;

  var API = mount.dataset.apiBase;
  var S = JSON.parse(mount.dataset.strings || "{}");
  var DOMAIN_DOT = { Recommendations: "info", Risk: "risk", Documents: "docs", Controls: "controls", Assistant: "assistant" };
  // Per-capability card icons (inner SVG paths; keyed by the registry 'icon').
  var ICONS = {
    rec: '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 00-4 12.7c.6.5 1 1.2 1 2h6c0-.8.4-1.5 1-2A7 7 0 0012 2z"/>',
    risk: '<path d="M12 2L2 7v6c0 5 4 8 10 9 6-1 10-4 10-9V7z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
    fair: '<path d="M3 3v18h18"/><path d="M7 15l3-4 3 2 4-6"/>',
    doc: '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h6"/>',
    match: '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
    tmpl: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    ctrl: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/><path d="M11 8v6M8 11h6"/>',
    chat: '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'
  };

  var caps = [];
  var state = { q: "", domain: "all", tier: "all", filterState: "all" };

  function esc(s) { var d = document.createElement("div"); d.textContent = s == null ? "" : s; return d.innerHTML; }
  function escAttr(s) { return esc(s).replace(/"/g, "&quot;").replace(/'/g, "&#39;"); }

  function resolved(c) { return c.state || (c.locked ? "locked" : (c.enabled ? "enabled" : "disabled")); }

  function shell() {
    mount.innerHTML =
      '<div class="aic-toolbar">' +
        '<div class="aic-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg><input id="aic-q" type="search" placeholder="' + escAttr(S.search) + '"></div>' +
        '<span class="aic-count" id="aic-count"></span>' +
      '</div>' +
      '<div class="aic-filters" id="aic-filters">' +
        filterSet("tier", S.tier, [["all", S.all], ["core", S.free], ["extra", S.extra]]) +
        filterSet("domain", S.domain, [["all", S.all]].concat(Object.keys(S.domains || {}).map(function (k) { return [k, S.domains[k]]; }))) +
        filterSet("state", S.state, [["all", S.all], ["enabled", S.enabled], ["disabled", S.disabled], ["locked", S.locked]],
          '<div class="aic-bulk">' +
            '<button type="button" class="aic-bulkbtn" id="aic-enable-all">' + esc(S.enableAll) + '</button>' +
            '<button type="button" class="aic-bulkbtn" id="aic-disable-all">' + esc(S.disableAll) + '</button>' +
          '</div>') +
      '</div>' +
      '<div class="aic-grid" id="aic-grid"></div>' +
      '<div class="aic-empty" id="aic-empty" hidden><h3>' + esc(S.noMatch) + '</h3><p>' + esc(S.noMatchHint) + '</p>' +
        '<button class="btn" id="aic-clear">' + esc(S.clear) + '</button></div>';

    mount.querySelector("#aic-q").addEventListener("input", function (e) { state.q = e.target.value.trim().toLowerCase(); render(); });
    mount.querySelector("#aic-filters").addEventListener("click", onFilter);
    mount.querySelector("#aic-clear").addEventListener("click", clearAll);
    mount.querySelector("#aic-enable-all").addEventListener("click", function () { bulkSet(true); });
    mount.querySelector("#aic-disable-all").addEventListener("click", function () { bulkSet(false); });
  }

  // A capability can be (bulk-)toggled only when it isn't locked (Extra active)
  // and isn't always-on (forced enabled).
  function isToggleable(c) { return resolved(c) !== "locked" && !c.always_on; }

  // Enable/disable every toggleable capability in the CURRENT view (search +
  // filters), then re-render — so "Enable all" under the Documents filter only
  // touches the Documents cards you're looking at.
  function bulkSet(enabled) {
    var targets = caps.filter(matches).filter(isToggleable).filter(function (c) { return c.enabled !== enabled; });
    var enBtn = mount.querySelector("#aic-enable-all");
    var disBtn = mount.querySelector("#aic-disable-all");
    if (enBtn) enBtn.disabled = true;
    if (disBtn) disBtn.disabled = true;
    var failed = 0;
    Promise.all(targets.map(function (c) {
      return fetch(API + "/ai/capabilities/" + encodeURIComponent(c.id), {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ enabled: enabled }),
      }).then(function (r) { if (r.ok) { c.enabled = enabled; } else { failed++; } }).catch(function () { failed++; });
    })).then(function () {
      if (enBtn) enBtn.disabled = false;
      if (disBtn) disBtn.disabled = false;
      // render() re-reads each card's true c.enabled, so a failed toggle stays
      // visually correct — but a silent partial failure still needs surfacing,
      // mirroring the single-toggle revert and the context auto-save toastr.
      render();
      if (failed > 0 && window.toastr) { toastr.error(S.bulkError); }
    });
  }

  function filterSet(group, label, opts, extra) {
    return '<div class="aic-set" data-group="' + group + '"><span class="aic-setlbl">' + esc(label) + '</span>' +
      opts.map(function (o, i) {
        return '<button class="aic-chip' + (i === 0 ? " on" : "") + '" data-v="' + escAttr(o[0]) + '">' + esc(o[1]) + "</button>";
      }).join("") + (extra || "") + "</div>";
  }

  function onFilter(e) {
    var chip = e.target.closest(".aic-chip"); if (!chip) return;
    var set = chip.closest(".aic-set");
    set.querySelectorAll(".aic-chip").forEach(function (c) { c.classList.remove("on"); });
    chip.classList.add("on");
    state[set.dataset.group === "state" ? "filterState" : set.dataset.group] = chip.dataset.v;
    render();
  }

  function clearAll() {
    state = { q: "", domain: "all", tier: "all", filterState: "all" };
    mount.querySelector("#aic-q").value = "";
    mount.querySelectorAll(".aic-set").forEach(function (set) {
      set.querySelectorAll(".aic-chip").forEach(function (c) { c.classList.toggle("on", c.dataset.v === "all"); });
    });
    render();
  }

  function matches(c) {
    var domains = c.domains || [c.domain];
    if (state.domain !== "all" && domains.indexOf(state.domain) === -1) return false;
    if (state.tier !== "all" && c.tier !== state.tier) return false;
    if (state.filterState !== "all") {
      var rs = resolved(c);
      var cmp = (rs === "needs_provider") ? "enabled" : rs;
      if (cmp !== state.filterState) return false;
    }
    if (state.q && (c.name + " " + c.description).toLowerCase().indexOf(state.q) === -1) return false;
    return true;
  }

  function card(c) {
    var rs = resolved(c);
    var tier = '<span class="aic-tier ' + (c.tier === "core" ? "free" : "extra") + '">' + esc(c.tier === "core" ? S.free : S.extra) + "</span>";
    var domain = (c.domains || [c.domain]).map(function (d) {
      return '<span class="aic-dchip"><span class="aic-dot ' + (DOMAIN_DOT[d] || "info") + '"></span>' +
        esc((S.domains && S.domains[d]) || d) + "</span>";
    }).join("");
    var foot;
    if (rs === "locked") {
      foot = '<span class="aic-surfaced">' + esc(c.surfaced_at) + '</span>' +
        '<button class="aic-lock" disabled>' + esc(c.extra_installed ? S.included : S.purchase) + "</button>";
    } else {
      foot = '<span class="aic-surfaced">' + esc(c.surfaced_at) + '</span>';
    }
    var hint = rs === "needs_provider" ? '<div class="aic-surfaced">' + esc(S.needsProvider) + "</div>" : "";
    var ico = '<span class="aic-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (ICONS[c.icon] || ICONS.rec) + "</svg></span>";
    // The top-right checkbox is the enabled control (checked = enabled). Absent on
    // locked cards; disabled (but shown checked) for always-on capabilities.
    var enableBox = (rs === "locked") ? "" :
      '<input type="checkbox" class="aic-enable" data-id="' + escAttr(c.id) + '"' +
      (c.enabled ? " checked" : "") + (c.always_on ? " disabled" : "") + '>';
    return '<div class="aic-card' + (rs === "locked" ? " locked" : "") + (c.enabled ? " enabled" : "") + '" data-id="' + escAttr(c.id) + '">' +
      '<div class="aic-top">' + ico + '<div class="aic-name">' + esc(c.name) + "</div>" + enableBox + "</div>" +
      '<div class="aic-desc">' + esc(c.description) + "</div>" +
      '<div class="aic-tags">' + domain + tier + "</div>" +
      '<div class="aic-foot">' + foot + "</div>" + hint + "</div>";
  }

  function render() {
    var grid = mount.querySelector("#aic-grid");
    var shown = caps.filter(matches);
    grid.innerHTML = shown.map(card).join("");
    mount.querySelector("#aic-empty").hidden = shown.length !== 0;
    grid.hidden = shown.length === 0;
    mount.querySelector("#aic-count").textContent = shown.length + " " + (shown.length === 1 ? S.countOne : S.countMany);
    grid.querySelectorAll(".aic-enable").forEach(function (inp) {
      inp.addEventListener("change", function () { toggle(inp.dataset.id, inp.checked, inp); });
    });
  }

  function toggle(id, enabled, inp) {
    inp.disabled = true;
    fetch(API + "/ai/capabilities/" + encodeURIComponent(id), {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ enabled: enabled }),
    }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      var c = caps.find(function (x) { return x.id === id; });
      if (c) c.enabled = enabled;
      var cardEl = inp.closest(".aic-card");
      if (cardEl) cardEl.classList.toggle("enabled", enabled);
    }).catch(function () {
      inp.checked = !enabled; // revert on failure
    }).then(function () { inp.disabled = false; });
  }

  function load() {
    fetch(API + "/ai/capabilities", { headers: { "Accept": "application/json" } })
      .then(function (r) { return r.json(); })
      .then(function (j) { caps = (j && j.data) || []; render(); })
      .catch(function () { mount.innerHTML = '<p class="aic-error">' + esc(S.loadError) + "</p>"; });
  }

  shell();
  load();
})();
