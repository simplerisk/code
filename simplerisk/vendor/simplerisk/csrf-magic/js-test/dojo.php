<?php require_once 'common.php'; ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Dojo test page for csrf-magic</title>
<?php $loc = print_javascript('dojo/dojo/dojo.js.uncompressed',
    '//ajax.googleapis.com/ajax/libs/dojo/1.9.1/dojo/dojo.js'
) ?>
</head>
<body>
<h1>Dojo test page for csrf-magic</h1>
<p>Using <?php echo $loc ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "Dojo " + dojo.version + ":\n"
    var callback = function (text) {
        textarea.value += text;
    }
    dojo.xhrPost({
        url: "dojo.php",
        load: callback,
        content: {ajax: 'yes', foo: 'bar'}
    });
//]]>
</script>
</body>
</html>
