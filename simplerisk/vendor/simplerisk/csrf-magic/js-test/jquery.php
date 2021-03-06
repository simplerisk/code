<?php require_once 'common.php'; ?>
<!DOCTYPE html 
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>jQuery test page for csrf-magic</title>
<?php $loc = print_javascript('jquery', 'http://code.jquery.com/jquery-latest.js') ?>
</head>
<body>
<h1>jQuery test page for csrf-magic</h1>
<p>Using <?php echo $loc ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "jQuery " + jQuery.fn.jquery + "\n";
    var callback = function (data) {
        textarea.value += data;
    }
    jQuery.post('jquery.php', 'ajax=yes&foo=bar', callback, 'text');
    jQuery.post('jquery.php', {ajax: 'yes', foo: 'bar'}, callback, 'text');
//]]>
</script>
</body>
</html>
