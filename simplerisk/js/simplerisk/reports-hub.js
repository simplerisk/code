/* Reports Hub — client-side tile catalog with search, filters, and
 * favorites. Driven by GET /api/v2/reports/catalog; renders tiles
 * client-side; uses jQuery.blockUI for the loading/error overlay.
 *
 * All user-facing strings come from window._lang (populated by SimpleRisk's
 * header.php from $lang via the page's required_localization_keys list).
 * Do not hardcode English text in this file.
 */

(function () {
    'use strict';

    // ---------------------------------------------------------------------------
    // Localization helper
    // Falls back to the key itself when missing (only happens during dev
    // before required_localization_keys are added on the page).
    // ---------------------------------------------------------------------------
    var L = function (k) { return (window._lang && window._lang[k]) || k; };

    // ---------------------------------------------------------------------------
    // Tag-to-lang-key mapping
    // Maps raw permission keys (used internally for matching) to the
    // localized-string key in $lang. Used for tile tag pills.
    // ---------------------------------------------------------------------------
    var TAG_TO_LANG_KEY = {
        riskmanagement:      'RiskManagement',
        compliance:          'Compliance',
        governance:          'Governance',
        asset:               'AssetManagement',
        incident_management: 'IncidentManagement',
    };
    var TAG_ORDER = ['riskmanagement', 'compliance', 'governance', 'asset', 'incident_management'];

    // ---------------------------------------------------------------------------
    // DOM root — bail out if not on a hub page
    // ---------------------------------------------------------------------------
    var root = document.querySelector('.hub');
    if (!root) { return; }

    var hubKind = root.dataset.hubKind || 'report';
    var grid    = root.querySelector('.hub__main') || root;

    // ---------------------------------------------------------------------------
    // BlockUI CSS constants — shared between block() and showError()
    // ---------------------------------------------------------------------------
    var BLOCK_UI_MESSAGE_CSS = {
        position:     'fixed',
        top:          '50%',
        left:         '50%',
        transform:    'translate(-50%, -50%)',
        background:   '#f9fafb',
        border:       '1px solid #ccc',
        borderRadius: '6px',
        fontSize:     '14px',
        color:        '#000',
        padding:      '12px 18px',
        textAlign:    'center',
        zIndex:       1050,
    };
    var BLOCK_UI_OVERLAY_CSS = {
        position:   'absolute',
        background: '#000',
        opacity:    0.3,
        cursor:     'wait',
        zIndex:     1040,
    };

    // ---------------------------------------------------------------------------
    // BlockUI helpers — reuse the same CSS shape as the SCF Extra
    // (fixed-position, light background, dimmed black overlay, opacity 0.3,
    // cursor:wait). Wrapping in block/unblock avoids duplicating the options.
    // ---------------------------------------------------------------------------
    var _blocked = false;

    function block(message) {
        message = String(message).replace(/\r\n|\n|\r/g, '<br>');
        if (!_blocked) {
            $(root).block({
                message:    message,
                css:        BLOCK_UI_MESSAGE_CSS,
                overlayCSS: BLOCK_UI_OVERLAY_CSS,
                centerX:    false,
                centerY:    false,
            });
            _blocked = true;
        } else {
            $(root).find('.blockMsg').html(message);
        }
    }

    function unblock() {
        if (_blocked) {
            $(root).unblock();
            _blocked = false;
        }
    }

    // ---------------------------------------------------------------------------
    // URL-state helpers
    // ---------------------------------------------------------------------------
    function getUrlState() {
        var params = new URLSearchParams(window.location.search);
        return {
            q:    params.get('q') || '',
            tag:  params.get('tag') || '',      // single chip key, or '' for 'all'
            fav:  params.get('fav') === '1',
            view: params.get('view') === 'list' ? 'list' : 'cards',
        };
    }

    // pushUrlState writes a single ?tag=<key> or ?fav=1 (not a csv list).
    // chip is a string: 'all', 'favorites', or a domain key.
    // view is 'cards' (default; omitted from URL) or 'list' (explicit).
    function pushUrlState(q, chip, fav, view) {
        var params = new URLSearchParams();
        if (q)                               { params.set('q', q); }
        if (fav)                             { params.set('fav', '1'); }
        else if (chip && chip !== 'all')     { params.set('tag', chip); }
        if (view === 'list')                 { params.set('view', 'list'); }
        var qs = params.toString();
        window.history.replaceState(null, '', qs ? ('?' + qs) : window.location.pathname);
    }

    // ---------------------------------------------------------------------------
    // Debounce utility
    // ---------------------------------------------------------------------------
    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var ctx  = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    // ---------------------------------------------------------------------------
    // Build a tile DOM element from a catalog entry
    // ---------------------------------------------------------------------------
    function buildTile(entry) {
        var a = document.createElement('div');
        a.className          = 'hub__tile';
        a.setAttribute('role',     'link');
        a.setAttribute('tabindex', '0');
        a.dataset.reportKey  = entry.key;
        a.dataset.tilePath   = '../' + entry.path;
        a.dataset.tags       = entry.tags.join(',');
        a.dataset.favorited  = entry.favorited ? '1' : '0';
        a.dataset.searchText = (entry.label + ' ' + entry.description).toLowerCase();

        var isFav = !!entry.favorited;

        // Star button
        var btn = document.createElement('button');
        btn.type        = 'button';
        btn.className   = 'hub__tile-star';
        btn.setAttribute('aria-pressed',  isFav ? 'true' : 'false');
        btn.setAttribute('aria-label',    isFav ? L('RemoveFromFavorites') : L('AddToFavorites'));
        btn.textContent = '★'; // ★
        a.appendChild(btn);

        // Title
        var h3 = document.createElement('h3');
        h3.textContent = entry.label;
        a.appendChild(h3);

        // Description
        var p = document.createElement('p');
        p.textContent = entry.description;
        a.appendChild(p);

        // Tag pills
        if (entry.tags && entry.tags.length) {
            var tagsDiv = document.createElement('div');
            tagsDiv.className = 'hub__tile-tags';
            entry.tags.forEach(function (tag) {
                var span = document.createElement('span');
                span.textContent = L(TAG_TO_LANG_KEY[tag] || tag);
                tagsDiv.appendChild(span);
            });
            a.appendChild(tagsDiv);
        }

        // Tile navigation: click navigates; keyboard Enter/Space also navigates
        a.addEventListener('click', function () {
            window.location.href = a.dataset.tilePath;
        });
        a.addEventListener('keydown', function (evt) {
            if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault();
                window.location.href = a.dataset.tilePath;
            }
        });

        return a;
    }

    // ---------------------------------------------------------------------------
    // Build a section element (heading + grid of tiles)
    // ---------------------------------------------------------------------------
    function buildSection(heading, tiles) {
        var section = document.createElement('div');
        section.className = 'hub__section';

        var h2 = document.createElement('h2');
        h2.textContent = heading;
        section.appendChild(h2);

        var tileGrid = document.createElement('div');
        tileGrid.className = 'hub__grid';
        tiles.forEach(function (tile) { tileGrid.appendChild(tile); });
        section.appendChild(tileGrid);

        return section;
    }

    // ---------------------------------------------------------------------------
    // Build a list-view <tr> for a catalog entry. Same data attributes as the
    // tile (data-report-key, data-tile-path, data-tags, data-favorited,
    // data-search-text) so the shared starButton + filter logic works on both.
    // ---------------------------------------------------------------------------
    function buildRow(entry) {
        var tr = document.createElement('tr');
        tr.className          = 'hub__row';
        tr.setAttribute('role',     'link');
        tr.setAttribute('tabindex', '0');
        tr.dataset.reportKey  = entry.key;
        tr.dataset.tilePath   = '../' + entry.path;
        tr.dataset.tags       = entry.tags.join(',');
        tr.dataset.favorited  = entry.favorited ? '1' : '0';
        tr.dataset.searchText = (entry.label + ' ' + entry.description).toLowerCase();

        var isFav = !!entry.favorited;

        // Star cell
        var starTd = document.createElement('td');
        starTd.className = 'hub__list-star-cell';
        var btn = document.createElement('button');
        btn.type        = 'button';
        btn.className   = 'hub__tile-star';
        btn.setAttribute('aria-pressed',  isFav ? 'true' : 'false');
        btn.setAttribute('aria-label',    isFav ? L('RemoveFromFavorites') : L('AddToFavorites'));
        btn.textContent = '★';
        starTd.appendChild(btn);
        tr.appendChild(starTd);

        // Content cell (title stacked over description)
        var contentTd = document.createElement('td');
        var title = document.createElement('div');
        title.className   = 'hub__list-title';
        title.textContent = entry.label;
        contentTd.appendChild(title);
        var desc = document.createElement('div');
        desc.className   = 'hub__list-desc';
        desc.textContent = entry.description;
        contentTd.appendChild(desc);
        tr.appendChild(contentTd);

        // Tags cell (pill list)
        var tagsTd = document.createElement('td');
        tagsTd.className = 'hub__list-tags-cell';
        if (entry.tags && entry.tags.length) {
            var tagsDiv = document.createElement('div');
            tagsDiv.className = 'hub__tile-tags';
            entry.tags.forEach(function (tag) {
                var span = document.createElement('span');
                span.textContent = L(TAG_TO_LANG_KEY[tag] || tag);
                tagsDiv.appendChild(span);
            });
            tagsTd.appendChild(tagsDiv);
        }
        tr.appendChild(tagsTd);

        // Whole row clickable; star button stops propagation in its own handler.
        tr.addEventListener('click', function () {
            window.location.href = tr.dataset.tilePath;
        });
        tr.addEventListener('keydown', function (evt) {
            if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault();
                window.location.href = tr.dataset.tilePath;
            }
        });

        return tr;
    }

    // ---------------------------------------------------------------------------
    // Build a list-view section: heading + <table> with <tbody> of rows.
    // No <thead>; the rows are self-explanatory (star + title/desc + tag pill).
    // ---------------------------------------------------------------------------
    function buildListSection(heading, rows) {
        var section = document.createElement('div');
        section.className = 'hub__section';

        var h2 = document.createElement('h2');
        h2.textContent = heading;
        section.appendChild(h2);

        var table = document.createElement('table');
        table.className = 'hub__list';
        var tbody = document.createElement('tbody');
        rows.forEach(function (row) { tbody.appendChild(row); });
        table.appendChild(tbody);
        section.appendChild(table);

        return section;
    }

    // ---------------------------------------------------------------------------
    // Empty-state element (rendered once, shown/hidden as needed)
    // ---------------------------------------------------------------------------
    var emptyEl = document.createElement('div');
    emptyEl.className    = 'hub__empty';
    emptyEl.style.display = 'none';

    function buildEmptyStateContent() {
        // Build using safe DOM methods so no user-supplied text reaches innerHTML
        while (emptyEl.firstChild) { emptyEl.removeChild(emptyEl.firstChild); }
        var msgNode = document.createTextNode(L('NoReportsMatch') + ' ');
        emptyEl.appendChild(msgNode);
        var clearLink = document.createElement('a');
        clearLink.href              = '#';
        clearLink.dataset.action    = 'clear-filters';
        clearLink.textContent       = L('ClearFilters');
        emptyEl.appendChild(clearLink);
    }

    // ---------------------------------------------------------------------------
    // State shared across filter/search interactions
    // ---------------------------------------------------------------------------
    var allEntries    = [];     // full filtered-by-kind catalog list
    var activeSearch  = '';
    var activeChip    = 'all';  // single selected chip: 'all', 'favorites', or a domain key
    var activeFavOnly = false;
    var activeView    = 'cards'; // 'cards' (default tile grid) or 'list' (compact table)

    // ---------------------------------------------------------------------------
    // View-aware dispatchers. The render-catalog body uses buildEntryNode()
    // and buildSectionFor() to stay agnostic about the active view mode.
    // ---------------------------------------------------------------------------
    function buildEntryNode(entry) {
        return activeView === 'list' ? buildRow(entry) : buildTile(entry);
    }

    function buildSectionFor(heading, entries) {
        var nodes = entries.map(buildEntryNode);
        return activeView === 'list'
            ? buildListSection(heading, nodes)
            : buildSection(heading, nodes);
    }

    // ---------------------------------------------------------------------------
    // Apply current search + filter state, show/hide tiles/rows and sections.
    // Only used when activeChip === 'all' (grouped layout); the single-chip
    // and favorites layouts are produced by a full renderCatalog() re-render.
    // ---------------------------------------------------------------------------
    function applyFilters() {
        var searchLower  = activeSearch.trim().toLowerCase();
        var visibleCount = 0;

        // Cards-mode tiles and list-mode rows share the data-search-text attribute.
        root.querySelectorAll('.hub__tile, .hub__row').forEach(function (node) {
            var matchesSearch = !searchLower ||
                (node.dataset.searchText || '').indexOf(searchLower) !== -1;

            var visible = matchesSearch;
            node.classList.toggle('is-hidden', !visible);
            if (visible) { visibleCount++; }
        });

        // Hide sections whose every entry is hidden (search narrows within the layout)
        root.querySelectorAll('.hub__section').forEach(function (section) {
            var anyVisible = section.querySelector(
                '.hub__tile:not(.is-hidden), .hub__row:not(.is-hidden)'
            );
            section.classList.toggle('is-empty', !anyVisible);
        });

        // Empty state
        emptyEl.style.display = visibleCount === 0 ? '' : 'none';
    }

    // ---------------------------------------------------------------------------
    // Render all sections into the grid container.
    //
    // Layout depends on activeChip:
    //   'all'       — favorites row (if any) + grouped domain sections with <h2>
    //   'favorites' — single grid of favorited entries (favorites section header)
    //   domain key  — favorites row of matching favorites (if any, no header) +
    //                 single flat grid of all matching entries, no domain <h2>
    // ---------------------------------------------------------------------------
    function renderCatalog(entries) {
        // Clear previous content (leave emptyEl attached — it will be re-appended)
        while (grid.firstChild) { grid.removeChild(grid.firstChild); }

        var searchLower = activeSearch.trim().toLowerCase();

        // Helper: does an entry pass the current search?
        function matchesSearch(e) {
            if (!searchLower) { return true; }
            return (e.label + ' ' + e.description).toLowerCase().indexOf(searchLower) !== -1;
        }

        if (activeChip === 'favorites') {
            // --- Favorites-only layout: single section with header, no domain grouping ---
            var favEntries = entries
                .filter(function (e) { return e.favorited && matchesSearch(e); })
                .slice()
                .sort(function (a, b) { return a.key < b.key ? -1 : a.key > b.key ? 1 : 0; });

            if (favEntries.length) {
                var favSection = buildSectionFor(L('Favorites'), favEntries);
                favSection.dataset.sectionType = 'favorites';
                grid.appendChild(favSection);
            }

        } else if (activeChip !== 'all') {
            // --- Single-domain layout: just the domain section ---
            // The favorites row is intentionally omitted when filtering by a
            // domain — favorited entries that match this domain still appear
            // in the main section, and the dedicated Favorites chip is one
            // click away if the user wants a favorites-only view.
            var domainKey = activeChip;

            var domainEntries = entries.filter(function (e) {
                return e.tags.indexOf(domainKey) !== -1 && matchesSearch(e);
            });

            if (domainEntries.length) {
                var domainHeading = L(TAG_TO_LANG_KEY[domainKey] || domainKey);
                var domainSection = buildSectionFor(domainHeading, domainEntries);
                domainSection.dataset.sectionTag = domainKey;
                grid.appendChild(domainSection);
            }

        } else {
            // --- 'all' layout: favorites row + grouped domain sections with <h2> ---

            // Favorites section
            var allFavEntries = entries
                .filter(function (e) { return e.favorited; })
                .slice()
                .sort(function (a, b) { return a.key < b.key ? -1 : a.key > b.key ? 1 : 0; });

            if (allFavEntries.length) {
                var allFavSection = buildSectionFor(L('Favorites'), allFavEntries);
                allFavSection.dataset.sectionType = 'favorites';
                grid.appendChild(allFavSection);
            }

            // Domain sections in fixed TAG_ORDER
            // Multi-tagged entries appear under every domain section they tag,
            // not just the first. E.g., an entry tagged [riskmanagement, asset]
            // renders in both the Risk Management section and the Asset section.
            TAG_ORDER.forEach(function (tag) {
                var tagged = entries.filter(function (e) {
                    return e.tags.indexOf(tag) !== -1;
                });
                if (!tagged.length) { return; }

                var sectionHeading = L(TAG_TO_LANG_KEY[tag] || tag);
                var section        = buildSectionFor(sectionHeading, tagged);
                section.dataset.sectionTag = tag;
                grid.appendChild(section);
            });
        }

        // Re-append empty state element
        grid.appendChild(emptyEl);

        // For 'all' chip, search is applied as a show/hide overlay over the grouped
        // layout; for single-chip layouts the search is baked into the render above.
        if (activeChip === 'all') {
            applyFilters();
        } else {
            // Determine empty state for non-'all' layouts
            var anyNode = grid.querySelector('.hub__tile, .hub__row');
            emptyEl.style.display = anyNode ? 'none' : '';
        }

        wireStarButtons();

        // Sync the page-breadcrumb title to the current chip + hub kind. Done
        // here (after the catalog has rendered) rather than in init() so it
        // wins the race against sidebar.php's #script_sidebarmenu onload
        // handler which sets the title to the active submenu's text.
        updatePageTitle();
    }

    // ---------------------------------------------------------------------------
    // Star toggle: optimistic update + API call with revert on failure
    // ---------------------------------------------------------------------------
    function wireStarButtonsIn(scopeEl) {
        scopeEl.querySelectorAll('.hub__tile-star').forEach(function (btn) {
            // Remove any prior listener by replacing with a clone
            var fresh = btn.cloneNode(true);
            btn.parentNode.replaceChild(fresh, btn);

            fresh.addEventListener('click', function (evt) {
                evt.preventDefault();
                evt.stopPropagation();

                var tile      = fresh.closest('.hub__tile, .hub__row');
                var reportKey = tile.dataset.reportKey;
                var isFav     = tile.dataset.favorited === '1';
                var newFav    = !isFav;

                // Optimistic update
                applyFavoritedState(tile, fresh, newFav);
                syncAllTilesForKey(reportKey, newFav);
                applyFilters();

                // API call
                var method = newFav ? 'POST' : 'DELETE';
                var url    = newFav
                    ? BASE_URL + '/api/v2/reports/favorites'
                    : BASE_URL + '/api/v2/reports/favorites/' + encodeURIComponent(reportKey);
                var fetchOptions = {
                    method:      method,
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json' },
                };
                // CSRF token required on POST; DELETE is exempt (csrf_check skips non-POST)
                if (newFav) {
                    fetchOptions.headers['CSRF-TOKEN'] =
                        (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '';
                    fetchOptions.body = JSON.stringify({ report_key: reportKey });
                }

                fetch(url, fetchOptions).then(function (resp) {
                    if (resp.status === 401) {
                        window.location.reload();
                        return;
                    }
                    if (!resp.ok) { throw new Error('HTTP ' + resp.status); }
                    refreshFavoritesSection();
                }).catch(function (err) {
                    console.error('hub: star toggle failed for', reportKey, err);
                    // Revert optimistic update
                    applyFavoritedState(tile, fresh, isFav);
                    syncAllTilesForKey(reportKey, isFav);
                    applyFilters();
                });
            });
        });
    }

    function wireStarButtons() {
        wireStarButtonsIn(root);
    }

    // Apply favorited visual state to a single tile and its star button
    function applyFavoritedState(tile, starBtn, isFav) {
        tile.dataset.favorited = isFav ? '1' : '0';
        starBtn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
        starBtn.setAttribute('aria-label', isFav ? L('RemoveFromFavorites') : L('AddToFavorites'));
        starBtn.textContent = isFav ? '★' : '☆';
    }

    // Sync the favorited state of all OTHER tiles/rows sharing a report key
    function syncAllTilesForKey(reportKey, isFav) {
        // CSS.escape is available in all modern browsers; provide inline fallback
        var escaped = (typeof CSS !== 'undefined' && CSS.escape)
            ? CSS.escape(reportKey)
            : reportKey.replace(/([!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~])/g, '\\$1');
        root.querySelectorAll(
            '.hub__tile[data-report-key="' + escaped + '"], ' +
            '.hub__row[data-report-key="' + escaped + '"]'
        ).forEach(function (node) {
            var sb = node.querySelector('.hub__tile-star');
            if (sb) { applyFavoritedState(node, sb, isFav); }
        });
    }

    // Rebuild the favorites section from current data-favorited state on
    // tiles/rows. After a star toggle the optimistic update has already
    // written updated data-favorited values to all rendered nodes, so we
    // read those back to sync allEntries, then do a full re-render so the
    // favorites row and flat grid/list are consistent with the current chip.
    function refreshFavoritesSection() {
        allEntries = allEntries.map(function (entry) {
            var escaped = (typeof CSS !== 'undefined' && CSS.escape)
                ? CSS.escape(entry.key)
                : entry.key.replace(/([!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~])/g, '\\$1');
            var node = root.querySelector(
                '.hub__tile[data-report-key="' + escaped + '"], ' +
                '.hub__row[data-report-key="' + escaped + '"]'
            );
            return Object.assign({}, entry, {
                favorited: node ? (node.dataset.favorited === '1') : entry.favorited,
            });
        });

        // Full re-render maintains correct layout for current chip + view
        renderCatalog(allEntries);
    }

    // ---------------------------------------------------------------------------
    // Wire search input
    // ---------------------------------------------------------------------------
    function wireSearch(input) {
        input.addEventListener('input', debounce(function () {
            activeSearch = input.value;
            // For 'all', search overlays in-place; for single-chip layouts re-render
            // so the search is correctly baked into the flat/favorites grid/list.
            if (activeChip === 'all') {
                applyFilters();
            } else {
                renderCatalog(allEntries);
            }
            pushUrlState(activeSearch, activeChip, activeFavOnly, activeView);
        }, 150));
    }

    // ---------------------------------------------------------------------------
    // Wire filter chips — single-select: clicking any chip deselects all others.
    // Domain chips no longer OR-combine; clicking a different domain chip
    // switches focus to that domain alone. 'Favorites' is mutually exclusive
    // with all other chips.
    // ---------------------------------------------------------------------------
    function wireChips(chipsContainer) {
        chipsContainer.querySelectorAll('button[data-chip]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var chip = btn.dataset.chip;

                // Single-select: clicking any chip deselects all others
                activeChip    = chip;
                activeFavOnly = (chip === 'favorites');

                syncChipAriaState(chipsContainer);
                // renderCatalog() also calls updatePageTitle() at the end —
                // no separate call needed here.
                renderCatalog(allEntries);
                pushUrlState(activeSearch, activeChip, activeFavOnly, activeView);
            });
        });
    }

    function syncChipAriaState(chipsContainer) {
        chipsContainer.querySelectorAll('button[data-chip]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.dataset.chip === activeChip ? 'true' : 'false');
        });
    }

    // ---------------------------------------------------------------------------
    // Update the page-breadcrumb title to reflect the active chip.
    //
    // The breadcrumb is server-rendered in sidebar.php as
    // <h4 class="page-title"> inside <div class="page-breadcrumb">. When the
    // user changes the chip filter we want the title to reflect what's now
    // being shown: "Reports" / "Dashboards" for 'all', "Favorites" for the
    // favorites chip, and "<Domain> Reports/Dashboards" for a domain chip.
    //
    // TODO i18n: composition is naive (chip-name + space + noun). Word order
    // is English-correct but may invert in some locales (e.g. Spanish: "Reportes
    // de Gestión de Riesgos"). Acceptable trade-off versus exploding into
    // 4-domain × 2-kind composed lang keys; revisit if Crowdin reports issues.
    // ---------------------------------------------------------------------------
    function updatePageTitle() {
        var titleEl = document.querySelector('div.page-breadcrumb h4.page-title');
        if (!titleEl) { return; }

        var noun = hubKind === 'dashboard' ? L('Dashboards') : L('Reports');
        var title;

        if (activeChip === 'favorites') {
            title = L('Favorites');
        } else if (activeChip === 'all') {
            title = noun;
        } else {
            var chipLabel = L(TAG_TO_LANG_KEY[activeChip] || activeChip);
            title = chipLabel + ' ' + noun;
        }

        titleEl.textContent = title;
    }

    // ---------------------------------------------------------------------------
    // Wire view-toggle buttons (Cards / List). Single-select like the chips.
    // ---------------------------------------------------------------------------
    function wireViewToggle(toggleContainer) {
        toggleContainer.querySelectorAll('button[data-view]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.dataset.view;
                if (view !== 'cards' && view !== 'list') { return; }
                if (view === activeView) { return; }

                activeView = view;
                syncViewToggleAriaState(toggleContainer);
                // Re-render: view determines tile-grid vs. list-table rendering
                renderCatalog(allEntries);
                pushUrlState(activeSearch, activeChip, activeFavOnly, activeView);
            });
        });
    }

    function syncViewToggleAriaState(toggleContainer) {
        toggleContainer.querySelectorAll('button[data-view]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.dataset.view === activeView ? 'true' : 'false');
        });
    }

    // ---------------------------------------------------------------------------
    // Hydrate URL state into controls.
    // Single-select: ?fav=1 selects the favorites chip; ?tag=<key> selects
    // that domain chip; neither present selects 'all'.
    // ---------------------------------------------------------------------------
    function hydrateUrlState(searchInput, chipsContainer, viewToggle) {
        var state = getUrlState();

        activeSearch = state.q;
        if (state.fav) {
            activeChip    = 'favorites';
            activeFavOnly = true;
        } else if (state.tag) {
            activeChip    = state.tag;
            activeFavOnly = false;
        } else {
            activeChip    = 'all';
            activeFavOnly = false;
        }
        activeView = state.view;

        if (searchInput) {
            searchInput.value = state.q;
        }
        if (chipsContainer) {
            syncChipAriaState(chipsContainer);
        }
        if (viewToggle) {
            syncViewToggleAriaState(viewToggle);
        }
    }

    // ---------------------------------------------------------------------------
    // Show error state with retry link
    // ---------------------------------------------------------------------------
    function showError() {
        // Build the blockUI error message using safe DOM construction.
        // blockUI accepts a jQuery object as its message option, so we build
        // the message as a DOM fragment and wrap it in jQuery before passing.
        var msgSpan = document.createElement('span');
        msgSpan.textContent = L('FailedToLoadReports');

        var retryLink = document.createElement('a');
        retryLink.id               = 'hub-retry';
        retryLink.href             = '#';
        retryLink.textContent      = L('Retry');
        retryLink.style.marginLeft = '0.5em';

        var wrapper = document.createElement('span');
        wrapper.appendChild(msgSpan);
        wrapper.appendChild(retryLink);

        // Pass the DOM wrapper directly to blockUI (jQuery object accepted as message).
        // We bypass the block() helper here so the DOM node is not coerced to a string.
        if (_blocked) {
            $(root).find('.blockMsg').empty().append(wrapper);
        } else {
            $(root).block({
                message:    $(wrapper),
                css:        BLOCK_UI_MESSAGE_CSS,
                overlayCSS: BLOCK_UI_OVERLAY_CSS,
                centerX:    false,
                centerY:    false,
            });
            _blocked = true;
        }

        // Attach retry handler via event delegation on root
        $(root).one('click', '#hub-retry', function (evt) {
            evt.preventDefault();
            loadCatalog();
        });
    }

    // ---------------------------------------------------------------------------
    // Fetch catalog and render
    // ---------------------------------------------------------------------------
    function loadCatalog() {
        block(L('LoadingReports'));

        var controller = new AbortController();
        var timeoutId  = setTimeout(function () { controller.abort(); }, 15000);

        fetch(BASE_URL + '/api/v2/reports/catalog', {
            credentials: 'same-origin',
            signal:      controller.signal,
        }).then(function (resp) {
            clearTimeout(timeoutId);
            if (resp.status === 401) {
                window.location.reload();
                return;
            }
            if (!resp.ok) { throw new Error('HTTP ' + resp.status); }
            return resp.json();
        }).then(function (json) {
            if (!json) { return; } // guard for 401 reload path (returns undefined)
            var reports = (json && json.data && json.data.reports) ? json.data.reports : [];

            // Filter to entries matching this hub's kind
            allEntries = reports.filter(function (e) { return e.kind === hubKind; });

            unblock();
            renderCatalog(allEntries);
        }).catch(function (err) {
            clearTimeout(timeoutId);
            console.error('hub: catalog fetch failed', err);
            showError();
        });
    }

    // ---------------------------------------------------------------------------
    // Initialise controls from DOM (the hub page renders these in the shell)
    // ---------------------------------------------------------------------------
    function init() {
        var searchInput    = root.querySelector('.hub__search input[type="search"]');
        var chipsContainer = root.querySelector('.hub__chips');
        var viewToggle     = root.querySelector('.hub__view-toggle');

        // Hydrate URL state before first render
        hydrateUrlState(searchInput, chipsContainer, viewToggle);

        // Wire up controls
        if (searchInput)    { wireSearch(searchInput); }
        if (chipsContainer) { wireChips(chipsContainer); }
        if (viewToggle)     { wireViewToggle(viewToggle); }

        // Wire "Clear filters" link via event delegation. Clears the search +
        // chip state but leaves activeView alone — view preference is
        // orthogonal to filters and persists through a clear.
        root.addEventListener('click', function (evt) {
            var target = evt.target;
            if (target && target.dataset.action === 'clear-filters') {
                evt.preventDefault();
                activeSearch  = '';
                activeChip    = 'all';
                activeFavOnly = false;
                if (searchInput)    { searchInput.value = ''; }
                if (chipsContainer) { syncChipAriaState(chipsContainer); }
                // renderCatalog() updates the page-breadcrumb title too.
                renderCatalog(allEntries);
                pushUrlState('', 'all', false, activeView);
            }
        });

        // Build empty-state content using safe DOM methods
        buildEmptyStateContent();

        // Load catalog
        loadCatalog();
    }

    // ---------------------------------------------------------------------------
    // Kick off once the DOM is ready
    // ---------------------------------------------------------------------------
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
