<?php require_once 'common.php'; ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Prototype test page for csrf-magic</title>
<?php $loc = print_javascript('prototype', 'https://ajax.googleapis.com/ajax/libs/prototype/1.7.1.0/prototype.js') ?>
</head>
<body>
<h1>Prototype test page for csrf-magic</h1>
<p>Using <?php echo $loc ?></p>
<textarea id="js-output" cols="80" rows="4"></textarea>
<script type="text/javascript">
//<![CDATA[
    var textarea = document.getElementById('js-output');
    textarea.value = "Prototype " + Prototype.Version + ":\n";
    var callback = function (transport) {
        textarea.value += transport.responseText;
    }
    new Ajax.Request('prototype.php',
    {
        parameters: {
            ajax: 'yes',
            foo: 'bar'
        },
        onSuccess: callback
    });
    new Ajax.Request('prototype.php',
    {
        parameters: 'ajax=yes&foo=bar',
        onSuccess: callback
    });
//]]>
</script>
</body>
</html>
