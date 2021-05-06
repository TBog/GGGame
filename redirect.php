<html>
<head>
<META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE">
<?php
if (empty($post_data)) {
?>
<noscript>
<meta http-equiv="refresh" content="2;URL=<?php echo $redirect_to?>">
</noscript>
<?php
}
?>
<title>Redirect page</title>
<script type='text/javascript' DEFER="DEFER">
/* <![CDATA[ */
<?php
if (empty($post_data)) {
?>
self.setTimeout("self.location.replace('<?php echo $redirect_to; ?>');",2000);
/*function change_page() {
	//self.location.replace('<?php echo $redirect_to; ?>'); // in IE 6 this does not reload the page
	self.setTimeout("self.location.replace('<?php echo $redirect_to; ?>');",1500);
}*/
<?php
} else {
?>
self.setTimeout("document.foo.submit();",1500);
<?php
}
?>
/* ]]> */
</script>
<link rel="stylesheet" type="text/css" href="css/index.css" />
<style type="text/css">
body {
	color: #808080;
}
input {
	background-color: black;
	color: #808080;
	border-color:#808080;
	filter:progid:DXImageTransform.Microsoft.Gradient(GradientType=0,StartColorStr='#cc808080',EndColorStr='#00444444');
}
a:link, a:visited {
	color: #808080;
	text-decoration: overline underline;
}
a:hover {
	color: white;
	text-decoration: overline underline;
}
</style>
</head>
<body class="firstpage">
<center>
<h2>You shoud be redirected ...</h2>
<?php
if (empty($post_data)) {
?>
If auto-redirect is not working click <a href="<?php echo $redirect_to?>">HERE</a>.
<br />
<?php
} else {
?>
<form name="foo" action="<?php echo $redirect_to;?>" method="post">
<?php
	if (isset($post_data['submit'])) unset($post_data['submit']);
	foreach ($post_data as $k=>$v) echo "<input type='hidden' name='$k' value='$v'>\r\n";
?>
If auto-redirect is not working click <input type="submit" value="HERE">.
</form>
<?php
}
echo '<div class="additional_text">';
#echo $redirect_to."<br>".microtime()."<br>";
echo $additional_text;
echo '</div>';
?>
</center>
</body>
</html>