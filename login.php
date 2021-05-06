<?php
function print_html_header($title){
?>
<html>
<head>
<title><?php echo $title;?></title>
<META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE">
<link rel="stylesheet" type="text/css" href="css/index.css" />
<style type="text/css">
input {
	background-color: #2a2a2a;
	color: #ffd700;
	border-color:#808080;
	filter:progid:DXImageTransform.Microsoft.Gradient(GradientType=0,StartColorStr='#cc808080',EndColorStr='#00444444');
}
</style>
</head>
<body>
<br>
<?php
}
ob_start();
require_once 'functions.inc.php';

if (isset($_GET['a'])) { // daca avem o actiune de facut
	if ($_GET['a']=='logout') { // logout
		logout($_GET['s']);
		redirect2page('http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'/','','logged out');
	}elseif ($_GET['a']=='new') { // new account (register)
		#die("this is a beta geme ... please contact the game master for new accounts<br><span style='color: #000000'>try <b>username:</b> test <b>pass:</b> test</span>");
		if (!isset($_POST['name'])) {
			print_html_header('New Account');
?>
<center>
<table class="about">
<tr><th class="about"><span class="opaque">Registration</span></th></tr>
<tr class="about"><td>
<p>In order to play you only have to enter a username, a password and the name of your first ship.
</td></tr>
</table>
<br>
<table>
<form method="POST" action="http://<?php echo $_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'/login.php?a=new'; ?>">
<tr><td>User name:</td><td><input type="text" name="name"></td></tr>
<tr><td>Ship name:</td><td><input type="text" name="ship"></td></tr>
<tr><td>Password:</td><td><input type="password" name="pass"></td></tr>
<tr><td>Re-type pass:</td><td><input type="password" name="rpass"></td></tr>
<tr><td></td><td><input type="submit" name="submit" value="Create"></td></tr>
</form>
</table></center>
<?php
			die('</body></html>');
		} else {
			print_html_header('Creating user');
			if(get_magic_quotes_gpc()) {
				$name=stripslashes($_POST['name']);
				$shipname=stripslashes($_POST['ship']);
				$pass=stripslashes($_POST['pass']);
				$rpass=stripslashes($_POST['rpass']);
			} else {
				$name=$_POST['name'];
				$shipname=$_POST['ship'];
				$pass=$_POST['pass'];
				$rpass=$_POST['rpass'];
			}
			if (strcmp($pass, $rpass) != 0)
				die('The two passwords are not the same.</body></html>');
			if (strlen($name) < 4)
				die('The username must be at least 4 characters long.</body></html>');
			if (strlen($name) > 20)
				die('The username length must be less then 21 characters.</body></html>');
			db_connect();
			$name = dbesc(substr($name, 0, 30));
			$shipname = dbesc(substr($shipname, 0, 30));
			$pass = dbesc(pass_hash($pass));
			$q=dbq("SELECT count(*) FROM users WHERE name='$name' LIMIT 1");
			list($nr) = mysql_fetch_row($q);
			if ($nr > 0)
				die('Username already exists</body></html>');
			db("INSERT INTO users (name, pass, session, turn_nr, max_turns, credits, last_login)
			VALUES ('$name', '$pass', 'new account', 20, 150, 10000, NOW())");
			$userid = mysql_insert_id();
			if ($userid == false)
				die('Coud not create user</body></html>');
			db("INSERT INTO ships (user_id, node, type, fighters, max_fighters, turrets, shield, max_shield, shield_regen, cargo_bays, mine_speed, upgrades_left, name)
			VALUES ($userid, 0, 1, 5, 10, 5, 5, 10, 1, 50, 7, 1, '$shipname')");
			$shipid = mysql_insert_id();
			db("UPDATE users SET ship=$shipid WHERE id=$userid");
			die('User created<br>Go to the <a href="http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'">login page</a>.</body></html>');
		}
	} elseif ($_GET['a']=='passchg') { // change password
		print_html_header('Change password');
		if(get_magic_quotes_gpc()) {
			$oldpass=stripslashes($_POST['oldpass']);
			$pass=stripslashes($_POST['newpass']);
			$rpass=stripslashes($_POST['rnewpass']);
			$session=stripslashes($_POST['loginsession']);
		} else {
			$oldpass=$_POST['oldpass'];
			$pass=$_POST['newpass'];
			$rpass=$_POST['rnewpass'];
			$session=$_POST['loginsession'];
		}
		db_connect();
		$oldpass = dbesc(pass_hash($oldpass));
		$pass = dbesc(pass_hash($pass));
		$rpass = dbesc(pass_hash($rpass));
		$session = dbesc($session);
		if (strcmp($pass, $rpass) != 0)
			die('The two passwords are not the same');
		$q=dbq("SELECT id FROM users WHERE session='$session' AND pass='$oldpass'");
		list($id) = mysql_fetch_row($q);
		if (!$id)
			die('Wrong old password ?</body></html>');
		db("UPDATE users SET pass='$pass' WHERE id=$id");
		die('Password changed<br>Go to the <a href="http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'">login page</a>.</body></html>');
	}
} elseif (isset($_POST['submit'])) { // daca a apasat pe login
	if(get_magic_quotes_gpc()) {
		$name=stripslashes($_POST['name']);
		$pass=stripslashes($_POST['pass']);
	} else {
		$name=$_POST['name'];
		$pass=$_POST['pass'];
	}
	if (get_user_id($name,$pass)) { // verific daca exista userul in baza de date
		$session=set_session($name,$pass);
		redirect2page('http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'/',array('loginsession'=>$session),'logged in');
	} else {
		//bad username or password
		$login_error=true;
	}
}

$logged=logged_in($session,$name);

print_html_header('Login');

#get_login_cookie($name,$pass);
#if (isset($_POST['loginsession']) && empty($session) ) $session=$_POST['loginsession'];
//if ( !($user_id=get_user_id($user,$pass)) || !($user_id=check_session($session,$user)) ) { // date incorecte
if (!$logged) {
	if (isset($login_error) && $login_error) {
?>
<div class="login_error">The username or password did not check.</div><br>
<?php
	}
?>
<form method="POST" action="http://<?php echo $_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'/login.php' ?>">
<center>
<table>
<tr><td>username:</td><td><input type="text" name="name" /></td></tr>
<tr><td>password:</td><td><input type="password" name="pass" /></td></tr>
<tr><td></td><td><input type="submit" name="submit" value="Login"></td></tr>
</table>
</center>
</form>
<?php 
} else { // logged in
	if ($logged===2) {
		//$ob_data=ob_get_contents();
		ob_end_clean();
		$info='cookie found, loading ...';
		redirect2page('http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\').'/',array('loginsession'=>$session),$info);
	}
	//aici nu o sa mai ajung niciodata, login.php nu primeste niciodata $_POST['loginsession']
	die("aici nu o sa mai ajung niciodata, 'logged_in' ar trebui sa ma fi logat");
?>
You are logged in as <?php echo $user; ?>.<br /><br />
<a href="login.php?a=logout">Logout</a><br /><br />
<form name="play" method="POST" 
	action="http://<?php echo $_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\') ?>/">
<input type="hidden" name="loginsession" value="<?php echo $session; ?>">
<a href="javascript:void(0);" onclick="javascript:document.play.submit()">Play</a>
</form>
<?php
// 	action="http://< ?php echo $_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'],0,strrpos($_SERVER['PHP_SELF'],'/')+1) ? >">
}
?>
</body>
</html>
<?php
ob_end_flush();
?>