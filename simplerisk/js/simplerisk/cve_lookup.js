/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/**************************
 * FUNCTION: CHECK CVE ID *
 **************************/
function check_cve_id(fieldName, parent)
{
    var cve = $("[name="+ fieldName +"]", parent).val();
	var pattern = /cve\-\d{4}-\d{4}/i;

	// If the field is a CVE ID
	if (cve.match(pattern))
	{
		// Select the CVSS Scoring Method
		select_cvss(parent);

		// Get the NVD CVE info
		get_nvd_cve_info(cve, parent);

		// Get the CVE info
		//get_cve_info(cve, parent);

		// Get the CVSS info
		//get_cvss_info(cve, parent);

		// Get the Nessus info
		//get_nessus_info(cve, parent);
	}
}

/******************************
 * FUNCTION: GET NVD CVE INFO *
 ******************************/
function get_nvd_cve_info(cve, parent)
{
    var url = 'https://olbat.github.io/nvdcve/'+cve.toUpperCase()+'.json';
    $.ajax({
        type:'GET',
        url:url,
        processData: true,
        cache: true,
        data: '',
        dataType: 'json',
        success: function (data) {
		//console.log(data);
                process_nvd_cve_info(data, parent);
        }
    });
}

/**********************************
 * FUNCTION: PROCESS NVD CVE INFO *
 **********************************/
function process_nvd_cve_info(cve_info_json, parent)
{
    var reference_id = cve_info_json['cve']['CVE_data_meta']['ID'];
    var assessment = cve_info_json['cve']['description']['description_data'][0]['value'];
    
    if (assessment.includes(". "))
    {
        var subject = assessment.substring(0, assessment.indexOf(". ")+1);
    }
    else var subject = assessment;

    var notes = 'http://cve.mitre.org/cgi-bin/cvename.cgi?name='+reference_id;
    var CVSS_v2_vector = cve_info_json['impact']['baseMetricV2']['cvssV2']['vectorString'];
    var CVSS_v2_vector_array = CVSS_v2_vector.split("/");

    for (var i=0; i < CVSS_v2_vector_array.length; i++)
    {
        CVSS_v2 = CVSS_v2_vector_array[i].split(":");
        var name = CVSS_v2[0];
        var value = CVSS_v2[1];
        this[name] = value;
    }

    if (reference_id) {
       $("[name=reference_id]", parent).val(reference_id);
    }
    if (subject) {
        $("[name=subject]", parent).val(subject);
    }
    if (assessment) {
        $("[name=assessment]", parent).val(assessment);
    }
    if (notes) {
        $("[name=notes]", parent).val(notes);
    }

    $("[name=AccessVector]", parent).val(AV);
    $("[name=AccessComplexity]", parent).val(AC);
    $("[name=Authentication]", parent).val(Au);
    $("[name=ConfImpact]", parent).val(C);
    $("[name=IntegImpact]", parent).val(I);
    $("[name=AvailImpact]", parent).val(A);
    
}

/**************************
 * FUNCTION: GET CVE INFO *
 **************************/
function get_cve_info(cve, parent)
{
	$.ajax({
	    type:'GET',
	    url:'https://olbat.github.io/nvdcve/'+cve+'.json',
	    processData: true,
	    cache: true,
	    data: {},
	    dataType: 'json',
	    success: function (data) {
		    process_cve_info(data, parent);
	    }
	});
}

/*******************************
 * FUNCTION: PROCESS CVE INFO *
 *******************************/
function process_cve_info(cve_info_json, parent)
{
    if (cve_info_json && cve_info_json[0]) {
        // Parse out the JSON values and process them

        if ('url' in cve_info_json[0])
            $("[name=notes]", parent).val(cve_info_json[0]['url']);

        if ('summary' in cve_info_json[0])
            $("[name=assessment]").val(cve_info_json[0]['summary']);
    }
}

/***************************
 * FUNCTION: GET CVSS INFO *
 ***************************/
function get_cvss_info(cve, parent)
{
    $.ajax({
        type:'GET',
        url:'https://olbat.github.io/nvdcve/'+cve+'.json',
        processData: true,
        cache: true,
        data: {},
        dataType: 'json',
        success: function (data) {
            process_cvss_info(data, parent);
        }
    });
}

/*******************************
 * FUNCTION: PROCESS CVSS INFO *
 *******************************/
function process_cvss_info(cvss_info_json, parent)
{
    if (cvss_info_json && cvss_info_json[0]) {
        // Parse out the JSON values and process them

        if ('Access Complexity' in cvss_info_json[0])
            process_access_complexity(cvss_info_json[0]['Access Complexity'], parent);

        if ('Access Vector' in cvss_info_json[0])
            process_access_vector(cvss_info_json[0]['Access Vector'], parent);

        if ('Authentication' in cvss_info_json[0])
            process_authentication(cvss_info_json[0]['Authentication'], parent);

        if ('Availability Impact' in cvss_info_json[0])
            process_availability_impact(cvss_info_json[0]['Availability Impact'], parent);

        if ('Confidentiality Impact' in cvss_info_json[0])
            process_confidentiality_impact(cvss_info_json[0]['Confidentiality Impact'], parent);

        if ('Integrity Impact' in cvss_info_json[0])
            process_integrity_impact(cvss_info_json[0]['Integrity Impact'], parent);
    }
}

/***************************************
 * FUNCTION: PROCESS ACCESS COMPLEXITY *
 ***************************************/
function process_access_complexity(access_complexity, parent)
{
	switch (access_complexity)
	{
		case "high":
            $("[name=AccessComplexity]", parent).val("H");
			break;
		case "medium":
            $("[name=AccessComplexity]", parent).val("M");
			break;
		case "low":
            $("[name=AccessComplexity]", parent).val("L");
			break;
	}
}

/***********************************
 * FUNCTION: PROCESS ACCESS VECTOR *
 ***********************************/
function process_access_vector(access_vector, parent)
{
    switch (access_vector)
    {
        case "local":
            $("[name=AccessVector]", parent).val("L");
            break;
        case "adjacent network":
            $("[name=AccessVector]", parent).val("A");
            break;
        case "network":
            $("[name=AccessVector]", parent).val("N");
            break;
    }
}

/************************************
 * FUNCTION: PROCESS AUTHENTICATION *
 ************************************/
function process_authentication(authentication, parent)
{
    switch (authentication)
    {
        case "none":
            $("[name=Authentication]", parent).val("N");
            break;
        case "single instance":
            $("[name=Authentication]", parent).val("S");
            break;
        case "multiple instances":
            $("[name=Authentication]", parent).val("M");
            break;
    }
}

/********************************************
 * FUNCTION: PROCESS CONFIDENTIALITY IMPACT *
 ********************************************/
function process_confidentiality_impact(confidentiality_impact, parent)
{
    switch (confidentiality_impact)
    {
        case "none":
            $("[name=ConfImpact]", parent).val("N");
            break;
        case "partial":
            $("[name=ConfImpact]", parent).val("P");
            break;
        case "complete":
            $("[name=ConfImpact]", parent).val("C");
            break;
    }
}

/**************************************
 * FUNCTION: PROCESS INTEGRITY IMPACT *
 **************************************/
function process_integrity_impact(integrity_impact, parent)
{
    switch (integrity_impact)
    {
        case "none":
            $("[name=IntegImpact]", parent).val("N");
            break;
        case "partial":
            $("[name=IntegImpact]", parent).val("P");
            break;
        case "complete":
            $("[name=IntegImpact]", parent).val("C");
            break;
    }
}

/*****************************************
 * FUNCTION: PROCESS AVAILABILITY IMPACT *
 *****************************************/
function process_availability_impact(availability_impact, parent)
{
    switch (availability_impact)
    {
        case "none":
            $("[name=AvailImpact]", parent).val("N");
            break;
        case "partial":
            $("[name=AvailImpact]", parent).val("P");
            break;
        case "complete":
            $("[name=AvailImpact]", parent).val("C");
            break;
    }
}

/*************************
 * FUNCTION: SELECT CVSS *
 *************************/
function select_cvss(parent)
{
	// Select CVSS from the Scoring Method dropdown
    var ddl = $("[name=scoring_method]", parent);
    ddl.val(2);

	// Show the CVSS scoring div
    $(".cvss-holder", parent).show();

	// Hide the other scoring divs
    $(".classic-holder", parent).hide();
    $(".dread-holder", parent).hide();
    $(".owasp-holder", parent).hide();
    $(".custom-holder", parent).hide();
}

/*************************
 * FUNCTION: Show/Hide Scoring elements *
 *************************/
function handleSelection(choice, parent) {
    if (choice=="1") {
        $(".classic-holder", parent).show();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="2") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).show();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="3") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).show();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="4") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).show();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="5") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).show();
        $(".contributing-risk-holder", parent).hide();
    }
    if (choice=="6") {
        $(".classic-holder", parent).hide();
        $(".cvss-holder", parent).hide();
        $(".dread-holder", parent).hide();
        $(".owasp-holder", parent).hide();
        $(".custom-holder", parent).hide();
        $(".contributing-risk-holder", parent).show();
    }
}

/*****************************
 * FUNCTION: GET NESSUS INFO *
 *****************************/
function get_nessus_info(cve, parent)
{
    $.ajax({
        type:'GET',
        url:'https://olbat.github.io/nvdcve/'+cve+'.json',
        processData: true,
        cache: true,
        data: {},
        dataType: 'json',
        success: function (data) {
                process_nessus_info(data, parent);
        }
    });
}

/*********************************
 * FUNCTION: PROCESS NESSUS INFO *
 *********************************/
function process_nessus_info(cve_info_json, parent)
{
    if (cve_info_json && cve_info_json[0]) {
        // Parse out the JSON values and process them
        if ('name' in cve_info_json[0])
            $("[name=subject]").val(cve_info_json[0]['name']);
    }
}
