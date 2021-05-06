<?php
function add_user($usr, $pas) {
	global $global_users;
	global $global_passws;
	$global_users[] = $usr;
	$global_passws[] = $pas;
}

$global_users = array();
$global_passws = array();
add_user('temp', 'qwe');
if ( isset($_POST['u']) && isset($_POST['p']) ) {
	$user = $_POST['u'];
	$pass = $_POST['p'];
	$k = array_search($user, $global_users);
	if ( is_int($k) && ($global_passws[$k] == $pass) ) {
		require_once 'http.inc.php';
		require_once 'func.inc.php';
		$http_client = new http(HTTP_V11);
		$http_client->host = 'ogame.org';
		if ( HTTP_STATUS_OK == $http_client->get('/') ) {
			$page = $http_client->get_response_body();
			$http_client->disconnect();
			unset( $http_client );
		} else {
			print( "An error occured while requesting your file !\n" );
			print_r( $http_client );
			die();
		}
		redirect_html_links($page, 'p.php?h=b2dhbWUub3Jn&l=');
		echo $page;
	} else {
		echo '<html><body bgcolor="black" text="black"><center>';
		$err = '<nobr><span style="padding: 0px 10px 0px 10px; color: rgb(%d, %d, %d);">What did you expect ?</span></nobr>';
		for ($r = 255; $r >= 0; $r -= 32)
			for ($g = 255; $g >= 0; $g -= 32)
				for ($b = 255; $b >= 0; $b -= 32)
					echo "\r\n".sprintf($err, $r, $g, $b);
		die("\r\n</center></body></html>");
	}
} else {
?>
<html>
<body bgcolor="black" text="lime">
<form name="loginForm" action="" method="POST" target="_self">
<table border="0" style="position: absolute;width: 220px;left: 50%;margin-left: -110px;top: 25%;">
<tr><td>u53r</td><td align="right"><input type="text" name="u"></td></tr>
<tr><td>p455</td><td align="right"><input type="password" name="p"></td></tr>
<tr><td colspan="2" align="center"><input type="submit" value="Login"></td></tr>
</table>
</form>
</body>
</html>
<?php
}
?>