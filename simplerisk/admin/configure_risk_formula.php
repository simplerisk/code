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
    add_security_headers();

    if (!isset($_SESSION))
    {
        // Session handler is database
        if (USE_DATABASE_FOR_SESSIONS == "true")
        {
            session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
        }

        // Start the session
        session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

        session_name('SimpleRisk');
        session_start();
    }

    // Include the language file
    require_once(language_file());

    // Check for session timeout or renegotiation
    session_check();

    // Check if access is authorized
    if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
    {
        set_unauthenticated_redirect();
        header("Location: ../index.php");
        exit(0);
    }

    // Check if access is authorized
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != "1")
    {
        header("Location: ../index.php");
        exit(0);
    }

    // Include the CSRF-magic library
    // Make sure it's called after the session is properly setup
    include_csrf_magic();

    // Check if the risk formula update was submitted
    if (isset($_POST['update_risk_formula']))
    {
        $risk_model = (int)$_POST['risk_models'];
        
        // Check if risk model value is integer
        if (is_int($risk_model))
        {
            // Risk model should be between 1 and 5
            if ((1 <= $risk_model) && ($risk_model <= 6))
            {
                // Update the risk model
                update_risk_model($risk_model);

                // Display an alert
                set_alert(true, "good", "The configuration was updated successfully.");
                
                refresh();
            }
            // Otherwise, there was a problem
            else
            {
                // Display an alert
                set_alert(true, "bad", "The risk formula submitted was an invalid value.");
            }
        }
        
    }
    
    // Check if the impact update was submitted
    if (isset($_POST['update_impact']))
    {
        $new_name = $_POST['new_name'];
        $value = (int)$_POST['impact'];

        // Verify value is an integer
        if (is_int($value))
        {
            update_table("impact", $new_name, $value);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessUpdatingImpactName']));

            refresh();
        }
    }

    // Check if the likelihood update was submitted
    if (isset($_POST['update_likelihood']))
    {
        $new_name = $_POST['new_name'];
        $value = (int)$_POST['likelihood'];

        // Verify value is an integer
        if (is_int($value))
        {
            update_table("likelihood", $new_name, $value);

            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessUpdatingLikelihoodName']));
            
            refresh();
        }
    }
    
    // Check if contributing risk was submitted
    if (isset($_POST['save_contributing_risk']))
    {
        $subjects = empty($_POST['subject']) ? [] : $_POST['subject'];
        $weights = empty($_POST['weight']) ? [] : $_POST['weight'];
        $existing_subjects = empty($_POST['existing_subject']) ? [] : $_POST['existing_subject'];
        $existing_weights = empty($_POST['existing_weight']) ? [] : $_POST['existing_weight'];
        
        // Save contributing risks
        if (save_contributing_risks($subjects, $weights, $existing_subjects, $existing_weights))
        {
            // Display an alert
            set_alert(true, "good", $escaper->escapeHtml($lang['SuccessSaveContributingRisks']));
            
            refresh();
        }
    }

    function displayEditableLineFor($localizationKey, $risk_levels, $level) {

        global $escaper;

        $risk_name = "<span data-level='{$level}'><span class='editable'>{$escaper->escapeHtml($risk_levels[$level]['display_name'])}</span>
                        <input type='text' data-field='display_name' class='editable' value='{$escaper->escapeHtml($risk_levels[$level]['display_name'])}' style='display: none;'></span>";

        $risk_value = "<span data-level='{$level}'><span class='editable'>{$escaper->escapeHtml($risk_levels[$level]['value'])}</span>
                        <input type='text' data-field='value' class='editable' value='{$escaper->escapeHtml($risk_levels[$level]['value'])}' style='display: none;'></span>";

        $color_select = "<span data-level='{$level}'><input data-field='color' class='level-colorpicker level-color editable' type='hidden' value='{$escaper->escapeHtml($risk_levels[$level]['color'])}'>
                        <div class='colorSelector'><div style='background-color:{$escaper->escapeHtml($risk_levels[$level]['color'])}'></div></div></span>";

        echo "<div>";
            echo _lang($localizationKey, array('risk_name' => $risk_name, 'risk_value' => $risk_value, 'color_select' => $color_select), false);
        echo "</div>";
    }
?>

<!doctype html>
<html>

  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=10,9,7,8">
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/common.js"></script>
    <script type="text/javascript" src="../js/colorpicker.js"></script>

    <title>SimpleRisk: Enterprise Risk Management Simplified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
    <link rel="stylesheet" href="../css/bootstrap.css">
    <link rel="stylesheet" href="../css/bootstrap-responsive.css">
    <link rel="stylesheet" media="screen" type="text/css" href="../css/colorpicker.css" />
    <link rel="stylesheet" href="../css/settings_tabs.css">
    

    <link rel="stylesheet" href="../css/divshot-util.css">
    <link rel="stylesheet" href="../css/divshot-canvas.css">
    <link rel="stylesheet" href="../css/display.css">

    <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/theme.css">

    <style type="text/css">
        .text-rotation {
            display: block;
            -webkit-transform: rotate(90deg);
            -moz-transform: rotate(90deg);
        }
        
        span.editable {
            line-height: 2.5;
        }
        
        td {
            padding: 2px 10px;
        }
    </style>

    <?php
        setup_alert_requirements("..");
    ?>    
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
                    <div class="risk-levels-container">
                        <div class="wrap">
                            <ul class="tabs group">
                              <li><a class="active" href="#/risk-levels"><?php echo $escaper->escapeHtml($lang['RiskLevels']); ?></a></li>
                              <li style="width: 200px;"><a href="#/classic-risk-formula"><?php echo $escaper->escapeHtml($lang['ClassicRiskFormula']); ?></a></li>
                              <li style="width: 200px;"><a href="#/contributing-risk-formula"><?php echo $escaper->escapeHtml($lang['ContributingRiskFormula']); ?></a></li>
                            </ul>
                                      
                        </div>
                        <div id="content">
                            <div id="risk-levels">
                                <?php $risk_levels = get_risk_levels(); ?>

                                <?php displayEditableLineFor('RiskLevelTextTop', $risk_levels, 3); ?>
                                <?php displayEditableLineFor('RiskLevelTextRest', $risk_levels, 2); ?>
                                <?php displayEditableLineFor('RiskLevelTextRest', $risk_levels, 1); ?>
                                <?php displayEditableLineFor('RiskLevelTextRest', $risk_levels, 0); ?>
                            </div>
                            <div id="classic-risk-formula" style="display: none;">
                                <?php create_risk_formula_table(); ?>
                            </div>
                            <div id="contributing-risk-formula" style="display: none;">
                                <?php display_contributing_risk_formula(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
              
            <div class="row-fluid">
                <div class="span12">
                  <div class="">
                    <?php echo "<p><font size=\"1\">* " . $escaper->escapeHtml($lang['AllRiskScoresAreAdjusted']) . "</font></p>"; ?>
                  </div>
                </div>
            </div>
        </div>
      </div>
    </div>
    <script>
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

        function resizable (el, factor) {
            var int = Number(factor) || 7.6;
            function resize() {el.width((el.val().length + 1) * int);}
            var e = ['keyup', 'keypress', 'focus', 'blur', 'change'];
            for (var i in e)
                el.on(e[i], resize);
            resize();
        }

        $(document).ready(function(){
            $('input.editable').each(function(){
                resizable($(this));
            });

            $("span.editable").click(function() {
                $(this).hide();
                $(this).parent().find('input').show().select();
            });

            $('input.editable').blur(function(){
                var label = $(this).parent().find('span.editable');
                $(this).hide();
                label.text($(this).val());
                label.show();
            });

            $('#risk-levels input.editable').change(function(){
                //saving it so it can be referenced from the AJAX callbacks
                var _this = $(this);
                var level = _this.parent().data('level');
                var field = _this.data('field');
                var value = _this.val();

                $.ajax({
                    type: "POST",
                    url: "/api/risklevel/update",
                    data: {
                        level: level,
                        field: field,
                        value: value
                    },
                    success: function(data){
                        if(data.status_message){
                            showAlertsFromArray(data.status_message);
                        }
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    },
                    complete: function(xhr,status){
                        if(xhr.responseJSON && xhr.responseJSON.data){
                            // If there's data returned set it back to the label
                            _this.parent().find('span.editable').text(xhr.responseJSON.data);
                            // and to the input
                            _this.val(xhr.responseJSON.data);
                        }
                    }
                });
            });

            $('.colorSelector').each(function(){
                var inp = $(this).parent().find('.level-colorpicker');
                var color = colourNameToHex(inp.val());

                $(this).ColorPicker({
                    color: color,
                    onShow: function (colpkr) {
                        $(colpkr).fadeIn(500);
                        inp.data('original', inp.val());
                        return false;
                    },
                    onHide: function (colpkr) {
                        $(colpkr).fadeOut(500);
                        if (inp.data('original') != inp.val())
                            inp.trigger('change');
                        return false;
                    },
                    onChange: function (hsb, hex, rgb, el) {
                        $('div', el).css('backgroundColor', '#' + hex);
                        $(el).parent().find('.level-color').val('#' + hex);
                    }
                });
            });

            var tabs =  $(".tabs li a");
            var hash = window.location.hash;
            if(hash){
                //console.log(hash);
                tabs.removeClass("active");
                $(".tabs").find("[href='"+hash+"']").addClass("active");

                var content = hash.replace('/','');
                $("#content > div").hide();
                $(content).fadeIn(200);
            }

            tabs.click(function() {
                var content = this.hash.replace('/','');
                tabs.removeClass("active");
                $(this).addClass("active");
                $("#content > div").hide();
                $(content).fadeIn(200);
            });
        });
    </script>

  </body>

</html>
