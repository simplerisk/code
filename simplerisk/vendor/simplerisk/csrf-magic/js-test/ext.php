<?php require_once 'common.php'; ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Ext test page for csrf-magic</title>
<?php
$loc = print_javascript('ext', 'http://cdn.sencha.io/ext-4.2.0-gpl/ext-all.js');
?>
</head>
<body>
<h1>Ext test page for csrf-magic</h1>
<p>Using <?php echo $loc; ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "Ext " + Ext.versions.extjs + "\n";
    var callback = function (transport) {
        textarea.value += transport.responseText;
    }
    Ext.Ajax.request({
        url: 'ext.php',
        success: callback,
        params: { ajax: 'yes', foo: 'bar' }
    });
//]]>
</script>
</body>
</html>
