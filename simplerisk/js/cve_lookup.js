/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: CHECK CVE ID *
 **************************/
function check_cve_id(fieldName)
{
	var cve = document.getElementById(fieldName).value;
	var pattern = /cve\-\d{4}-\d{4}/i;

	// If the field is a CVE ID
	if (cve.match(pattern))
	{
		// Select the CVSS Scoring Method
		select_cvss();

		// Get the CVE info
		get_cve_info(cve);

		// Get the CVSS info
		get_cvss_info(cve);
	}
}

/**************************
 * FUNCTION: GET CVE INFO *
 **************************/
function get_cve_info(cve)
{
	$.ajax({
	type:'GET',
	url:'https://vfeed.simplerisk.it/?method=get_cve&id='+cve,
	processData: true,
	cache: true,
	data: {},
	dataType: 'json',
	success: function (data) {
		process_cve_info(data);
	}
	});
}

/*******************************
 * FUNCTION: PROCESS CVE INFO *
 *******************************/
function process_cve_info(cve_info_json)
{
        // Parse out the JSON values and process them
        var url = cve_info_json[0]['url'];
	document.getElementById('notes').value=url;
        var summary = cve_info_json[0]['summary'];
	document.getElementById('assessment').value=summary;
        var id = cve_info_json[0]['id'];
        var modified = cve_info_json[0]['modified'];
        var published = cve_info_json[0]['published'];
}

/***************************
 * FUNCTION: GET CVSS INFO *
 ***************************/
function get_cvss_info(cve)
{
        $.ajax({
        type:'GET',
        url:'https://vfeed.simplerisk.it/?method=get_cvss&id='+cve,
        processData: true,
        cache: true,
        data: {},
        dataType: 'json',
        success: function (data) {
                process_cvss_info(data);
        }
        });
}

/*******************************
 * FUNCTION: PROCESS CVSS INFO *
 *******************************/
function process_cvss_info(cvss_info_json)
{
	// Parse out the JSON values and process them
	var access_complexity = cvss_info_json[0]['access complexity'];
	process_access_complexity(access_complexity);
	var access_vector = cvss_info_json[0]['access vector'];
	process_access_vector(access_vector);
	var authentication = cvss_info_json[0]['authentication'];
	process_authentication(authentication);
	var availability_impact = cvss_info_json[0]['availability impact'];
	process_availability_impact(availability_impact);
	var base = cvss_info_json[0]['base'];
	var confidentiality_impact = cvss_info_json[0]['confidentiality impact'];
	process_confidentiality_impact(confidentiality_impact);
	var exploit = cvss_info_json[0]['exploit'];
	var impact = cvss_info_json[0]['impact'];
	var integrity_impact = cvss_info_json[0]['integrity impact'];
	process_integrity_impact(integrity_impact);
}

/***************************************
 * FUNCTION: PROCESS ACCESS COMPLEXITY *
 ***************************************/
function process_access_complexity(access_complexity)
{
	switch (access_complexity)
	{
		case "high":
			document.getElementById('AccessComplexity').value="H";
			break;
		case "medium":
			document.getElementById('AccessComplexity').value="M";
			break;
		case "low":
			document.getElementById('AccessComplexity').value="L";
			break;
	}
}

/***********************************
 * FUNCTION: PROCESS ACCESS VECTOR *
 ***********************************/
function process_access_vector(access_vector)
{
        switch (access_vector)
        {
                case "local":
                        document.getElementById('AccessVector').value="L";
                        break;
                case "adjacent network":
                        document.getElementById('AccessVector').value="A";
                        break;
                case "network":
                        document.getElementById('AccessVector').value="N";
                        break;
        }
}

/************************************
 * FUNCTION: PROCESS AUTHENTICATION *
 ************************************/
function process_authentication(authentication)
{
        switch (authentication)
        {
                case "none":
                        document.getElementById('Authentication').value="N";
                        break;
                case "single instance":
                        document.getElementById('Authentication').value="S";
                        break;
                case "multiple instances":
                        document.getElementById('Authentication').value="M";
                        break;
        }
}

/********************************************
 * FUNCTION: PROCESS CONFIDENTIALITY IMPACT *
 ********************************************/
function process_confidentiality_impact(confidentiality_impact)
{
        switch (confidentiality_impact)
        {
                case "none":
                        document.getElementById('ConfImpact').value="N";
                        break;
                case "partial":
                        document.getElementById('ConfImpact').value="P";
                        break;
                case "complete":
                        document.getElementById('ConfImpact').value="C";
                        break;
        }
}

/**************************************
 * FUNCTION: PROCESS INTEGRITY IMPACT *
 **************************************/
function process_integrity_impact(integrity_impact)
{
        switch (integrity_impact)
        {
                case "none":
                        document.getElementById('IntegImpact').value="N";
                        break;
                case "partial":
                        document.getElementById('IntegImpact').value="P";
                        break;
                case "complete":
                        document.getElementById('IntegImpact').value="C";
                        break;
        }
}

/*****************************************
 * FUNCTION: PROCESS AVAILABILITY IMPACT *
 *****************************************/
function process_availability_impact(availability_impact)
{
        switch (availability_impact)
        {
                case "none":
                        document.getElementById('AvailImpact').value="N";
                        break;
                case "partial":
                        document.getElementById('AvailImpact').value="P";
                        break;
                case "complete":
                        document.getElementById('AvailImpact').value="C";
                        break;
        }
}

/*************************
 * FUNCTION: SELECT CVSS *
 *************************/
function select_cvss()
{
	// Select CVSS from the Scoring Method dropdown
	var ddl = document.getElementById("select");
	var opts = ddl.options.length;
	for (var i=0; i<opts; i++)
	{
		if (ddl.options[i].value == "2")
		{
			ddl.options[i].selected = true;
			break;
		}
	}

	// Show the CVSS scoring div
	document.getElementById("cvss").style.display = "";

	// Hide the other scoring divs
	document.getElementById("classic").style.display = "none";
	document.getElementById("dread").style.display = "none";
	document.getElementById("owasp").style.display = "none";
	document.getElementById("custom").style.display = "none";
}
