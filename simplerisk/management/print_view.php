<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
     * License, v. 2.0. If a copy of the MPL was not distributed with this
     * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Include required functions file
    require_once(realpath(__DIR__ . '/../includes/functions.php'));
    require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
    require_once(realpath(__DIR__ . '/../includes/display.php'));
    require_once(realpath(__DIR__ . '/../includes/permissions.php'));
    require_once(realpath(__DIR__ . '/../vendor/autoload.php'));

	// Add various security headers
	add_security_headers();

	// Add the session — flagged is_action because this view runs a
	// secondary team-separation check that may itself redirect; without
	// the flag a denial would bounce the user back to this same URL.
	$permissions = array(
			"check_access" => true,
			"check_riskmanagement" => true,
			"is_action" => true,
	);
	add_session_check($permissions);

	// Include the CSRF Magic library
	include_csrf_magic();

	// Include the SimpleRisk language file
	require_once(language_file());

	// Set a global variable for the current app version, so we don't have to call a function every time
	$current_app_version = current_version("app");

    // Check if a risk ID was sent
    $id = 0;
    $risk = [];
    if (isset($_GET['id'])) {

        // Test that the ID is a numeric value
        $id = (is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

        // If team separation is enabled
        if (team_separation_extra()) {

            //Include the team separation extra
            require_once(realpath(__DIR__ . '/../extras/separation/index.php'));

            // If the user should not have access to the risk
            if (!extra_grant_access($_SESSION['uid'], $id)) {
                redirect_permission_denied('NoPermissionForThisAction', "print view of risk id={$id}");
            }
        }

        // Get the details of the risk
        $risk = get_risk_by_id($id);
    }

    // Only $id/$subject/$status/$calculated_risk are needed here now --
    // Details/Mitigation/Review no longer render from a pile of individually
    // extracted $risk[0][...] fields (view_print_risk_details()/
    // view_print_mitigation_details()/view_print_mitigation_controls()/
    // view_print_review_details(), includes/display.php); RiskDetailsView
    // (js/simplerisk/common/risk-details-view.js) renders all three tabs
    // client-side from the same /api/v2/ui/risk/{id}/* endpoints the live
    // Cards view (management/view.php) uses, so this page only needs enough
    // server-side data for the top summary table.
    $display_risk = (count($risk) != 0);
    if ($display_risk) {
        $subject = $risk[0]['subject'];
        $status = $risk[0]['status'];
        $calculated_risk = $risk[0]['calculated_risk'];
    } else {
        $subject = "N/A";
        $calculated_risk = "0.0";

        // If Risk ID exists but this session can't see it vs. doesn't exist at all.
        $status = check_risk_by_id($id) ? $lang["RiskDisplayPermission"] : $lang["RiskIdDoesNotExist"];
    }

    // The exact key list CUSTOM:common/risk-details-view.js and
    // CUSTOM:common/risk-details-form.js (the latter loaded only for its
    // shared window.CvssRiskLevelPill/window.ClassicScoring-family bridges --
    // RiskDetailsForm.init() is never called on this read-only page) need,
    // resolved via the SAME get_localization_required_by_scripts() map
    // header.php's render_header_and_sidebar() uses -- see that function's
    // own docblock (includes/functions.php) for why it's callable standalone.
    // This page renders its own <head> instead of going through
    // render_header_and_sidebar() (no sidebar/topbar/breadcrumb chrome --
    // design-system.md's print view is deliberately bare), so it resolves
    // this itself rather than dragging that whole helper in.
    $print_required_scripts = ['CUSTOM:common/risk-details-view.js', 'CUSTOM:common/risk-details-form.js'];
    $print_localization_keys = resolve_required_localization_keys(
        $print_required_scripts,
        [],
        get_localization_required_by_scripts()
    );
    $print_lang_json = encode_js_lang_subset(build_js_lang_subset($print_localization_keys, $lang));
?>
<!doctype html>
<html>
	<head>
		<title>SimpleRisk: Enterprise Risk Management Simplified</title>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">

		<!-- Favicon icon -->
		<?php setup_favicon("..");?>

		<!-- Bootstrap CSS -->
        <link rel="stylesheet" href="../css/style.min.css?<?= $current_app_version ?>" />

		<!-- extra css -->
		<link rel="stylesheet" href="../vendor/components/font-awesome/css/fontawesome.min.css?<?= $current_app_version ?>">

		<!-- DataTables (Mitigation Controls list inside the Mitigation tab's Cards) -->
		<link rel="stylesheet" href="../vendor/node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css?<?= $current_app_version ?>">

		<script type="text/javascript">
			var BASE_URL = '<?= $escaper->escapeHtml(rtrim(($_SESSION['base_url'] ?? get_setting("simplerisk_base_url")), '/'))?>';
		</script>

		<!-- _lang/L() -- same baseline shape header.php's own (always-emitted,
		     never-deferred) block establishes, so any of the loaded engines'
		     _lang['X']/L('X') reads degrade to the key name instead of
		     throwing before any deferred script runs. -->
		<script type="text/javascript">
			var _lang = <?= $print_lang_json ?>;
			window.L = window.L || function (k) { return (window._lang && window._lang[k]) || k; };
		</script>

		<!-- jQuery Javascript -->
		<script src="../vendor/node_modules/jquery/dist/jquery.min.js?<?= $current_app_version ?>" id="script_jquery"></script>

		<!-- Bootstrap tether Core JavaScript -->
		<script src="../vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

		<script language="javascript" src="../js/basescript.js?<?= $current_app_version ?>" type="text/javascript" defer></script>

		<!-- Chart.js (Details tab's Risk Scoring History widget) -->
		<script src="../vendor/node_modules/chart.js/dist/chart.umd.js?<?= $current_app_version ?>" id="script_chartjs" defer></script>

		<!-- DataTables (Mitigation Controls list) -- mirrors header.php's own
		     'datatables' case verbatim (the defaults/Show-All-button wiring is
		     page-wide setup, not sidebar-coupled, so it's safe to duplicate
		     here rather than drag render_header_and_sidebar()'s whole sidebar/
		     breadcrumb chrome in just to reach it). -->
		<script src="../vendor/node_modules/datatables.net/js/dataTables.min.js?<?= $current_app_version ?>" defer></script>
		<script src="../vendor/node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js?<?= $current_app_version ?>" id="script_datatables" defer></script>
		<script src="../js/simplerisk/dataTables.renderers.js?<?= $current_app_version ?>" id="script_datatables_renderers" defer></script>
		<script>
			$('#script_datatables').on('load', function () {
				Object.assign(DataTable.defaults, {
					lengthMenu: [[10, 25, 50, -1], [10, 25, 50, _lang['All']]],
					lengthChange: true,
					filter: true,
					processing: true,
					serverSide: true,
					layout: {
						topStart: 'pageLength',
						topEnd: {div: {className: 'col-sm-12 col-md-12 settings'}},
						bottomStart: 'info',
						bottomEnd: {
							className: 'd-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto paginate',
							features: ['paging']
						},
					},
				});
				Object.assign(DataTable.defaults.language, {
					paginate: {
						first: _lang['First'],
						previous: _lang['Previous'],
						next: _lang['Next'],
						last: _lang['Last'],
					}
				});
			});
		</script>

		<!-- Read-only Cards engines (Details/Mitigation/Review), matching
		     management/view.php's own load order requirements: the scoring
		     helper scripts (each defines window.<Method>Scoring, read by
		     both risk-details-form.js and risk-details-view.js at BUILD
		     time) must precede both engines. risk-details-form.js is loaded
		     ONLY for the shared window.CvssRiskLevelPill bridge it defines --
		     RiskDetailsForm.init() is never called on this read-only page,
		     so none of its GridStack/WYSIWYG/multiselect/selectize
		     dependencies are needed here. -->
		<script src="../js/simplerisk/common/cvss-v2-scoring.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/dread-scoring.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/owasp-scoring.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/classic-scoring.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/contributing-risk-scoring.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/risk-details-form.js?<?= $current_app_version ?>" defer></script>
		<script src="../js/simplerisk/common/risk-details-view.js?<?= $current_app_version ?>" defer></script>
	</head>
	<body class="sr-print-page">
		<div class="preloader">
            <div class="lds-ripple">
                <div class="lds-pos"></div>
                <div class="lds-pos"></div>
            </div>
        </div>
		<div id="main-wrapper">
            <!-- Page wrapper  -->
            <div class="page-wrapper" style="top: 0px;">
            	<div class="scroll-content">
            		<div class="content-wrapper">
						<!-- container - It's the direct container of all the -->
						<!-- No '.content' class -- that rule (scss/core/layout/layout.scss)
						     margins a WHITE box in from .page-wrapper's own grey fill,
						     reading as a grey border/ring around the page instead of the
						     grey canvas the Cards design language wants underneath. -->
						<div class='container-fluid'>
							<div class='row'>
								<div class='col-12'>
									<div class="risk-session overview clearfix">
										<div class='row'>
											<div class='col-12'>
<?php
												// The SAME record-header component the live Cards view
												// (management/view.php, via management/partials/overview.php)
												// uses -- view_top_table(), includes/display.php -- so the
												// score tiles and ID/Status/Subject card look identical
												// here. The trailing `false` is display_risk/show-actions:
												// print has no Actions dropdown or inline Edit Subject
												// affordance to wire up (risk.js isn't loaded), regardless
												// of whether the risk itself was found.
												view_top_table($id, $calculated_risk, $subject, $status, false, 0, false);
?>
											</div>
										</div>
									</div>
<?php if ($display_risk): ?>
									<!-- No outer card/border here -- RiskDetailsView.init() already
									     renders its own .sr-qcards-stack of .sr-qcard cards into each
									     mount (General/Scoring/etc.), so wrapping the whole tab in a
									     SECOND bordered box just double-boxed it. The heading sits
									     directly on the grey canvas, same as the record header above. -->
									<div class='mb-4'>
										<h3 class='mb-3'><?= $escaper->escapeHtml($lang['Details']) ?></h3>
										<div id="print-details-view"></div>
									</div>
									<div class='mb-4'>
										<h3 class='mb-3'><?= $escaper->escapeHtml($lang['Mitigation']) ?></h3>
										<div id="print-mitigation-view"></div>
									</div>
									<div class='mb-4'>
										<h3 class='mb-3'><?= $escaper->escapeHtml($lang['Review']) ?></h3>
										<div id="print-review-view"></div>
									</div>
									<!-- Comments/Audit Trail have no Cards renderer of their own
									     (get_comments()/get_audit_trail_html() are plain server-
									     rendered HTML) -- they get an explicit .sr-qcard here, the
									     same card component the record header and every Details/
									     Mitigation/Review card above use, instead of Bootstrap's bare
									     .card-body (no background of its own -- invisible against the
									     grey canvas; it used to look fine only because it sat inside
									     the old .content rule's own white fill). -->
									<div class='sr-qcard mb-4'>
										<div class='sr-qcard-head'><h3 class='sr-qcard-title'><?= $escaper->escapeHtml($lang['Comments']) ?></h3></div>
										<div class='sr-qcard-body comments-container'>
											<?php get_comments($id); ?>
										</div>
									</div>
									<div class='sr-qcard mb-4'>
										<div class='sr-qcard-head'><h3 class='sr-qcard-title'><?= $escaper->escapeHtml($lang['AuditTrail']) ?></h3></div>
										<div class='sr-qcard-body audit-trail-container'>
											<?php get_audit_trail_html($id, 36500, 'risk'); ?>
										</div>
									</div>
<?php else: ?>
									<div class='sr-qcard mb-4'>
										<div class='sr-qcard-body'><strong><?= $escaper->escapeHtml($status) ?></strong></div>
									</div>
<?php endif; ?>
								</div>
							</div>
						</div>
					</div>
                	<!-- End of content-wrapper -->
        		</div>
        		<!-- End of scroll-content -->
          	</div>
          <!-- End Page wrapper  -->
        </div>
        <!-- End Wrapper -->
<?php if ($display_risk): ?>
		<script>
			// Forces open every accordion RiskDetailsView renders (the Details/
			// Mitigation/Review cards themselves already render expanded by
			// default -- see risk-details-view.js's renderCards() -- this
			// covers the nested ones that don't: the CVSS "Advanced Metrics"
			// accordion, the Risk Scoring History widget, and the Mitigation
			// Controls list). A plain CSS class add is not enough on its own:
			// the Scoring History widget lazily builds its Chart.js chart on
			// the FIRST 'show.bs.collapse' event (so the live page never pays
			// for a chart nobody expanded), and that event is a plain jQuery
			// custom event -- .trigger()ing it fires the same handler without
			// needing a real bootstrap.Collapse instance behind it.
			function expandAllAccordionsIn(containerSelector) {
				$(containerSelector).find('.accordion-collapse.collapse:not(.show)').each(function () {
					var $body = $(this).addClass('show');
					$body.trigger('show.bs.collapse').trigger('shown.bs.collapse');
					var id = $body.attr('id');
					if (id) {
						$('[data-bs-toggle="collapse"][data-bs-target="#' + id + '"]')
							.removeClass('collapsed')
							.attr('aria-expanded', 'true');
					}
				});

				// Print/export means "everything", not "the first page" -- a
				// server-side-paged DataTable (Mitigation Controls) otherwise
				// silently drops every row past its default page length.
				$(containerSelector).find('table').each(function () {
					if ($.fn.DataTable.isDataTable(this)) {
						$(this).DataTable().page.len(-1).draw();
					}
				});
			}

			$(function () {
				RiskDetailsView.init('#print-details-view', <?= (int)$id ?>, function () {
					expandAllAccordionsIn('#print-details-view');
				});
				RiskDetailsView.init('#print-mitigation-view', <?= (int)$id ?>, function () {
					expandAllAccordionsIn('#print-mitigation-view');
				}, {
					tabIndex: 2,
					valuesPath: '/ui/risk/<?= (int)$id ?>/mitigation-values',
					supportingDocumentationHtmlKey: 'mitigation_supporting_documentation_html'
				});
				RiskDetailsView.init('#print-review-view', <?= (int)$id ?>, function () {
					expandAllAccordionsIn('#print-review-view');
				}, {
					tabIndex: 3,
					valuesPath: '/ui/risk/<?= (int)$id ?>/review-values',
					reviewHistoryHtmlKey: 'review_history_html'
				});

				// Fading out the preloader once everything is done rendering
				$(".preloader").fadeOut();
			});
		</script>
<?php else: ?>
		<script>
			$(function() {
				// Fading out the preloader once everything is done rendering
				$(".preloader").fadeOut();
			});
		</script>
<?php endif; ?>
	</body>
</html>
