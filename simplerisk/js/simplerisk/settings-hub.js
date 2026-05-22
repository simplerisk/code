/* Settings Hub — client-side tile catalog with search, filters, and
 * favorites. Driven by GET /api/v2/admin/settings/catalog; renders tiles
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

    // -----------------------------------------------------------------------
    // Modal helper. Renders a centered modal with a title, body, and a
    // configurable action button. Returns an object with `close()`,
    // `setError(msg)`, and `setBusy(busy)` methods so callers can manage
    // request lifecycle without rebuilding the DOM.
    //
    // opts: {
    //   title:         string,
    //   body:          string | HTMLElement,
    //   primaryLabel:  string,
    //   onPrimary:     function (modal) — called on primary click
    //   cancelLabel:   string (default: L('Cancel'))
    // }
    // -----------------------------------------------------------------------
    function openModal(opts) {
        // Capture the element that had focus before the modal opened so
        // close() can restore it. Keyboard users who tab to a tile and
        // hit Enter expect to land back on that tile after dismissal.
        var previouslyFocused = document.activeElement;

        var overlay = document.createElement('div');
        overlay.className = 'hub__modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'hub__modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');

        var h = document.createElement('h3');
        h.textContent = opts.title;
        modal.appendChild(h);

        var errorEl = document.createElement('div');
        errorEl.className = 'hub__modal-error';
        errorEl.style.display = 'none';
        modal.appendChild(errorEl);

        var bodyEl;
        if (typeof opts.body === 'string') {
            bodyEl = document.createElement('p');
            bodyEl.textContent = opts.body;
        } else {
            bodyEl = opts.body;
        }
        modal.appendChild(bodyEl);

        var actions = document.createElement('div');
        actions.className = 'hub__modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.textContent = opts.cancelLabel || L('Cancel');
        actions.appendChild(cancelBtn);

        var primaryBtn = document.createElement('button');
        primaryBtn.type = 'button';
        primaryBtn.className = 'btn btn-submit';
        primaryBtn.textContent = opts.primaryLabel;
        actions.appendChild(primaryBtn);

        modal.appendChild(actions);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        function close() {
            document.removeEventListener('keydown', escHandler);
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
                previouslyFocused.focus();
            }
        }
        function setError(msg) {
            if (msg) {
                errorEl.textContent = msg;
                errorEl.style.display = '';
            } else {
                errorEl.textContent = '';
                errorEl.style.display = 'none';
            }
        }
        function setBusy(busy) {
            primaryBtn.disabled = busy;
            cancelBtn.disabled  = busy;
            primaryBtn.textContent = busy
                ? L('Processing')
                : opts.primaryLabel;
        }

        function escHandler(evt) {
            if (evt.key === 'Escape') { close(); }
        }
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (evt) {
            if (evt.target === overlay) { close(); }
        });
        document.addEventListener('keydown', escHandler);

        primaryBtn.addEventListener('click', function () {
            opts.onPrimary({ close: close, setError: setError, setBusy: setBusy });
        });

        // Focus the primary button for keyboard users
        primaryBtn.focus();

        return { close: close, setError: setError, setBusy: setBusy };
    }

    // ---------------------------------------------------------------------------
    // Tag-to-lang-key mapping
    // Maps raw tag keys (used internally for matching) to the
    // localized-string key in $lang. Used for tile tag pills.
    // ---------------------------------------------------------------------------
    var TAG_TO_LANG_KEY = {
        system:        'System',
        customization: 'Customization',
        users:         'UsersAndAccess',
        data:          'DataManagement',
        maintenance:   'Maintenance',
        extras:        'Extras',
    };
    var TAG_ORDER = ['system', 'customization', 'users', 'data', 'maintenance', 'extras'];

    // Whether a catalog entry is an Extras-category tile. Used by tile
    // and row builders to gate state-badge rendering, and by upcoming
    // phases to gate modal click flows.
    function isExtrasEntry(entry) {
        return !!(entry && entry.tags && entry.tags.indexOf('extras') !== -1);
    }

    // Sort order for Extras-tag tiles within the Extras section. Other
    // sections continue to use alpha-by-key ordering. Phase 4 will add
    // ready_to_download and purchase to this set after async license
    // enrichment resolves uninstalled tiles.
    var STATE_ORDER = {
        activated:             0,
        deactivated:           1,
        ready_to_download:     2,
        uninstalled:           3,
        registration_required: 4,
        purchase:              5,
    };

    // Lang key per state for the badge text. Uninstalled tiles render
    // 'Checking…' until license enrichment completes (Phase 4).
    // registration_required is the SCF-specific terminal state for uninstalled
    // tiles whose catalog entry declares uninstalled_state — license
    // enrichment leaves it alone and the click router routes to register.php.
    var STATE_TO_LANG_KEY = {
        activated:             'Active',
        deactivated:           'Disabled',
        ready_to_download:     'StateReadyToDownload',
        uninstalled:           'StateChecking',
        registration_required: 'StateRegistrationRequired',
        purchase:              'Purchase',
    };

    // Sort entries by (STATE_ORDER, label). Used to order tiles inside
    // the Extras section in both the 'all' and single-domain layouts.
    // Returns a new array; does not mutate the input.
    function sortByStateThenLabel(entries) {
        return entries.slice().sort(function (a, b) {
            var ao = STATE_ORDER[a.state] === undefined ? 99 : STATE_ORDER[a.state];
            var bo = STATE_ORDER[b.state] === undefined ? 99 : STATE_ORDER[b.state];
            if (ao !== bo) { return ao - bo; }
            return a.label < b.label ? -1 : a.label > b.label ? 1 : 0;
        });
    }

    // ---------------------------------------------------------------------------
    // DOM root — bail out if not on a hub page
    // ---------------------------------------------------------------------------
    var root = document.querySelector('.hub');
    if (!root) { return; }

    var hubKind = root.dataset.hubKind || 'settings';
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

    // -----------------------------------------------------------------------
    // Click router for Extras tiles. Branches on tile state.
    // (ready_to_download and purchase land here too; the install and
    // purchase modal handlers are added in Phases 5 and 6 respectively.)
    // -----------------------------------------------------------------------
    function handleExtrasTileClick(entry, tileNode) {
        var state = tileNode.dataset.state || entry.state || 'activated';
        switch (state) {
            case 'activated':
                if (entry.sub_hub) {
                    enterSubHub(entry.sub_hub);
                    return;
                }
                window.location.href = tileNode.dataset.tilePath;
                return;
            case 'deactivated':
                openActivateModal(entry);
                return;
            case 'ready_to_download':
                openInstallModal(entry);
                return;
            case 'registration_required':
                // SCF Extra is included automatically once the instance is
                // registered with SimpleRisk. Send the admin straight to the
                // Register & Upgrade page rather than the marketplace.
                window.location.href = '../admin/register.php';
                return;
            case 'purchase':
                openPurchaseModal(entry);
                return;
            case 'uninstalled':
            default:
                // No-op while license enrichment is in flight (Phase 4)
                return;
        }
    }

    function openActivateModal(entry) {
        var extraName = entry.extra_name || '';
        var body = L('ActivateExtraBody').replace('{name}', entry.label);

        openModal({
            title:        L('ActivateExtraTitle'),
            body:         body,
            primaryLabel: L('Activate'),
            onPrimary:    function (m) {
                m.setError('');
                m.setBusy(true);

                // URL-encoded body matches the existing admin/vulnmgmt.php
                // form submission pattern; csrf-magic's check reads the
                // CSRF-TOKEN HTTP header (preferred) and falls back to
                // $_POST['__csrf_magic']. The previous FormData + body
                // field "csrf-magic" failed because that's not the
                // project's csrf-magic input name (which is __csrf_magic).
                var body = new URLSearchParams();
                body.append('extra_type', extraName);
                body.append('activate', '1');

                fetch(BASE_URL + '/api/v2/admin/activate_deactivate_extra', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers: {
                        'CSRF-TOKEN': (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '',
                    },
                    body: body,
                }).then(function (resp) {
                    if (resp.status === 401) {
                        window.location.reload();
                        return;
                    }
                    // Always parse the body so the modal can surface the
                    // server's status_message on failure (e.g. "Invalid extra
                    // type specified.") instead of a generic fallback.
                    return resp.json().then(function (json) {
                        if (!resp.ok) {
                            var serverMsg = (json && json.status_message) ? json.status_message : null;
                            var err = new Error(serverMsg || ('HTTP ' + resp.status));
                            err.serverMessage = serverMsg;
                            throw err;
                        }
                        return json;
                    });
                }).then(function (json) {
                    if (!json) { return; }
                    m.close();
                    loadCatalog();
                }).catch(function (err) {
                    console.error('hub: activation failed for', extraName, err);
                    m.setBusy(false);
                    m.setError(err.serverMessage || L('ActivateExtraError'));
                });
            },
        });
    }

    function openInstallModal(entry) {
        var extraName = entry.extra_name || '';
        var body = L('InstallExtraBody').replace('{name}', entry.label);

        openModal({
            title:        L('InstallExtraTitle'),
            body:         body,
            primaryLabel: L('Install'),
            onPrimary:    function (m) {
                m.setError('');
                m.setBusy(true);

                fetch(BASE_URL + '/api/v2/admin/extras/install', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'CSRF-TOKEN':   (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '',
                    },
                    body: JSON.stringify({ name: extraName }),
                }).then(function (resp) {
                    if (resp.status === 401) {
                        window.location.reload();
                        return;
                    }
                    // Always parse the body, even on non-OK, so the modal can
                    // surface the server's status_message (e.g. "Not Purchased",
                    // "Invalid Instance or Key") instead of a generic fallback.
                    return resp.json().then(function (json) {
                        if (!resp.ok) {
                            var serverMsg = (json && json.status_message) ? json.status_message : null;
                            var err = new Error(serverMsg || ('HTTP ' + resp.status));
                            err.serverMessage = serverMsg;
                            throw err;
                        }
                        return json;
                    });
                }).then(function (json) {
                    if (!json) { return; }
                    m.close();
                    loadCatalog();
                }).catch(function (err) {
                    console.error('hub: install failed for', extraName, err);
                    m.setBusy(false);
                    m.setError(err.serverMessage || L('InstallExtraError'));
                });
            },
        });
    }

    function openPurchaseModal(entry) {
        var body = L('PurchaseExtraBody').replace('{name}', entry.label);

        openModal({
            title:        L('PurchaseExtraTitle'),
            body:         body,
            primaryLabel: L('ViewExtras'),
            onPrimary:    function (m) {
                window.open('https://www.simplerisk.com/extras/', '_blank', 'noopener');
                m.close();
            },
        });
    }

    // -----------------------------------------------------------------------
    // Sub-hub mode. Replaces .hub__main with a heading + N sub-tiles + Back
    // link. The chip row, search, and view toggle stay in the DOM but are
    // visually de-emphasized via .hub--sub-hub-mode on the root.
    // -----------------------------------------------------------------------
    function enterSubHub(subHub) {
        activeSubHub = subHub;
        root.classList.add('hub--sub-hub-mode');

        // Push URL state — bookmark/back-button friendly.
        var params = new URLSearchParams(window.location.search);
        params.set('section', subHub.section_key);
        // Clear any chip/fav/search state — they don't apply inside the sub-hub.
        params.delete('q');
        params.delete('fav');
        params.delete('tag');
        window.history.pushState(null, '', '?' + params.toString());

        renderSubHub(subHub);
    }

    function renderSubHub(subHub) {
        while (grid.firstChild) { grid.removeChild(grid.firstChild); }

        var section = document.createElement('div');
        section.className = 'hub__section hub__section--sub-hub';
        section.dataset.sectionType = 'sub-hub';
        section.dataset.subHubKey   = subHub.section_key;

        var back = document.createElement('a');
        back.className   = 'hub__back-link';
        back.href        = '#';
        back.textContent = '← ' + L('BackToConfigure');
        back.addEventListener('click', function (evt) {
            evt.preventDefault();
            exitSubHub();
        });
        section.appendChild(back);

        var h2 = document.createElement('h2');
        h2.textContent = subHub.heading;
        section.appendChild(h2);

        var tileGrid = document.createElement('div');
        tileGrid.className = 'hub__grid';
        subHub.tiles.forEach(function (st) {
            var subTile = document.createElement('div');
            subTile.className     = 'hub__tile';
            subTile.setAttribute('role',     'link');
            subTile.setAttribute('tabindex', '0');
            subTile.dataset.tileKey  = st.key;
            subTile.dataset.tilePath = '../' + st.path;

            var h3 = document.createElement('h3');
            h3.textContent = st.label;
            subTile.appendChild(h3);

            subTile.addEventListener('click', function () {
                window.location.href = subTile.dataset.tilePath;
            });
            subTile.addEventListener('keydown', function (evt) {
                if (evt.key === 'Enter' || evt.key === ' ') {
                    evt.preventDefault();
                    window.location.href = subTile.dataset.tilePath;
                }
            });

            tileGrid.appendChild(subTile);
        });
        section.appendChild(tileGrid);

        grid.appendChild(section);
    }

    function exitSubHub() {
        activeSubHub = null;
        root.classList.remove('hub--sub-hub-mode');

        // Drop the section param; preserve any other URL state (chip, fav,
        // search, view) so backing out of a sub-hub lands on the same main
        // view the user was on before they entered.
        var params = new URLSearchParams(window.location.search);
        params.delete('section');
        var qs = params.toString();
        window.history.pushState(null, '', qs ? ('?' + qs) : window.location.pathname);

        renderCatalog(allEntries);
    }

    // ---------------------------------------------------------------------------
    // Build a tile DOM element from a catalog entry
    // ---------------------------------------------------------------------------
    function buildTile(entry) {
        var a = document.createElement('div');
        a.className          = 'hub__tile';
        a.setAttribute('role',     'link');
        a.setAttribute('tabindex', '0');
        a.dataset.tileKey    = entry.key;
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

        // State badge — Extras tiles only. Non-Extras tiles get no badge.
        var isExtras = isExtrasEntry(entry);
        if (isExtras) {
            a.dataset.state = entry.state || 'activated';
            var badge = document.createElement('span');
            badge.className = 'hub__tile-state hub__tile-state--' + a.dataset.state;
            badge.textContent = L(STATE_TO_LANG_KEY[a.dataset.state] || a.dataset.state);
            a.appendChild(badge);
        }

        // Description: rendered in-body for every category, and also exposed
        // as a `title` attribute so hover surfaces it as a native browser
        // tooltip. The tooltip is useful when CSS truncates the body text
        // (long descriptions) and as a quick read without committing to a
        // click-through.
        a.setAttribute('title', entry.description || '');
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

        // Tile navigation: click navigates; keyboard Enter/Space also navigates.
        // Extras tiles dispatch through handleExtrasTileClick so state-specific
        // behavior (modals for deactivated/ready_to_download/purchase, no-op
        // for uninstalled) routes correctly; non-Extras tiles navigate inline.
        function handleTileActivation() {
            if (isExtrasEntry(entry)) {
                handleExtrasTileClick(entry, a);
            } else {
                window.location.href = a.dataset.tilePath;
            }
        }
        a.addEventListener('click', handleTileActivation);
        a.addEventListener('keydown', function (evt) {
            if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault();
                handleTileActivation();
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
    // tile (data-tile-key, data-tile-path, data-tags, data-favorited,
    // data-search-text) so the shared starButton + filter logic works on both.
    // ---------------------------------------------------------------------------
    function buildRow(entry) {
        var tr = document.createElement('tr');
        tr.className          = 'hub__row';
        tr.setAttribute('role',     'link');
        tr.setAttribute('tabindex', '0');
        tr.dataset.tileKey    = entry.key;
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
        tr.setAttribute('title', entry.description || '');
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

        // State cell is appended to EVERY row so the list-view <table> stays
        // rectangular when favorites mix Extras and non-Extras tiles. The
        // cell is empty for non-Extras rows; .hub__list-state-cell has
        // width:1% which absorbs empty cells with no visual cost.
        var stateTd = document.createElement('td');
        stateTd.className = 'hub__list-state-cell';
        if (isExtrasEntry(entry)) {
            tr.dataset.state = entry.state || 'activated';
            var rowBadge = document.createElement('span');
            rowBadge.className = 'hub__tile-state hub__tile-state--' + tr.dataset.state;
            rowBadge.textContent = L(STATE_TO_LANG_KEY[tr.dataset.state] || tr.dataset.state);
            stateTd.appendChild(rowBadge);
        }
        tr.appendChild(stateTd);

        // Whole row clickable; star button stops propagation in its own handler.
        // Extras rows dispatch through handleExtrasTileClick so state-specific
        // behavior (modals for deactivated/ready_to_download/purchase, no-op
        // for uninstalled) routes correctly; non-Extras rows navigate inline.
        function handleRowActivation() {
            if (isExtrasEntry(entry)) {
                handleExtrasTileClick(entry, tr);
            } else {
                window.location.href = tr.dataset.tilePath;
            }
        }
        tr.addEventListener('click', handleRowActivation);
        tr.addEventListener('keydown', function (evt) {
            if (evt.key === 'Enter' || evt.key === ' ') {
                evt.preventDefault();
                handleRowActivation();
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
    var allEntries    = [];     // full catalog list
    var activeSearch  = '';
    var activeChip    = 'all';  // single selected chip: 'all', 'favorites', or a domain key
    var activeFavOnly = false;
    var activeView    = 'cards'; // 'cards' (default tile grid) or 'list' (compact table)
    var activeSubHub  = null;    // null in main mode; otherwise the resolved sub_hub object from the catalog response

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
                if (domainKey === 'extras') {
                    domainEntries = sortByStateThenLabel(domainEntries);
                }
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
            // not just the first. E.g., an entry tagged [system, users]
            // renders in both the System section and the Users & Access section.
            TAG_ORDER.forEach(function (tag) {
                var tagged = entries.filter(function (e) {
                    return e.tags.indexOf(tag) !== -1;
                });
                if (!tagged.length) { return; }

                if (tag === 'extras') {
                    tagged = sortByStateThenLabel(tagged);
                }

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

        // Sync the page-breadcrumb title to the current chip. Done here (after
        // the catalog has rendered) rather than in init() so it wins the race
        // against sidebar.php's #script_sidebarmenu onload handler which sets
        // the title to the active submenu's text.
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

                var tile    = fresh.closest('.hub__tile, .hub__row');
                var tileKey = tile.dataset.tileKey;
                var isFav   = tile.dataset.favorited === '1';
                var newFav  = !isFav;

                // Optimistic update
                applyFavoritedState(tile, fresh, newFav);
                syncAllTilesForKey(tileKey, newFav);
                applyFilters();

                // API call
                var method = newFav ? 'POST' : 'DELETE';
                var url    = newFav
                    ? BASE_URL + '/api/v2/admin/settings/favorites'
                    : BASE_URL + '/api/v2/admin/settings/favorites/' + encodeURIComponent(tileKey);
                var fetchOptions = {
                    method:      method,
                    credentials: 'same-origin',
                    headers:     { 'Content-Type': 'application/json' },
                };
                // CSRF token required on POST; DELETE is exempt (csrf_check skips non-POST)
                if (newFav) {
                    fetchOptions.headers['CSRF-TOKEN'] =
                        (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : '';
                    fetchOptions.body = JSON.stringify({ key: tileKey });
                }

                fetch(url, fetchOptions).then(function (resp) {
                    if (resp.status === 401) {
                        window.location.reload();
                        return;
                    }
                    if (!resp.ok) { throw new Error('HTTP ' + resp.status); }
                    refreshFavoritesSection();
                }).catch(function (err) {
                    console.error('hub: star toggle failed for', tileKey, err);
                    // Revert optimistic update
                    applyFavoritedState(tile, fresh, isFav);
                    syncAllTilesForKey(tileKey, isFav);
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

    // Sync the favorited state of all OTHER tiles/rows sharing a tile key
    function syncAllTilesForKey(tileKey, isFav) {
        // CSS.escape is available in all modern browsers; provide inline fallback
        var escaped = (typeof CSS !== 'undefined' && CSS.escape)
            ? CSS.escape(tileKey)
            : tileKey.replace(/([!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~])/g, '\\$1');
        root.querySelectorAll(
            '.hub__tile[data-tile-key="' + escaped + '"], ' +
            '.hub__row[data-tile-key="' + escaped + '"]'
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
                '.hub__tile[data-tile-key="' + escaped + '"], ' +
                '.hub__row[data-tile-key="' + escaped + '"]'
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
    // being shown: "Configure" for 'all', "Favorites" for the favorites chip,
    // and "<Tag> Configure" for a domain chip.
    //
    // TODO i18n: composition is naive (chip-name + space + noun). Word order
    // is English-correct but may invert in some locales. Acceptable trade-off
    // versus exploding into per-tag composed lang keys; revisit if Crowdin
    // reports issues.
    // ---------------------------------------------------------------------------
    function updatePageTitle() {
        var titleEl = document.querySelector('div.page-breadcrumb h4.page-title');
        if (!titleEl) { return; }

        var noun = L('Settings');
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

        // Capture any ?section=<key> from the URL so loadCatalog() can
        // resolve it against the catalog response and enter sub-hub mode
        // on initial render. The catalog hasn't been fetched yet at this
        // point, so the matching sub_hub object isn't available — stash a
        // sentinel that loadCatalog() will swap for the resolved object.
        var params  = new URLSearchParams(window.location.search);
        var section = params.get('section') || '';
        activeSubHub = section ? { _wantedSectionKey: section } : null;

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
    // Fallback tile set rendered when the catalog API fails.
    //
    // These three tiles — Preferences, Health Check, and About — give the
    // admin a clickable recovery path: Preferences is where default values
    // and behavioral toggles live (the most-likely first stop), Health
    // Check is where the admin can diagnose what's wrong, and About
    // identifies the SimpleRisk version. They render through the same
    // buildTile() pipeline as catalog tiles so they look identical.
    // ---------------------------------------------------------------------------
    var FALLBACK_TILES = [
        {
            key:         'settings_preferences',
            label:       'Preferences',
            description: '',
            path:        'admin/settings_preferences.php',
            tags:        ['customization'],
            favorited:   false,
        },
        {
            key:         'health_check',
            label:       'Health Check',
            description: '',
            path:        'admin/health_check.php',
            tags:        ['system'],
            favorited:   false,
        },
        {
            key:         'register',
            label:       'Register & Upgrade',
            description: '',
            path:        'admin/register.php',
            tags:        ['system'],
            favorited:   false,
        },
    ];

    // Localize the hardcoded English label using the existing $lang lookup.
    // The lang keys here ('Preferences', 'HealthCheck', 'RegisterAndUpgrade')
    // are guaranteed loaded by the page's required_localization_keys list.
    var FALLBACK_LANG_KEY = {
        settings_preferences: 'Preferences',
        health_check:         'HealthCheck',
        register:             'RegisterAndUpgrade',
    };
    function localizedFallbackTiles() {
        return FALLBACK_TILES.map(function (t) {
            return Object.assign({}, t, { label: L(FALLBACK_LANG_KEY[t.key] || t.label) });
        });
    }

    // ---------------------------------------------------------------------------
    // Show error state with retry link.
    //
    // Renders an inline error banner above the tile grid and renders the
    // fallback tiles inside .hub__main so they remain interactive. We
    // intentionally avoid blockUI here — a modal overlay would cover the
    // fallback tiles and block the very recovery path they exist to offer.
    // ---------------------------------------------------------------------------
    function showError() {
        // Ensure no stale blockUI overlay is still up (loadCatalog blocks while fetching).
        unblock();

        // Render the fallback tiles using the same pipeline as the success
        // path. renderCatalog() clears .hub__main, then groups by tag — the
        // two fallback tiles both carry the 'system' tag and will land in
        // the System section. Force the chip to 'all' so the tiles are
        // visible regardless of what the URL state had selected (e.g. if
        // the user landed on the page with ?fav=1, neither fallback tile
        // is favorited and the favorites filter would hide them).
        activeChip    = 'all';
        activeFavOnly = false;
        activeSearch  = '';
        allEntries    = localizedFallbackTiles();
        renderCatalog(allEntries);

        // Build an inline error banner with a Retry link. Placed at the top
        // of .hub__main so the fallback tiles render directly below it.
        var existing = root.querySelector('.hub__error-banner');
        if (existing) { existing.parentNode.removeChild(existing); }

        var banner = document.createElement('div');
        banner.className = 'hub__error-banner';
        banner.setAttribute('role', 'alert');

        var msg = document.createElement('span');
        msg.textContent = L('FailedToLoadReports');
        banner.appendChild(msg);

        var retryLink = document.createElement('a');
        retryLink.id               = 'hub-retry';
        retryLink.href             = '#';
        retryLink.textContent      = L('Retry');
        retryLink.style.marginLeft = '0.5em';
        banner.appendChild(retryLink);

        // Insert the banner at the top of .hub__main, above the fallback tiles.
        grid.insertBefore(banner, grid.firstChild);

        // Attach retry handler. Use a single one-shot delegated handler so
        // we don't accumulate listeners across repeated failures.
        $(root).off('click.hubRetry').on('click.hubRetry', '#hub-retry', function (evt) {
            evt.preventDefault();
            $(root).off('click.hubRetry');
            // Remove the banner before retrying so the loading state is clean.
            var b = root.querySelector('.hub__error-banner');
            if (b) { b.parentNode.removeChild(b); }
            loadCatalog();
        });
    }

    // -----------------------------------------------------------------------
    // Async license enrichment.
    //
    // After the catalog has rendered with synchronously-computed state, fire
    // a second request to /admin/settings/extras/licenses. The response
    // tells us which uninstalled Extras the customer has licensed; those
    // tiles upgrade to 'ready_to_download', the rest upgrade to 'purchase'.
    // If the request fails or times out (15 s), the uninstalled tiles stay
    // in 'Checking…' state and an inline retry notice appears at the bottom
    // of the Extras section.
    // -----------------------------------------------------------------------
    function enrichExtrasLicenses() {
        // Skip if no uninstalled tiles to enrich
        var anyUninstalled = allEntries.some(function (e) { return e.state === 'uninstalled'; });
        if (!anyUninstalled) { return; }

        removeLicenseErrorNotice();

        var controller = new AbortController();
        var timeoutId  = setTimeout(function () { controller.abort(); }, 15000);

        fetch(BASE_URL + '/api/v2/admin/settings/extras/licenses', {
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
            if (!json) { return; }
            var licensed = Array.isArray(json && json.data && json.data.licensed)
                ? json.data.licensed
                : [];
            applyLicenseEnrichment(licensed);
        }).catch(function (err) {
            clearTimeout(timeoutId);
            console.error('hub: license enrichment failed', err);
            showLicenseErrorNotice();
        });
    }

    function applyLicenseEnrichment(licensed) {
        // Update allEntries: uninstalled tiles become ready_to_download (if
        // in licensed) or purchase. Other states stay as-is.
        allEntries = allEntries.map(function (e) {
            if (e.state !== 'uninstalled') { return e; }
            var newState = licensed.indexOf(e.extra_name) !== -1
                ? 'ready_to_download'
                : 'purchase';
            return Object.assign({}, e, { state: newState });
        });

        // Don't render the main hub if the user is currently in a sub-hub —
        // the state mutation above is still useful for when they exit, but
        // rendering now would yank them out of the sub-hub they're viewing.
        if (activeSubHub) { return; }

        // Full re-render so badges, sort order, and click handlers update.
        renderCatalog(allEntries);
    }

    function showLicenseErrorNotice() {
        // Locate (or append to) the Extras section. After Phase 2 sort, the
        // section is rendered with data-section-tag="extras".
        var section = root.querySelector('.hub__section[data-section-tag="extras"]');
        if (!section) { return; }

        removeLicenseErrorNotice();

        var notice = document.createElement('div');
        notice.className = 'hub__license-error';
        notice.setAttribute('role', 'alert');

        var msg = document.createElement('span');
        msg.textContent = L('CouldNotReachServicesApi') + ' ';
        notice.appendChild(msg);

        var retry = document.createElement('a');
        retry.href              = '#';
        retry.dataset.action    = 'retry-license';
        retry.textContent       = L('Retry');
        notice.appendChild(retry);

        section.appendChild(notice);

        retry.addEventListener('click', function (evt) {
            evt.preventDefault();
            removeLicenseErrorNotice();
            enrichExtrasLicenses();
        });
    }

    function removeLicenseErrorNotice() {
        var existing = root.querySelector('.hub__license-error');
        if (existing) { existing.parentNode.removeChild(existing); }
    }

    // ---------------------------------------------------------------------------
    // Fetch catalog and render
    // ---------------------------------------------------------------------------
    function loadCatalog() {
        block(L('LoadingReports'));

        var controller = new AbortController();
        var timeoutId  = setTimeout(function () { controller.abort(); }, 15000);

        fetch(BASE_URL + '/api/v2/admin/settings/catalog', {
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
            var tiles = (json && json.data && json.data.tiles) ? json.data.tiles : [];

            // Every Configure Hub tile renders identically (no kind split).
            allEntries = tiles;

            unblock();

            // Resolve a wanted sub-hub section (from ?section=<key>) against
            // the fetched catalog before falling through to the main render.
            // The sentinel was stashed by hydrateUrlState() at init time.
            if (activeSubHub && activeSubHub._wantedSectionKey) {
                var wanted = activeSubHub._wantedSectionKey;
                var match  = null;
                tiles.forEach(function (t) {
                    if (t.sub_hub && t.sub_hub.section_key === wanted) {
                        match = t.sub_hub;
                    }
                });
                activeSubHub = match;
                if (activeSubHub) {
                    root.classList.add('hub--sub-hub-mode');
                    renderSubHub(activeSubHub);
                    enrichExtrasLicenses();
                    return;
                }
                // No match — drop the sentinel and fall through to the main render.
                activeSubHub = null;
            }

            renderCatalog(allEntries);
            enrichExtrasLicenses();
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

        // Browser Back/Forward — round-trip between the main Hub and any
        // sub-hub section by reading the URL's ?section=<key> param and
        // re-rendering accordingly. Push state for sub-hub entry/exit is
        // done in enterSubHub/exitSubHub; this listener catches user-driven
        // navigation between those states.
        window.addEventListener('popstate', function () {
            var params  = new URLSearchParams(window.location.search);
            var section = params.get('section') || '';
            if (section) {
                var match = null;
                allEntries.forEach(function (t) {
                    if (t.sub_hub && t.sub_hub.section_key === section) {
                        match = t.sub_hub;
                    }
                });
                if (match) {
                    activeSubHub = match;
                    root.classList.add('hub--sub-hub-mode');
                    renderSubHub(match);
                    return;
                }
            }
            activeSubHub = null;
            root.classList.remove('hub--sub-hub-mode');
            renderCatalog(allEntries);
        });

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
