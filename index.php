<?php
/*if (!isset($_POST['loginsession'])) {
	include("login.php");
	die();
}*/
require_once ('config.inc.php');
require_once ('functions.inc.php');
require_once ('ajax.inc.php');
require_once ('class.visitorcounter.inc.php');

/*$logged=true;$user='??';
if (isset($_POST['loginsession'])) $session=$_POST['loginsession'];
else $logged=false;*/
$logged=logged_in($session,$user,false);

if (!$logged) {
	$session=-1;
/*	include("login.php");
	die();*/
} else {
	//if ($logged===2) redirect2page('http://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']), '/\\').'/',array('loginsession'=>$session),'$logged=2');
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title><?php echo SERVER_NAME; ?></title>
<META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE">
<META NAME="KEYWORDS" CONTENT="mmorpg, mmog, web based, browser based, turn game, free, multiplayer, galaxy, game">
<META NAME="DESCRIPTION" CONTENT="You start out with just one spaceship and turn that into a mighty empire able to defend your colonies">
<META HTTP-EQUIV="Author" CONTENT="TBog">
<meta name="verify-v1" content="i+wvfWy4+e71OoiZPTP8GHd5/xGPdyz6EAkYr5u8abw=" />
<?php
if ($logged) {
?>
<noscript>
<meta http-equiv="refresh" content="5;URL=noscript.html">
<h1>This page is not working without javascript</h1>
<h2>You will be redirected</h2>
</noscript>
<?php
	$xajax->printJavascript();
?>
<script type="text/javascript">
/* <![CDATA[ */
xajax.callback.global.onRequest = function() {
	xajax.$('loading').style.display = 'block';
}
xajax.callback.global.beforeResponseProcessing = function() {
	xajax.$('loading').style.display='none';
}
var lsn = "<?php echo $session; ?>";
var listdisplaymemory = new Object();
var timer_counter = 0;
var timer_handle;
var all_ships_sort = 1;
/* ]]> */
</script>
<script type="text/javascript" src="./inc/functions.js"></script>
<?php
}
?>
<link rel="stylesheet" type="text/css" href="css/index.css">
</head>
<?php
$counter = new VisitorCounter;
$counter->Visitor();
if ($logged){
#if ($_SERVER['REMOTE_ADDR']!="86.104.172.68") die("<body><h1>Game under development ... please wait</h1></body></html>");
?>
<body onload="init()" class="gamebody">
<div id="loading"><img src="img/loading.gif" alt="Loading..." height="100%"></div>
<div id="page_body">
<img id="logo" src="./img/ggg.jpg" alt="<?php echo SERVER_NAME; ?>">
<div id="menu">menu</div>
<div id="details">details</div>
<a href="graph.php" target="_blank"><img id='minimap' src="./img/pixel.bmp" alt="Mini Map"></a>
<div id="menuright"></div>
</div>
<?php
} else {
?>
<body class="firstpage">
<div id="page_body">
<img id="logo" src="./img/gggbig.jpg" alt="<?php echo SERVER_NAME; ?>"><br>
<div style="text-align: right">Please <a href="login.php">log in</a> or create a <a href="login.php?a=new">new account</a>.</div>
<br><i>This is an alpha version ...</i>
<table class="about">
<tr><th class="about"><span class="opaque">What is Galaxy Graph Game ?</span></th><th rowspan="2"><img src="./img/ss01.jpg" width="500"></th></tr>
<tr class="about"><td>
<p>GGGame is a free, browser-based, space combat multiplayer game.
<p>You start out with just one spaceship and turn that into a mighty empire able to defend your colonies.
<p>Create an economic and military infrastructure to support your quest for the next greatest technological achievements.
<p>Will you terrorize the area around you? Or will you strike fear into the hearts of those who attack the helpless?
</td></tr>
</table>
</div>
<?php
}
?>
<div id="tooltip" onClick="locktooltip(false);tooltip()"></div>
<div id="cover"></div>
<div id="popup"></div>
<div id="programmer">game written by TBog<div id="visitor_counter" style="margin:auto"><?php echo $counter->show(); ?></div></div>
</body>
</html>