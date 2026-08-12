<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));

    // vm_vulnerabilities is deliberately NOT in this list. 'vulnerability' is
    // absent from both of ai_context_search_entities()'s search maps
    // (ai_context_search_columns()'s plaintext map and the encrypted-name
    // map), so it can never be the RESULT of a search -- it only ever
    // appears as a depth-1 neighbor of an asset or risk focal. A caller
    // holding vm_vulnerabilities alone (no riskmanagement/asset/governance/
    // compliance/view_exception) could reach this page but could never
    // search for anything, making the page permanently blank for them.
    // Granting the permission here would be a dead grant, not a real
    // capability -- see reports_catalog.php's matching 'permissions' entry,
    // which must stay in sync with this gate.
    render_header_and_sidebar(
        ['graphology', 'datatables', 'CUSTOM:pages/connectivity-visualizer.js'],
        ['check_any_of' => ['riskmanagement', 'asset', 'governance', 'compliance', 'view_exception']],
        active_sidebar_submenu: 'Reporting_Reports',
        active_sidebar_menu: 'Reporting',
        breadcrumb_title_key: 'ConnectivityVisualizer'
    );

    // The node-type -> color map is defined ONCE, server-side, and handed to
    // the page. The JS must never carry its own copy: a second list silently
    // diverges the first time a node type is added.
    require_once(realpath(__DIR__ . '/../includes/entity_graph.php'));

    global $escaper;
?>
<div class="row">
    <div class="col-12">
        <!--
            The Explorer renders entirely client-side from
            GET /api/v2/ai/context/{type}/{id}. No graph data is emitted here:
            the same permission- and team-scoped bundle the AI consumer reads
            is the single source of truth, so the report can never drift from
            it the way the old server-rendered path did.
        -->
        <div id="sr-connectivity-explorer"
             class="sr-connectivity-explorer"
             data-node-palette="<?= $escaper->escapeHtmlAttr(json_encode(graph_node_palette())) ?>"></div>
    </div>
</div>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
