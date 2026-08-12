/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**
 * Connectivity Explorer.
 *
 * Renders the entity graph from GET /api/v2/ai/context/{type}/{id} -- the
 * same permission- and team-scoped bundle the AI/MCP consumer reads. Nothing
 * about the graph is computed here: the server decides what this caller may
 * see, which types cluster, and how neighbors rank.
 */
(function () {
    'use strict';

    const MOUNT_ID = 'sr-connectivity-explorer';

    // Server-resolved strings. Never hardcode user-facing English here.
    //
    // The global is `_lang` (see header.php, which emits `var _lang = {...}`
    // from the CUSTOM: key list and also defines a `window.L(k)` helper that
    // returns the KEY on a miss). It is NOT `__srLang`. Reading the wrong
    // global fails silently: every lookup falls through to the English
    // fallback, so the page looks perfect in English and is untranslated in
    // every other locale.
    //
    // A local L is kept rather than using window.L because we want a readable
    // English fallback on a miss rather than the raw key leaking into the UI.
    const L = (key, fallback) =>
        (window._lang && window._lang[key]) || fallback || key;

    // Node types that can appear as NEIGHBORS but are never a valid focal
    // type -- absent from ai_context_node_types() (includes/ai_context_graph.php),
    // so GET /ai/context/{type}/{id} 404s for either of them. Keep in sync
    // with that function: it is the source of truth, but there is no
    // server-emitted list to read this off at runtime (unlike the node-color
    // palette), so this is a small, rarely-changing, explicitly-flagged
    // duplicate rather than an extra round trip just to disable a re-centre.
    const NEIGHBOR_ONLY_TYPES = new Set(['test_result', 'self_assessment_result']);

    const state = {
        focal: null,          // {type, id}
        bundle: null,         // last API response payload
        trail: [],            // breadcrumb: [{type, id, name}]
        hiddenTypes: new Set(),
        depth: 1,
        selectedNodeId: null,
        expanded: {},         // type -> raised limit
        sigma: null,
        graph: null,
        palette: {},          // node-type -> color, emitted by the page shell
        connectionsDt: null,  // the live DataTables instance over the connections table, if any
        resizeObserver: null, // watches [data-role="canvas"]; see watchCanvasResize()
        selectionLayoutTimer: null, // pending inspector-column expand; see setSelection()
        // Canvas launchpad (docs/superpowers/specs/2026-07-27-connectivity-explorer-launchpad.md):
        // Level-1 type tiles / Level-2 browsable entity list, shown in
        // [data-role="canvas"] until a focal is chosen. `type` is the active
        // Level-2 type, or null while Level-1 tiles are showing.
        browse: {
            type: null,
            filter: '',
            cursor: null,
            hasMore: false,
            rows: [],
            loading: false,
        },
    };

    // The server's own default (api_v2_ai_context_entities_get(),
    // artificial_intelligence.php) -- passed explicitly rather than omitted so
    // the Load More page size is a named, visible constant here too.
    const BROWSE_PAGE_LIMIT = 50;

    // Mirrors ai_context_cluster_threshold() (includes/ai_context_graph.php)
    // -- the default per-type neighbor cap before a type is "clustered" and
    // needs an explicit expand.
    const CLUSTER_THRESHOLD = 25;
    // How much each "show more" click raises one type's limit by.
    const EXPAND_STEP = 50;
    // Mirrors ai_context_max_expand_limit() (includes/ai_context_graph.php)
    // -- the server's own hard ceiling on how many neighbors of one type a
    // single expand can ever return, regardless of how many times the caller
    // clicks "show more".
    const MAX_EXPAND_LIMIT = 500;

    /**
     * Build an API URL that survives a subpath install
     * (https://example.com/simplerisk/). Mirrors the convention used by the
     * other page scripts (BASE_URL, emitted by header.php from
     * $_SESSION['base_url'] / the simplerisk_base_url setting) -- do not
     * hardcode a leading /api/v2.
     */
    function apiUrl(path, params) {
        const base = (typeof BASE_URL !== 'undefined' && BASE_URL) ? BASE_URL : '';
        const url = new URL(base + '/api/v2' + path, window.location.origin);
        Object.entries(params || {}).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') {
                url.searchParams.set(k, v);
            }
        });
        return url.toString();
    }

    async function apiGet(path, params) {
        const res = await fetch(apiUrl(path, params), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const json = await res.json();
        return json.data;
    }

    function init(mount) {
        loadPalette(mount);
        mount.innerHTML = `
            <nav class="sr-connectivity-explorer__crumbs" aria-label="${escapeAttr(L('Breadcrumb', 'Breadcrumb'))}"
                 data-role="crumbs"></nav>
            <div class="sr-connectivity-explorer__layout">
                <div class="sr-connectivity-explorer__canvas-col">
                    <div class="sr-table-card sr-connectivity-explorer__canvas-card">
                        <div class="sr-table-toolbar">
                            <div class="sr-connectivity-explorer__search-field">
                                <label class="form-label visually-hidden" for="sr-ce-search">${escapeHtml(L('SearchEntities', 'Search entities'))}</label>
                                <input id="sr-ce-search" type="search" class="form-control form-control-sm"
                                       autocomplete="off" role="combobox" aria-expanded="false"
                                       aria-controls="sr-ce-results"
                                       placeholder="${escapeAttr(L('SearchEntitiesPlaceholder', 'Search…'))}">
                                <ul id="sr-ce-results" class="list-group list-group-flush" role="listbox" hidden></ul>
                            </div>

                            <div class="dropdown sr-connectivity-explorer__types-filter">
                                <button type="button" class="sr-table-filter dropdown-toggle" id="sr-ce-types-toggle"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    ${escapeHtml(L('ShowTypes', 'Show types'))}
                                    <span class="sr-table-fcount" data-role="type-fcount" hidden>0/0</span>
                                </button>
                                <div class="dropdown-menu" aria-labelledby="sr-ce-types-toggle">
                                    <div class="sr-connectivity-explorer__type-list" data-role="type-chips"></div>
                                </div>
                            </div>

                            <div class="sr-connectivity-explorer__depth-field">
                                <label class="form-label" for="sr-ce-depth">${escapeHtml(L('Depth', 'Depth'))}</label>
                                <input id="sr-ce-depth" type="range" min="1" max="2" step="1" value="1" class="form-range">
                            </div>

                            <button type="button" class="btn btn-sm btn-outline-secondary sr-connectivity-explorer__clear-btn"
                                    data-role="clear-graph" hidden>
                                <i class="fa fa-xmark" aria-hidden="true"></i> ${escapeHtml(L('ClearGraph', 'Clear graph'))}
                            </button>
                        </div>

                        <div class="sr-connectivity-explorer__canvas" data-role="canvas">
                            <div class="sr-connectivity-explorer__launchpad" data-role="launchpad"></div>
                        </div>
                    </div>
                </div>

                <aside class="sr-connectivity-explorer__inspector-col">
                    <div class="sr-table-card sr-connectivity-explorer__inspector-card">
                        <div class="sr-table-toolbar">
                            <span class="sr-table-title">${escapeHtml(L('Inspector', 'Inspector'))}</span>
                        </div>
                        <div class="sr-connectivity-explorer__inspector-body" data-role="inspector"></div>
                    </div>
                </aside>
            </div>
            <div class="sr-connectivity-explorer__connections" data-role="connections"></div>
        `;

        const search = mount.querySelector('#sr-ce-search');
        let searchTimer = null;
        search.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            const q = search.value.trim();
            if (q.length < 2) {
                hideResults(mount);
                return;
            }
            // Debounce so a fast typist does not issue a request per keystroke.
            searchTimer = window.setTimeout(() => runSearch(mount, q), 250);
        });

        mount.querySelector('#sr-ce-depth').addEventListener('change', function (e) {
            state.depth = parseInt(e.target.value, 10) || 1;
            if (state.focal) {
                loadFocal(mount, state.focal.type, state.focal.id, null, false);
            }
        });

        mount.querySelector('[data-role="clear-graph"]').addEventListener('click', function () {
            clearGraph(mount);
        });

        // Deselecting a node (Escape is the conventional way out of a
        // selected state) restores the inspector-collapsed, full-width
        // canvas -- see setSelection(). Guarded on state.selectedNodeId so
        // this is a no-op keystroke everywhere else on the page (nothing to
        // undo, no work done) rather than an unconditional re-render on
        // every Escape press.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.selectedNodeId) {
                setSelection(mount, null);
            }
        });

        watchCanvasResize(mount);
        // No focal yet: the launchpad IS the empty state (see
        // showLaunchpadTiles()) -- collapse the inspector column from the
        // very first render, same as after clearGraph().
        mount.classList.add('sr-connectivity-explorer--no-selection');
        showLaunchpadTiles(mount);
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s === null || s === undefined ? '' : s);
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }

    function hideResults(mount) {
        const list = mount.querySelector('#sr-ce-results');
        list.hidden = true;
        list.innerHTML = '';
        mount.querySelector('#sr-ce-search').setAttribute('aria-expanded', 'false');
    }

    async function runSearch(mount, q) {
        const list = mount.querySelector('#sr-ce-results');
        try {
            const data = await apiGet('/ai/context/search', { q: q, limit: 25 });
            list.innerHTML = '';
            (data.results || []).forEach(function (row) {
                const li = document.createElement('li');
                li.className = 'list-group-item list-group-item-action py-1 px-2';
                li.setAttribute('role', 'option');
                li.tabIndex = 0;
                li.textContent = row.name;

                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary ms-2';
                badge.textContent = typeLabel(row.type);
                li.appendChild(badge);

                const pick = () => {
                    hideResults(mount);
                    mount.querySelector('#sr-ce-search').value = '';
                    state.trail = [];
                    loadFocal(mount, row.type, row.id, row.name, true);
                };
                li.addEventListener('click', pick);
                li.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') { pick(); }
                });
                list.appendChild(li);
            });
            list.hidden = list.children.length === 0;
            mount.querySelector('#sr-ce-search')
                 .setAttribute('aria-expanded', String(!list.hidden));
        } catch (err) {
            list.hidden = true;
            // Log with enough context to identify the failing request --
            // the toast alone tells the user something failed, not which
            // fetch, for which query, or why. A prior version of this file
            // had no console output at all in any catch block, which is
            // exactly what let a real bug (see renderGraph()'s history)
            // hide behind a caught exception with zero trace.
            console.error('[connectivity-visualizer] entity search failed for query "' + q + '":', err);
            if (window.toastr) {
                window.toastr.error(L('CouldNotSearchEntities', 'Could not search entities.'));
            }
        }
    }

    function typeLabel(type) {
        const map = {
            risk: L('Risk', 'Risk'),
            asset: L('Asset', 'Asset'),
            framework: L('Framework', 'Framework'),
            control: L('Control', 'Control'),
            test: L('Test', 'Test'),
            test_result: L('TestResult', 'Test Result'),
            document: L('Document', 'Document'),
            exception: L('Exception', 'Exception'),
            audit: L('Audit', 'Audit'),
            risk_catalog: L('RiskCatalog', 'Risk Catalog'),
            threat_catalog: L('ThreatCatalog', 'Threat Catalog'),
            vulnerability: L('Vulnerability', 'Vulnerability'),
            self_assessment_result: L('NodeTypeSelfAssessmentResult', 'Self-Assessment Result'),
        };
        return map[type] || type;
    }

    /**
     * Level-1 tiles: one per browsable type the caller can see, each carrying
     * that type's palette swatch and the caller's own visible count
     * (docs/superpowers/specs/2026-07-27-connectivity-explorer-launchpad.md).
     * GET /ai/context/entity-counts omits a type entirely when the caller
     * fails its domain permission -- there is no "0 count" case to render
     * specially, the type's tile simply never exists.
     */
    async function showLaunchpadTiles(mount) {
        state.browse.type = null;
        const box = mount.querySelector('[data-role="launchpad"]');
        if (!box) {
            // The launchpad container only exists while [data-role="canvas"]
            // holds it -- renderGraph() replaces canvas.innerHTML wholesale
            // once a focal is chosen (see its own comment on why). A
            // late-resolving fetch from BEFORE that replacement must not
            // paint into a container that render replaced or removed.
            return;
        }
        try {
            const types = await apiGet('/ai/context/entity-counts', {});
            renderLaunchpadTiles(mount, box, types || []);
        } catch (err) {
            console.error('[connectivity-visualizer] failed to load entity counts:', err);
            box.innerHTML = `
                <div class="sr-table-empty">
                    <div class="sr-table-empty-icon"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i></div>
                    <div class="sr-table-empty-title">${escapeHtml(L('CouldNotLoadEntityCounts', 'Could not load entity counts.'))}</div>
                </div>`;
            if (window.toastr) {
                window.toastr.error(L('CouldNotLoadEntityCounts', 'Could not load entity counts.'));
            }
        }
    }

    /**
     * Renders the Level-1 tile grid into the launchpad container. A type
     * whose `capped` flag is true is a FLOOR, never an exact count (see the
     * spec's `capped` section) -- rendered via the CountFloor template
     * ("2,000+"), never the raw number, so a capped count can never be
     * mistaken for an exact one.
     *
     * Swatch colors are painted via paintPaletteDots() (the same
     * data-palette-color + style-property mechanism the connections table's
     * type chips use) rather than interpolated into the markup string --
     * same discipline, same reason (nodeColor()'s doc comment).
     */
    function renderLaunchpadTiles(mount, box, types) {
        if (!types.length) {
            // A caller who reached this page via a permission that grants no
            // browsable type at all (see connectivity_visualizer.php's own
            // doc comment on the vm_vulnerabilities-only case) -- the page
            // loads, but there is nothing to browse.
            box.innerHTML = `
                <div class="sr-table-empty">
                    <div class="sr-table-empty-icon"><i class="fa fa-lock" aria-hidden="true"></i></div>
                    <div class="sr-table-empty-title">${escapeHtml(L('NoBrowsableTypes', 'You do not have permission to browse any entity type.'))}</div>
                </div>`;
            return;
        }

        const tiles = types.map(function (t) {
            const countText = t.capped
                ? fillTemplate(L('CountFloor', '{0}+'), [t.count.toLocaleString()])
                : t.count.toLocaleString();
            return `
                <button type="button" class="sr-connectivity-explorer__tile" data-type="${escapeAttr(t.type)}">
                    <span class="sr-connectivity-explorer__tile-swatch" data-palette-color="${escapeAttr(nodeColor(t.type))}"></span>
                    <span class="sr-connectivity-explorer__tile-label">${escapeHtml(typeLabel(t.type))}</span>
                    <span class="sr-connectivity-explorer__tile-count">${escapeHtml(countText)}</span>
                </button>`;
        }).join('');

        box.innerHTML = `
            <div class="sr-connectivity-explorer__tiles" role="group"
                 aria-label="${escapeAttr(L('BrowsableEntityTypes', 'Browsable entity types'))}">
                ${tiles}
            </div>`;
        paintPaletteDots(box);
        box.querySelectorAll('[data-type]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                showLaunchpadList(mount, btn.dataset.type);
            });
        });
    }

    /**
     * Level-2: a scrollable list of one browsable type, with a filter box
     * (no 2-character minimum -- an empty filter IS the browse case, unlike
     * the toolbar search) and a "Load more" button driven by `has_more`
     * ONLY, never by a short page (see ai_context_browse_entities()'s
     * docblock: a page can come back short of `limit` and still have more
     * behind it, when the server's per-request scan budget ran out first).
     */
    function showLaunchpadList(mount, type) {
        state.browse.type = type;
        state.browse.filter = '';
        state.browse.cursor = null;
        state.browse.hasMore = false;
        state.browse.rows = [];
        renderLaunchpadListShell(mount, type);
        loadBrowsePage(mount, { reset: true });
    }

    function renderLaunchpadListShell(mount, type) {
        const box = mount.querySelector('[data-role="launchpad"]');
        if (!box) {
            return;
        }
        box.innerHTML = `
            <div class="sr-connectivity-explorer__browse">
                <div class="sr-connectivity-explorer__browse-head">
                    <button type="button" class="sr-connectivity-explorer__back-link" data-role="browse-back">
                        &lsaquo; ${escapeHtml(L('AllTypes', 'All types'))}
                    </button>
                    <span class="sr-connectivity-explorer__browse-type">
                        <span class="sr-connectivity-explorer__tile-swatch" data-palette-color="${escapeAttr(nodeColor(type))}"></span>
                        ${escapeHtml(typeLabel(type))}
                    </span>
                </div>
                <input type="search" class="form-control form-control-sm sr-connectivity-explorer__browse-filter"
                       data-role="browse-filter" autocomplete="off"
                       placeholder="${escapeAttr(L('FilterEntitiesPlaceholder', 'Filter…'))}">
                <ul class="list-group list-group-flush sr-connectivity-explorer__browse-results" data-role="browse-results"></ul>
                <button type="button" class="btn btn-sm btn-outline-secondary sr-connectivity-explorer__load-more"
                        data-role="browse-load-more" hidden>${escapeHtml(L('LoadMore', 'Load more'))}</button>
            </div>`;
        paintPaletteDots(box);

        box.querySelector('[data-role="browse-back"]').addEventListener('click', function () {
            showLaunchpadTiles(mount);
        });

        let filterTimer = null;
        box.querySelector('[data-role="browse-filter"]').addEventListener('input', function (e) {
            window.clearTimeout(filterTimer);
            const value = e.target.value;
            // No length gate here (unlike the toolbar search's q.length < 2)
            // -- an empty filter is the browse case, which is the entire
            // point of this endpoint. Only the debounce is shared.
            filterTimer = window.setTimeout(function () {
                state.browse.filter = value.trim();
                state.browse.cursor = null;
                loadBrowsePage(mount, { reset: true });
            }, 250);
        });

        box.querySelector('[data-role="browse-load-more"]').addEventListener('click', function () {
            loadBrowsePage(mount, { reset: false });
        });
    }

    /**
     * Fetches one page of GET /ai/context/entities and appends (or, on
     * `opts.reset`, replaces) the accumulated rows. `cursor` is always the
     * PREVIOUS response's `next_cursor`, passed back verbatim -- see the
     * spec's pagination-contract section on why this must never be
     * constructed, parsed, or arithmetic'd client-side: it is opaque by
     * design, and re-deriving an offset from it would reintroduce exactly
     * the enumeration oracle the cursor replaced.
     */
    async function loadBrowsePage(mount, opts) {
        const box = mount.querySelector('[data-role="launchpad"]');
        if (!box || state.browse.loading) {
            return;
        }
        const loadMoreBtn = box.querySelector('[data-role="browse-load-more"]');
        state.browse.loading = true;
        if (loadMoreBtn) {
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = L('Loading', 'Loading…');
        }
        try {
            const page = await apiGet('/ai/context/entities', {
                type: state.browse.type,
                filter: state.browse.filter,
                cursor: state.browse.cursor,
                limit: BROWSE_PAGE_LIMIT,
            });
            if (opts.reset) {
                state.browse.rows = [];
            }
            state.browse.rows = state.browse.rows.concat(page.results || []);
            // Never derived from results.length vs. limit -- has_more is the
            // ONLY signal a short page still has more behind it (see this
            // function's doc comment).
            state.browse.cursor = page.next_cursor;
            state.browse.hasMore = !!page.has_more;
            renderBrowseResults(mount, box);
        } catch (err) {
            console.error('[connectivity-visualizer] failed to load entities for type "' + state.browse.type + '":', err);
            if (window.toastr) {
                window.toastr.error(L('CouldNotLoadEntities', 'Could not load entities.'));
            }
        } finally {
            state.browse.loading = false;
            if (loadMoreBtn) {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = L('LoadMore', 'Load more');
            }
        }
    }

    function renderBrowseResults(mount, box) {
        const list = box.querySelector('[data-role="browse-results"]');
        const loadMoreBtn = box.querySelector('[data-role="browse-load-more"]');
        if (!list || !loadMoreBtn) {
            return;
        }
        list.innerHTML = '';
        if (state.browse.rows.length === 0) {
            list.innerHTML = `
                <li class="sr-table-empty">
                    <div class="sr-table-empty-icon"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></div>
                    <div class="sr-table-empty-title">${escapeHtml(L('NoMatchingEntities', 'No entities match your filter.'))}</div>
                </li>`;
        } else {
            state.browse.rows.forEach(function (row) {
                const li = document.createElement('li');
                li.className = 'list-group-item list-group-item-action py-1 px-2';
                li.setAttribute('role', 'option');
                li.tabIndex = 0;
                li.textContent = row.name;
                const pick = () => pickBrowseRow(mount, row);
                li.addEventListener('click', pick);
                li.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { pick(); }
                });
                list.appendChild(li);
            });
        }
        loadMoreBtn.hidden = !state.browse.hasMore;
    }

    /**
     * Clicking a Level-2 row sets the focal exactly the way picking a
     * toolbar search result does (runSearch()'s pick()) -- same trail reset,
     * same loadFocal() call, so the graph renders identically regardless of
     * which path found the entity.
     */
    function pickBrowseRow(mount, row) {
        state.trail = [];
        loadFocal(mount, row.type, row.id, row.name, true);
    }

    /**
     * Returns the Explorer to its pre-selection state: no focal, no graph,
     * no breadcrumbs -- and the launchpad back on screen (spec: "Once a
     * focal entity is chosen the launchpad is gone; it returns when the
     * user clears the graph"). Mirrors loadFocal()'s own teardown of the
     * PREVIOUS bundle (kill the live Sigma instance and DataTable before
     * discarding the state they were built from) rather than leaving either
     * attached to DOM nodes about to be replaced.
     */
    function clearGraph(mount) {
        if (state.sigma) {
            state.sigma.kill();
            state.sigma = null;
        }
        if (state.connectionsDt) {
            state.connectionsDt.destroy();
            state.connectionsDt = null;
        }
        state.focal = null;
        state.bundle = null;
        state.trail = [];
        state.hiddenTypes = new Set();
        state.expanded = {};
        state.graph = null;

        const crumbs = mount.querySelector('[data-role="crumbs"]');
        if (crumbs) { crumbs.innerHTML = ''; }
        const chips = mount.querySelector('[data-role="type-chips"]');
        if (chips) { chips.innerHTML = ''; }
        updateTypesFilterCount(mount, new Set());
        const connections = mount.querySelector('[data-role="connections"]');
        if (connections) { connections.innerHTML = ''; }
        const clearBtn = mount.querySelector('[data-role="clear-graph"]');
        if (clearBtn) { clearBtn.hidden = true; }
        const search = mount.querySelector('#sr-ce-search');
        if (search) { search.value = ''; }
        hideResults(mount);

        // renderGraph() replaces canvas.innerHTML wholesale when a focal
        // loads (Sigma takes ownership of its container and wipes existing
        // children -- see that function's own comment), which destroys the
        // [data-role="launchpad"] div along with everything else. Recreate
        // it here before asking showLaunchpadTiles() to populate it --
        // otherwise its `mount.querySelector('[data-role="launchpad"]')`
        // guard (there for a late-resolving fetch racing a real navigation)
        // finds nothing and silently no-ops, leaving the canvas blank.
        const canvas = mount.querySelector('[data-role="canvas"]');
        if (canvas) {
            canvas.innerHTML = '<div class="sr-connectivity-explorer__launchpad" data-role="launchpad"></div>';
        }

        setSelection(mount, null);
        showLaunchpadTiles(mount);
    }

    /**
     * Single chokepoint for what the Inspector shows and whether its column
     * occupies any layout space at all. `state.selectedNodeId` is null
     * exactly when nothing is selected -- the launchpad, a focal with no
     * node yet explicitly clicked, or after an explicit deselect -- and the
     * `--no-selection` modifier this toggles on the mount (see
     * _connectivity-visualizer.scss) collapses the inspector column so the
     * canvas/launchpad gets the freed width. Every caller that changes
     * `state.selectedNodeId` goes through here rather than setting it
     * directly, so the inspector content and the column's visibility can
     * never disagree about what is currently selected.
     */
    function setSelection(mount, node) {
        state.selectedNodeId = node ? node.node_id : null;
        renderInspector(mount, node);

        // Any earlier pending expand is superseded by this call, whatever
        // it turns out to do -- see below.
        if (state.selectionLayoutTimer) {
            window.clearTimeout(state.selectionLayoutTimer);
            state.selectionLayoutTimer = null;
        }

        if (!node) {
            // Deselecting collapses immediately: there is no coordinate a
            // second click is about to rely on staying put, so nothing is
            // lost by applying it right away -- and doubleClickNode's own
            // loadFocal() path (which deselects first) NEEDS this immediate,
            // since it is itself the thing that must win a race against a
            // pending expand from the double-click's own first half (below).
            mount.classList.add('sr-connectivity-explorer--no-selection');
            return;
        }

        // Selecting is DEFERRED past Sigma's own doubleClickTimeout (300ms;
        // verified live against the vendored sigma@3.0.3 build via
        // sigma.getSetting('doubleClickTimeout') rather than assumed) before
        // the layout actually narrows the canvas. Sigma's mouse captor
        // emits "clickNode" for the FIRST click of every double-click
        // unconditionally (handleClick() only special-cases the SECOND
        // click, past sigma.esm.js:511-524) -- so this handler always runs
        // once before a genuine doubleClickNode ever fires, and narrowing
        // the canvas synchronously on that first click shifts every node's
        // on-screen position (Sigma re-frames to the new width) out from
        // under the second click before it lands, silently turning a
        // double-click-to-recentre into two unrelated single clicks.
        // Confirmed by hand: with no defer, doubleClickNodeByLabel's second
        // click missed the target node it had just resolved the screen
        // point for. Deselecting inside loadFocal() (the `!node` branch
        // above) always clears this timer first, so a genuine double-click
        // cancels the pending expand outright and the layout never moves
        // during the gesture; an unanswered single click expands, just
        // slightly late.
        const SELECTION_LAYOUT_DELAY_MS = 320;
        state.selectionLayoutTimer = window.setTimeout(function () {
            state.selectionLayoutTimer = null;
            // Only apply if this node is STILL the selection -- a click on a
            // DIFFERENT node in the meantime already re-ran this function
            // for that node, whose own timer is what should win.
            if (state.selectedNodeId === node.node_id) {
                mount.classList.remove('sr-connectivity-explorer--no-selection');
            }
        }, SELECTION_LAYOUT_DELAY_MS);
    }

    /**
     * Sigma sizes its canvas to its container ONCE, at construction (`new
     * Sigma(graph, canvas)` in renderGraph()) -- it does not observe later
     * size changes on its own (confirmed against sigma@3.0.3's own source,
     * sigma.esm.js: the container's dimensions are read only inside
     * resize(), which nothing here calls automatically). The inspector
     * column's collapse/expand (setSelection()) is exactly such a later
     * change, and so is a plain browser window resize while a graph is on
     * screen. A ResizeObserver on the canvas container, set up ONCE here,
     * is what tells Sigma to re-read its container's current dimensions --
     * far more robust than calling resize() from every code path that can
     * change that container's size (the inspector toggle today, something
     * else tomorrow).
     *
     * resize() alone only recomputes sigma.width/height and the canvas
     * elements' own pixel dimensions -- it does not repaint (its own source
     * ends with `this.emit("resize")`, no render call), so refresh() follows
     * it to actually redraw at the new size and recompute every node's
     * framed position against it. Deliberately the SYNCHRONOUS refresh(),
     * not scheduleRefresh() (which defers to the next animation frame) --
     * Sigma's OWN native-window-resize handler uses scheduleRefresh()
     * because that event can fire at high frequency and wants debouncing,
     * but this callback fires once per discrete, already-settled selection
     * toggle. The deferred variant left a one-frame window where a
     * geometry-resolving click (resolveNodeScreenPoint(),
     * connectivity-visualizer.page.ts) could read a node's position from
     * the OLD framing after the container had already resized -- caught by
     * hand chaining select -> deselect -> re-select on the same node in
     * quick succession, which intermittently resolved a stale point. The
     * synchronous call closes that window: by the time this callback
     * returns, Sigma's internal display data already reflects the new
     * container size.
     */
    function watchCanvasResize(mount) {
        if (state.resizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }
        const canvas = mount.querySelector('[data-role="canvas"]');
        if (!canvas) {
            return;
        }
        state.resizeObserver = new ResizeObserver(function () {
            if (state.sigma) {
                state.sigma.resize();
                state.sigma.refresh();
            }
        });
        state.resizeObserver.observe(canvas);
    }

    async function loadFocal(mount, type, id, name, pushTrail) {
        state.focal = { type: type, id: id };
        state.expanded = {};
        try {
            const data = await apiGet('/ai/context/' + encodeURIComponent(type) + '/' + encodeURIComponent(id),
                                      { depth: state.depth });
            state.bundle = data;

            if (pushTrail) {
                state.trail.push({ type: type, id: id, name: name || (data.focal && data.focal.name) || String(id) });
            }

            const clearBtn = mount.querySelector('[data-role="clear-graph"]');
            if (clearBtn) { clearBtn.hidden = false; }

            renderCrumbs(mount);
            renderTypeChips(mount);
            renderGraph(mount);
            // Deselected by default -- the focal being loaded is not itself
            // "a node selected" in the sense that shows the inspector (see
            // setSelection()'s doc comment); the inspector column stays
            // collapsed and the canvas keeps the full width until the user
            // explicitly clicks a node. This also clears whatever was
            // selected in the PREVIOUS bundle: that node id almost
            // certainly does not exist in this one (a different focal has a
            // different neighbor set), so carrying it forward would either
            // show stale details or hand renderInspector() a dangling id.
            // Covers double-click re-centre and breadcrumb navigation too,
            // since both call loadFocal().
            setSelection(mount, null);
            renderConnections(mount);
        } catch (err) {
            // Context: which focal (type/id) the failed fetch was for --
            // without this, a caught error here is indistinguishable from
            // any other "could not load the graph" failure in the console.
            console.error('[connectivity-visualizer] failed to load focal ' + type + '/' + id + ':', err);
            if (window.toastr) {
                window.toastr.error(L('CouldNotLoadGraph', 'Could not load the connectivity graph.'));
            }
        }
    }

    /**
     * A node whose only path back to the focal ran through a now-hidden
     * type is exactly as disconnected as a node the server itself dropped
     * for having no surviving parent (see ai_context_drop_orphaned(),
     * includes/ai_context_graph.php) -- drawing it anyway asserts a
     * relationship the graph can no longer support, and a viewer reads
     * "on the canvas" as "connected to the focal". The type filter alone
     * (state.hiddenTypes) only removes nodes OF the hidden type; it does
     * nothing about a still-visible-type node whose sole edge ran through
     * one of them (e.g. a test reached only via a now-hidden control).
     *
     * Pure and side-effect-free so it can be unit-tested directly: given
     * the focal's node_id, the set of node ids the type filter left
     * standing, and the full (unfiltered) edge list, returns the set of
     * ids -- INCLUDING the focal -- reachable from the focal by a path of
     * edges whose both endpoints are themselves in that allowed set. A
     * plain breadth-first walk; nothing depth-aware is needed because the
     * edge list already IS the graph regardless of how many API "depth"
     * hops produced it (see the {@link visibleNodesResult} doc comment).
     *
     * Edges are walked UNDIRECTED: the server canonicalises `rel` per
     * type-pair (ai_context_edge_rel(), includes/ai_context_graph.php), so
     * a given pair's edge may point either way depending on which side of
     * the association was walked first -- a directed walk would silently
     * drop legitimately-connected nodes whose edge happens to point
     * "toward" the focal instead of "away from" it.
     */
    function reachableNodeIds(focalId, allowedNodeIds, edges) {
        const allowed = new Set(allowedNodeIds);
        allowed.add(focalId);

        const adjacency = new Map();
        (edges || []).forEach(function (e) {
            if (!allowed.has(e.from) || !allowed.has(e.to)) {
                return;
            }
            if (!adjacency.has(e.from)) { adjacency.set(e.from, []); }
            if (!adjacency.has(e.to))   { adjacency.set(e.to, []); }
            adjacency.get(e.from).push(e.to);
            adjacency.get(e.to).push(e.from);
        });

        const visited = new Set([focalId]);
        const queue = [focalId];
        while (queue.length > 0) {
            const current = queue.shift();
            (adjacency.get(current) || []).forEach(function (neighbor) {
                if (!visited.has(neighbor)) {
                    visited.add(neighbor);
                    queue.push(neighbor);
                }
            });
        }
        return visited;
    }

    /**
     * The type filter's own output, before reachability is considered --
     * every fetched node whose type is not hidden. {@link visibleNodesResult}
     * narrows this further; nothing else should filter on state.hiddenTypes
     * directly, or the two filters could drift apart.
     */
    function typeFilteredNodes() {
        const nodes = (state.bundle && state.bundle.nodes) || [];
        return nodes.filter((n) => !state.hiddenTypes.has(n.type));
    }

    /**
     * The actual "what gets drawn" answer: the type filter's survivors,
     * further narrowed to only those with a surviving path back to the
     * focal (see {@link reachableNodeIds}). Both the canvas (renderGraph())
     * and the connections table (renderConnections()) call this -- never
     * the raw type filter -- so the two can never disagree about which
     * nodes exist. `prunedCount` (survived the type filter but not the
     * reachability walk) is what drives the disclosure note in
     * renderConnections(); it is 0 whenever nothing but direct type-hides
     * happened, so the note only appears when hiding a type had this
     * knock-on effect on some OTHER type.
     *
     * Cluster placeholder nodes (`cluster_<type>`, added directly in
     * renderGraph()) are not part of state.bundle.nodes and never reach
     * this function -- they hang directly off the focal by construction
     * (renderGraph() adds their edge unconditionally), so they cannot be
     * orphaned by this walk and are correctly untouched by it.
     */
    function visibleNodesResult() {
        const typeFiltered = typeFilteredNodes();
        const focal = state.bundle && state.bundle.focal;
        if (!focal) {
            return { nodes: typeFiltered, prunedCount: 0 };
        }
        const edges = (state.bundle && state.bundle.edges) || [];
        const reachable = reachableNodeIds(focal.node_id, typeFiltered.map((n) => n.node_id), edges);
        const nodes = typeFiltered.filter((n) => reachable.has(n.node_id));
        return { nodes: nodes, prunedCount: typeFiltered.length - nodes.length };
    }

    function visibleNodes() {
        return visibleNodesResult().nodes;
    }

    function renderGraph(mount) {
        const canvas = mount.querySelector('[data-role="canvas"]');
        const focal  = state.bundle.focal;
        const nodes  = visibleNodes();

        if (state.sigma) {
            state.sigma.kill();
            state.sigma = null;
        }
        // Sigma's constructor takes ownership of the container it is given
        // and wipes its existing children -- including the static "empty"
        // placeholder markup from init()'s template. That placeholder is
        // therefore GONE after the first successful render, so every later
        // call re-creates it on demand instead of querying for a node that
        // may no longer exist (mount.querySelector(...) on a destroyed node
        // returns null, and null.hidden = ... throws, silently aborting the
        // rest of loadFocal -- the crumb/inspector/table renders after it
        // never run, and the canvas is left blank with no visible error).
        canvas.innerHTML = '';

        if (nodes.length === 0) {
            // Show the "no connections" overlay, but do NOT return early: the
            // focal itself must still be drawn below, even when every
            // neighbor is hidden or pruned for unreachability (see
            // visibleNodesResult()) -- it is the subject of the page, not one
            // of its own filterable neighbors, and hiding every type must
            // never blank it off the canvas. The overlay is absolutely
            // positioned over the whole canvas (.sr-connectivity-explorer__empty,
            // _connectivity-visualizer.scss), so it and a focal-only graph
            // underneath it coexist without fighting for layout space.
            const empty = document.createElement('div');
            empty.className = 'sr-connectivity-explorer__empty';
            empty.setAttribute('data-role', 'empty');
            empty.textContent = L('NoConnectionsFound', 'No connections found for the selected entity.');
            canvas.appendChild(empty);
        }

        const graph = new graphology.Graph();
        state.graph = graph;

        graph.addNode(focal.node_id, {
            label: focal.name,
            size: 16,
            color: nodeColor(focal.type),
            x: 0, y: 0,
            srType: focal.type,
        });

        const summary = (state.bundle.meta && state.bundle.meta.neighbor_summary) || {};

        nodes.forEach(function (n, i) {
            if (!graph.hasNode(n.node_id)) {
                graph.addNode(n.node_id, {
                    label: n.name,
                    size: 8,
                    color: nodeColor(n.type),
                    // Seed positions on a ring; ForceAtlas2 relaxes them.
                    x: Math.cos((i / nodes.length) * 2 * Math.PI),
                    y: Math.sin((i / nodes.length) * 2 * Math.PI),
                    srType: n.type,
                });
            }
        });

        // One cluster node per clustered type, carrying the TRUE count.
        Object.entries(summary).forEach(function ([type, s], idx) {
            if (!s.clustered || state.hiddenTypes.has(type)) {
                return;
            }
            const clusterId = 'cluster_' + type;
            graph.addNode(clusterId, {
                label: '+' + (s.total - s.returned) + ' ' + typeLabel(type),
                size: 14,
                color: nodeColor(type),
                x: Math.cos((idx / 8) * 2 * Math.PI) * 1.6,
                y: Math.sin((idx / 8) * 2 * Math.PI) * 1.6,
                srCluster: type,
            });
            graph.addEdge(focal.node_id, clusterId, { size: 1, color: nodeColor('edge_cluster') });
        });

        (state.bundle.edges || []).forEach(function (e) {
            if (graph.hasNode(e.from) && graph.hasNode(e.to) && !graph.hasEdge(e.from, e.to)) {
                graph.addEdge(e.from, e.to, { size: 1, color: nodeColor('edge'), srRel: e.rel });
            }
        });

        // Force-directed replaces the old circular layout, so proximity on the
        // canvas actually means something.
        //
        // NAMESPACING (verified against graphology-library@0.8.0's UMD build,
        // not assumed): the bundle registers a single global `graphologyLibrary`
        // and exposes force-atlas2 at `.layoutForceAtlas2`. The standard layouts
        // (circular, random, circlepack) live under `.layout`. There is NO
        // top-level `graphologyLayoutForceAtlas2` global -- that was the shape
        // of the separate per-package builds this plan originally assumed.
        const fa2 = window.graphologyLibrary && window.graphologyLibrary.layoutForceAtlas2;
        if (fa2 && typeof fa2.assign === 'function') {
            fa2.assign(graph, { iterations: 120 });
        }

        state.sigma = new Sigma(graph, canvas);

        state.sigma.on('clickNode', function (payload) {
            const attrs = graph.getNodeAttributes(payload.node);
            if (attrs.srCluster) {
                expandCluster(mount, attrs.srCluster);
                return;
            }
            const node = (state.bundle.nodes || []).find((n) => n.node_id === payload.node)
                      || state.bundle.focal;
            setSelection(mount, node);
        });

        // Clicking empty canvas background -- Sigma's own "missed every
        // node/edge" event (verified against sigma@3.0.3's source,
        // sigma.esm.js: createInteractionListener() emits "<type>Stage"
        // exactly when getNodeAtPosition()/getEdgeAtPoint() both come back
        // empty) -- is the expected gesture to deselect: same teardown as
        // Escape, restoring the full-width canvas.
        state.sigma.on('clickStage', function () {
            setSelection(mount, null);
        });

        state.sigma.on('doubleClickNode', function (payload) {
            const node = (state.bundle.nodes || []).find((n) => n.node_id === payload.node);
            // test_result / self_assessment_result render as nodes but are
            // never a valid focal type -- re-centering on one would always
            // 404. Leave the graph as-is rather than firing a request that
            // can only fail.
            if (node && !NEIGHBOR_ONLY_TYPES.has(node.type)) {
                loadFocal(mount, node.type, focalIdFor(node), node.name, true);
            }
        });
    }

    /**
     * The id to address a node by in the API path. Vulnerabilities use a
     * composite <platform>_<id> (their node_id prefix stripped of
     * "vulnerability_id_"); every other type -- including risk -- uses its
     * plain id.
     */
    function focalIdFor(node) {
        if (node.type === 'vulnerability') {
            return String(node.node_id).replace(/^vulnerability_id_/, '');
        }
        // Risks are addressed by their RAW db id, NOT the display id.
        // Verified empirically: ai_context_entity_name('risk', rawId) resolves,
        // while rawId + 1000 returns null -> the bundle endpoint 404s. The
        // +1000 display id is a presentation convention only; it appears in
        // labels ("[1654] Subject"), never in an API path.
        return node.id;
    }

    /**
     * The palette is emitted server-side by the page shell from
     * graph_node_palette() (includes/entity_graph.php) and read from the
     * mount's data-node-palette attribute. There is deliberately NO copy of
     * the colors here: a second list would diverge the first time a node type
     * is added and only one side updated. Also used for the two non-node-type
     * entries ('edge', 'edge_cluster') and the neutral fallback ('default'),
     * same rationale. The bare hex literal below is a LAST resort for when
     * the palette itself failed to parse (loadPalette()'s catch already
     * logs that case) -- it is not a stand-in for a missing palette entry.
     */
    function nodeColor(type) {
        return (state.palette && (state.palette[type] || state.palette.default)) || '#7f8c8d';
    }

    function loadPalette(mount) {
        try {
            state.palette = JSON.parse(mount.dataset.nodePalette || '{}');
        } catch (e) {
            console.error('[connectivity-visualizer] failed to parse data-node-palette:', mount.dataset.nodePalette, e);
            state.palette = {};
        }
    }

    async function expandCluster(mount, type) {
        const current = state.expanded[type] || CLUSTER_THRESHOLD;
        state.expanded[type] = Math.min(current + EXPAND_STEP, MAX_EXPAND_LIMIT);
        const f = state.focal;
        try {
            const data = await apiGet('/ai/context/' + encodeURIComponent(f.type) + '/' + encodeURIComponent(f.id),
                                      { depth: state.depth, expand: type, limit: state.expanded[type] });
            state.bundle = data;
            renderTypeChips(mount);
            // renderGraph()/renderConnections() both derive their node set
            // from visibleNodesResult() fresh on every call rather than a
            // cached list, so the reachability prune re-runs automatically
            // against the newly-expanded bundle here -- no separate re-prune
            // step needed. Called out explicitly because it would be easy to
            // assume (wrongly) that expanding a cluster needs its own prune
            // call: it doesn't, by construction.
            renderGraph(mount);
            renderConnections(mount);
        } catch (err) {
            // Context: which type was being expanded, for which focal --
            // an expand-cluster failure and a load-focal failure would
            // otherwise be indistinguishable in the console.
            console.error('[connectivity-visualizer] failed to expand cluster "' + type + '" for focal '
                + f.type + '/' + f.id + ':', err);
            if (window.toastr) {
                window.toastr.error(L('CouldNotLoadGraph', 'Could not load the connectivity graph.'));
            }
        }
    }

    function renderCrumbs(mount) {
        const nav = mount.querySelector('[data-role="crumbs"]');
        nav.innerHTML = '';
        state.trail.forEach(function (crumb, i) {
            if (i > 0) {
                nav.appendChild(document.createTextNode(' › '));
            }
            const isLast = i === state.trail.length - 1;
            const el = document.createElement(isLast ? 'span' : 'button');
            el.textContent = crumb.name;
            if (isLast) {
                el.setAttribute('aria-current', 'page');
            } else {
                el.type = 'button';
                el.addEventListener('click', function () {
                    state.trail = state.trail.slice(0, i + 1);
                    loadFocal(mount, crumb.type, crumb.id, crumb.name, false);
                });
            }
            nav.appendChild(el);
        });
    }

    function renderTypeChips(mount) {
        const box = mount.querySelector('[data-role="type-chips"]');
        box.innerHTML = '';
        const present = new Set((state.bundle.nodes || []).map((n) => n.type));
        Object.keys((state.bundle.meta && state.bundle.meta.neighbor_summary) || {})
              .forEach((t) => present.add(t));

        Array.from(present).sort().forEach(function (type) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sr-connectivity-explorer__chip';
            btn.setAttribute('aria-pressed', String(!state.hiddenTypes.has(type)));

            const sw = document.createElement('span');
            sw.className = 'sr-connectivity-explorer__chip-swatch';
            sw.style.background = nodeColor(type);
            btn.appendChild(sw);
            btn.appendChild(document.createTextNode(typeLabel(type)));

            btn.addEventListener('click', function () {
                if (state.hiddenTypes.has(type)) {
                    state.hiddenTypes.delete(type);
                } else {
                    state.hiddenTypes.add(type);
                }
                btn.setAttribute('aria-pressed', String(!state.hiddenTypes.has(type)));
                updateTypesFilterCount(mount, present);
                renderGraph(mount);
                renderConnections(mount);
            });
            box.appendChild(btn);
        });

        updateTypesFilterCount(mount, present);
    }

    /**
     * Keeps the "Show types" popover button's .sr-table-fcount badge (the
     * shipped active-filter-count pattern, design-system.md §6) reading
     * "<shown>/<total>" against the types actually present on the current
     * bundle -- e.g. "9/11" when 2 of 11 present types are hidden. Hidden
     * (no badge) until a bundle has loaded and there is a total to report,
     * so the button never implies a filter is active before one can be.
     */
    function updateTypesFilterCount(mount, present) {
        const badge = mount.querySelector('[data-role="type-fcount"]');
        if (!badge) {
            return;
        }
        const total = present.size;
        if (total === 0) {
            badge.hidden = true;
            return;
        }
        const shown = Array.from(present).filter((t) => !state.hiddenTypes.has(t)).length;
        badge.hidden = false;
        badge.textContent = shown + '/' + total;
    }

    function renderInspector(mount, node) {
        const box = mount.querySelector('[data-role="inspector"]');
        if (!node) {
            // Reuses the shipped .sr-table-empty* pattern (design-system.md,
            // already consumed by renderConnections()'s empty state) rather
            // than a bare blank box, now that the Inspector is its own card
            // on gray ground and a plain empty div reads as unfinished.
            box.innerHTML = `
                <div class="sr-table-empty">
                    <div class="sr-table-empty-icon"><i class="fa fa-hand-pointer" aria-hidden="true"></i></div>
                    <div class="sr-table-empty-title">${escapeHtml(L('SelectANodeToInspect', 'Select a node to view its details.'))}</div>
                </div>`;
            return;
        }
        const fields = node.fields || {};
        // The server (ai_context_enrich_fetch(), ai_context_graph.php) sends
        // every coded field as a raw value PLUS a "<key>_label" sibling when a
        // human label was resolvable (e.g. status: 2, status_label: "Not
        // Tested"). Render the label when present, fall back to the raw
        // value otherwise, and never render a "*_label" entry as its own row.
        const isMeaningful = (v) => v !== null && v !== undefined && v !== '' && typeof v !== 'object';
        const rows = Object.entries(fields)
            .filter(([k, v]) => !k.endsWith('_label') && (isMeaningful(v) || isMeaningful(fields[k + '_label'])))
            .map(([k, v]) => {
                const label = fields[k + '_label'];
                const display = isMeaningful(label) ? label : v;
                return `<div class="sr-connectivity-explorer__kv"><dt>${escapeHtml(fieldLabel(k))}</dt>` +
                    `<dd>${escapeHtml(display)}</dd></div>`;
            })
            .join('');

        // The "Inspector" heading now lives in the persistent card toolbar
        // (init()'s template) rather than being re-rendered here on every
        // node selection.
        box.innerHTML = `
            <div class="card"><div class="card-body p-2">
                <div class="fw-semibold">${escapeHtml(node.name)}</div>
                <div class="text-muted small mb-2">${escapeHtml(typeLabel(node.type))}</div>
                <dl class="mb-0">${rows}</dl>
            </div></div>
        `;
    }

    // Every key here is emitted by ai_context_enrich_fetch() /
    // ai_context_enrich_vulnerabilities() (includes/ai_context_graph.php) for
    // at least one node type. "<key>_label" siblings are never rendered as
    // their own row (see renderInspector()) and so need no entry here.
    function fieldLabel(key) {
        const map = {
            // control
            maturity: L('CurrentMaturity', 'Current Maturity'),
            desired_maturity: L('DesiredMaturity', 'Desired Maturity'),
            family: L('ControlFamily', 'Control Family'),
            mitigation_percent: L('MitigationPercent', 'Mitigation Percent'),
            // shared across several types
            status: L('Status', 'Status'),
            owner: L('Owner', 'Owner'),
            approved: L('Approved', 'Approved'),
            approver: L('Approver', 'Approver'),
            approval_state: L('ApprovalState', 'Approval State'),
            approval_status: L('ApprovalStatus', 'Approval Status'),
            severity: L('Severity', 'Severity'),
            // test
            objective: L('Objective', 'Objective'),
            test_steps: L('TestSteps', 'Test Steps'),
            expected_results: L('ExpectedResults', 'Expected Results'),
            desired_frequency: L('DesiredFrequency', 'Desired Frequency'),
            last_date: L('LastDate', 'Last Date'),
            last_result: L('LastResult', 'Last Result'),
            last_result_date: L('LastResultDate', 'Last Result Date'),
            // asset
            valuation: L('AssetValuation', 'Asset Valuation'),
            verified: L('Verified', 'Verified'),
            // risk
            calculated_risk: L('CalculatedRisk', 'Calculated Risk'),
            manager: L('Manager', 'Manager'),
            // exception
            justification: L('Justification', 'Justification'),
            next_review_date: L('NextReviewDate', 'Next Review Date'),
            // assessment
            percent: L('PercentComplete', 'Percent Complete'),
            // self_assessment_result
            response: L('Response', 'Response'),
            assessment_date: L('AssessmentDate', 'Assessment Date'),
            framework_name: L('FrameworkName', 'Framework Name'),
            // incident
            score: L('Score', 'Score'),
            playbook: L('Playbook', 'Playbook'),
            // audit
            next_date: L('NextDate', 'Next Date'),
            tester: L('Tester', 'Tester'),
            control_id: L('ControlID', 'Control ID'),
            test_id: L('TestID', 'Test ID'),
            // risk_catalog / threat_catalog
            number: L('Number', 'Number'),
            grouping: L('Grouping', 'Grouping'),
            description: L('Description', 'Description'),
            // vulnerability
            hidden: L('Hidden', 'Hidden'),
            risk_id: L('RiskId', 'Risk ID'),
            first_found: L('FirstFound', 'First Found'),
            last_found: L('LastFound', 'Last Found'),
            patchable: L('Patchable', 'Patchable'),
            solution: L('Solution', 'Solution'),
            platform: L('Platform', 'Platform'),
        };
        // Every enrichment key above has a lang entry, so this fallback is a
        // safety net for a key the server adds later without a matching JS
        // update -- not an expected path. It still degrades to readable
        // English rather than raw snake_case, but is NOT itself localized;
        // see CLAUDE.md's language-lookup rule.
        return map[key] || key.replace(/_/g, ' ');
    }

    // Maps neighbor_summary.<type>.ranked_by (ai_context_rank_key(),
    // includes/ai_context_graph.php) to the lang key that names the ordering
    // for a human reader. Keep in sync with that function's switch -- a rank
    // key with no entry here falls back to RankedByName, same as the server
    // falls back to 'name_asc' for an unmapped type.
    const RANK_KEY_LANG = {
        maturity_gap_desc: 'RankedByMaturityGap',
        calculated_risk_desc: 'RankedByRiskScore',
        recent_failure_first: 'RankedByRecentFailure',
        next_review_date_asc: 'RankedByReviewDate',
        severity_desc: 'RankedBySeverity',
        name_asc: 'RankedByName',
    };

    /**
     * Minimal "{0}", "{1}" positional-token substitution for a $lang
     * template string (e.g. ShowingTopNOfM, RelationshipOfType). This file
     * renders entirely client-side from already-localized _lang values --
     * there is no server-side _lang() call in this path to do the
     * substitution for us.
     */
    function fillTemplate(tpl, args) {
        return String(tpl).replace(/\{(\d+)\}/g, function (match, i) {
            const v = args[Number(i)];
            return (v === undefined || v === null) ? match : String(v);
        });
    }

    /**
     * The server encodes an edge's relationship as "<childType>_of_
     * <parentType>" (ai_context_edge_rel(), includes/ai_context_graph.php)
     * -- a machine-facing edge label, not a display string ("exception_of_
     * control"). None of the node-type slugs it's built from contain the
     * substring "_of_" themselves, so splitting on it is unambiguous.
     * Humanize by localizing each half through typeLabel() and composing
     * via RelationshipOfType, so the cell reads "Exception of control"
     * instead of leaking the raw snake_case key.
     */
    function relationshipLabel(rel) {
        if (!rel) {
            return '';
        }
        const parts = String(rel).split('_of_');
        if (parts.length !== 2) {
            return rel;
        }
        return fillTemplate(L('RelationshipOfType', '{0} of {1}'), [typeLabel(parts[0]), typeLabel(parts[1])]);
    }

    // A type chip for the connections table's Type column, colored from the
    // same state.palette the canvas nodes use (see nodeColor()) so the table
    // and the graph agree visually. The color is data (server-emitted
    // palette config), so -- same discipline as sevPill()/paintSevPills() in
    // self-assessment.js -- it goes into a safe data-* attribute here and is
    // applied via the style *property* in paintPaletteDots(), never
    // interpolated directly into the markup string.
    function typeChipHtml(type) {
        return '<span class="sr-connectivity-explorer__type-chip">' +
            '<span class="sr-connectivity-explorer__type-chip-dot" data-palette-color="' +
            escapeAttr(nodeColor(type)) + '"></span>' +
            escapeHtml(typeLabel(type)) +
            '</span>';
    }

    function paintPaletteDots(container) {
        container.querySelectorAll('[data-palette-color]').forEach(function (el) {
            const c = el.getAttribute('data-palette-color');
            if (c) {
                el.style.background = c;
            }
        });
    }

    /**
     * One note per visible type whose neighbor_summary marks it clustered
     * (more neighbors exist than were returned) -- e.g. "Control · Showing
     * top 25 of 142 · Ranked by maturity gap". Without this the table
     * silently implies it lists every neighbor, when a clustered type is
     * actually a ranked top-N subset (see ai_context_apply_clustering()).
     *
     * A second, single-line note is appended when hiding a type also
     * pruned some OTHER type's nodes for having lost their only path to
     * the focal (`prunedCount`, from {@link visibleNodesResult}) -- e.g.
     * hide "Control" and a test that only reached the focal THROUGH a
     * control disappears too. Without this, a viewer who only hid one
     * type has no explanation for why nodes of a type they never touched
     * also vanished; it reads as a broken filter rather than the graph
     * correctly refusing to draw a now-unsupported relationship. Reuses
     * this same disclosure-row affordance rather than inventing a new one
     * (design brief: "follow that existing pattern"), and says only a
     * count -- no per-node detail -- to stay quiet.
     */
    function renderConnectionsNotes(nodes, summary, prunedCount) {
        const types = Array.from(new Set(nodes.map((n) => n.type))).sort();
        const notes = types
            .filter((type) => summary[type] && summary[type].clustered)
            .map(function (type) {
                const s = summary[type];
                const rankLangKey = RANK_KEY_LANG[s.ranked_by] || 'RankedByName';
                const countText = fillTemplate(L('ShowingTopNOfM', 'Showing top {0} of {1}'), [s.returned, s.total]);
                return '<li><strong>' + escapeHtml(typeLabel(type)) + '</strong> &middot; ' +
                    escapeHtml(countText) + ' &middot; ' + escapeHtml(L(rankLangKey)) + '</li>';
            });
        if (prunedCount > 0) {
            const pruneText = fillTemplate(
                L('HiddenUnreachableNodes', '{0} node(s) hidden: no longer connected without the hidden type(s).'),
                [prunedCount]);
            notes.push('<li>' + escapeHtml(pruneText) + '</li>');
        }
        if (notes.length === 0) {
            return '';
        }
        return '<ul class="sr-connectivity-explorer__connections-notes">' + notes.join('') + '</ul>';
    }

    /**
     * Initialize the connections table as a client-side DataTable inside the
     * classic .sr-table-card chrome (design-system.md §6) -- the same
     * dom-string pattern initSrTable() uses on self-assessment.js /
     * compliance-define-tests.js (each page keeps its own local copy; there
     * is no shared helper to import). `serverSide`/`processing` are `true`
     * in the app-wide DataTable.defaults (header.php) for the common
     * server-paged table; this one is a small, already-fetched array
     * rendered client-side, so both are overridden to `false` here.
     * Everything else -- lengthMenu, the pagination language strings -- is
     * inherited from those defaults rather than re-specified. No `f`
     * (search box) in the dom string: the type chips already filter which
     * rows exist, so a second, redundant free-text filter isn't part of
     * this table's feature set.
     */
    function initConnectionsTable(tableEl) {
        if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) {
            return null;
        }
        return jQuery(tableEl).DataTable({
            serverSide: false,
            processing: false,
            dom: 'rt<"sr-table-foot"i<"sr-table-foot-right"lp>>',
            // Every data column sorts (design-system.md §6) -- no columnDefs
            // override needed, that's DataTables' default. An empty `order`
            // preserves the server's ranking (see renderConnectionsNotes()) as
            // the initial row order instead of forcing an alphabetical sort.
            order: [],
        });
    }

    function renderConnections(mount) {
        const box = mount.querySelector('[data-role="connections"]');

        // renderConnections() re-renders the table wholesale via innerHTML
        // below on every focal change and cluster expand. A DataTable
        // initialized on the PREVIOUS markup would otherwise leak: its
        // instance, its generated pager/search chrome, and its event
        // handlers all stay bound to DOM nodes that are about to be
        // discarded. Destroy it here, while it is still attached to the
        // document, rather than relying on DataTables' own isDataTable()
        // check against a freshly-replaced node (which would never match,
        // since the old element is gone by the time anything could check).
        if (state.connectionsDt) {
            state.connectionsDt.destroy();
            state.connectionsDt = null;
        }

        const visible = visibleNodesResult();
        const nodes = visible.nodes;
        const summary = (state.bundle.meta && state.bundle.meta.neighbor_summary) || {};
        const title = escapeHtml(L('Connections', 'Connections'));

        if (nodes.length === 0) {
            // Reuses the same "no connections" message the canvas shows for
            // the identical visibleNodes()===0 condition (§10: a table this
            // small, entirely driven by the rail's type chips with no
            // filter-clear affordance of its own, doesn't warrant a second,
            // divergent empty-state copy). Still surfaces the prune note
            // (visible.prunedCount > 0) above the empty box when hiding a
            // type is what emptied the table -- otherwise a viewer who hid
            // one type and watched every row disappear has no explanation
            // at all, which is a worse version of the exact "reads as a
            // broken filter" problem this note exists to prevent.
            const emptyNotesHtml = renderConnectionsNotes([], summary, visible.prunedCount);
            box.innerHTML = `
                <div class="sr-table-card">
                    <div class="sr-table-toolbar">
                        <span class="sr-table-title">${title}<span class="sr-table-count">0</span></span>
                    </div>
                    ${emptyNotesHtml}
                    <div class="sr-table-empty">
                        <div class="sr-table-empty-icon"><i class="fa fa-diagram-project" aria-hidden="true"></i></div>
                        <div class="sr-table-empty-title">${escapeHtml(L('NoConnectionsFound', 'No connections found for the selected entity.'))}</div>
                    </div>
                </div>`;
            return;
        }

        // The Relationship column asserts ONE edge per row -- the one
        // connecting that row's node to the FOCAL, when such an edge exists,
        // since that is the relationship a reader inspecting the focal's
        // connections table actually cares about. A node can carry more than
        // one edge (e.g. at depth 2, or a framework focal's own first hop,
        // where control->test/control->document/control->risk edges appear
        // alongside control->framework): without this preference, a node's
        // relationship was whichever edge happened to appear first in
        // state.bundle.edges, which is frequently a neighbor-to-neighbor
        // edge that never touches the focal at all -- silently wrong in a
        // way that always renders something plausible. Falls back to the
        // first other edge only when no focal-anchored edge exists.
        const focalId = state.bundle.focal && state.bundle.focal.node_id;
        const edgeByNode = {};
        (state.bundle.edges || []).forEach(function (e) {
            const isFocalEdge = !!focalId && (e.from === focalId || e.to === focalId);
            if (isFocalEdge) {
                edgeByNode[e.from] = e.rel;
                edgeByNode[e.to]   = e.rel;
            } else {
                edgeByNode[e.from] = edgeByNode[e.from] || e.rel;
                edgeByNode[e.to]   = edgeByNode[e.to]   || e.rel;
            }
        });

        const notesHtml = renderConnectionsNotes(nodes, summary, visible.prunedCount);

        const rows = nodes.map((n) => `
            <tr>
                <td>${typeChipHtml(n.type)}</td>
                <td>${escapeHtml(n.name)}</td>
                <td>${escapeHtml(relationshipLabel(edgeByNode[n.node_id]))}</td>
            </tr>`).join('');

        box.innerHTML = `
            <div class="sr-table-card">
                <div class="sr-table-toolbar">
                    <span class="sr-table-title">${title}<span class="sr-table-count">${nodes.length}</span></span>
                </div>
                ${notesHtml}
                <div class="sr-table-scroll">
                    <table class="sr-table" id="sr-ce-connections-table">
                        <thead><tr>
                            <th>${escapeHtml(L('Type', 'Type'))}</th>
                            <th>${escapeHtml(L('Name', 'Name'))}</th>
                            <th>${escapeHtml(L('Relationship', 'Relationship'))}</th>
                        </tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>`;
        paintPaletteDots(box);
        state.connectionsDt = initConnectionsTable(box.querySelector('#sr-ce-connections-table'));
    }

    window.addEventListener('DOMContentLoaded', function () {
        const mount = document.getElementById(MOUNT_ID);
        if (mount) {
            init(mount);
        }
    });
})();
