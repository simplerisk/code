<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

require_once(realpath(__DIR__ . '/../vendor/autoload.php'));
require_once(realpath(__DIR__ . '/escaper.php'));

// Include Escaper for HTML Output Encoding
$escaper = new simpleriskEscaper();

/*****************************
 * FUNCTION: INSTALLER LOG   *
 *****************************/
// Logging helper for the install path. Prefers SimpleRisk's write_debug_log
// so installer events land in the same log stream as the rest of the app.
// Falls back to error_log only when functions.php has not been loaded —
// which is the case during the pre-install bootstrap, because functions.php
// require_once's config.php at its top and config.php may not exist yet.
//
// $level is required (no default) — same rule as write_debug_log itself, so
// the wrapper doesn't quietly accept calls that omit a level.
function installer_log($message, $level)
{
    if (function_exists('write_debug_log')) {
        write_debug_log("Installer: {$message}", $level);
    } else {
        error_log("SimpleRisk installer [{$level}]: {$message}");
    }
}

/*************************************
 * FUNCTION: SIMPLERISK INSTALLATION *
 *************************************/
function simplerisk_installation()
{
    // Display the header
    display_install_header();

    // If nothing has been POSTed
    if (!$_POST)
    {
        // Load the install page
        echo "<h1 class=\"text-center welcome--msg\">SimpleRisk Installer</h1>\n";
        echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
        step_1_install_page();
        echo "</form>\n";
    }
    // If something has been POSTed
    else {
        // Get the POSTed values
        $_POST['db_host'] = isset($_POST['db_host']) ? $_POST['db_host'] : "localhost";
        $_POST['db_port'] = isset($_POST['db_port']) ? $_POST['db_port'] : "3306";
        $_POST['db_user'] = isset($_POST['db_user']) ? $_POST['db_user'] : "root";
        $_POST['db_pass'] = isset($_POST['db_pass']) ? $_POST['db_pass'] : "";
        $_POST['sr_host'] = isset($_POST['sr_host']) ? $_POST['sr_host'] : $_POST['db_host'];
        $_POST['sr_db'] = isset($_POST['sr_db']) ? $_POST['sr_db'] : "simplerisk";
        $_POST['sr_user'] = isset($_POST['sr_user']) ? $_POST['sr_user'] : "simplerisk";
        $_POST['default_language'] = isset($_POST['default_language']) ? $_POST['default_language'] : "en";
        $_POST['db_sessions'] = isset($_POST['db_sessions']) ? $_POST['db_sessions'] : "true";
        $_POST['full_name'] = isset($_POST['full_name']) ? $_POST['full_name'] : "";
        $_POST['email']  = isset($_POST['email']) ? $_POST['email'] : "";
        $_POST['username'] = isset($_POST['username']) ? $_POST['username'] : "";
        $_POST['mailing_list'] = isset($_POST['mailing_list']) ? "true" : "false";
        $_POST['db_ssl_cert_path'] = isset($_POST['db_ssl_cert_path']) ? $_POST['db_ssl_cert_path'] : "";

        // Remove any backticks from DB connection information
        $pattern = '/`/';
        $replacement = '';
        $_POST['db_host'] = preg_replace($pattern, $replacement, $_POST['db_host']);
        $_POST['db_port'] = preg_replace($pattern, $replacement, $_POST['db_port']);
        $_POST['db_user'] = preg_replace($pattern, $replacement, $_POST['db_user']);
        $_POST['db_pass'] = preg_replace($pattern, $replacement, $_POST['db_pass']);
        $_POST['sr_host'] = preg_replace($pattern, $replacement, $_POST['sr_host']);
        $_POST['sr_db'] = preg_replace($pattern, $replacement, $_POST['sr_db']);
        $_POST['sr_user'] = preg_replace($pattern, $replacement, $_POST['sr_user']);

        // If we are moving to the health check
        if (isset($_POST['step_2_health_check']))
        {
            // Load the health check page
            echo "<h1 class=\"text-center welcome--msg\">Health Check</h1>\n";
            echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
            step_2_health_check();
            echo "</form>\n";
        }
        // If we are moving to the database credential check
        else if (isset($_POST['step_3_database_credentials']))
        {
            // Load the database credentials page
            echo "<h1 class=\"text-center welcome--msg\">Database Credentials</h1>\n";
            echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
            step_3_database_credentials();
            echo "</form>\n";
        }
        // If we need to validate the database credentials
        else if (isset($_POST['verify_step_3_database_credentials']))
        {
            // Verify that step 3 database credentials was successful
            $verify_step_3_database_credentials = verify_step_3_database_credentials();

            // If we were able to verify step 3 database credentials
            if ($verify_step_3_database_credentials['success'])
            {
                // Move to step 4 to get the SimpleRisk information
                echo "<h1 class=\"text-center welcome--msg\">SimpleRisk Configuration</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_4_simplerisk_info();
                echo "</form>\n";
            }
            // If we could not verify step 3 database credentials
            else
            {
                // Go back to step 3 with the error messages
                echo "<h1 class=\"text-center welcome--msg\">SimpleRisk Configuration</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_3_database_credentials($verify_step_3_database_credentials['error_message']);
                echo "</form>\n";
            }
        }
        // If we are moving to create the default admin account
        else if (isset($_POST['step_5_default_admin_account']))
        {
            // Verify that step 4 simplerisk info was successful
            $verify_step_4_simplerisk_info = verify_step_4_simplerisk_info();

            // If we were able to verify step 4 simplerisk info
            if ($verify_step_4_simplerisk_info['success']) {
                echo "<h1 class=\"text-center welcome--msg\">Admin Account Creation</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_5_default_admin_account();
                echo "</form>\n";
            }
            // If we could not verify step 4 simplerisk info
            else
            {
                // Go back to step 4 with the error messages
                echo "<h1 class=\"text-center welcome--msg\">SimpleRisk Configuration</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_4_simplerisk_info($verify_step_4_simplerisk_info['error_message']);
                echo "</form>\n";
            }
        }
        // If we need to verify the default admin account
        else if (isset($_POST['verify_step_5_default_admin_account']))
        {
            // Verify that step 5 default admin account was successful
            $verify_step_5_default_admin_account = verify_step_5_default_admin_account();

            // If we were able to verify step 5 default admin account
            if ($verify_step_5_default_admin_account['success'])
            {
                echo "<h1 class=\"text-center welcome--msg\">SimpleRisk Installation</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_6_simplerisk_installation();
                echo "</form>\n";
            }
            // If we could not verify step 5 default admin account
            else
            {
                // Go back to step 5 with the error messages
                echo "<h1 class=\"text-center welcome--msg\">Admin Account Creation</h1>\n";
                echo "<form name=\"install\" method=\"post\" action=\"\" class=\"loginForm\">\n";
                step_5_default_admin_account($verify_step_5_default_admin_account['error_message']);
                echo "</form>\n";
            }
        }
    }

    // Display the trailer
    display_install_trailer();
}

/************************************
 * FUNCTION: DISPLAY INSTALL HEADER *
 ************************************/
function display_install_header()
{
    ?>

    <!DOCTYPE html>
    <html dir="ltr" lang="en" xml:lang="en">
    <head>
        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <!-- Favicon icon -->
        <link rel='shortcut icon' href='favicon.ico' />
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="css/style.min.css" />
        <!-- jQuery Javascript -->
        <script src="vendor/node_modules/jquery/dist/jquery.min.js" id="script_jquery"></script>
        <!-- Bootstrap tether Core JavaScript -->
        <script src="vendor/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js" defer></script>

        <script>
            $(function() {
                // Fading out the preloader once everything is done rendering
                $(".preloader").fadeOut();
            });
        </script>
    </head>
    <body>
    <div class="preloader">
        <div class="lds-ripple">
            <div class="lds-pos"></div>
            <div class="lds-pos"></div>
        </div>
    </div>
    <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="none" data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full" data-function="assessment">
    <header class="topbar" data-navbarbg="skin5">
        <nav class="navbar top-navbar navbar-expand-md navbar-dark">
            <div class="navbar-header">
                <!-- The wordmark, not display_brand_logo(): this page runs before
                     config.php exists, so there is no database to read a custom
                     logo setting from. The markup and its CSS need neither. -->
                <span class="navbar-brand sr-wordmark">
                    <img class="sr-brand-logo" src="images/simplerisk-logo-icon.png" alt="SimpleRisk" />
                    <span class="sr-brand-text"><span class="s">Simple</span><span class="r">Risk</span></span>
                </span>
            </div>
            <div class="navbar-collapse collapse show" id="navbarSupportedContent" data-navbarbg="skin5">
                <!-- Right side toggle and nav items -->
                <ul class="navbar-nav float-end ms-auto">
                    <li class="nav-item dropdown">
                        <a href="index.php" style='color: var(--sr-light)'>Install SimpleRisk</a>
                    </li>
                </ul>
            </div>
        </nav>
    </header>
    <!-- ============================================================== -->
    <!-- Page wrapper  -->
    <div class="page-wrapper">
    <div class="scroll-content">
    <div class="content-wrapper">
    <!-- container - It's the direct container of all the -->
    <div class="content container-fluid">

    <?php
}

/*************************************
 * FUNCTION: DISPLAY INSTALL TRAILER *
 *************************************/
function display_install_trailer()
{
    ?>

    </div>
    <!-- End of content -->
    <footer class="footer text-center">
        Copyright 2026 SimpleRisk, Inc. All rights reserved.
    </footer>
    </div>
    <!-- End of content-wrapper -->
    </div>
    <!-- End of scroll-content -->
    </div>
    <!-- End Page wrapper  -->
    </div>
    <!-- End Wrapper -->

    <script>
        $(function() {
            // Fading out the preloader once everything is done rendering
            $(".preloader").fadeOut();
        });
    </script>
    </body>
    </html>

    <?php
}

/*********************************
 * FUNCTION: STEP 0 INSTALL PAGE *
 *********************************/
function step_1_install_page()
{
    // Get the current SimpleRisk application version
    $app_version = installer_get_current_version();

    echo "<p>You are running the SimpleRisk {$app_version} release installer.  SimpleRisk is a comprehensive GRC solution that is:</p>\n";
    echo "<ul>\n";
    echo "<li><span style='color: #fc502c; font-weight: bold'>SIMPLE</span> - Intuitive workflows promotes organization-wide adoption.</li>\n";
    echo "<li><span style='color: #ff1a41; font-weight: bold'>EFFECTIVE</span> - From \"Zero to GRC\" in minutes.</li>\n";
    echo "<li><span style='color: #cc0f2f; font-weight: bold'>AFFORDABLE</span> - Comprehensive Governance, Risk Management and Compliance at a fraction of the cost.</li>\n";
    echo "</ul>\n";
    echo "<p>The next step will perform a health check of your system to ensure that it is ready for a SimpleRisk installation.  It may take up to a minute for the health check to complete.</p>\n";
    echo "<p>Click the \"CONTINUE\" button below to begin your SimpleRisk installation.</p><br />\n";
    echo "<input type=\"submit\" name=\"step_2_health_check\" value=\"CONTINUE\" />\n";
}

/*********************************
 * FUNCTION: STEP 2 HEALTH CHECK *
 *********************************/
function step_2_health_check()
{
    // Get the current and latest versions
    $current_app_version = installer_get_current_version();
    $latest_app_version = installer_get_latest_version();

    // Check that we are running the latest version of the SimpleRisk application
    $check_app_version = installer_check_app_version($current_app_version, $latest_app_version);

    // Check that SimpleRisk can connect to the services platforms
    $check_web_connectivity = installer_check_web_connectivity();

    // Check that this is PHP 7
    $check_php_version = installer_check_php_version();

    // Check the PHP memory_limit
    $check_php_memory_limit = installer_check_php_memory_limit();

    // Check the PHP max_input_vars
    $check_php_max_input_vars = installer_check_php_max_input_vars();

    // Check the necessary PHP extensions are installed
    $check_php_extensions = installer_check_php_extensions();

    // Check the simplerisk directory permissions
    $check_simplerisk_directory_permissions = installer_check_simplerisk_directory_permissions();

    echo "<b><u>SimpleRisk Version</u></b><br />\n";
    installer_display_health_check_results($check_app_version);
    echo "<br />\n";
    echo "<b><u>Connectivity</u></b><br />";
    installer_display_health_check_array_results($check_web_connectivity);
    echo "<br />\n";
    echo "<b><u>PHP</u></b><br />\n";
    installer_display_health_check_results($check_php_version);
    installer_display_health_check_results($check_php_memory_limit);
    installer_display_health_check_results($check_php_max_input_vars);
    installer_display_health_check_array_results($check_php_extensions);
    echo "<br />\n";
    echo "<b><u>File and Directory Permissions</u></b><br />";
    installer_display_health_check_array_results($check_simplerisk_directory_permissions);
    echo "<br />\n";
    echo "If everything looks alright with the health check above, click &quot;CONTINUE&quot; to proceed with the installation.<br /><br />\n";
    echo "<input type=\"submit\" name=\"step_3_database_credentials\" value=\"CONTINUE\" />\n";
}

/*****************************************
 * FUNCTION: STEP 3 DATABASE CREDENTIALS *
 *****************************************/
function step_3_database_credentials($error_message = null)
{
    global $escaper;

    // If an error message exists
    if (!empty($error_message)) {
        foreach ($error_message as $message)
        {
            installer_health_check_bad($message);
        }
        echo "<br />\n";
    }

    echo "Enter your database information to proceed with SimpleRisk install:<br /><br />\n";

    // Database connection information table
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">Database Connection Information</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>Database IP/Hostname:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"db_host\" value=\"" . (isset($_POST['db_host']) ? $escaper->escapeHtml($_POST['db_host']) : "localhost") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Database Port:</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"db_port\" value=\"" . (isset($_POST['db_port']) ? $escaper->escapeHtml($_POST['db_port']) : "3306") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Database Username:</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" maxlength=\"16\" name=\"db_user\" id=\"db_user\" value=\"" . (isset($_POST['db_user']) ? $escaper->escapeHtml($_POST['db_user']) : "root") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Database Password:</label></td>\n";
    echo "<td><input type=\"password\" size=\"30\" name=\"db_pass\" value=\"" . (isset($_POST['db_pass']) ? $escaper->escapeHtml($_POST['db_pass']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<br />\n";
    echo "<input type=\"submit\" name=\"verify_step_3_database_credentials\" value=\"CONTINUE\" />\n";
    echo "<script>\n";
    echo "function checkLength(value){\n";
    echo "  var maxLength = 16;\n";
    echo "  if (value.length >= maxLength) return false;\n";
    echo "  return true;\n";
    echo "}\n";
    echo "document.getElementById('db_user').onkeyup = function(){\n";
    echo "  if(!checkLength(this.value)) alert('MySQL usernames cannot be longer than 16 characters!');\n";
    echo "}\n";
    echo "</script>\n";
}

/************************************************
 * FUNCTION: VERIFY STEP 3 DATABASE CREDENTIALS *
 ************************************************/
function verify_step_3_database_credentials()
{
    // Database Connection Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);

    $error_message = [];
    $result = [];

    // Connect to the database
    try
    {
        $db = new PDO("mysql:charset=UTF8;dbname=mysql;host=".$db_host.";port=".$db_port,$db_user,$db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

        // If an error message exists
        if (!empty($error_message)) {
            // For each error message provided
            foreach ($error_message as $message) {
                installer_health_check_bad($message);
            }
            echo "<br />\n";
        }

        $error = false;

        // If STRICT mode is enabled
        if (check_mysql_strict()) {
            $error_message[] = "SimpleRisk will not work properly with STRICT_TRANS_TABLES enabled.";
            $error = true;
        }

        // If NO_ZERO_DATE is enabled
        if (check_mysql_no_zero_date()) {
            $error_message[] = "SimpleRisk will not work properly with NO_ZERO_DATE enabled.";
            $error = true;
        }

        // If ONLY_FULL_GROUP_BY is enabled
        if (check_mysql_only_full_group_by()) {
            $error_message[] = "SimpleRisk will not work properly with ONLY_FULL_GROUP_BY enabled.";
            $error = true;
        }

        // If there were errors
        if ($error)
        {
            $result['success'] = false;
            $result['error_message'] = $error_message;
        }
        else
        {
            $result['success'] = true;
            $result['error_message'] = null;
        }
    }
        // If there was an issue connecting to the database
    catch (PDOException $e)
    {
        //die("Database Connection Failed: " . $e->getMessage());
        // Display an error message and prompt for credentials again
        $error_message[] = "Unable to connect to the database with the credentials provided.";
        $result['success'] = false;
        $result['error_message'] = $error_message;
    }

    // Return the validation result
    return $result;
}

/************************************
 * FUNCTION: STEP 4 SIMPLERISK INFO *
 ************************************/
function step_4_simplerisk_info($error_message = null)
{
    // If an error message exists
    if (!empty($error_message)) {
        // For each error message provided
        foreach ($error_message as $message) {
            installer_health_check_bad($message);
        }
        echo "<br />\n";
    }

    global $escaper;

    echo "<br /><p>The information below will be used to install and configure your SimpleRisk database in MySQL.</p>\n";
    echo "<ul>\n";
    echo "<li><b>SimpleRisk IP/Host:</b>&nbsp;&nbsp;This is the IP address or hostname of the server that will be connecting to the SimpleRisk database instance.  It is used to restrict MySQL communication for the SimpleRisk database user to only that system.  Use a comma-separated list for multiple instances accessing the same database.</li>\n";
    echo "<li><b>SimpleRisk Database:</b>&nbsp;&nbsp;This is the name of the database that will be created for the SimpleRisk application to use.  This value is limited to 64 characters and a database with this name cannot already exist.</li>\n";
    echo "<li><b>SimpleRisk Username:</b>&nbsp;&nbsp;This is the name of the user that will be created for the SimpleRisk application to use.  This value is limited to 16 characters and a user with this name cannot already exist.</li>\n";
    echo "<li><b>Default Language:</b>&nbsp;&nbsp;This will configure the default language for SimpleRisk and install the appropriate database schema for the language, where available.</li>\n";
    echo "<li><b>Use Database for Sessions:</b>&nbsp;&nbsp;This controls whether SimpleRisk will use the database or file system for sessions.  Using the database is both faster and more secure.</li>\n";
    echo "<li><b>SSL Certificate Path:</b>&nbsp;&nbsp;This is an optional value to tell SimpleRisk to use a SSL certificate to connect to MySQL.";
    echo "</ul>\n";
    echo "<br />\n";

    // Hidden fields for the working database credentials
    echo "<input type=\"hidden\" name=\"db_host\" value=\"" . $escaper->escapeHtml($_POST['db_host']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_port\" value=\"" . $escaper->escapeHtml($_POST['db_port']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_user\" value=\"" . $escaper->escapeHtml($_POST['db_user']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_pass\" value=\"" . $escaper->escapeHtml($_POST['db_pass']) . "\" />\n";

    // SimpleRisk installation information table
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">SimpleRisk Installation Information</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>SimpleRisk IP/Host:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"sr_host\" value=\"" . (isset($_POST['sr_host']) ? $escaper->escapeHtml($_POST['sr_host']) : $escaper->escapeHtml($_POST['db_host'])) . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>SimpleRisk Database:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"sr_db\" value=\"" . (isset($_POST['sr_db']) ? $escaper->escapeHtml($_POST['sr_db']) : "simplerisk") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>SimpleRisk Username:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" maxlength=\"16\" name=\"sr_user\" id=\"sr_user\" value=\"" . (isset($_POST['sr_user']) ? $escaper->escapeHtml($_POST['sr_user']) : "simplerisk") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td colspan=\"2\"><label for>NOTE: A password will be randomly generated for the SimpleRisk user.</label></td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<br />\n";
    echo "<script>\n";
    echo "function checkLength(value){\n";
    echo "  var maxLength = 16;\n";
    echo "  if (value.length >= maxLength) return false;\n";
    echo "  return true;\n";
    echo "}\n";
    echo "document.getElementById('sr_user').onkeyup = function(){\n";
    echo "  if(!checkLength(this.value)) alert('MySQL usernames cannot be longer than 16 characters!');\n";
    echo "}\n";
    echo "</script>\n";

    // SimpleRisk configuration information table
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">SimpleRisk Configuration Information</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>Default Language:&nbsp;&nbsp;</label></td>\n";
    echo "<td>\n";
    echo "<select name=\"default_language\">\n";
    echo "<option value=\"af\"" . (isset($_POST) && $_POST['default_language'] == "af" ? " selected" : "") . ">Afrikaans</option>\n";
    echo "<option value=\"ar\"" . (isset($_POST) && $_POST['default_language'] == "ar" ? " selected" : "") . ">Arabic</option>\n";
    echo "<option value=\"bg\"" . (isset($_POST) && $_POST['default_language'] == "bg" ? " selected" : "") . ">Bulgarian</option>\n";
    echo "<option value=\"ca\"" . (isset($_POST) && $_POST['default_language'] == "ca" ? " selected" : "") . ">Catalan</option>\n";
    echo "<option value=\"zh-CN\"" . (isset($_POST) && $_POST['default_language'] == "zh-CN" ? " selected" : "") . ">Chinese Simplified</option>\n";
    echo "<option value=\"zh-TW\"" . (isset($_POST) && $_POST['default_language'] == "zh-TW" ? " selected" : "") . ">Chinese Traditional</option>\n";
    echo "<option value=\"cs\"" . (isset($_POST) && $_POST['default_language'] == "cs" ? " selected" : "") . ">Czech</option>\n";
    echo "<option value=\"da\"" . (isset($_POST) && $_POST['default_language'] == "da" ? " selected" : "") . ">Danish</option>\n";
    echo "<option value=\"nl\"" . (isset($_POST) && $_POST['default_language'] == "nl" ? " selected" : "") . ">Dutch</option>\n";
    echo "<option value=\"en\"" . (!isset($_POST) || $_POST['default_language'] == "en" ? " selected" : "") . ">English</option>\n";
    echo "<option value=\"fi\"" . (isset($_POST) && $_POST['default_language'] == "fi" ? " selected" : "") . ">Finnish</option>\n";
    echo "<option value=\"fr\"" . (isset($_POST) && $_POST['default_language'] == "fr" ? " selected" : "") . ">French</option>\n";
    echo "<option value=\"de\"" . (isset($_POST) && $_POST['default_language'] == "de" ? " selected" : "") . ">German</option>\n";
    echo "<option value=\"el\"" . (isset($_POST) && $_POST['default_language'] == "el" ? " selected" : "") . ">Greek</option>\n";
    echo "<option value=\"he\"" . (isset($_POST) && $_POST['default_language'] == "he" ? " selected" : "") . ">Hebrew</option>\n";
    echo "<option value=\"hi\"" . (isset($_POST) && $_POST['default_language'] == "hi" ? " selected" : "") . ">Hindi</option>\n";
    echo "<option value=\"hu\"" . (isset($_POST) && $_POST['default_language'] == "hu" ? " selected" : "") . ">Hungarian</option>\n";
    echo "<option value=\"it\"" . (isset($_POST) && $_POST['default_language'] == "it" ? " selected" : "") . ">Italian</option>\n";
    echo "<option value=\"ja\"" . (isset($_POST) && $_POST['default_language'] == "ja" ? " selected" : "") . ">Japanese</option>\n";
    echo "<option value=\"ko\"" . (isset($_POST) && $_POST['default_language'] == "ko" ? " selected" : "") . ">Korean</option>\n";
    echo "<option value=\"mn\"" . (isset($_POST) && $_POST['default_language'] == "mn" ? " selected" : "") . ">Mongolian</option>\n";
    echo "<option value=\"no\"" . (isset($_POST) && $_POST['default_language'] == "no" ? " selected" : "") . ">Norwegian</option>\n";
    echo "<option value=\"pl\"" . (isset($_POST) && $_POST['default_language'] == "pl" ? " selected" : "") . ">Polish</option>\n";
    echo "<option value=\"pt\"" . (isset($_POST) && $_POST['default_language'] == "pt" ? " selected" : "") . ">Portuguese</option>\n";
    echo "<option value=\"bp\"" . (isset($_POST) && $_POST['default_language'] == "bp" ? " selected" : "") . ">Portuguese, Brazilian</option>\n";
    echo "<option value=\"ro\"" . (isset($_POST) && $_POST['default_language'] == "ro" ? " selected" : "") . ">Romanian</option>\n";
    echo "<option value=\"ru\"" . (isset($_POST) && $_POST['default_language'] == "ru" ? " selected" : "") . ">Russian</option>\n";
    echo "<option value=\"sr\"" . (isset($_POST) && $_POST['default_language'] == "sr" ? " selected" : "") . ">Serbian (Cyrillic)</option>\n";
    echo "<option value=\"si\"" . (isset($_POST) && $_POST['default_language'] == "si" ? " selected" : "") . ">Sinhala</option>\n";
    echo "<option value=\"sk\"" . (isset($_POST) && $_POST['default_language'] == "sk" ? " selected" : "") . ">Slovak</option>\n";
    echo "<option value=\"es\"" . (isset($_POST) && $_POST['default_language'] == "es" ? " selected" : "") . ">Spanish</option>\n";
    echo "<option value=\"sv\"" . (isset($_POST) && $_POST['default_language'] == "sv" ? " selected" : "") . ">Swedish</option>\n";
    echo "<option value=\"tr\"" . (isset($_POST) && $_POST['default_language'] == "tr" ? " selected" : "") . ">Turkish</option>\n";
    echo "<option value=\"uk\"" . (isset($_POST) && $_POST['default_language'] == "uk" ? " selected" : "") . ">Ukrainian</option>\n";
    echo "<option value=\"vi\"" . (isset($_POST) && $_POST['default_language'] == "vi" ? " selected" : "") . ">Vietnamese</option>\n";
    echo "</select>\n";
    echo "</td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Use Database for Sessions:&nbsp;&nbsp;</label></td>\n";
    echo "<td>\n";
    echo "<select name=\"db_sessions\">\n";
    echo "<option value=\"true\"" . ($_POST['db_sessions'] == "true" ? " selected" : "") . ">true</option>\n";
    echo "<option value=\"false\"" . ($_POST['db_sessions'] == "false" ? " selected" : "") . ">false</option>\n";
    echo "</select><br />\n";
    echo "</td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<br />\n";

    // Optional SSL Certificate Path
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">(OPTIONAL) Database SSL Certificate</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>SSL Certificate Path:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"db_ssl_cert_path\" value=\"" . (isset($_POST['db_ssl_cert_path']) ? $escaper->escapeHtml($_POST['db_ssl_cert_path']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td colspan=\"2\"><label for>NOTE: Leave blank for no database SSL certificate.</label></td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";

    echo "<br /><input type=\"submit\" name=\"step_5_default_admin_account\" value=\"CONTINUE\" />\n";
}

/*******************************************
 * FUNCTION: VERIFY STEP 4 SIMPLERISK INFO *
 *******************************************/
function verify_step_4_simplerisk_info()
{
    // Database Connection Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);
    $sr_host = addslashes($_POST['sr_host']);
    $sr_db = addslashes($_POST['sr_db']);
    $sr_user = addslashes($_POST['sr_user']);
    $db_ssl_cert_path = $_POST['db_ssl_cert_path'];

    $error = false;
    $error_message = [];
    $result = [];

    // Check if the sr_host is a valid value
    $sr_host_array = explode(",", $sr_host);
    foreach ($sr_host_array as $sr_host)
    {
        // Remove any white space from the string
        $sr_host = str_replace(" ", "", $sr_host);

        // If the resulting sr_host value is not a valid domain, IP, wildcard, or netmask
        if (!is_valid_sr_host($sr_host))
        {
            // Plain text — installer_health_check_bad() escapes once at the rendering sink.
            $error_message[] = "The SimpleRisk IP/Host \"" . $sr_host . "\" is not valid.";
            $error = true;
        }
    }

    // If the SimpleRisk username is longer than 16 characters
    if (strlen($sr_user) > 16) {
        $error_message[] = "The SimpleRisk username is longer than 16 characters.";
        $error = true;
    }

    // Connect to the mysql database
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, "mysql");

    // Check if the database already exists
    $stmt = $db->prepare("SHOW DATABASES LIKE :sr_db");
    $stmt->bindParam(":sr_db", $sr_db, PDO::PARAM_STR);
    $stmt->execute();
    $array = $stmt->fetchAll();

    // If the database exists
    if (!empty($array))
    {
        $error_message[] = "A database with the name \"" . $sr_db . "\" already exists.";
        $error = true;
    }

    // Check if the username already exists in the user table
    $stmt = $db->prepare("SELECT * FROM user WHERE User=:sr_user");
    $stmt->bindParam(":sr_user", $sr_user, PDO::PARAM_STR);
    $stmt->execute();
    $array = $stmt->fetchAll();

    // If the username exists
    if (!empty($array))
    {
        $error_message[] = "An entry in the mysql user table with the name \"" . $sr_user . "\" already exists.";
        $error = true;
    }

    // Check if the username already exists in the db table
    $stmt = $db->prepare("SELECT * FROM db WHERE User=:sr_user");
    $stmt->bindParam(":sr_user", $sr_user, PDO::PARAM_STR);
    $stmt->execute();
    $array = $stmt->fetchAll();

    // If the array is not empty
    if (!empty($array))
    {
        $error_message[] = "An entry in the mysql db table with the name \"" . $sr_user . "\" already exists.";
        $error = true;
    }

    // If the db_ssl_cert_path is not empty
    if ($db_ssl_cert_path != "")
    {
        // If the db_ssl_cert_path does not exists
        if (!file_exists($db_ssl_cert_path))
        {
            $error_message[] = "No file exists at the specified SSL Certificate File path.";
            $error = true;
        }
    }

    // Close the database
    installer_db_close($db);

    // If there were errors
    if ($error)
    {
        $result['success'] = false;
        $result['error_message'] = $error_message;
    }
    else
    {
        $result['success'] = true;
        $result['error_message'] = null;
    }

    // Return the validation result
    return $result;
}

/******************************************
 * FUNCTION: STEP 5 DEFAULT ADMIN ACCOUNT *
 ******************************************/
function step_5_default_admin_account($error_message = null)
{
    global $escaper;

    // If an error message exists
    if (!empty($error_message)) {
        // For each error message provided
        foreach ($error_message as $message) {
            installer_health_check_bad($message);
        }
        echo "<br />\n";
    }

    // Hidden fields for the working values
    echo "<input type=\"hidden\" name=\"db_host\" value=\"" . $escaper->escapeHtml($_POST['db_host']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_port\" value=\"" . $escaper->escapeHtml($_POST['db_port']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_user\" value=\"" . $escaper->escapeHtml($_POST['db_user']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_pass\" value=\"" . $escaper->escapeHtml($_POST['db_pass']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"sr_host\" value=\"" . $escaper->escapeHtml($_POST['sr_host']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"sr_db\" value=\"" . $escaper->escapeHtml($_POST['sr_db']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"sr_user\" value=\"" . $escaper->escapeHtml($_POST['sr_user']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_ssl_cert_path\" value=\"" . $escaper->escapeHtml($_POST['db_ssl_cert_path']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"db_sessions\" value=\"" . $escaper->escapeHtml($_POST['db_sessions']) . "\" />\n";
    echo "<input type=\"hidden\" name=\"default_language\" value=\"" . $escaper->escapeHtml($_POST['default_language']) . "\" />\n";

    // Admin account information table
    echo "<table>\n";
    echo "<thead>\n";
    echo "<tr>\n";
    echo "<th align=\"left\" colspan=\"2\"><label class=\"login--label\">Admin Account Information</label></th>\n";
    echo "</tr>\n";
    echo "</thead>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td><label for>Username:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"username\" value=\"" . (isset($_POST['username']) ? $escaper->escapeHtml($_POST['username']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Full Name:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"full_name\" value=\"" . (isset($_POST['full_name']) ? $escaper->escapeHtml($_POST['full_name']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Email Address:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"text\" size=\"30\" name=\"email\" value=\"" . (isset($_POST['email']) ? $escaper->escapeHtml($_POST['email']) : "") . "\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Password:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"password\" size=\"30\" name=\"password\" value=\"\" /></td>\n";
    echo "</tr>\n";
    echo "<tr>\n";
    echo "<td><label for>Confirm Password:&nbsp;&nbsp;</label></td>\n";
    echo "<td><input type=\"password\" size=\"30\" name=\"confirm_password\" value=\"\" /></td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";
    echo "<table>\n";
    echo "<tbody>\n";
    echo "<tr>\n";
    echo "<td style='padding: 10px'><input type='checkbox' id='mailing_list' name='mailing_list'" . (isset($_POST['mailing_list']) ? " checked" : "") . " /></td>\n";
    echo "<td style='padding: 10px'><label for='mailing_list'>Add me to the SimpleRisk mailing list for educational content and notifications about new releases.</label></td><td>\n";
    echo "</tr>\n";
    echo "</tbody>\n";
    echo "</table>\n";

    echo "<br /><input type=\"submit\" name=\"verify_step_5_default_admin_account\" value=\"INSTALL\" />\n";
}

/*************************************************
 * FUNCTION: VERIFY STEP 5 DEFAULT ADMIN ACCOUNT *
 *************************************************/
function verify_step_5_default_admin_account()
{
    $error = false;
    $error_message = [];
    $result = [];

    // If the Username is empty
    if ($_POST['username'] == "")
    {
        $error_message[] = "The admin account must have a username.";
        $error = true;
    }

    // If the Full Name is empty
    if ($_POST['full_name'] == "")
    {
        $error_message[] = "Please specify a full name for the admin account.";
        $error = true;
    }

    // If the email address is not a proper email address format
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error_message[] = "An invalid email address was specified.";
        $error = true;
    }

    // If the Password is empty
    if ($_POST['password'] == "")
    {
        $error_message[] = "The admin account must have a password.";
        $error = true;
    }

    // If the password and confirm password do not match
    if ($_POST['password'] !== $_POST['confirm_password']) {
        $error_message[] = "The Password and Confirm Password values do not match.  Please try again.";
        $error = true;
    }

    // If there were errors
    if ($error)
    {
        $result['success'] = false;
        $result['error_message'] = $error_message;
    }
    else
    {
        $result['success'] = true;
        $result['error_message'] = null;
    }

    // Return the validation result
    return $result;
}

/********************************************
 * FUNCTION: STEP 6 SIMPLERISK INSTALLATION *
 ********************************************/
function step_6_simplerisk_installation()
{
    // Helper to sanitize input
    $sanitize = fn($value) => str_replace('`', '', addslashes(trim($value)));

    // Get POSTed information
    $db_host = $sanitize($_POST['db_host'] ?? '');
    $db_port = $sanitize($_POST['db_port'] ?? '');
    $db_user = $sanitize($_POST['db_user'] ?? '');
    $db_pass = $sanitize($_POST['db_pass'] ?? '');
    $sr_host = $sanitize($_POST['sr_host'] ?? '');
    $sr_db = $sanitize($_POST['sr_db'] ?? '');
    $sr_user = $sanitize($_POST['sr_user'] ?? '');
    $default_language = $_POST['default_language'] ?? 'en';
    $db_sessions = ($_POST['db_sessions'] ?? 'false') === 'false' ? 'false' : 'true';
    $db_ssl_cert_path = $_POST['db_ssl_cert_path'] ?? '';
    $username = $_POST['username'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $mailing_list = isset($_POST['mailing_list']) ? 'true' : 'false';

    // Generate password for SimpleRisk user
    $sr_pass = installer_generate_token(20);

    // Connect to MySQL
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, 'mysql');

    // Check super privileges
    $grantee = "'{$db_user}'@'{$db_host}'";
    $stmt = $db->prepare("SELECT * FROM information_schema.USER_PRIVILEGES WHERE GRANTEE=:grantee AND PRIVILEGE_TYPE='SUPER'");
    $stmt->bindParam(':grantee', $grantee, PDO::PARAM_STR);
    $stmt->execute();
    $privileges = $stmt->fetchAll();

    if (count($privileges) > 0) {
        $db->exec("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'STRICT_TRANS_TABLES',''))");
        $db->exec("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'NO_ZERO_DATE',''))");
        $db->exec("SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

    // Create database safely
    if (!validate_mysql_identifier($sr_db)) throw new Exception("Invalid database name.");
    $sr_db_q = quote_identifier($sr_db);
    $db->exec("CREATE DATABASE {$sr_db_q}");

    // Handle multiple hosts
    $sr_host_array = array_map('trim', explode(',', $sr_host));

    if (!validate_mysql_identifier($sr_user)) throw new Exception("Invalid database username.");
    $sr_user_safe = str_replace("'", '', $sr_user);

    foreach ($sr_host_array as $host) {
        if (is_valid_sr_host($host)) {
            $sr_pass_quoted = $db->quote($sr_pass);
            $db->exec("CREATE USER '{$sr_user_safe}'@'{$host}' IDENTIFIED BY {$sr_pass_quoted}");
            $db->exec("GRANT SELECT,INSERT,UPDATE,ALTER,DELETE,CREATE,DROP,INDEX,REFERENCES,LOCK TABLES ON {$sr_db_q}.* TO '{$sr_user_safe}'@'{$host}'");
        }
    }

    $db->exec("FLUSH PRIVILEGES");
    installer_db_close($db);

    // Determine schema file
    $app_version = installer_get_current_version();
    $file_map = ['en' => "simplerisk-en-$app_version.sql", 'es' => "simplerisk-es-$app_version.sql", 'bp' => "simplerisk-bp-$app_version.sql"];
    $file = $file_map[$default_language] ?? $file_map['en'];
    $branch = defined('DB_BRANCH') ? DB_BRANCH : 'master';
    $file_url = "https://raw.githubusercontent.com/simplerisk/database/{$branch}/{$file}";

    $web_file = @fopen($file_url, 'r');

    // If the current version's SQL file isn't published yet (e.g. pre-release branch),
    // fall back to the latest published release. The upgrade script will bring the DB
    // up to the current version after installation.
    if (!$web_file) {
        $latest_version = installer_get_latest_version();
        if ($latest_version && $latest_version !== $app_version) {
            $fallback_map = ['en' => "simplerisk-en-$latest_version.sql", 'es' => "simplerisk-es-$latest_version.sql", 'bp' => "simplerisk-bp-$latest_version.sql"];
            $fallback_file = $fallback_map[$default_language] ?? $fallback_map['en'];
            $file_url = "https://raw.githubusercontent.com/simplerisk/database/{$branch}/{$fallback_file}";
            $web_file = @fopen($file_url, 'r');
        }
    }

    if (!$web_file) { echo "ERROR: Unable to obtain file from {$file_url}"; exit;
    }

    $memory_file = fopen('php://memory', 'rw');
    stream_copy_to_stream($web_file, $memory_file);
    rewind($memory_file);
    fclose($web_file);

    load_file($db_host, $db_port, $db_user, $db_pass, $sr_db, $memory_file);
    fclose($memory_file);

    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, $sr_db);

    $stmt = $db->query("SELECT COUNT(*) FROM user");
    if ((int)$stmt->fetchColumn() === 0) $db->exec("ALTER TABLE user AUTO_INCREMENT = 1");

    installer_add_admin_user($username, $email, $full_name, $password);

    $simplerisk_base_url = get_simplerisk_base_url();
    $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('simplerisk_base_url', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute([$simplerisk_base_url]);

    if (($parse_url = parse_url($simplerisk_base_url)) && ($parse_url['scheme'] ?? '') === 'https') {
        $strCookie = 'SimpleRisk=' . session_id() . '; path=/';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $simplerisk_base_url);
        curl_setopt($curl, CURLOPT_COOKIE, $strCookie);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        $json_result = curl_exec($curl);
        $json_array = json_decode($json_result, true);

        $ssl_ok = ($json_result && ($json_array['status'] ?? 0) === 200) ? '1' : '0';
        $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('ssl_certificate_check_simplerisk', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
        $stmt->execute([$ssl_ok]);
    }

    // Verify TLS certificates on outbound calls to external services by default.
    // ssl_certificate_check_simplerisk (above) governs calls to this instance's
    // own URL; ssl_certificate_check_external governs calls to external services
    // (licensing.simplerisk.com, the version feed, Extra integrations). Seed it
    // here so a fresh install is secure by default — the upgrade migration that
    // adds it does not run on a fresh install. Admins can disable it on the
    // Security settings page.
    $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('ssl_certificate_check_external', '1') ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute();

    $stmt = $db->prepare("UPDATE settings SET value=? WHERE name='default_language'");
    $stmt->execute([$default_language]);

    $instance_id = installer_generate_token(50);
    $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('instance_id', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute([$instance_id]);

    $stmt = $db->prepare("DELETE FROM settings WHERE name='schedule_cron_ping'");
    $stmt->execute();
    $schedule = rand(0,59) . ' ' . rand(0,23) . ' * * *';
    $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('schedule_cron_ping', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $stmt->execute([$schedule]);

    installer_instance_registration($db, $instance_id, $full_name, $email, $mailing_list);

    if (create_config_file($db_host, $db_port, $sr_user, $sr_pass, $sr_db, $db_sessions, $db_ssl_cert_path)) {
        echo "Configuration file has been created successfully.<br><br>";
        echo "SimpleRisk should now be communicating with the database.<br><br>";
        echo "<input type=\"button\" value=\"GO TO SIMPLERISK\" onclick=\"window.location.reload(true)\" />";
    }
}

/****************************************************
 * FUNCTION: INSTALLER DISPLAY HEALTH CHECK RESULTS *
 ****************************************************/
function installer_display_health_check_results($health_check)
{
    // If the result was good
    if ($health_check['result'] === 1)
    {
        installer_health_check_good($health_check['text']);
    }
    else
    {
        installer_health_check_bad($health_check['text']);
    }
}

/**********************************************************
 * FUNCTION: INSTALLER DISPLAY HEALTH CHECK ARRAY RESULTS *
 **********************************************************/
function installer_display_health_check_array_results($health_check_array)
{
    foreach($health_check_array as $health_check)
    {
        installer_display_health_check_results($health_check);
    }
}

/*****************************************
 * FUNCTION: INSTALLER HEALTH CHECK GOOD *
 *****************************************/
// $text is plain text — this function is the HTML sink and escapes once.
// Callers must not pre-escape; doing so would render literal entities.
function installer_health_check_good($text)
{
    global $escaper;

    echo "<img src=\"images/check-mark-8-16.png\" />&nbsp&nbsp;" . $escaper->escapeHtml($text) . "<br />";
}

/****************************************
 * FUNCTION: INSTALLER HEALTH CHECK BAD *
 ****************************************/
// $text is plain text — this function is the HTML sink and escapes once.
// Callers must not pre-escape; doing so would render literal entities.
function installer_health_check_bad($text)
{
    global $escaper;

    echo "<img src=\"images/x-mark-5-16.png\" />&nbsp;&nbsp;" . $escaper->escapeHtml($text) . "<br />";
}

/********************************
 * FUNCTION: CHECK MYSQL STRICT *
 ********************************/
function check_mysql_strict()
{
    // Database Connection Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);

    // Remove any backticks from DB connection information
    $pattern = '/`/';
    $replacement = '';
    $db_host = preg_replace($pattern, $replacement, $db_host);
    $db_port = preg_replace($pattern, $replacement, $db_port);
    $db_user = preg_replace($pattern, $replacement, $db_user);
    $db_pass = preg_replace($pattern, $replacement, $db_pass);

    // Connect to the mysql database
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, "mysql");

    // Query for the current SQL mode
    $stmt = $db->prepare("SELECT @@sql_mode;");
    $stmt->execute();
    $array = $stmt->fetch();
    $sql_mode = $array['@@sql_mode'];

    // Close the mysql database
    installer_db_close($db);

    // If the row contains STRICT_TRANS_TABLES
    if (preg_match("/.*STRICT_TRANS_TABLES.*/", $sql_mode))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/**************************************
 * FUNCTION: CHECK MYSQL NO ZERO DATE *
 **************************************/
function check_mysql_no_zero_date()
{
    // Database Connection Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);

    // Remove any backticks from DB connection information
    $pattern = '/`/';
    $replacement = '';
    $db_host = preg_replace($pattern, $replacement, $db_host);
    $db_port = preg_replace($pattern, $replacement, $db_port);
    $db_user = preg_replace($pattern, $replacement, $db_user);
    $db_pass = preg_replace($pattern, $replacement, $db_pass);

    // Connect to the mysql database
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, "mysql");

    // Query for the current SQL mode
    $stmt = $db->prepare("SELECT @@sql_mode;");
    $stmt->execute();
    $array = $stmt->fetch();
    $sql_mode = $array['@@sql_mode'];

    // Close the mysql database
    installer_db_close($db);

    // If the row contains NO_ZERO_DATE
    if (preg_match("/.*NO_ZERO_DATE.*/", $sql_mode))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/********************************************
 * FUNCTION: CHECK MYSQL ONLY FULL GROUP BY *
 ********************************************/
function check_mysql_only_full_group_by()
{
    // Database Connection Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);

    // Remove any backticks from DB connection information
    $pattern = '/`/';
    $replacement = '';
    $db_host = preg_replace($pattern, $replacement, $db_host);
    $db_port = preg_replace($pattern, $replacement, $db_port);
    $db_user = preg_replace($pattern, $replacement, $db_user);
    $db_pass = preg_replace($pattern, $replacement, $db_pass);

    // Connect to the mysql database
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, "mysql");

    // Query for the current SQL mode
    $stmt = $db->prepare("SELECT @@sql_mode;");
    $stmt->execute();
    $array = $stmt->fetch();
    $sql_mode = $array['@@sql_mode'];

    // Close the mysql database
    installer_db_close($db);

    // If the row contains ONLY_FULL_GROUP_BY
    if (preg_match("/.*ONLY_FULL_GROUP_BY.*/", $sql_mode))
    {
        return true;
    }
    else
    {
        return false;
    }
}

/******************************
 * FUNCTION: DATABASE CONNECT *
 ******************************/
function installer_db_open($db_host, $db_port, $db_user, $db_pass, $db_name)
{
    // Connect to the database
    try
    {
        $db = new PDO("mysql:charset=UTF8;dbname=".$db_name.";host=".$db_host.";port=".$db_port,$db_user,$db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

        // PHP 8.5 moved PDO MySQL constants to the Pdo\Mysql namespace and
        // deprecated the PDO::MYSQL_ATTR_* aliases. See db_open() in
        // functions.php for the same shim.
        $pdo_mysql_init_command = defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? constant('Pdo\Mysql::ATTR_INIT_COMMAND') : PDO::MYSQL_ATTR_INIT_COMMAND;

        $db->setAttribute($pdo_mysql_init_command, "SET NAMES utf8");
        $db->setAttribute($pdo_mysql_init_command, "SET CHARACTER SET utf8");
        $db->setAttribute($pdo_mysql_init_command, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        return $db;
    }
    catch (PDOException $e)
    {
        global $escaper;
        die("Database Connection Failed: " . $escaper->escapeHtml($e->getMessage()));
    }

    return null;
}

/*********************************
 * FUNCTION: DATABASE DISCONNECT *
 *********************************/
function installer_db_close($db)
{
    // Close the DB connection
    $db = null;
}

/*************************************
 * FUNCTION: GET SIMPLERISK BASE URL *
 *************************************/
function get_simplerisk_base_url()
{
    // Check if we are using the HTTPS protocol
    $isHTTPS = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on");

    // Set the port
    $port = (isset($_SERVER['SERVER_PORT']) && ((!$isHTTPS && $_SERVER['SERVER_PORT'] != "80") || ($isHTTPS && $_SERVER['SERVER_PORT'] != "443")));
    $port = ($port) ? ":" . $_SERVER['SERVER_PORT'] : "";

    // Set the current URL
    $base_url = ($isHTTPS ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . $port;

    $dir_path = realpath(dirname(dirname(__FILE__)));
    $document_root = realpath($_SERVER["DOCUMENT_ROOT"]);
    $app_root = str_replace($document_root,"",$dir_path);
    $app_root = str_replace(DIRECTORY_SEPARATOR ,"/",$app_root);
    $base_url .= $app_root;

    // Return the base url value
    return $base_url;
}

/********************************
 * FUNCTION: CREATE CONFIG FILE *
 ********************************/
function create_config_file($dbhost, $dbport, $sr_user, $sr_pass, $sr_db, $db_sessions, $db_ssl_cert_path)
{
    $sample_file = __DIR__ . '/config.sample.php';
    $target_file = __DIR__ . '/config.php';
    $target_dir  = __DIR__;

    // Identify the OS user PHP is running as so permission errors point the
    // operator at the right account to fix in chown/chmod.
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $pwuid = posix_getpwuid(posix_geteuid());
        $runtime_user = is_array($pwuid) && isset($pwuid['name']) ? $pwuid['name'] : 'unknown';
    } else {
        $runtime_user = function_exists('get_current_user') ? (get_current_user() ?: 'unknown') : 'unknown';
    }

    if (!file_exists($sample_file)) {
        installer_report_config_error("Installer cannot create config.php: missing template at {$sample_file}.");
        return false;
    }

    $template = file_get_contents($sample_file);
    if ($template === false) {
        installer_report_config_error("Installer could not read the config template at {$sample_file}.");
        return false;
    }

    // Resolve the SSL certificate path: blank if not supplied, blank if the
    // supplied path does not exist on disk.
    $resolved_ssl_path = ($db_ssl_cert_path !== '' && file_exists($db_ssl_cert_path)) ? $db_ssl_cert_path : '';

    // Render up front so any permission-failure branch below can fall back
    // to showing the operator the exact contents to drop into config.php
    // by hand. This is the "manual recovery" path: on restrictive hosts
    // where the web user cannot write to includes/, the installer would
    // otherwise leave the operator stuck — they would not know what
    // create_config_file() was going to produce, and could not finish the
    // install without reading source. Showing the rendered template
    // restores the recovery story.
    $rendered = render_config_template($template, [
        '__DB_HOSTNAME__'               => $dbhost,
        '__DB_PORT__'                   => $dbport,
        '__DB_USERNAME__'               => $sr_user,
        '__DB_PASSWORD__'               => $sr_pass,
        '__DB_DATABASE__'               => $sr_db,
        '__USE_DATABASE_FOR_SESSIONS__' => $db_sessions,
    ]);

    // SSL cert path: the sample config ships with the DB_SSL_CERTIFICATE_PATH
    // define commented out as an opt-in marker. When the operator supplied
    // a real path at install time, replace that commented line with an
    // active define. When no path was supplied, leave the line commented
    // so SimpleRisk does not attempt SSL at all (preferable to silently
    // running with a stale or stub path).
    if ($resolved_ssl_path !== '') {
        $rendered = preg_replace(
            "~^[ \t]*//[ \t]*define\(\s*'DB_SSL_CERTIFICATE_PATH'.*$~m",
            "define('DB_SSL_CERTIFICATE_PATH', '" . addcslashes($resolved_ssl_path, "'\\") . "');",
            $rendered,
            1
        );
    }

    if (!is_writable($target_dir)) {
        installer_report_config_error("Installer cannot write config.php to {$target_dir}. The PHP process is running as '{$runtime_user}'. Make this directory writable by that user and reload the installer.");
        installer_show_manual_config_recovery($target_file, $rendered);
        return false;
    }

    // Atomic write: stage to a sibling .tmp file then rename into place.
    // file_put_contents alone is not atomic — a parallel request landing
    // mid-write could otherwise require_once a half-written file and hit
    // a parse error. rename() is atomic on POSIX when source and
    // destination share a filesystem (they do here, both in $target_dir).
    $tmp_target_file = $target_file . '.tmp';
    if (file_put_contents($tmp_target_file, $rendered) === false) {
        installer_report_config_error("Installer failed to write the staging config file at {$tmp_target_file}. Process is running as '{$runtime_user}'. Check filesystem permissions on {$target_dir}.");
        installer_show_manual_config_recovery($target_file, $rendered);
        return false;
    }

    // Tighten permissions before publishing the file: config.php holds the
    // database password, so default-umask 0644 (world-readable) is too loose
    // on shared hosts. 0640 keeps it readable by the webserver group and
    // hidden from other local accounts. Silenced because chmod can fail on
    // filesystems without POSIX permissions (e.g. some Windows/NFS mounts);
    // the file is already written, so we proceed and let the rename land.
    @chmod($tmp_target_file, 0640);

    if (!rename($tmp_target_file, $target_file)) {
        @unlink($tmp_target_file);
        installer_report_config_error("Installer wrote {$tmp_target_file} but could not rename it to {$target_file}. Process is running as '{$runtime_user}'. Check filesystem permissions on {$target_dir}.");
        installer_show_manual_config_recovery($target_file, $rendered);
        return false;
    }

    installer_log("Wrote config.php to {$target_file}.", 'notice');
    return true;
}

/**********************************************
 * FUNCTION: INSTALLER SHOW MANUAL CONFIG     *
 *           RECOVERY                         *
 **********************************************/
/**
 * Surface the rendered config.php contents to the operator after a
 * permission-failure branch so they can create the file by hand and
 * resume installation. Restores the manual-recovery story the old
 * pre-template installer offered via nl2br(htmlentities(...)).
 *
 * Note: the rendered template contains the operator-supplied DB
 * password. That value never leaves the operator's own browser
 * session — this is the same surface the installer form they just
 * submitted lives in. We are not weakening the security boundary,
 * only making the recovery actionable.
 */
function installer_show_manual_config_recovery(string $target_file, string $rendered_config): void
{
    global $escaper;

    echo "<div class=\"alert alert-info\">\n";
    echo "<p><b>Manual recovery:</b> if the permission issue above cannot be resolved, you can finish installation by creating <code>" . $escaper->escapeHtml($target_file) . "</code> yourself. Copy the contents below into that file with your preferred editor, save it, and reload this page — SimpleRisk will detect the configuration and continue.</p>\n";
    echo "<pre style=\"max-height: 400px; overflow: auto; white-space: pre-wrap;\">" . $escaper->escapeHtml($rendered_config) . "</pre>\n";
    echo "</div>\n";
}

/******************************************
 * FUNCTION: RENDER CONFIG TEMPLATE       *
 ******************************************/
/**
 * Substitute __PLACEHOLDER__ tokens in $template with values from
 * $values, escaping each value so it is safe to land inside a
 * single-quoted PHP string literal.
 *
 * Pure: takes a template string and a placeholder=>value map, returns
 * the rendered string. No file I/O, no escaper state, no globals.
 *
 * Each value is run through addcslashes($value, "'\\") before
 * substitution — this neutralises the only two characters that can
 * break out of a single-quoted PHP literal (`'` and `\`). All other
 * characters (including PHP open tags `<?` / `<?php`, newlines, null
 * bytes, unicode) pass through unchanged because they are inert
 * inside a single-quoted literal. strtr() then does the substitution
 * in a single non-overlapping pass, so a raw value that happens to
 * contain another placeholder substring is not re-substituted.
 *
 * The escape step is the security-critical contract: a password
 * containing `'` rendered without escaping becomes
 * `define('DB_PASSWORD', 'pa'ss')` — a PHP parse error that bricks
 * every subsequent request. The accompanying unit tests pin this
 * behaviour across single quotes, backslashes, both combined, PHP
 * open tags, empty strings, and unicode.
 *
 * @param string                $template  Raw template (typically
 *                                         file_get_contents on
 *                                         config.sample.php).
 * @param array<string, string> $values    Map of placeholder => raw
 *                                         (un-escaped) value.
 */
function render_config_template(string $template, array $values): string
{
    $escaped = [];
    foreach ($values as $placeholder => $value) {
        $escaped[$placeholder] = addcslashes($value, "'\\");
    }
    return strtr($template, $escaped);
}

/**********************************************
 * FUNCTION: INSTALLER REPORT CONFIG ERROR    *
 **********************************************/
function installer_report_config_error($message)
{
    global $escaper;

    installer_log($message, 'error');
    echo "<div class=\"alert alert-danger\"><b>" . $escaper->escapeHtml($message) . "</b></div>\n";
}

/***********************
 * FUNCTION: LOAD FILE *
 ***********************/
function load_file($db_host, $db_port, $db_user, $db_pass, $sr_db, $memory_file)
{
    // Connect to the simplerisk database
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, $sr_db);

    // Disable foreign-key checks for the duration of the load. mysqldump emits
    // CREATE TABLE statements in alphabetical order, so a child table can be
    // created before the parent it references (e.g. `notification_recipients`
    // has a foreign key to `notifications`, but sorts first). The dump guards
    // against this with a `/*!40014 ... FOREIGN_KEY_CHECKS=0 */` directive, but
    // the comment-stripping below removes that directive along with every other
    // block comment — so we set it explicitly here. Without this, the
    // out-of-order child CREATE fails with MySQL error 1824 ("Failed to open
    // the referenced table"), the schema load aborts partway, and the installer
    // never reaches its completion screen.
    $db->exec("SET FOREIGN_KEY_CHECKS=0");

    // Get the data from the memory file
    $content = stream_get_contents($memory_file);

    // Remove comments from the content
    $content = preg_replace("/--(.*)\n/", "", $content);
    $content = preg_replace("/\/\*(.*?)\*\//", "", $content);

    // Get each mysql command
    $lines = explode(";\n", $content);
    $lines = array_filter($lines);

    foreach ($lines as $line)
    {
        // If the line is not empty
        if (preg_match("/[a-zA-Z]+/", $line))
        {
            // Perform the query
            $stmt = $db->prepare($line);
            try
            {
                $stmt->execute();
            }
            catch (PDOException $e)
            {
                echo $line;
                echo 'Schema load failed: ' . $e->getMessage();
                installer_db_close($db);
                return false;
            }
        }
    }

    // Re-enable foreign-key checks now that every table exists.
    $db->exec("SET FOREIGN_KEY_CHECKS=1");

    // Close the simplerisk database
    installer_db_close($db);

    echo "Database Schema Loaded Successfully!<br /><br />\n";

    return true;
}

/*********************************************
 * FUNCTION: INSTALLER INSTANCE REGISTRATION *
 *********************************************/
function installer_instance_registration($db, $instance_id, $full_name, $email, $mailing_list)
{
    // Resolve the licensing service URL.
    // licensing.php cannot be required here because its include chain pulls
    // in extras.php, which requires functions.php, which requires config.php
    // — and config.php does not yet exist during installation.
    // Mirror the licensing_url() logic inline instead.
    $base_url = defined('LICENSING_URL') ? LICENSING_URL : 'https://licensing.simplerisk.com';
    $url = rtrim($base_url, '/') . '/register';

    // Split full name into fname/lname like the runtime register flow.
    // Conservative split: the last whitespace-separated token is the
    // lname; everything before is the fname. Single-word names become
    // fname-only with an empty lname.
    $trimmed_name = trim((string)$full_name);
    $parts = preg_split('/\s+/', $trimmed_name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $fname = $parts ? implode(' ', array_slice($parts, 0, -1)) : '';
    $lname = $parts ? end($parts) : '';
    if (!$fname && $lname) {
        // Single-word name — treat the whole thing as fname.
        $fname = $lname;
        $lname = '';
    }

    // Build the JSON payload
    $payload = json_encode([
        'instance_id'  => $instance_id,
        'fname'        => $fname,
        'lname'        => $lname,
        'email'        => $email,
        'mailing_list' => ($mailing_list === 'true'),
    ]);

    // POST JSON to the licensing service
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // SSL verification — peer + host. Required for MITM resistance during
    // install, the highest-risk moment for credential interception (host has
    // no prior trust anchor). Bundle the project's pinned CA roots via
    // composer/ca-bundle, which vendor/autoload.php has already loaded by
    // the time this code runs.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    $ca = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
    if ($ca) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response_body = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // Persist the keys returned by the licensing service
    if ($http_code === 200 && $response_body !== false) {
        $body = json_decode($response_body, true);
        if (is_array($body) && isset($body['services_api_key'])) {
            $stmt = $db->prepare("INSERT INTO settings (name,value) VALUES ('services_api_key', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
            $stmt->execute([$body['services_api_key']]);
        } else {
            // Record the gap so a silent registration failure is diagnosable from the
            // install transcript (config.php may be absent here, so write_debug_log is
            // unavailable — installer_log is the sanctioned pre-install logger).
            installer_log("Instance registration returned HTTP 200 but no services_api_key; instance left unregistered.", 'warning');
        }
    } else {
        installer_log("Instance registration with the licensing service failed (HTTP {$http_code}); instance left unregistered.", 'warning');
    }
    // 409 collision-retry is handled by the runtime register page
    // (admin/register.php), not the installer.  A freshly-generated
    // instance_id makes installer collisions vanishingly improbable.
}

/*******************************************
 * FUNCTION: INSTALLER GET CURRENT VERSION *
 *******************************************/
function installer_get_current_version()
{
    require_once(realpath(__DIR__ . '/version.php'));

    return APP_VERSION;
}

/******************************************
 * FUNCTION: INSTALLER GET LATEST VERSION *
 ******************************************/
function installer_get_latest_version()
{
    // Url for SimpleRisk current versions
    if (defined('UPDATES_URL'))
    {
        $url = UPDATES_URL . '/releases.xml';
    }
    else $url = 'https://updates.simplerisk.com/releases.xml';

    // Set the default socket timeout to 5 seconds
    ini_set('default_socket_timeout', 5);

    // Get the file headers for the URL
    $file_headers = @get_headers($url, 1);

    // If we were unable to connect to the URL
    if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
    {
        installer_log("Unable to connect to {$url}", 'warning');
    }
    // We were able to connect to the URL
    else
    {
        // Load the versions file
        if (defined('UPDATES_URL'))
        {
            $version_page = file_get_contents(UPDATES_URL . '/releases.xml');
        }
        else $version_page = file_get_contents('https://updates.simplerisk.com/releases.xml');

        // Convert it to be an array
        $releases_array = json_decode(json_encode(new SimpleXMLElement($version_page)), true);
        $latest_release = reset($releases_array);
        $latest_versions = [];
        $latest_versions['appversion'] = $latest_release[0]['@attributes']['version'];

        // Adding aliases, as the values not always requested with the same name the XML serves it
        $latest_version = $latest_versions['appversion'];

        // Return the latest version
        return $latest_version;
    }
}

/*****************************************
 * FUNCTION: INSTALLER CHECK APP VERSION *
 *****************************************/
function installer_check_app_version($current_app_version, $latest_app_version)
{
    // If the current and latest versions are the same
    if ($current_app_version === $latest_app_version)
    {
        return array("result" => 1, "text" => "Running the current version (" . $current_app_version . ") of the SimpleRisk application.");
    }
    else
    {
        return array("result" => 0, "text" => "Running an outdated version (" . $current_app_version . ") of the SimpleRisk application.");
    }
}

/**********************************************
 * FUNCTION: INSTALLER CHECK WEB CONNECTIVITY *
 **********************************************/
function installer_check_web_connectivity()
{
    // URLs to check
    $urls = array("https://licensing.simplerisk.com/healthz", "https://services.nvd.nist.gov", "https://github.com", "https://raw.githubusercontent.com", "https://simplerisk-downloads.s3.amazonaws.com");

    // Create an empty array
    $array = array();

    // Check the URLs
    foreach ($urls as $url)
    {
        // Get the headers for the URL
        $file_headers = @get_headers($url, 1);

        if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found')
        {
            installer_log("Unable to connect to {$url}", 'warning');
            $array[] = array("result" => 0, "text" => "SimpleRisk was unable to connect to " . $url . ".");
        }
        else
        {
            $array[] = array("result" => 1, "text" => "SimpleRisk connected to " . $url . ".");
        }
    }

    return $array;
}

/*****************************************
 * FUNCTION: INSTALLER CHECK PHP VERSION *
 *****************************************/
function installer_check_php_version()
{
    // Get the version of PHP
    if (!defined('PHP_VERSION_ID'))
    {
        $version = explode('.', PHP_VERSION);

        define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
    }

    // If PHP is at least 7
    if (PHP_VERSION_ID >= 70000)
    {
        return array("result" => 1, "text" => "SimpleRisk is running under PHP version " . phpversion() . ".");
    }
    // If this is PHP 5.x
    else if (PHP_VERSION_ID >= 50000 && PHP_VERSION_ID < 60000)
    {
        return array("result" => 0, "text" => "SimpleRisk will no longer run properly under PHP version " . phpversion() . ".  Please upgrade to PHP 7.");
    }
    else
    {
        return array("result" => 0, "text" => "SimpleRisk requires PHP 7 to run properly.");
    }
}

/**********************************************
 * FUNCTION: INSTALLER CHECK PHP MEMORY LIMIT *
 **********************************************/
function installer_check_php_memory_limit()
{
    // Get the currently set memory limit
    $memory_limit = ini_get('memory_limit');

    // If the memory limit is not set
    if ($memory_limit === false)
    {
        return array("result" => 0, "text" => "No memory_limit value is set in the php.ini file and PHP is likely using the default value which is less than the current size of the SimpleRisk application.  SimpleRisk will function normally, however, this creates an issue with the one-click upgrade process.  We recommend setting the memory_limit value to 256M or higher.");
    }
    // If the memory limit is set to unlimited
    else if ($memory_limit == -1)
    {
        return array("result" => 1, "text" => "The memory_limit value in the php.ini file is set to -1.  This provides unlimited memory to PHP, which should be acceptable for the SimpleRisk application.");
    }
    // Otherwise
    else
    {
        $memory_limit_bytes = 0;

        // If the memory limit is a number followed by characters
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches))
        {
            // If the memory limit is in megabytes
            if ($matches[2] == 'M')
            {
                // Get the memory limit in bytes
                $memory_limit_bytes = $matches[1] * 1024 * 1024;
            }
            // If the memory limit is in kilobytes
            else if ($matches[2] == 'K')
            {
                // Get the memory limit in bytes
                $memory_limit_bytes = $matches[1] * 1024;
            }
            // If the memory limit is in Gigabytes
            else if ($matches[2] == 'G')
            {
                // Get the memory limit in bytes
                $memory_limit_bytes = $matches[1] * 1024 * 1024 * 1024;
            }
        }

        // Set the current SimpleRisk size in bytes
        $simplerisk_size_bytes = 180 * 1024 * 1024;

        // If the memory limit is less than the SimpleRisk size
        if ($memory_limit_bytes < $simplerisk_size_bytes)
        {
            return array("result" => 0, "text" => "The memory_limit value in the php.ini file is set to " . $memory_limit . ", which is less than the current size of the SimpleRisk application.  SimpleRisk will function normally, however, this creates an issue with the one-click upgrade process.  We recommend setting the memory_limit value to 256M or higher.");
        }
        // The memory limit is higher than the SimpleRisk size
        else
        {
            return array("result" => 1, "text" => "The memory_limit value in the php.ini file is set to " . $memory_limit . ".");
        }
    }
}

/************************************************
 * FUNCTION: INSTALLER CHECK PHP MAX INPUT VARS *
 ************************************************/
function installer_check_php_max_input_vars()
{
    // Get the currently set max_iput_vars
    $max_input_vars = ini_get('max_input_vars');

    // If the max_input_vars is not set
    if ($max_input_vars === false)
    {
        return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is not explicitly set.  The default value of 1000 is too low and the SimpleRisk Dynamic Risk Report will not function properly with this configuration.  We recommend setting the max_input_vars to 3000.");
    }
    // If the max_input_vars is set
    else
    {
        // If the max_input_vars is a number followed by characters
        if (preg_match('/^(\d+)$/', $max_input_vars, $matches))
        {
            // If the max_input_vars is 1000
            if ($max_input_vars == 1000)
            {
                return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is set to the default value of 1000.  The SimpleRisk Dynamic Risk Report will not function properly with this configuration.  We recommend setting the max_input_vars to 3000.");
            }
            // If the max_input_vars is less than 3000
            else if ($max_input_vars < 3000)
            {
                return array("result" => 0, "text" => "The max_input_vars value in the php.ini file is set to {$max_input_vars}, which could cause issues with the SimpleRisk Dynamic Risk Report.  We recommend setting the max_input_vars to 3000.");
            }
            // If the max_input_vars is 3000 or higher
            else if ($max_input_vars >= 3000)
            {
                return array("result" => 1, "text" => "The max_input_vars value in the php.ini file is set to {$max_input_vars}.");
            }
        }
    }
}

/********************************************
 * FUNCTION: INSTALLER CHECK PHP EXTENSIONS *
 ********************************************/
function installer_check_php_extensions()
{
    // List of extensions to check for
    $extensions = array("pdo", "pdo_mysql", "json", "phar", "zlib", "mbstring", "ldap", "dom", "curl", "posix", "zip", "gd");

    // Create an empty array
    $array = array();

    // For each extension
    foreach ($extensions as $extension)
    {
        if (extension_loaded($extension))
        {
            $array[] = array("result" => 1, "text" => "The PHP \"" . $extension . "\" extension is loaded.");
        }
        else
        {
            $array[] = array("result" => 0, "text" => "The PHP \"" . $extension . "\" extension is not loaded.");
        }
    }

    return $array;
}

/**************************************************************
 * FUNCTION: INSTALLER CHECK SIMPLERISK DIRECTORY PERMISSIONS *
 **************************************************************/
function installer_check_simplerisk_directory_permissions()
{
    // Create an empty array
    $array = array();

    $simplerisk_dir = realpath(__DIR__ . '/..');

    // If the simplerisk directory is writeable
    if (is_writeable($simplerisk_dir))
    {
        $array[] = array("result" => 1, "text" => "The SimpleRisk directory (" . $simplerisk_dir . ") is writeable by the web user.");
    }
    else
    {
        $array[] = array("result" => 0, "text" => "The SimpleRisk directory (" . $simplerisk_dir . ") is not writeable by the web user.");
    }

    $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($simplerisk_dir), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($objects as $name => $object)
    {
        // Do not check the directory above the SimpleRisk directory
        if ($name != $simplerisk_dir . "/..")
        {
            // If the directory is writeable
            if (!is_writeable($name))
            {
                $array[] = array("result" => 0, "text" => $name . " is not writeable by the web user.");
            }
        }
    }

    return $array;
}

/**************************************
 * FUNCTION: INSTALLER GENERATE TOKEN *
 **************************************/
function installer_generate_token($size)
{
    $token = "";
    $values = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
    $values_count = count($values);

    for ($i = 0; $i < $size; $i++)
    {
        // If the random int function exists (PHP 7)
        if (function_exists('random_int'))
        {
            // Generate the token using the random_int function
            $token .= $values[random_int(0, $values_count-1)];
        }
        else $token .= $values[array_rand($values)];
    }

    return $token;
}

/********************************
 * FUNCTION: INSTALLER ADD USER *
 ********************************/
function installer_add_admin_user($user, $email, $name, $password)
{
    // Set the default values for an admin user
    $custom_display_settings = json_encode(array(
        'id',
        'subject',
        'calculated_risk',
        'submission_date',
        'mitigation_planned',
        'management_review'
    ));

    $custom_plan_mitigation_display_settings = '{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["submission_date","1"],["closure_date","0"],["reference_id","0"],["regulation","0"],["control_number","0"],["location","0"],["source","0"],["category","0"],["team","0"],["additional_stakeholders","0"],["technology","0"],["owner","0"],["manager","0"],["submitted_by","0"],["risk_tags","0"],["scoring_method","0"],["residual_risk","0"],["project","0"],["days_open","0"],["affected_assets","0"],["risk_assessment","0"],["additional_notes","0"],["risk_mapping","0"],["threat_mapping","0"]],"mitigation_colums":[["mitigation_planned","1"],["planning_strategy","0"],["planning_date","0"],["mitigation_effort","0"],["mitigation_cost","0"],["mitigation_owner","0"],["mitigation_team","0"],["mitigation_accepted","0"],["mitigation_date","0"],["mitigation_controls","0"],["current_solution","0"],["security_recommendations","0"],["security_requirements","0"]],"review_colums":[["management_review","1"],["review_date","0"],["next_review_date","0"],["next_step","0"],["comments","0"]]}';

    $custom_perform_reviews_display_settings = '{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["submission_date","1"],["closure_date","0"],["reference_id","0"],["regulation","0"],["control_number","0"],["location","0"],["source","0"],["category","0"],["team","0"],["additional_stakeholders","0"],["technology","0"],["owner","0"],["manager","0"],["submitted_by","0"],["risk_tags","0"],["scoring_method","0"],["residual_risk","0"],["project","0"],["days_open","0"],["affected_assets","0"],["risk_assessment","0"],["additional_notes","0"],["risk_mapping","0"],["threat_mapping","0"]],"mitigation_colums":[["mitigation_planned","1"],["planning_strategy","0"],["planning_date","0"],["mitigation_effort","0"],["mitigation_cost","0"],["mitigation_owner","0"],["mitigation_team","0"],["mitigation_accepted","0"],["mitigation_date","0"],["mitigation_controls","0"],["current_solution","0"],["security_recommendations","0"],["security_requirements","0"]],"review_colums":[["management_review","1"],["review_date","0"],["next_review_date","0"],["next_step","0"],["comments","0"]]}';

    $custom_reviewregularly_display_settings = '{"risk_colums":[["id","1"],["risk_status","1"],["subject","1"],["calculated_risk","1"],["days_open","1"],["closure_date","0"],["reference_id","0"],["regulation","0"],["control_number","0"],["location","0"],["source","0"],["category","0"],["team","0"],["additional_stakeholders","0"],["technology","0"],["owner","0"],["manager","0"],["submitted_by","0"],["risk_tags","0"],["scoring_method","0"],["residual_risk","0"],["submission_date","0"],["project","0"],["affected_assets","0"],["risk_assessment","0"],["additional_notes","0"],["risk_mapping","0"],["threat_mapping","0"]],"mitigation_colums":[["mitigation_planned","0"],["planning_strategy","0"],["planning_date","0"],["mitigation_effort","0"],["mitigation_cost","0"],["mitigation_owner","0"],["mitigation_team","0"],["mitigation_accepted","0"],["mitigation_date","0"],["mitigation_controls","0"],["current_solution","0"],["security_recommendations","0"],["security_requirements","0"]],"review_colums":[["management_review","0"],["review_date","0"],["next_step","0"],["next_review_date","1"],["comments","0"]]}';

    $type = "simplerisk";
    $role_id = 1;
    $admin = 1;
    $multi_factor = 0;
    $change_password = 0;
    $manager = 0;

    // Create a unique salt
    $salt = "";
    $values = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
    for ($i = 0; $i < 20; $i++)
    {
        $salt .= $values[array_rand($values)];
    }

    // Hash the salt
    $salt_hash = '$2a$15$' . md5($salt);

    // Generate the password hash for admin user
    set_time_limit(120);
    $hash = crypt($password, $salt_hash);

    // Get the POSTed Information
    $db_host = addslashes($_POST['db_host']);
    $db_port = addslashes($_POST['db_port']);
    $db_user = addslashes($_POST['db_user']);
    $db_pass = addslashes($_POST['db_pass']);
    $sr_db = addslashes($_POST['sr_db']);

    // Remove any backticks from DB connection information
    $pattern = '/`/';
    $replacement = '';
    $db_host = preg_replace($pattern, $replacement, $db_host);
    $db_port = preg_replace($pattern, $replacement, $db_port);
    $db_user = preg_replace($pattern, $replacement, $db_user);
    $db_pass = preg_replace($pattern, $replacement, $db_pass);
    $sr_db = preg_replace($pattern, $replacement, $sr_db);

    // Open the database connection
    $db = installer_db_open($db_host, $db_port, $db_user, $db_pass, $sr_db);

    // Insert the new user
    // Ignoring next line detection as it does not describe the reason for the tainted argument
    // @phan-suppress-next-line SecurityCheck-SQLInjection
    $stmt = $db->prepare(
        "INSERT INTO
            `{$sr_db}`.`user` (
                `type`,
                `username`,
                `name`,
                `email`,
                `salt`,
                `password`,
                `role_id`,
                `admin`,
                `multi_factor`,
                `change_password`,
                `manager`,
                `custom_display_settings`,
                `custom_plan_mitigation_display_settings`,
                `custom_perform_reviews_display_settings`,
                `custom_reviewregularly_display_settings`
            )
        VALUES (
            :type,
            :user,
            :name,
            :email,
            :salt,
            :hash,
            :role_id,
            :admin,
            :multi_factor,
            :change_password,
            :manager,
            :custom_display_settings,
            :custom_plan_mitigation_display_settings,
            :custom_perform_reviews_display_settings,
            :custom_reviewregularly_display_settings
        );
    ");
    $stmt->bindParam(":type", $type, PDO::PARAM_STR);
    $stmt->bindParam(":user", $user, PDO::PARAM_STR);
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":email", $email, PDO::PARAM_STR);
    $stmt->bindParam(":salt", $salt, PDO::PARAM_STR);
    $stmt->bindParam(":hash", $hash, PDO::PARAM_STR);
    $stmt->bindParam(":role_id", $role_id, PDO::PARAM_INT);
    $stmt->bindParam(":admin", $admin, PDO::PARAM_INT);
    $stmt->bindParam(":multi_factor", $multi_factor, PDO::PARAM_INT);
    $stmt->bindParam(":change_password", $change_password, PDO::PARAM_INT);
    $stmt->bindParam(":manager", $manager, PDO::PARAM_INT);
    $stmt->bindParam(":custom_display_settings", $custom_display_settings, PDO::PARAM_STR);
    $stmt->bindParam(":custom_plan_mitigation_display_settings", $custom_plan_mitigation_display_settings, PDO::PARAM_STR);
    $stmt->bindParam(":custom_perform_reviews_display_settings", $custom_perform_reviews_display_settings, PDO::PARAM_STR);
    $stmt->bindParam(":custom_reviewregularly_display_settings", $custom_reviewregularly_display_settings, PDO::PARAM_STR);
    $stmt->execute();

    $user_id = $db->lastInsertId();

    // Get the list of all team values
    // Ignoring next line detection as it does not describe the reason for the tainted argument
    // @phan-suppress-next-line SecurityCheck-SQLInjection
    $stmt = $db->prepare("SELECT `value` FROM `{$sr_db}`.`team` ORDER BY `value`;");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Make sure that all teams are assigned to the user
    foreach ($teams as $team_id)
    {
        // Ignoring next line detection as it does not describe the reason for the tainted argument
        // @phan-suppress-next-line SecurityCheck-SQLInjection
        $stmt = $db->prepare("INSERT INTO `{$sr_db}`.`user_to_team` VALUES (:user_id, :team_id);");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":team_id", $team_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Get the list of all permission values
    // Ignoring next line detection as it does not describe the reason for the tainted argument
    // @phan-suppress-next-line SecurityCheck-SQLInjection
    $stmt = $db->prepare("SELECT `id` FROM `{$sr_db}`.`permissions`;");
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Make sure all permissions are assigned to the user
    foreach($permissions as $permission_id)
    {
        // Ignoring next line detection as it does not describe the reason for the tainted argument
        // @phan-suppress-next-line SecurityCheck-SQLInjection
        $stmt = $db->prepare("INSERT INTO `{$sr_db}`.`permission_to_user` VALUES (:permission_id, :user_id);");
        $stmt->bindParam(":permission_id", $permission_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // Close the database connection
    installer_db_close($db);
}

/**
 * Validate a MySQL identifier we control with a strict whitelist.
 * Allows letters, numbers, underscore and hyphen. Adjust as needed.
 */
/**
 * Returns true if $host is a valid value for a MySQL user host field.
 *
 * Accepts:
 *   - Plain IPv4/IPv6 addresses and hostnames
 *   - MySQL LIKE wildcards: '%' (any host), '%.domain' (subdomain wildcard),
 *     or an IPv4 pattern where any octet is replaced by '%' or '_'
 *     (e.g. 192.168.1.%, 192.168.%.%)
 *   - IPv4 netmask notation with a full dotted-quad mask
 *     (e.g. 192.168.1.0/255.255.255.0) — /24 shorthand is NOT accepted
 */
function is_valid_sr_host(string $host): bool {
    // Plain IP address (IPv4 or IPv6)
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return true;
    }

    // Plain hostname or domain
    if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return true;
    }

    // Bare '%' — matches any host
    if ($host === '%') {
        return true;
    }

    // Wildcard subdomain: %.hostname (e.g. %.example.com)
    if (str_starts_with($host, '%.')) {
        $suffix = substr($host, 2);
        if ($suffix !== '' && filter_var($suffix, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return true;
        }
    }

    // IPv4 wildcard: four dot-separated segments, each a valid octet (0–255),
    // '%', or '_', with at least one wildcard present.
    // e.g. 192.168.1.%, 192.168.%.%
    $octet = '(?:25[0-5]|2[0-4]\d|[01]?\d\d?)';
    $seg   = "(?:{$octet}|[%_])";
    if ((str_contains($host, '%') || str_contains($host, '_')) &&
        preg_match("/^{$seg}(?:\\.{$seg}){3}$/", $host)) {
        return true;
    }

    // IPv4 netmask notation: base_ip/netmask_ip (full dotted-quad mask only)
    // e.g. 192.168.1.0/255.255.255.0
    if (str_contains($host, '/')) {
        $parts = explode('/', $host, 2);
        if (filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($parts[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }
    }

    return false;
}

function validate_mysql_identifier(string $ident): bool {
    // Prevent empty, leading/trailing whitespace, or backticks
    if ($ident === '' || preg_match('/`/', $ident)) {
        return false;
    }
    // Allow a conservative set of characters only
    return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $ident);
}

/**
 * Safe-quote a validated identifier by surrounding with backticks.
 * Assumes validate_mysql_identifier() already returned true.
 */
function quote_identifier(string $ident): string {
    // extra safety: strip any backticks that might have slipped through
    $safe = str_replace('`', '', $ident);
    return "`" . $safe . "`";
}

/**
 * Safe-quote user/host parts used in user@host strings.
 * Only called after host/user validated.
 */
function quote_string_literal(PDO $db, string $val): string {
    return $db->quote($val); // returns quoted string including surrounding single quotes
}

?>