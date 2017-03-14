<?php
        /* This Source Code Form is subject to the terms of the Mozilla Public
         * License, v. 2.0. If a copy of the MPL was not distributed with this
         * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

        // Include required functions file
        require_once(realpath(__DIR__ . '/../includes/functions.php'));
        require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
	require_once(realpath(__DIR__ . '/../includes/display.php'));
	require_once(realpath(__DIR__ . '/../includes/alerts.php'));

        // Include Zend Escaper for HTML Output Encoding
        require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
        $escaper = new Zend\Escaper\Escaper('utf-8');

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

        // Include the language file
        require_once(language_file());

	require_once(realpath(__DIR__ . '/../includes/csrf-magic/csrf-magic.php'));

        // Check for session timeout or renegotiation
        session_check();

        // Check if access is authorized
        if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if access is authorized
        if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
        {
                header("Location: ../index.php");
                exit(0);
        }

        // Check if the risk level update was submitted
        if (isset($_POST['update_risk_levels']))
        {
            $level = $_POST['level'];
		    $veryhigh = $level['Very High'];
            $high = $level['High'];
            $medium = $level['Medium'];
            $low = $level['Low'];
            $risk_model = (int)$_POST['risk_models'];

            // Check if all values are integers
            if (is_numeric($veryhigh['value']) && is_numeric($high['value']) && is_numeric($medium['value']) && is_numeric($low['value']) && is_int($risk_model))
            {
                // Check if low < medium < high < very high
                if (($low['value'] < $medium['value']) && ($medium['value'] < $high) && ($high['value'] < $veryhigh['value']))
                {
                    // Update the risk level
                    update_risk_levels($veryhigh, $high, $medium, $low);

				    // Risk model should be between 1 and 5
				    if ((1 <= $risk_model) && ($risk_model <= 5))
				    {
					    // Update the risk model
					    update_risk_model($risk_model);

					    // Display an alert
					    set_alert(true, "good", "The configuration was updated successfully.");
				    }
                    // Otherwise, there was a problem
                    else
                    {
				        // Display an alert
				        set_alert(true, "bad", "The risk formula submitted was an invalid value.");
                    }
                }
			    // Otherwise, there was a problem
			    else
			    {
				    // Display an alert
				    set_alert(true, "bad", "Your LOW risk needs to be less than your MEDIUM risk which needs to be less than your HIGH risk which needs to be less than your VERY HIGH risk.");
			    }
            }
		    // Otherwise, there was a problem
		    else
		    {
			    // Display an alert
			    set_alert(true, "bad", "One of the submitted risk values is not a numeric value.");
		    }
        }
?>

<!doctype html>
<html>

  <head>
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script type="text/javascript" src="../js/colorpicker.js"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" media="screen" type="text/css" href="../css/colorpicker.css" />

    <style type="text../css">.text-rotation {display: block; -webkit-transform: rotate(-90deg); -moz-transform: rotate(-90deg);}</style>

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">
  </head>

  <body>

<?php
	view_top_menu("Configure");

	// Get any alert messages
	get_alert();
?>
    <div class="container-fluid">
      <div class="row-fluid">
        <div class="span3">
          <?php view_configure_menu("ConfigureRiskFormula"); ?>
        </div>
        <div class="span9">
          <div class="row-fluid">
            <div class="span12">
              <div class="hero-unit risk-levels-container">
                <h4><?php echo $escaper->escapeHtml($lang['MyClassicRiskFormulaIs']); ?>:</h4>

                <form name="risk_levels" method="post" action="">
                <p><?php echo $escaper->escapeHtml($lang['RISK']); ?> = <?php create_dropdown("risk_models", get_setting("risk_model")) ?></p>

                <?php $risk_levels = get_risk_levels(); ?>

                <div>
                    <?php echo $escaper->escapeHtml($lang['IConsiderVeryHighRiskToBeAnythingGreaterThan']); ?>: 
                    <input type="text" name="level[Very High][value]" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[3]['value']); ?>" /> 
                    <input class="level-colorpicker level-color" type="hidden" name="level[Very High][color]" value="<?php echo $escaper->escapeHtml($risk_levels[3]['color']); ?>"> 
                    <div class="colorSelector">
                        <div style="background-color: <?php echo $escaper->escapeHtml($risk_levels[3]['color']); ?>;"></div>
                    </div>
                </div>
                
                
                <div>
                    <?php echo $escaper->escapeHtml($lang['IConsiderHighRiskToBeLessThanAboveButGreaterThan']); ?>: 
                    <input type="text" name="level[High][value]" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[2]['value']); ?>" />
                    <input class="level-colorpicker level-color" type="hidden" name="level[High][color]" value="<?php echo $escaper->escapeHtml($risk_levels[2]['color']); ?>"> 
                    <div class="colorSelector">
                        <div style="background-color: <?php echo $escaper->escapeHtml($risk_levels[2]['color']); ?>;"></div>
                    </div>
                </div>
                <div>
                    <?php echo $escaper->escapeHtml($lang['IConsiderMediumRiskToBeLessThanAboveButGreaterThan']); ?>: 
                    <input type="text" name="level[Medium][value]" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[1]['value']); ?>" />
                    <input class="level-colorpicker level-color" type="hidden" name="level[Medium][color]" value="<?php echo $escaper->escapeHtml($risk_levels[1]['color']); ?>"> 
                    <div class="colorSelector">
                        <div style="background-color: <?php echo $escaper->escapeHtml($risk_levels[1]['color']); ?>;"></div>
                    </div>
                </div>
                <div>
                    <?php echo $escaper->escapeHtml($lang['IConsiderlowRiskToBeLessThanAboveButGreaterThan']); ?>: 
                    <input type="text" name="level[Low][value]" size="2" value="<?php echo $escaper->escapeHtml($risk_levels[0]['value']); ?>" />
                    <input class="level-colorpicker level-color" type="hidden" name="level[Low][color]" value="<?php echo $escaper->escapeHtml($risk_levels[0]['color']); ?>"> 
                    <div class="colorSelector">
                        <div style="background-color: <?php echo $escaper->escapeHtml($risk_levels[0]['color']); ?>;"></div>
                    </div>
                </div>

                <input type="submit" value="<?php echo $escaper->escapeHtml($lang['Update']); ?>" name="update_risk_levels" />

                </form>

                <?php create_risk_table(); ?>

                <?php echo "<p><font size=\"1\">* " . $escaper->escapeHtml($lang['AllRiskScoresAreAdjusted']) . "</font></p>"; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script type="">
        function colourNameToHex(colour)
        {
            var colours = {"aliceblue":"#f0f8ff","antiquewhite":"#faebd7","aqua":"#00ffff","aquamarine":"#7fffd4","azure":"#f0ffff",
            "beige":"#f5f5dc","bisque":"#ffe4c4","black":"#000000","blanchedalmond":"#ffebcd","blue":"#0000ff","blueviolet":"#8a2be2","brown":"#a52a2a","burlywood":"#deb887",
            "cadetblue":"#5f9ea0","chartreuse":"#7fff00","chocolate":"#d2691e","coral":"#ff7f50","cornflowerblue":"#6495ed","cornsilk":"#fff8dc","crimson":"#dc143c","cyan":"#00ffff",
            "darkblue":"#00008b","darkcyan":"#008b8b","darkgoldenrod":"#b8860b","darkgray":"#a9a9a9","darkgreen":"#006400","darkkhaki":"#bdb76b","darkmagenta":"#8b008b","darkolivegreen":"#556b2f",
            "darkorange":"#ff8c00","darkorchid":"#9932cc","darkred":"#8b0000","darksalmon":"#e9967a","darkseagreen":"#8fbc8f","darkslateblue":"#483d8b","darkslategray":"#2f4f4f","darkturquoise":"#00ced1",
            "darkviolet":"#9400d3","deeppink":"#ff1493","deepskyblue":"#00bfff","dimgray":"#696969","dodgerblue":"#1e90ff",
            "firebrick":"#b22222","floralwhite":"#fffaf0","forestgreen":"#228b22","fuchsia":"#ff00ff",
            "gainsboro":"#dcdcdc","ghostwhite":"#f8f8ff","gold":"#ffd700","goldenrod":"#daa520","gray":"#808080","green":"#008000","greenyellow":"#adff2f",
            "honeydew":"#f0fff0","hotpink":"#ff69b4",
            "indianred ":"#cd5c5c","indigo":"#4b0082","ivory":"#fffff0","khaki":"#f0e68c",
            "lavender":"#e6e6fa","lavenderblush":"#fff0f5","lawngreen":"#7cfc00","lemonchiffon":"#fffacd","lightblue":"#add8e6","lightcoral":"#f08080","lightcyan":"#e0ffff","lightgoldenrodyellow":"#fafad2",
            "lightgrey":"#d3d3d3","lightgreen":"#90ee90","lightpink":"#ffb6c1","lightsalmon":"#ffa07a","lightseagreen":"#20b2aa","lightskyblue":"#87cefa","lightslategray":"#778899","lightsteelblue":"#b0c4de",
            "lightyellow":"#ffffe0","lime":"#00ff00","limegreen":"#32cd32","linen":"#faf0e6",
            "magenta":"#ff00ff","maroon":"#800000","mediumaquamarine":"#66cdaa","mediumblue":"#0000cd","mediumorchid":"#ba55d3","mediumpurple":"#9370d8","mediumseagreen":"#3cb371","mediumslateblue":"#7b68ee",
            "mediumspringgreen":"#00fa9a","mediumturquoise":"#48d1cc","mediumvioletred":"#c71585","midnightblue":"#191970","mintcream":"#f5fffa","mistyrose":"#ffe4e1","moccasin":"#ffe4b5",
            "navajowhite":"#ffdead","navy":"#000080",
            "oldlace":"#fdf5e6","olive":"#808000","olivedrab":"#6b8e23","orange":"#ffa500","orangered":"#ff4500","orchid":"#da70d6",
            "palegoldenrod":"#eee8aa","palegreen":"#98fb98","paleturquoise":"#afeeee","palevioletred":"#d87093","papayawhip":"#ffefd5","peachpuff":"#ffdab9","peru":"#cd853f","pink":"#ffc0cb","plum":"#dda0dd","powderblue":"#b0e0e6","purple":"#800080",
            "rebeccapurple":"#663399","red":"#ff0000","rosybrown":"#bc8f8f","royalblue":"#4169e1",
            "saddlebrown":"#8b4513","salmon":"#fa8072","sandybrown":"#f4a460","seagreen":"#2e8b57","seashell":"#fff5ee","sienna":"#a0522d","silver":"#c0c0c0","skyblue":"#87ceeb","slateblue":"#6a5acd","slategray":"#708090","snow":"#fffafa","springgreen":"#00ff7f","steelblue":"#4682b4",
            "tan":"#d2b48c","teal":"#008080","thistle":"#d8bfd8","tomato":"#ff6347","turquoise":"#40e0d0",
            "violet":"#ee82ee",
            "wheat":"#f5deb3","white":"#ffffff","whitesmoke":"#f5f5f5",
            "yellow":"#ffff00","yellowgreen":"#9acd32"};

            if (typeof colours[colour.toLowerCase()] != 'undefined')
                return colours[colour.toLowerCase()];

            return colour;
        }    
    
    
        $('.colorSelector').each(function(){
            var color = $(this).parent().find('.level-colorpicker').val()
            color = colourNameToHex(color)
            $(this).ColorPicker({
                color: color,
                onShow: function (colpkr) {
                    $(colpkr).fadeIn(500);
                    return false;
                },
                onHide: function (colpkr) {
                    $(colpkr).fadeOut(500);
                    return false;
                },
                onSubmit: function (hsb, hex, rgb, el) {
                    console.log(el)
                },
                onChange: function (hsb, hex, rgb, el) {
                    $('div', el).css('backgroundColor', '#' + hex);
                    $(el).parent().find('.level-color').val('#' + hex);
                }
            });    
        })
    </script>
    
  </body>

</html>
