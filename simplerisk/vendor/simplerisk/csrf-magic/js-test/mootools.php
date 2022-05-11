<?php require_once 'common.php'; ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>MooTools test page for csrf-magic</title>
<?php $loc = print_javascript('mootools',
    '//ajax.googleapis.com/ajax/libs/mootools/1.4.5/mootools-yui-compressed.js'
) ?>
</head>
<body>
<h1>MooTools test page for csrf-magic</h1>
<p>Using <?php echo $loc ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "MooTools " + MooTools.version + ":\n"
    var callback = function (text) {
        textarea.value += text;
    }
    var request = new Request(
    {
        url: 'mootools.php'
    });
    request.addEvent('onSuccess', callback);
    request.send('ajax=yes&foo=bar');
//]]>
</script>
</body>
</html>
