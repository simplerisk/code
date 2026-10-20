<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // The Advanced Search page (spec §5.4). A Core-tree page with Extra-owned
    // behaviour: with the Extra active it renders the search mount and loads
    // the Extra's scripts; without it, the shell with the locked affordance.
    // There is no sidebar entry; the topbar is the way in.
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    require_once(realpath(__DIR__ . '/../includes/functions.php'));

    $advanced_search_active = advanced_search_extra();
    $advanced_search_scripts = $advanced_search_active ? [
        'EXTRA:JS:advanced_search:search-core.js',
        'EXTRA:JS:advanced_search:search-dom.js',
        'EXTRA:JS:advanced_search:search-page.js',
        'EXTRA:JS:advanced_search:search-related.js',
    ] : [];

    // Any one searchable domain opens the page (the union of the providers'
    // permissions); which TYPES it may search is decided per provider, and a
    // type the caller cannot search is omitted from the rail. The conditional
    // types' permissions are listed too; a holder of one whose Extra is
    // inactive, or a holder of `assessments` without
    // `assessment_view_results` (the assessment type needs both), gets the
    // page's no-access state.
    render_header_and_sidebar(
        $advanced_search_scripts,
        ['check_any_of' => ['riskmanagement', 'governance', 'compliance', 'asset', 'im_incidents', 'assessments', 'vm_vulnerabilities']],
        breadcrumb_title_key: 'Search'
    );

    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/settings_catalog.php'));

    if ($advanced_search_active) {
        require_once(realpath(__DIR__ . '/../extras/advanced_search/index.php'));
        if (!function_exists('render_advanced_search_page')) {
            write_debug_log("The Advanced Search Extra is active but has no render_advanced_search_page(); it predates this Core and needs updating.", 'warning');
        }
    }

    // An installed Extra older than this Core has no page renderer;
    // display_search_page_body() falls back to an "update the Extra" card.
    display_search_page_body(
        $advanced_search_active,
        $advanced_search_active ? null : advanced_search_acquisition_state(is_admin(), get_setting('registration_registered') == 1)
    );

    render_footer();
?>
