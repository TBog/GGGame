<html>
<head>
<title>install GGGame</title>
<link rel="stylesheet" type="text/css" href="css/install.css" />
</head>
<body>

<?php
@include 'config.inc.php';
if (defined('GAME_INSTALLED')) {
	echo "game installed";
} elseif (!isset($_POST['submit'])){
	?>

<form method="post">

<center>
<div class="title">database settings</div>
<table class="subtitle">
<tr><td>server:</td><td><input type="text" name="dbserver" value="localhost" /></td></tr>
<tr><td>user:</td><td><input type="text" name="dbuser" value="" /></td></tr>
<tr><td>password:</td><td><input type="text" name="dbpass" value="" /></td></tr>
<tr><td>database name:</td><td><input type="text" name="dbname" value="gggame" /></td></tr>
<tr><td>server name:</td><td><input type="text" name="servername" value="Galaxy Graph Game" /></td></tr>
</table><br />
<div class="title">admin settings</div>
<table class="subtitle">
<tr><td>account name:</td><td><input type="text" name="admin_name" value="admin" /></td></tr>
<tr><td>account pass:</td><td><input type="text" name="admin_pass" value="admin" /></td></tr>
</table><br />
<br />
<input type="submit" name="submit" value="submit" />
</center>

</form>
</body>
</html>
<?php
die();
} else {
	echo "<pre class='debug'>";
	var_export($_POST);

	// encode db pass
	$dbpass=$_POST['dbpass'];
	//$_POST['dbpass']="'.base64_decode('".base64_encode($_POST['dbpass'])."').'";

	echo "</pre>";
	$text="<?php\r\n";
	$cfg = array("DATABASE_HOST"=>'dbserver', "DATABASE"=>'dbname', "DATABASE_USER"=>'dbuser', "DATABASE_PASSWORD"=>'dbpass', "SERVER_NAME"=>'servername');
	foreach ($cfg as $c=>$v) {
		if(get_magic_quotes_gpc())
			$_POST[$v] = stripslashes($_POST[$v]);
		$text.="define('$c', base64_decode('".base64_encode($_POST[$v])."'));\r\n";//"\$cfg['".$v."']='".$_POST[$v]."';\r\n";
	}
	//$text.='$cfg["game_name"]="Galaxy Graph Game";';
	$text.="define('GAME_INSTALLED', true);\r\n";
	$text.="?>";
	
	// connect to DB
	echo "connecing to DB ...<br \>";
	if (!mysql_connect($_POST['dbserver'],$_POST['dbuser'],$dbpass)) die('mysql_connect failed: '.mysql_error());
	if (!mysql_query('CREATE DATABASE '.$_POST['dbname'])) die('coud not create database: '.mysql_error());
	if (!mysql_select_db($_POST['dbname'])) die('coud not select DB');
	echo "creating tables ...<br \>";
	$dbtables = file_get_contents('db.sql');
	if (!mysql_query($dbtables)) die(mysql_error());
	require_once ('functions.inc.php');
	if (!mysql_query("INSERT INTO users (name, pass) VALUES ('".dbesc($_POST['admin_name'])."','".dbesc(pass_hash($_POST['admin_pass']))."')")) die(mysql_error());
	
	file_put_contents('config.inc.php',$text);
}
?>
<center>
<div class="title">Install finished</div>
<br />
<a href="<?php echo substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')+1); ?>">Play</a>
</center>
</body>
</html>