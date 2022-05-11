<?php 

function csrf_startup() {
    csrf_conf('rewrite-js', 'csrf-magic.js');
    if (isset($_POST['ajax'])) csrf_conf('rewrite', false);
}
include dirname(__FILE__) . '/csrf-magic.php';

// Handle an AJAX request
if (isset($_POST['ajax'])) {
    header('Content-type: text/xml;charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8" ?><response>Good!</response>';
    exit;
}

?>
<html lang="en">
<head>
<title>Test page for csrf-magic</title>
</head>
<body>
<h1>Test page for csrf-magic</h1>
<p>
  This page might be vulnerable to CSRF, but never fear: csrf-magic is here!
  Close by: <a href="js-test/all.php">tests for Internet Explorer support with
  all the major JavaScript libraries!</a>
</p>
<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') { ?>
<p>Post data:</p>
<pre>
<?php echo htmlspecialchars(var_export($_POST, true)); ?>
</pre>
<?php } ?>
<form action="" method="post">
  Form field: <input type="text" name="foobar" /><br />
  <input type="submit" value="Submit" />
</form>
<FORM METHOD = "POST" ACTION="">
  Another form field! <INPUT TYPE="TEXT" NAME="BARFOO" /><BR />
  <INPUT TYPE="SUBMIT" value="Submit 2" />
</FORM>
<form action="" method="post">
  This form fails CSRF validation (we cheated and overrode the CSRF token
  later in the form.) <br />
  <input type="text" name="foobar[2]" />
  <input type="submit" name="__csrf_magic" value="invalid" />
</form>
<form action="" method="get">
  This form uses GET and is thus not protected.
  <input type="submit" name="foo" value="Submit" />
</form>
<p>
  How about some JavaScript?
</p>
<script type="text/javascript">
//<![CDATA[
    document.writeln('<for'+'m action="" method="post">Dynamically generated form: <input type="submit" /></form>');
//]]>
</script>
<textarea id="js-output" cols="80" rows="2"></textarea>
<script type="text/javascript">
//<![CDATA[
    params = 'ajax=yes&var=foo';
    var http = new CsrfMagic();
    http.open('POST', 'test.php', true);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.setRequestHeader("Content-length", params.length);
    http.setRequestHeader("Connection", "close");
    http.onreadystatechange = function () {
        document.getElementById('js-output').value = 'Ajax: ' + http.responseText;
    }
    http.send(params);
//]]>
</script>
</body>
</html>
