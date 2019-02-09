<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required configuration files
require_once(realpath(__DIR__ . '/functions.php'));

// Include the language file
require_once(language_file());

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

/*******************************************
 * FUNCTION: POSSIBLE FORMATS              *
 * Calculates what formats the input       *
 * (formatted date string) can be in       *
 *******************************************/
function possibleFormats($date) {

    if (!(preg_match("(\d{2}[ \.\/-]{1}\d{2}[ \.\/-]{1}\d{4})", $date) || preg_match("(\d{4}[ \.\/-]{1}\d{2}[ \.\/-]{1}\d{2})", $date)))
        return array();

    $separator = preg_replace("/([0-9]*)/", "", $date)[0];
    $parts = explode($separator, $date);

    if ((int)$parts[0] > 31) { //starts with Y
        if ((int)$parts[1] === (int)$parts[2]) { // month and day are the same, just return something
            return array("Y{$separator}m{$separator}d");
        } elseif ((int)$parts[1] > 12) { //it's Ydm
            return array("Y{$separator}d{$separator}m");
        } elseif((int)$parts[2] > 12) { //it's Ymd
            return array("Y{$separator}m{$separator}d");
        } else { //can be both
            return array("Y{$separator}d{$separator}m", "Y{$separator}m{$separator}d");
        }
    } elseif((int)$parts[2] > 31) { //ends with Y
        if ((int)$parts[0] === (int)$parts[1]) { // month and day are the same, just return something
            return array("d{$separator}m{$separator}Y");
        } elseif ((int)$parts[0] > 12) { //it's dmY
            return array("d{$separator}m{$separator}Y");
        } elseif((int)$parts[1] > 12) { //it's mdY
            return array("m{$separator}d{$separator}Y");
        } else { //can be both
            return array("d{$separator}m{$separator}Y", "m{$separator}d{$separator}Y");
        }
    } else return array();
}

/******************************************
 * FUNCTION: GET REVIEWS WITH DATE ISSUES *
 ******************************************/
function getReviewsWithDateIssues($order_column = 0, $order_dir = "asc", $offset = 0, $page_size = -1) {

    $limit =  $page_size>0 ? " LIMIT {$offset}, {$page_size}" : "";

    if ($order_column == 1)
        $order_column = 'ri.subject';
    else $order_column = 'ri.id';

    $db = db_open();

    $stmt = $db->prepare("
        select SQL_CALC_FOUND_ROWS re.id as review_id, ri.id as risk_id, re.next_review, ri.subject
            from `mgmt_reviews` re
            left join `risks` ri on re.risk_id = ri.id
            where re.next_review not REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' 
            order by {$order_column} {$order_dir}, re.id {$limit};");
    $stmt->execute();

    $reviews = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT FOUND_ROWS();");
    $stmt->execute();
    $recordsTotal = $stmt->fetch()[0];

    db_close($db);

    return array($recordsTotal, $reviews);
}

/**********************************************
 * FUNCTION: GET ALL REVIEWS WITH DATE ISSUES *
 **********************************************/
function getAllReviewsWithDateIssues() {

    $db = db_open();

    $stmt = $db->prepare("select `id` review_id, `next_review` from `mgmt_reviews` where `next_review` not REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' order by `id`;");
    $stmt->execute();

    $array = $stmt->fetchAll();

    db_close($db);

    return $array;    
}

/*********************************************
 * FUNCTION: CHANGE NEXT_REVIEW TO DATE TYPE *
 *********************************************/
function changeNextReviewToDateType() {
    $db = db_open();

    $stmt = $db->prepare("ALTER TABLE `mgmt_reviews` CHANGE `next_review` `next_review` DATE NOT NULL DEFAULT '0000-00-00';");
    $stmt->execute();

    db_close($db);

    return getTypeOfColumn('mgmt_reviews', 'next_review') == 'date';
}

/*****************************************
 * FUNCTION: FIX NEXT REVIEW DATE FORMAT *
 *****************************************/
function fixNextReviewDateFormat($id, $format) {

    $db = db_open();

    $stmt = $db->prepare("select `next_review` from `mgmt_reviews` where `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    $next_review = $stmt->fetch();

    if ($next_review && strlen($next_review[0]) == 10) {

        $next_review = $next_review[0];

        $d = DateTime::createFromFormat($format, $next_review);
        $standard_date = $d ? $d->format('Y-m-d') : false;

        if ($standard_date) {
            //save the date
            $stmt = $db->prepare("update `mgmt_reviews` set `next_review`=:standard_date where `id`=:id;");
            $stmt->bindParam(":standard_date", $standard_date, PDO::PARAM_STR);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            db_close($db);
            return true;
        }
    }

    db_close($db);
    return false;
}


/************************************
 * FUNCTION: RESET NEXT REVIEW DATE *
 ************************************/
function resetNextReviewDate($id) {

    $db = db_open();

    $stmt = $db->prepare("update `mgmt_reviews` set `next_review`='0000-00-00' where `id`=:id;");
    $stmt->bindParam(":id", $id, PDO::PARAM_INT);
    $stmt->execute();

    db_close($db);
}

/******************************************
 * FUNCTION: CONVERT DATE FORMAT FROM PHP *
 ******************************************/
function convertDateFormatFromPHP($format) {
    $disp_format = str_replace('Y', 'YYYY', $format);
    $disp_format = str_replace('m', 'MM', $disp_format);
    $disp_format = str_replace('d', 'DD', $disp_format);

    return $disp_format;
}

/****************************************
 * FUNCTION: CONVERT DATE FORMAT TO PHP *
 ****************************************/
function convertDateFormatToPHP($disp_format) {
    $format = str_replace('YYYY', 'Y', $disp_format);
    $format = str_replace('MM', 'm', $format);
    $format = str_replace('DD', 'd', $format);

    return $format;
}

?>
