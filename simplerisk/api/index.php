<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
	require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/epiphany/src/Epi.php'));
	require_once(realpath(__DIR__ . '/../includes/api.php'));

	// Add various security headers
	header("X-Frame-Options: DENY");
	header("X-XSS-Protection: 1; mode=block");

	// If we want to enable the Content Security Policy (CSP) - This may break Chrome
	if (CSP_ENABLED == "true")
	{
		// Add the Content-Security-Policy header
		header("Content-Security-Policy: default-src 'self' 'unsafe-inline';");
	}

	// Session handler is database
	if (USE_DATABASE_FOR_SESSIONS == "true")
	{
		session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
	}

	// Start the session
	session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        if (!isset($_SESSION))
        {
        	session_name('SimpleRisk');
        	session_start();
        }

	// Check for session timeout or renegotiation
	session_check();

	// If access is authenticated
	if (is_authenticated())
	{
        	// Set the base path for epiphany
	        Epi::setPath('base', realpath(__DIR__ . '/../includes/epiphany/src'));

        	// Initialize the epiphany api
	        Epi::init('api', 'route', 'session');

        	// Disable exceptions
	        Epi::setSetting('exceptions', true);

		// Define the normal routes
		getRoute()->get('/', 'show_endpoints');
		getRoute()->get('/version', 'show_version');
		getRoute()->get('/whoami', 'whoami');
		getRoute()->get('/management', 'show_management');
        getRoute()->get('/management/risk/viewhtml', 'viewriskHtml');
		getRoute()->get('/management/risk/view', 'viewrisk');
		getRoute()->get('/management/risk/add', 'addrisk');
		getRoute()->get('/management/mitigation/view', 'viewmitigation');
		getRoute()->get('/management/mitigation/add', 'addmitigation');
		getRoute()->get('/management/review/view', 'viewreview');
		getRoute()->get('/management/review/add', 'addreview');
		getRoute()->get('/admin', 'show_admin');
		getRoute()->get('/admin/users/all', 'allusers');
		getRoute()->get('/admin/users/enabled', 'enabledusers');
		getRoute()->get('/admin/users/disabled', 'disabledusers');
		getRoute()->get('/reports', 'show_reports');
		getRoute()->post('/reports/dynamic', 'dynamicrisk');

        // Actions via ajax
        getRoute()->get('/management/risk/reopen', 'reopen');
        getRoute()->get('/management/risk/overview', 'overview');
        
        getRoute()->get('/management/risk/closerisk', 'closeriskHtml');
        getRoute()->post('/management/risk/closerisk', 'closerisk');
        
        getRoute()->get('/management/risk/view_all_reviews', 'viewAllReviews');
        getRoute()->get('/management/risk/editdetails', 'editdetails');
        getRoute()->post('/management/risk/saveDetails', 'saveDetails');
        getRoute()->post('/management/risk/saveMitigation', 'saveMitigation');
        getRoute()->post('/management/risk/saveReview', 'saveReview');
        
        getRoute()->get('/management/risk/changestatus', 'changestatus');
        getRoute()->post('/management/risk/updateStatus', 'updateStatus');
        
        getRoute()->get('/management/risk/scoreaction', 'scoreaction');
        getRoute()->post('/management/risk/saveScore', 'saveScore');
        
        getRoute()->post('/management/risk/saveSubject', 'saveSubject');
        
        getRoute()->post('/management/risk/saveComment', 'saveComment');

        
		// Define the API routes
		getApi()->get('/version.json', 'api_version', EpiApi::external);

		// Run epiphany
		getRoute()->run();
	}
?>
