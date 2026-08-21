/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

(function () {
  'use strict';

  const POLL_INTERVAL_MS = 60000;

  // Derive API base from the server-resolved BASE_URL global (set in header.php).
  // Falling back to a pathname regex is fragile under non-standard install paths,
  // so we prefer the explicit value the server already provides.
  const API_BASE = (window.BASE_URL || '').replace(/\/$/, '') + '/api/v2';

  const labels = {};
  let badgeEl, toggleEl, pollTimer;

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    toggleEl = document.querySelector('.notifications-toggle');
    if (!toggleEl) return;

    badgeEl = toggleEl.querySelector('.notifications-badge');
    readLabels(toggleEl);
    startPolling();
    refreshCounts();

    document.addEventListener('visibilitychange', function() {
      if (document.visibilityState === 'visible') {
        refreshCounts();
        startPolling();
      } else {
        stopPolling();
      }
    });

    toggleEl.addEventListener('click', function(e) {
      e.preventDefault();
      togglePanel();
    });
  }

  function readLabels(el) {
    for (var i = 0; i < el.attributes.length; i++) {
      var attr = el.attributes[i];
      if (attr.name.indexOf('data-label-') === 0) {
        labels[attr.name.slice('data-label-'.length)] = attr.value;
      }
    }
  }

  function startPolling() {
    stopPolling();
    pollTimer = setInterval(refreshCounts, POLL_INTERVAL_MS);
  }

  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function refreshCounts() {
    fetch(API_BASE + '/notifications/counts', { credentials: 'same-origin' })
      .then(function(res) { return res.ok ? res.json() : null; })
      .then(function(json) {
        if (!json) return;
        var unread = (json.data && json.data.unread) || 0;
        updateBadge(unread);
      })
      .catch(function() { /* ignore polling errors */ });
  }

  function updateBadge(n) {
    if (!badgeEl) return;
    if (n <= 0) {
      badgeEl.setAttribute('hidden', '');
      badgeEl.textContent = '0';
    } else {
      badgeEl.removeAttribute('hidden');
      badgeEl.textContent = n >= 100 ? '99+' : String(n);
    }
  }

  // ===========================================================================
  // Panel
  // ===========================================================================

  let panelEl;
  let activeTab = sessionStorage.getItem('notifications.activeTab') || 'unread';
  let panelData = { items: [], total: 0, counts: { unread: 0, all: 0, trash: 0 } };

  function togglePanel() {
    if (!panelEl) panelEl = buildPanel();
    if (panelEl.hasAttribute('hidden')) {
      panelEl.removeAttribute('hidden');
      refreshPanel();
    } else {
      panelEl.setAttribute('hidden', '');
    }
  }

  function buildPanel() {
    // All interpolated values are passed through escapeHtml() — no raw user content.
    const tpl = '' +
      '<div class="notifications-panel" hidden>' +
        '<div class="notifications-panel__header">' +
          '<span><i class="fas fa-bell notifications-panel__header-icon"></i> ' + escapeHtml(labels.notifications) + '</span>' +
          '<span class="notifications-panel__close"><i class="fas fa-times"></i></span>' +
        '</div>' +
        '<div class="notifications-panel__tabs">' +
          '<button class="notifications-panel__tab" data-tab="unread">' + escapeHtml(labels.unread) + ' <span class="count">0</span></button>' +
          '<button class="notifications-panel__tab" data-tab="all">' + escapeHtml(labels.all) + ' <span class="count">0</span></button>' +
          '<button class="notifications-panel__tab" data-tab="trash">' + escapeHtml(labels.trash) + ' <span class="count">0</span></button>' +
        '</div>' +
        '<div class="notifications-panel__toolbar">' +
          '<input type="checkbox" class="select-all"> <span>' + escapeHtml(labels.selectall) + '</span>' +
          '<div class="bulk-actions" hidden></div>' +
        '</div>' +
        '<div class="notifications-panel__list"></div>' +
      '</div>';
    const tmp = document.createElement('div');
    tmp.innerHTML = tpl; // safe: tpl contains only string literals + escapeHtml()-encoded label values
    const el = tmp.firstElementChild;
    toggleEl.parentNode.appendChild(el);
    wirePanel(el);
    attachListEvents(el);
    return el;
  }

  function wirePanel(el) {
    el.querySelector('.notifications-panel__close').addEventListener('click', function() { el.setAttribute('hidden', ''); });
    el.querySelectorAll('.notifications-panel__tab').forEach(function(btn) {
      btn.addEventListener('click', function() { switchTab(btn.dataset.tab); });
    });
    document.addEventListener('click', function(e) {
      if (el.hasAttribute('hidden')) return;
      if (!el.contains(e.target) && !toggleEl.contains(e.target)) el.setAttribute('hidden', '');
    });
  }

  function switchTab(tab) {
    activeTab = tab;
    sessionStorage.setItem('notifications.activeTab', tab);
    refreshPanel();
  }

  function refreshPanel() {
    if (!panelEl) return;
    Promise.all([
      fetch(API_BASE + '/notifications?tab=' + encodeURIComponent(activeTab) + '&limit=50', { credentials: 'same-origin' }).then(function(r) { return r.json(); }),
      fetch(API_BASE + '/notifications/counts', { credentials: 'same-origin' }).then(function(r) { return r.json(); }),
    ]).then(function(results) {
      const listJson = results[0];
      const countsJson = results[1];
      panelData.items = (listJson.data && listJson.data.items) || [];
      panelData.counts = countsJson.data || { unread: 0, all: 0, trash: 0 };
      renderTabs();
      renderList();
      updateBadge(panelData.counts.unread);
    }).catch(function() { /* swallow */ });
  }

  function renderTabs() {
    panelEl.querySelectorAll('.notifications-panel__tab').forEach(function(btn) {
      const t = btn.dataset.tab;
      btn.classList.toggle('active', t === activeTab);
      btn.querySelector('.count').textContent = panelData.counts[t] || 0;
    });
  }

  function renderList() {
    const listEl = panelEl.querySelector('.notifications-panel__list');
    if (panelData.items.length === 0) {
      const emptyKey = activeTab === 'trash' ? labels.nothingintrash : labels.nonotifications;
      // safe: only string literals and escapeHtml()-encoded label values
      listEl.innerHTML = '<div class="notifications-panel__empty">' +
        '<i class="far fa-bell-slash"></i><div>' + escapeHtml(emptyKey) + '</div></div>';
      return;
    }
    // safe: rowHtml() escapes all API-sourced values via escapeHtml() / stripHtml()
    listEl.innerHTML = panelData.items.map(rowHtml).join('');
  }

  function rowHtml(item) {
    const isUnread = !item.read_at && activeTab !== 'trash';
    const promo = item.is_promo
      ? ' <span class="promo-tag">' + escapeHtml(labels.promo) + '</span>' : '';
    // All item fields are passed through escapeHtml() or stripHtml()+escapeHtml()
    return '' +
      '<div class="notifications-panel__row ' + (isUnread ? '' : 'read') + '" data-id="' + escapeHtml(String(item.id)) + '">' +
        (isUnread ? '<div class="unread-dot"></div>' : '') +
        '<div><input type="checkbox" class="row-cb" data-id="' + escapeHtml(String(item.id)) + '"></div>' +
        '<div class="row-body">' +
          '<div class="title-line">' +
            '<div class="title">' + escapeHtml(item.title) + promo + '</div>' +
            '<div class="timestamp">' + escapeHtml(relativeTime(item.created_at)) + '</div>' +
          '</div>' +
          '<div class="preview">' + escapeHtml(stripHtml(item.body)) + '</div>' +
        '</div>' +
      '</div>';
  }

  // ===========================================================================
  // Actions
  // ===========================================================================

  function attachListEvents(el) {
    const listEl = el.querySelector('.notifications-panel__list');

    listEl.addEventListener('click', function(e) {
      const cb = e.target.closest('.row-cb');
      if (cb) { e.stopPropagation(); updateBulkBar(); return; }

      const row = e.target.closest('.notifications-panel__row');
      if (!row) return;

      const item = panelData.items.find(function(i) { return String(i.id) === String(row.dataset.id); });
      if (item) openDetailModal(item);
    });

    el.querySelector('.select-all').addEventListener('change', function(e) {
      const checked = e.target.checked;
      el.querySelectorAll('.row-cb').forEach(function(c) { c.checked = checked; });
      updateBulkBar();
    });
  }

  function updateBulkBar() {
    const selectedIds = Array.from(panelEl.querySelectorAll('.row-cb:checked')).map(function(c) { return parseInt(c.dataset.id, 10); });
    // Keep the row highlight in sync with each checkbox (drives .notifications-panel__row.selected).
    panelEl.querySelectorAll('.row-cb').forEach(function(c) {
      const r = c.closest('.notifications-panel__row');
      if (r) r.classList.toggle('selected', c.checked);
    });
    const toolbar = panelEl.querySelector('.notifications-panel__toolbar');
    const bulk    = panelEl.querySelector('.bulk-actions');

    if (selectedIds.length === 0) {
      toolbar.classList.remove('notifications-panel__toolbar--bulk');
      panelEl.classList.remove('bulk-mode');
      bulk.setAttribute('hidden', '');
      bulk.innerHTML = '';
      return;
    }

    toolbar.classList.add('notifications-panel__toolbar--bulk');
    panelEl.classList.add('bulk-mode');
    bulk.removeAttribute('hidden');

    const isTrash = activeTab === 'trash';
    const primaryLabel = labels.markread;
    const secondaryLabel = isTrash ? labels.restore : labels.delete;
    const secondaryAction = isTrash ? 'restore' : 'trash';

    bulk.innerHTML = '' +
      '<button class="btn" data-bulk="mark_read"><i class="fas fa-check"></i>' + escapeHtml(primaryLabel) + '</button>' +
      '<button class="btn ' + (isTrash ? '' : 'danger') + '" data-bulk="' + secondaryAction + '">' +
        '<i class="fas fa-' + (isTrash ? 'undo' : 'trash') + '"></i>' + escapeHtml(secondaryLabel) +
      '</button>';

    bulk.querySelectorAll('button').forEach(function(btn) {
      btn.addEventListener('click', function() { runBulk(btn.dataset.bulk, selectedIds); });
    });
  }

  function runBulk(action, ids) {
    const path = {
      mark_read: '/notifications/mark-read',
      trash:     '/notifications/trash',
      restore:   '/notifications/restore',
    }[action];
    if (!path) return;

    fetch(API_BASE + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '',
      },
      body: JSON.stringify({ ids: ids }),
    })
    .then(function(res) { return res.json(); })
    .then(function(json) {
      panelData.counts = (json.data && json.data.counts) || panelData.counts;
      updateBadge(panelData.counts.unread);
      refreshPanel();
    })
    .catch(function() { /* swallow */ });
  }

  // ===========================================================================
  // Detail modal
  // ===========================================================================

  function openDetailModal(item) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.innerHTML = '' +
      '<div class="modal-dialog modal-dialog-centered">' +
        '<div class="modal-content">' +
          '<div class="modal-header">' +
            '<h5 class="modal-title">' + escapeHtml(item.title) + '</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
          '</div>' +
          '<div class="modal-body">' +
            '<div class="modal-notification-body"></div>' +
            '<p class="text-muted mt-3 modal-notification-timestamp">' + escapeHtml(relativeTime(item.created_at)) + '</p>' +
          '</div>' +
          '<div class="modal-footer">' +
            (item.link && /^https?:\/\//i.test(item.link) ? '<a class="btn btn-primary" href="' + escapeHtml(item.link) + '" target="_blank" rel="noopener noreferrer" data-action="view">' + escapeHtml(labels.view) + '</a>' : '') +
            '<button class="btn btn-secondary" data-bs-dismiss="modal">' + escapeHtml(labels.close) + '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    // Render body — server-side purify_html() is the sanitization layer; render
    // as stripped text in v1 (HTML markup in bodies is not yet supported).
    const bodyEl = modal.querySelector('.modal-notification-body');
    bodyEl.textContent = stripHtml(item.body || '');

    document.body.appendChild(modal);
    // bootstrap.Modal is loaded globally
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    modal.addEventListener('hidden.bs.modal', function() { modal.remove(); });

    // Mark-as-read fires when the user opens the modal (if no link) OR when they click View
    if (!item.link) {
      runBulk('mark_read', [item.id]);
    } else {
      const viewBtn = modal.querySelector('[data-action="view"]');
      if (viewBtn) viewBtn.addEventListener('click', function() { runBulk('mark_read', [item.id]); });
    }
  }

  // ===========================================================================
  // Helpers
  // ===========================================================================

  function escapeHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function stripHtml(s) {
    const div = document.createElement('div');
    div.innerHTML = s || ''; // safe: content is discarded; only textContent is read back
    return (div.textContent || div.innerText || '').trim();
  }

  function relativeTime(iso) {
    if (!iso) return '';
    const then = new Date(iso).getTime();
    const sec = Math.max(0, Math.floor((Date.now() - then) / 1000));
    if (sec < 60) return sec + labels.timeseconds;
    if (sec < 3600) return Math.floor(sec / 60) + labels.timeminutes;
    if (sec < 86400) return Math.floor(sec / 3600) + labels.timehours;
    if (sec < 86400 * 7) return Math.floor(sec / 86400) + labels.timedayunit;
    return new Date(iso).toLocaleDateString();
  }
})();
