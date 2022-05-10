<?php if (!session_id()) session_start(); ?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>All Javascript tests for csrf-magic</title>
</head>
<body>
<h1>All Javascript tests for csrf-magic</h1>
<?php
$dh = opendir('.');
while (($file = readdir($dh)) !== false) {
    if (strrchr($file, '.') !== '.php') continue;
    if ($file === 'all.php' || $file === 'common.php') continue;
?>
<iframe src="<?php echo $file; ?>" width="100%" height="180"></iframe>
<?php } ?>
</body>
</html>
