<?php
    /* This Source Code Form is subject to the terms of the Mozilla Public
    * License, v. 2.0. If a copy of the MPL was not distributed with this
    * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

    // Render the header and sidebar
    require_once(realpath(__DIR__ . '/../includes/renderutils.php'));
    render_header_and_sidebar(['blockUI', 'CUSTOM:common.js'], ['check_admin' => true]);

    // If the Security settings form was submitted
    if (isset($_POST['update_security_settings'])) {

        // Set the error to false
        $error = false;

        // Update the base url (relocated from Default Values to Security —
        // it's an instance-identity setting that feeds CSRF validation and
        // redirect-target checks, not a form default).
        $simplerisk_base_url = $_POST['simplerisk_base_url'];
        $current_simplerisk_base_url = get_setting("simplerisk_base_url");
        if ($simplerisk_base_url != $current_simplerisk_base_url) {
            // If the base url is not empty
            if ($simplerisk_base_url != "" && is_valid_base_url($simplerisk_base_url)) {
                // Update the base url
                update_setting("simplerisk_base_url", $simplerisk_base_url);

                $_SESSION['base_url'] = rtrim($simplerisk_base_url, '/');
            } else {
                set_alert(true, "bad", $escaper->escapeHtml($lang['InvalidSimpleriskBaseUrl']));
                $error = true;
            }
        }

        // Update the session activity timeout setting
        $session_activity_timeout = (int)$_POST['session_activity_timeout'];

        // If the session_activity_timeout value is at least 5 minutes
        if ($session_activity_timeout >= 300) {
            $current_session_activity_timeout = get_setting("session_activity_timeout");
            if ($session_activity_timeout != $current_session_activity_timeout) {
                update_setting("session_activity_timeout", $session_activity_timeout);
            }
        } else {
            $error = true;
            set_alert(true, "bad", "We do not recommend setting a session activity timeout less than 300 seconds.");
        }

        // Update the session absolute timeout setting
        $session_absolute_timeout = (int)$_POST['session_absolute_timeout'];

        // If the session_absolute_timeout value is less than the session_activity_timeout
        if ($session_absolute_timeout > get_setting("session_activity_timeout")) {
            $current_session_absolute_timeout = get_setting("session_absolute_timeout");
            if ($session_absolute_timeout != $current_session_absolute_timeout) {
                update_setting("session_absolute_timeout", $session_absolute_timeout);
            }
        } else {
            $error = true;
            set_alert(true, "bad", "The session absolute timeout should be more than the session activity timeout.");
        }

        // Update the password reset token expiration period setting
        $password_reset_token_expiration = (int)$_POST['password_reset_token_expiration'];

        // If the password_reset_token_expiration value is at least 5 minutes
        if ($password_reset_token_expiration >= 5) {
            $current_password_reset_token_expiration = get_setting("password_reset_token_expiration");
            if ($password_reset_token_expiration != $current_password_reset_token_expiration) {
                update_setting("password_reset_token_expiration", $password_reset_token_expiration);
            }
        } else {
            $error = true;
            set_alert(true, "bad", $escaper->escapeHtml($lang['APasswordResetTokenExpirationPeriodShouldBeMoreThan5Minutes']));
        }

        // Update the password reset attempt lockout time setting
        $password_reset_attempt_lockout_time = (int)$_POST['password_reset_attempt_lockout_time'];

        // If the password_reset_attempt_lockout_time value is at least 1 minute
        if ($password_reset_attempt_lockout_time >= 1) {
            $current_password_reset_attempt_lockout_time = get_setting("password_reset_attempt_lockout_time");
            if ($password_reset_attempt_lockout_time != $current_password_reset_attempt_lockout_time) {
                update_setting("password_reset_attempt_lockout_time", $password_reset_attempt_lockout_time);
            }
        } else {
            $error = true;
            set_alert(true, "bad", "The password reset attempt lockout time must be at least 1 minute.");
        }

        // Update the content security policy
        $content_security_policy = isset($_POST['content_security_policy']) ? 1 : 0;
        $current_content_security_policy = get_setting("content_security_policy");
        if ($content_security_policy != $current_content_security_policy) {
            update_setting("content_security_policy", $content_security_policy);
        }

        // Update the SSL certificate check for the SimpleRisk API
        $ssl_certificate_check = isset($_POST['ssl_certificate_check_simplerisk']) ? 1 : 0;
        $current_ssl_certificate_check = get_setting("ssl_certificate_check_simplerisk");
        if ($ssl_certificate_check != $current_ssl_certificate_check) {
            update_setting("ssl_certificate_check_simplerisk", $ssl_certificate_check);
        }

        // Update the SSL certificate check for external websites
        $ssl_certificate_check = isset($_POST['ssl_certificate_check_external']) ? 1 : 0;
        $current_ssl_certificate_check = get_setting("ssl_certificate_check_external");
        if ($ssl_certificate_check != $current_ssl_certificate_check) {
            update_setting("ssl_certificate_check_external", $ssl_certificate_check);
        }

        // Update the API v1 enable setting
        $enable_api_v1 = isset($_POST['enable_api_v1']) ? 1 : 0;
        $current_enable_api_v1 = get_setting("enable_api_v1");
        if ($enable_api_v1 != $current_enable_api_v1) {
            update_setting("enable_api_v1", $enable_api_v1);
        }

        // Update the proxy settings
        $proxy_web_requests = isset($_POST['proxy_web_requests']) ? 1 : 0;
        $current_proxy_web_requests = get_setting("proxy_web_requests");
        if ($proxy_web_requests != $current_proxy_web_requests) {
            update_setting("proxy_web_requests", $proxy_web_requests);
        }

        // If proxy web requests is enabled
        if ($proxy_web_requests) {
            // Get the new proxy values
            $proxy_authenticated = isset($_POST['proxy_authenticated']) ? 1 : 0;
            $proxy_verify_ssl_certificate = isset($_POST['proxy_verify_ssl_certificate']) ? 1 : 0;
            $proxy_host = isset($_POST['proxy_host']) ? $_POST['proxy_host'] : "";
            $proxy_port = isset($_POST['proxy_port']) ? $_POST['proxy_port'] : "";
            $proxy_user = isset($_POST['proxy_user']) ? $_POST['proxy_user'] : "";
            $proxy_pass = isset($_POST['proxy_pass']) ? $_POST['proxy_pass'] : "";

            // Get the current proxy values
            $current_proxy_authenticated = get_setting("proxy_authenticated");
            $current_proxy_verify_ssl_certificate = get_setting("proxy_verify_ssl_certificate");
            $current_proxy_host = get_setting("proxy_host");
            $current_proxy_port = get_setting("proxy_port");
            $current_proxy_user = get_setting("proxy_user");
            $current_proxy_pass = get_setting("proxy_pass");

            // Update the proxy settings
            if ($proxy_authenticated != $current_proxy_authenticated) {
                update_setting("proxy_authenticated", $proxy_authenticated);
            }

            if ($proxy_verify_ssl_certificate != $current_proxy_verify_ssl_certificate) {
                update_setting("proxy_verify_ssl_certificate", $proxy_verify_ssl_certificate);
            }

            if ($proxy_host != $current_proxy_host) {
                // If this is a valid IP or domain name
                if (filter_var($proxy_host, FILTER_VALIDATE_IP) || filter_var($proxy_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    update_setting("proxy_host", $proxy_host);
                }
            }

            if ($proxy_port != $current_proxy_port) {
                // Set the minimum and maximum port range
                $options = array("options" => array("min_range"=>0, "max_range"=>65535));

                // If this is a valid integer value
                if (filter_var($proxy_port, FILTER_VALIDATE_INT, $options)) {
                    update_setting("proxy_port", $proxy_port);
                }
            }

            if ($proxy_user != $current_proxy_user) {
                update_setting("proxy_user", $proxy_user);
            }

            // If the proxy password has been changed
            if ($proxy_pass != "XXXXXXXXXX" && $proxy_pass != $current_proxy_pass) {
                update_setting("proxy_pass", $proxy_pass);
            }
        }

        // If all setting values were saved successfully
        if (!$error) {
            // Display an alert
            set_alert(true, "good", "The settings were updated successfully.");
        }
    }

    global $escaper, $lang;
?>
<div class="row">
    <div class="col-12">
        <form name="security_settings" method="post" action="">
            <div class="row">
                <div class="col-12">
                    <div class="card-body my-2 border">
                        <h4 class="page-title"><?= $escaper->escapeHtml($lang['SimpleriskBaseUrl']); ?></h4>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['SimpleriskBaseUrl']); ?> :</label>
                                    <input type="text" name="simplerisk_base_url" value="<?= $escaper->escapeHtml(get_setting("simplerisk_base_url")); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                    <div class="card-body my-2 border">
                        <h4 class="page-title">
                            <?= $escaper->escapeHtml($lang['UserSessions']); ?>
                        </h4>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['SessionActivityTimeout']) . " (" . $escaper->escapeHtml($lang["seconds"]) . ")"; ?> :</label>
                                    <input name="session_activity_timeout" id="session_activity_timeout" type="number" min="0" size="20px" value="<?= $escaper->escapeHtml(get_setting("session_activity_timeout")); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['SessionAbsoluteTimeout']) . " (" . $escaper->escapeHtml($lang["seconds"]) . ")"; ?> :</label>
                                    <input name="session_absolute_timeout" id="session_absolute_timeout" type="number" min="0" size="20px" value="<?= $escaper->escapeHtml(get_setting("session_absolute_timeout")); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml($lang['PasswordResetTokenExpirationPeriod']) . " (" . $escaper->escapeHtml($lang["minutes"]) . ")"; ?> :</label>
                                    <input name="password_reset_token_expiration" id="password_reset_token_expiration" type="number" min="5" size="20px" value="<?= $escaper->escapeHtml(get_setting("password_reset_token_expiration") ?: 15); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label><?= $escaper->escapeHtml("Password Reset Attempt Lockout Time") . " (" . $escaper->escapeHtml($lang["minutes"]) . ")"; ?> :</label>
                                    <input name="password_reset_attempt_lockout_time" id="password_reset_attempt_lockout_time" type="number" min="1" size="20px" value="<?= $escaper->escapeHtml(get_setting("password_reset_attempt_lockout_time") ?: 5); ?>" class="form-control"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 d-flex flex-column">
                    <div class="card-body my-2 border flex-grow-1">
                        <h4 class="page-title"><?= $escaper->escapeHtml($lang['Security']); ?></h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-4">
                                    <input <?php if($escaper->escapeHtml(get_setting('content_security_policy')) == 1){ echo "checked"; } ?> name="content_security_policy" size="2" value="90" id="content_security_policy" type="checkbox"  class="form-check-input">
                                    <label  for="content_security_policy" class="form-check-label mb-0 ms-2" ><?= $escaper->escapeHtml($lang['EnableCSP']); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12"><div class="text-danger"><?= $escaper->escapeHtml($lang['SSLSecurityCheckWarning']); ?></div></div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-4">
                                    <input <?php if($escaper->escapeHtml(get_setting('ssl_certificate_check_simplerisk')) == 1){ echo "checked"; } ?> name="ssl_certificate_check_simplerisk"  class="form-check-input" size="2" value="90" id="ssl_certificate_check_simplerisk" type="checkbox">
                                    <label for="ssl_certificate_check_simplerisk" class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['EnableSSLCertificateCheckSimpleRisk']); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-4">
                                   <input <?php if($escaper->escapeHtml(get_setting('ssl_certificate_check_external')) == 1){ echo "checked"; } ?> name="ssl_certificate_check_external" class="form-check-input" size="2" value="90" id="ssl_certificate_check_external" type="checkbox">
                                   <label for="ssl_certificate_check_external"  class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['EnableSSLCertificateCheckExternal']); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-check mr-sm-4">
                                   <input <?php if($escaper->escapeHtml(get_setting('enable_api_v1')) == 1){ echo "checked"; } ?> name="enable_api_v1" class="form-check-input" size="2" value="1" id="enable_api_v1" type="checkbox">
                                   <label for="enable_api_v1" class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['EnableAPIv1Endpoints']); ?></label>
                                   <div class="text-muted small mt-1"><?= $escaper->escapeHtml($lang['EnableAPIv1EndpointsHelp']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body mb-2 border">
                <h4 class="page-title"><?= $escaper->escapeHtml($lang['Proxy']); ?></h4>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('proxy_web_requests')) == 1){ echo "checked"; } ?> name="proxy_web_requests" id="proxy_web_requests_checkbox" type="checkbox" onclick="update_proxy()"  class="form-check-input">
                            <label  for="proxy_web_requests_checkbox" class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['ProxyWebRequests']); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row mb-2" id="proxy_verify_ssl_certificate_row" <?= (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : "");     ?>>
                    <div class="col-md-6">
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('proxy_verify_ssl_certificate')) == 1){ echo "checked"; } ?> name="proxy_verify_ssl_certificate" id="proxy_verify_ssl_certificate_checkbox" type="checkbox" onclick="update_proxy()" class="form-check-input">
                            <label for="proxy_verify_ssl_certificate_checkbox" class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['VerifySSLCertificate']); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6" id="proxy_host_row" <?= (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ProxyHostname']); ?> :</label>
                            <input name="proxy_host" id="proxy_host" type="text" value="<?= $escaper->escapeHtml(get_setting("proxy_host")); ?>"class="form-control"/>
                        </div>
                    </div>
                    <div class="col-md-6" id="proxy_port_row" <?= (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : ""); ?>>
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ProxyPort']); ?> :</label>
                            <input name="proxy_port" id="proxy_port" type="number" min="0" size="20px" value="<?= $escaper->escapeHtml(get_setting("proxy_port")); ?>" class="form-control"/>
                        </div>
                    </div>
                </div>
                <div class="row mb-2" id="proxy_authenticated_row" <?= (get_setting('proxy_web_requests') != 1 ? " style='display: none;'" : "");  ?>>
                    <div class="col-md-6">
                        <div class="form-check mr-sm-4">
                            <input <?php if($escaper->escapeHtml(get_setting('proxy_authenticated')) == 1){ echo "checked"; } ?> name="proxy_authenticated" id="proxy_authenticated_checkbox" type="checkbox" onclick="update_proxy()"  class="form-check-input">
                            <label for="proxy_authenticated_checkbox" class="form-check-label mb-0 ms-2"><?= $escaper->escapeHtml($lang['AuthenticatedProxy']); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6"  id="proxy_user_row" <?= (get_setting('proxy_web_requests') != 1 || get_setting('proxy_authenticated') != 1 ? " style='display: none;'" : ""); ?>>
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ProxyUsername']); ?> :</label>
                            <input  name="proxy_user" id="proxy_user" type="text" size="20px" value="<?= $escaper->escapeHtml(get_setting("proxy_user")); ?>" class="form-control"/>
                        </div>
                    </div>
                    <div class="col-md-6" id="proxy_pass_row" <?= (get_setting('proxy_web_requests') != 1 || get_setting('proxy_authenticated') != 1 ? " style='display: none;'" : ""); ?>>
                        <div class="form-group">
                            <label><?= $escaper->escapeHtml($lang['ProxyPassword']); ?> :</label>
                            <input  name="proxy_pass" id="proxy_pass" type="password" size="20px" value="XXXXXXXXXX" class="form-control"/>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <div class="card-body mb-2 border">
                        <button type="submit" name="update_security_settings" class="btn btn-submit"><?= $escaper->escapeHtml($lang['Update']); ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    function update_proxy()
    {
        var proxy_web_requests_checkbox = document.getElementById("proxy_web_requests_checkbox");
        var proxy_verify_ssl_certificate_checkbox = document.getElementById("proxy_verify_ssl_certificate_checkbox");
        var proxy_verify_ssl_certificate_row = document.getElementById("proxy_verify_ssl_certificate_row");
        var proxy_authenticated_row = document.getElementById("proxy_authenticated_row");
        var proxy_authenticated_checkbox = document.getElementById("proxy_authenticated_checkbox");
        var proxy_host_row = document.getElementById("proxy_host_row");
        var proxy_port_row = document.getElementById("proxy_port_row");
        var proxy_user_row = document.getElementById("proxy_user_row");
        var proxy_pass_row = document.getElementById("proxy_pass_row");

        if (proxy_web_requests_checkbox.checked == true) {
            proxy_verify_ssl_certificate_row.style.display = "";
            proxy_host_row.style.display = "";
            proxy_port_row.style.display = "";
            proxy_authenticated_row.style.display = "";

            if (proxy_authenticated_checkbox.checked == true) {
                proxy_user_row.style.display = "";
                proxy_pass_row.style.display = "";
            } else {
                proxy_user_row.style.display = "none";
                proxy_pass_row.style.display = "none";
            }
        } else {
            proxy_verify_ssl_certificate_row.style.display = "none";
            proxy_host_row.style.display = "none";
            proxy_port_row.style.display = "none";
            proxy_authenticated_row.style.display = "none";
            proxy_user_row.style.display = "none";
            proxy_pass_row.style.display = "none";
        }
    }
</script>
<?php
    // Render the footer of the page. Please don't put code after this part.
    render_footer();
?>
