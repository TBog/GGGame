<?php
$error_report = E_ALL | E_NOTICE;
if ( (@constant('E_STRICT')) && ($error_report & E_STRICT) )
	$error_report = $error_report ^ E_STRICT;
error_reporting($error_report);

require_once ("config.inc.php");

/***********************************************************************************************************************************************************
	CONSTANTS
***********************************************************************************************************************************************************/
// utilities
define('SHIP_SHOP',						bindec('00000001'));
define('AUCTION',						bindec('00000010'));
define('EQUIPMENT_SHOP',				bindec('00000100'));
define('UPGRADE_SHOP',					bindec('00001000'));
define('COLONIST_SHOP',					bindec('00010000'));
define('BOUNTY_CENTER',					bindec('00100000'));
define('STAR_PORT',						bindec('01000000'));
define('UTILITY_FLAGS',					bindec('01111111')); // mask
// messages
define('DEFAULT_MSG',					bindec('00000001'));
define('INBOX',							bindec('00000010'));
define('OUTBOX',						bindec('00000100'));
define('NOTES',							bindec('00001000'));
// mine_mode, cargo type, starport
define('NOT_MINING',					bindec('00000000'));
// cargo type, starport, mine_mode
define('METAL',							bindec('00000001'));
define('ANTIMATTER',					bindec('00000010'));
// cargo type, starport
define('ORGANICS',						bindec('00000100'));
// cargo type
define('COLONISTS',						bindec('00001000'));
// not cargo type for ships (but can be transfer the same to and from planets)
define('FIGHTERS',						bindec('00010000'));
// cargo direction - this shoud not have the same flag value with any of the cargo types
define('_2FLEET',						bindec('00100000'));
define('_2PLANET',						bindec('01000000'));
// mine span, starport - this shoud not have the same flag value with any of the mine_mode, transaction type
define('FLEET',					bindec('0000010000000000'));
define('SHIP',					bindec('0000100000000000'));
// confirmed or not (for buying stuff)
define('CONFIRM',				bindec('0000000100000000'));
define('CONFIRMED',				bindec('0000001000000000'));
// transaction type
define('BUY',					bindec('0001000000000000'));
define('SELL',					bindec('0010000000000000'));
// bombs / dedeploy devices, equipment
define('GENESIS_DEVICE',		bindec('0100000000000000'));

/***********************************************************************************************************************************************************
	TRANSLATE - language file
***********************************************************************************************************************************************************/
function text_translate(&$string,$session,$userid=null){
	db_connect();
	if (is_null($userid)) $q=dbq("SELECT translate FROM users WHERE session='".dbesc($session)."'");
	else $q=dbq("SELECT translate FROM users WHERE id=$userid");
	list($lang)=mysql_fetch_row($q);
	// load language file
	$trans=include("./lang/$lang.php");
	// transform
	foreach ($trans as $code=>$text) {
		$string=str_replace('^'.$code.'^',$text,$string);
	}
}

/***********************************************************************************************************************************************************
	LOGIN / LOGOUT
***********************************************************************************************************************************************************/
function logout($session){
	db_connect();
	setcookie('login','',0);
	db("UPDATE users SET session='logged out' WHERE session='".dbesc($session)."'");
}
function get_user_id($name, $pass) {
	db_connect();
	$q=mysql_query("SELECT id FROM users WHERE name='".dbesc($name)."' AND pass='".dbesc(pass_hash($pass))."'");
	if (mysql_num_rows($q)==0) return false;
	$row=mysql_fetch_row($q);
	return $row[0];
}
function get_user_name($loginsession, &$name) {
	db_connect();
	$q=dbq("SELECT id, name FROM users WHERE session='".dbesc($loginsession)."'");
	if (mysql_num_rows($q)==0) return false;
	$row=mysql_fetch_row($q);
	$name=$row[1];
	return $row[0];
}
/*function get_user_info($id){
	db_connect();
	db("SELECT turn_nr, credits, ships.name AS ship_name FROM users, ships WHERE users.id=".(int)$id." AND users.ship=ships.id");
	$result=dbr(1);
	return $result[0];
}*/
function set_session($name, $pass) {
	require_once ('class.visitorcounter.inc.php');
	$counter = new VisitorCounter;
	$counter->Visitor(); // to be sure that the user IP has been logged
	db_connect();
	do {
		db('SELECT UUID()'); // format: aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee
		list($loginsession)=dbrow();
		$q=dbq("SELECT session FROM users WHERE session='".dbesc($loginsession)."'");
	} while (mysql_num_rows($q)!=0);

	// checking
	$_SERVER['REMOTE_ADDR'] = isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'no ip found';
	
	db("UPDATE users SET session='".dbesc($loginsession)."',last_ip='".dbesc($_SERVER['REMOTE_ADDR'])."' , last_login=NOW() WHERE name='".dbesc($name)."' AND pass='".dbesc(pass_hash($pass))."'");
	if (mysql_affected_rows()==0) return false;
	db("SELECT id FROM users WHERE session='".dbesc($loginsession)."'");
	list($user_id) = dbrow();
	db("SELECT id FROM ".$counter->counter_table." WHERE ip='".dbesc($_SERVER['REMOTE_ADDR'])."'");
	list($ip_id) = dbrow();
	db("SELECT COUNT(*) FROM users_to_iplist WHERE user_id=$user_id AND ip_id=$ip_id");
	list($cnt) = dbrow();
	if (!$cnt) db("INSERT INTO users_to_iplist (user_id, ip_id) VALUES($user_id, $ip_id)");
	
	setcookie('login',base64_encode($loginsession), time()+60*60*24*2);
	return $loginsession;
}
function get_login_cookie () {
	if ( (!isset($_COOKIE['login'])) || (empty($_COOKIE['login'])) ) return false;
	if (ini_get('magic_quotes_gpc')) $COOKIElogin=stripslashes($_COOKIE['login']);
	else $COOKIElogin=$_COOKIE['login'];
	return base64_decode($COOKIElogin);
}
function logged_in(&$session,&$name,$log_in=true) {
	if (isset($_POST['loginsession'])) {
		if(get_magic_quotes_gpc())
			$session = stripslashes($_POST['loginsession']);
		else
			$session = $_POST['loginsession'];
	} else
		$session=false;
	$exit = false;
	if (empty($session)) {
		//return false;
		if (!$log_in) 
			return false;
		$session = get_login_cookie();
		$exit = 2;
/*		if (get_login_cookie($name,$pass)) {
			if (get_user_id($name,$pass)) {
				#echo "setez sesiune pentru $name, $pass";
				$session=set_session($name,$pass);
				#echo "\r\nam setat sesiunea $session\r\n";
				return 2;
			} else return false;
		} else return false;*/
	}
	if (get_user_name($session,$name))
		$exit = $exit?$exit:true;
	else
		$exit = false;
	return $exit;
}
/***********************************************************************************************************************************************************
	OTHER FUNCTIONS
***********************************************************************************************************************************************************/
function clamp(&$val, $min, $max) {
	return $val = max( $min, min($max, $val) );
}
function query_ship_cargo() {
	if (func_num_args() < 1)
		$as_what = '';
	else
		$as_what = ' AS '.func_get_arg(0);
	return "(ships.colonists + ships.metal + ships.antimatter + ships.organics + 10 * ships.genesis)".$as_what;
}
function s_var_dump($info, $pre = false) {
	ob_start();
	if ($pre)
		echo '<pre>';
	var_dump($info);
	if ($pre)
		echo "</pre>";
	$txt = ob_get_contents();
	ob_end_clean();
	return $txt;
}
function pass_hash($pass) {
	if ( defined('PASS_CODE_SHA512') )
		return hash('sha512',$pass);
	else {
		require_once ('sha256.inc.php');
		return sha256($pass);
	}
}
function format_message_output($msg) {
	return str_replace(array("\r\n", "\n", "\r"), '<br>', $msg);
}
function nr_format($nr) {
	$decimals = is_float($nr)?2:0;
	return number_format($nr, $decimals, '.', ' ');
}
function get_map_array(&$graph){
	db_connect();
	db("SELECT id,name,x,y,link1,link2,link3,link4,link5,link6 FROM map");
	$graph=array();
	foreach (dbr(1) as $n) {
		$graph[$n['id']]=array('name'=>$n['name'],'x'=>$n['x'],'y'=>$n['y'],'links'=>array());
		for ($i=1;$i<=6;$i+=1) if (isset($n['link'.$i])) $graph[$n['id']]['links'][]=(int)$n['link'.$i];
	}
}
function clamp_colonists(&$colonists, &$col_fighters, &$col_organics) {
	$colonists		= (int)max(0, min($colonists, 2147483647 - 1));
	$col_organics	= (int)max(0, min($col_organics, $colonists * 2, 2147483647 - 1));
	$col_fighters	= (int)max(0, min($col_fighters, $colonists * 2, 2147483647 - 1));
	
	$sum = 0;
	$tmp = array(&$col_fighters, &$col_organics);
	shuffle($tmp);
	foreach ($tmp as $v)
		$sum += $v;
	while ($colonists < $sum) {
		$X = $sum - $colonists;
		$X = max(1, (int)ceil( (float)$X / (float)count($tmp) ));
		foreach ($tmp as $k=>$v) {
			$tmp[$k] = max(0, $v - $X);
			$sum = $sum - $v + $tmp[$k];
			if ($colonists >= $sum) {
				$tmp[$k] += $colonists - $sum;
				$sum += $colonists - $sum;
				break;
			}
		}
	}
}
function print_neighbors_links($node,$graph='') {
	if (empty($graph)) get_map_array($graph);
	$txt='';
	foreach ($graph[$node]['links'] as $v) $txt.="<input type='button' value='$v' class='warpbutton' onclick='xajax_move_to_node(lsn, $v);'> ";
	return $txt;
}
function print_change_pass($session){
	$txt=<<<end_of_text
<form method="POST" action="login.php?a=passchg">
<table border=0>
<tr><td>current password:<td><input type="password" name="oldpass" value=""><br>
<tr><td>new password:<td><input type="password" name="newpass" value=""><br>
<tr><td>verify new pass:<td><input type="password" name="rnewpass" value=""><br>
<input type="hidden" name="loginsession" value="$session">
<tr><td colspan=2 align=center><input type="submit" value="Change">
</table>
</form>
end_of_text;
	return $txt;
}
function print_autowarp($id,$path=null) {
	if (is_null($path)) {
		db_connect();
		$q=dbq("SELECT autowarp, node FROM users, ships WHERE users.id=$id AND users.ship=ships.id");
		$row=mysql_fetch_row($q);
		$path=$row[0];
		$node=(int)$row[1];
	}
	if (strlen($path)==0){
		$autowarp='<input type="button" value="set auto-warp" class="autowarpbutton" onclick="xajax_set_autowarp(lsn,document.getElementById(\'autowarptxt\').value)"> to: <input type="text" id="autowarptxt" value="" maxlength="3" onKeyPress="return numbersonly(event)"> <i>(costs 1 turn to generate)</i>';
		$autowarpon = false;
	} else {
		$path=explode(',',$path);
		$autowarp="<input type='button' value='".array_shift($path)."' class='warpbutton' onclick='xajax_autowarp_to_node(lsn);'>";
		foreach ($path as $node) $autowarp.=' - '.$node;
		$autowarp.=' <input type="button" value="cancel auto warp" class="autowarpbutton" onclick=\'xajax_set_autowarp(lsn,"cancel")\'>';
		$autowarpon = true;
	}
	return array($autowarp, $autowarpon);
}
function print_planets($node,$id) {
	db_connect();
	db("SELECT id, name, user_id FROM planets WHERE node=$node");
	$planets=dbr(1);
	$planetnr=count($planets);
	$txt='';
	if ($planetnr==0) $txt.='There are no planets here.';
	elseif ($planetnr==1) {
		$txt.='There is a planet in this Solar System named <b>'.$planets[0]['name'].'</b>.<br>You ';
		if ($planets[0]['user_id']==$id) $txt.='own ';
		else $txt.='don\'t own ';
		$txt.="this planet. <input type='button' value='Land' class='planetaction' onclick='xajax_show_planet(".$planets[0]['id'].",lsn)'><br>";
	} else {
		$ownplanets=array();
		$otherplanets=array();
		foreach ($planets as $planet) 
			if ($planet['user_id']==$id) $ownplanets[]=$planet;
			else $otherplanets[]=$planet;
		if (count($ownplanets)>0) {
			$txt.='Planets that you own:<br>';
			foreach ($ownplanets as $planet) $txt.='<b>'.$planet['name']."</b> - <input type='button' value='Land' class='planetaction' onclick='xajax_show_planet($planet[id],lsn)'><br>";
		}
		if (count($otherplanets)>0) {
			$txt.='Other planets:<br>';
			foreach ($otherplanets as $planet) $txt.='<b>'.$planet['name']."</b> - <input type='button' value='Land' class='planetaction' onclick='xajax_show_planet($planet[id],lsn)'><br>";
		}
	}
	return $txt;
}
function print_planet_details($planetid,$userid) {
	$planetid=(int)$planetid;
	db_connect();
	$q=dbq("SELECT map.name AS sysname, node, user_id, image, planets.name, colonists, fighters, planets.metal, planets.antimatter, planets.character, organics, col_fighters, col_organics 
	FROM planets, map WHERE planets.id=$planetid AND planets.node=map.id");
	$planet = mysql_fetch_assoc($q);
	$txt="<img src='./img/planets/$planet[image]' class='planetimage'>";
	if ($userid!=$planet['user_id']) {
		$q=dbq("SELECT name FROM users WHERE id=$planet[user_id]");
		$owner=mysql_fetch_assoc($q);
		$txt.="This is planet <b>$planet[name]</b><br>Solar System <b>$planet[node]</b> - <b>$planet[sysname]</b><br>The owner is <b>$owner[name]</b><br>";
	} else {
		$transfer_buttons ='<input type="button" class="planetaction" value="to fleet" onclick="xajax_transfer(lsn,'.$planetid.',%u+'._2FLEET.')"> ';
		$transfer_buttons.='<input type="button" class="planetaction" value="from fleet" onclick="xajax_transfer(lsn,'.$planetid.',%1$u+'._2PLANET.')">';
	
		$txt.="Welcome on planet <b>$planet[name]</b><br>Solar System <b>$planet[node]</b> - <b>$planet[sysname]</b><br>You are the owner of this planet.<br>";
		$txt.='<input type="button" value="rename planet" class="planetaction" onclick="xajax_rename_planet('.$planetid.',lsn,document.getElementById(\'planetnametxt\').value)"> to: <input type="text" id="planetnametxt" value="" size="10"><br>';
		$txt.='<table class="planet">';
		$txt.='<tr><th class="planet"></th><th class="planet"></th><th class="planet">Transfer direction</th></tr>';
		$txt.="<tr><td class=\"planet\">Colonists</td><td class=\"planet\">".nr_format($planet['colonists'])."</td><td class=\"planet\">".sprintf($transfer_buttons, COLONISTS)."</td></tr>";
		//$txt.="<tr><td>Tax</td><td><input type='text' class='planettext' id='tax' value='$planet[tax]' size='2' maxlength='2' onKeyPress=\"return numbersonly(event)\">%</td><td><input type='button' class='planetaction' value='Set' onclick='xajax_set_tax(lsn,$planetid,document.getElementById(\"tax\").value)'></td></tr>";
		$txt.="<tr><td class=\"planet\">Fighters</td><td class=\"planet\">".nr_format($planet['fighters'])."</td><td class=\"planet\">".sprintf($transfer_buttons, FIGHTERS)."</td></tr>";
		$txt.="<tr><td class=\"planet\">metal</td><td class=\"planet\">".nr_format($planet['metal'])."</td><td class=\"planet\">".sprintf($transfer_buttons, METAL)."</td></tr>";
		$txt.="<tr><td class=\"planet\">antimatter</td><td class=\"planet\">".nr_format($planet['antimatter'])."</td><td class=\"planet\">".sprintf($transfer_buttons, ANTIMATTER)."</td></tr>";
		$txt.="<tr><td class=\"planet\">organics</td><td class=\"planet\">".nr_format($planet['organics'])."</td><td class=\"planet\">".sprintf($transfer_buttons, ORGANICS)."</td></tr>";
		$txt.='</table>';
		$txt.='<table class="planet_colonists">';
		$txt.='<tr><th class="planet_colonists">Colonists</th><td class="planet_colonists"><input type="button" class="planetaction" value="Set" onClick="xajax_set_colonists(lsn, '.$planetid.', document.getElementById(\'col_fighters\').value, document.getElementById(\'col_organics\').value)"></td></tr>';
		$txt.='<tr><th class="planet_colonists">working on fighters</th><td class="planet_colonists"><input type="text" id="col_fighters" onKeyUp="colonists('.$planet['colonists'].')" value="'.$planet['col_fighters'].'" onKeyPress="return numbersonly(event)" size="4"></td></tr>';
		$txt.='<tr><th class="planet_colonists">makeing organics</th><td class="planet_colonists"><input type="text" id="col_organics" onKeyUp="colonists('.$planet['colonists'].')" value="'.$planet['col_organics'].'" onKeyPress="return numbersonly(event)" size="4"></td></tr>';
		$txt.='<tr><th class="planet_colonists">idle</th><td class="planet_colonists"><div id="col_idle">'.nr_format($planet['colonists'] - $planet['col_organics'] - $planet['col_fighters']).'</div></td></tr>';
		$txt.='<tr><th class="planet_colonists">total</th><td class="planet_colonists">'.nr_format($planet['colonists']).'</td></tr>';
		$txt.='</table>';
		$txt.="The fighters on this planet are <b>$planet[character]</b>";
	}
	return $txt;
}
function print_ships($node,$userid,$translate=true) {
	$node=(int)$node;
	$userid=(int)$userid;
	db_connect();
	$q=dbq("SELECT ship FROM users WHERE id=$userid");
	list($currentship) = mysql_fetch_row($q);
	db("SELECT type, towed_by, ships.name AS shipname, ship_types.name AS typename, ships.id AS ship_id, abvr, ships.user_id, ships.fighters, users.name AS username 
	FROM ships, ship_types, users
	WHERE node=$node AND type=ship_types.id AND users.id=ships.user_id AND (users.id<>$userid OR ships.id<>users.ship)
	ORDER BY type, shipname, ship_id");

	$ships=array('own'=>array(),'other'=>array());
	foreach (dbr(1) as $ship) 
		if ($ship['user_id']==$userid)
			$ships['own'][$ship['type']][]=$ship;
		else
			$ships['other'][$ship['user_id']][]=$ship;
	
	$txt='';
	
	ob_start();
	echo "<div style='float: left;'><pre>ships:\r\n";
	print_r($ships);
	echo "</pre></div>";
	#$txt.=ob_get_contents();
	ob_end_clean();
	//print own ships
	if (count($ships['own']) > 0) {
		$txt.='<table border="0" class="expandlist"><tr><td colspan="2">^Your ships^:</td></tr>';
		foreach ($ships['own'] as $shiptype) {
			$count=count($shiptype);
			$id='ownshiptype'.$shiptype[0]['type'];
			$txt.='<tr><td class="expandbutton"><input type="button" class="expand" id="'.$id.'button" onclick="xchg_list_display(\''.$id.'\')"></td><td>'.$count.'<b> ^'.$shiptype[0]['typename'].'^</b>'.($count>1?'s':'');
			$txt.='<div class="shiplist" id="'.$id.'">';
			$tow = $release = 0;
			foreach ($shiptype as $ship) {
				$txt.="$ship[shipname] <i>($ship[fighters] fighters)</i> ";
				if ($ship['towed_by']==$currentship)
				{
					$txt.='<input type="button" value="^release^" class="shipaction" onclick="xajax_ship_release(lsn,'.$ship['ship_id'].')">';
					$release += 1;
				} else {
					$txt.='<input type="button" value="^tow^" class="shipaction" onclick="xajax_ship_tow(lsn,'.$ship['ship_id'].')">';
					$tow += 1;
				}
				$txt.=' - <input type="button" value="^command^" class="shipaction" onclick="xajax_ship_command(lsn,'.$ship['ship_id'].')">';
				$txt.='<br>';
			}
			if ($tow) {
				$txt.='<input type="button" value="^tow all^" class="shipaction" onclick="xajax_ship_tow(lsn,'.$shiptype[0]['type'].', 0)">';
				if ($release)
					$txt.=' - ';
			}
			if ($release)
				$txt.='<input type="button" value="^release all^" class="shipaction" onclick="xajax_ship_release(lsn,'.$shiptype[0]['type'].', 0)">';
			$txt.='</div></td></tr>';
		}
		$txt.='</table>';
	}
	else $txt.='^The only ship you own in this system is the one you command^.';
	$txt.='<br>';
	//print other ships
	if (count($ships['other'])>0) {
		$txt.='<table border="0" class="expandlist"><tr><td colspan="2">^Other ships^:</td></tr>';
		foreach ($ships['other'] as $shipowner) {
			$count=count($shipowner);
			$id='othershiptype'.$shipowner[0]['user_id'];
			$txt.='<tr><td class="expandbutton"><input type="button" class="expand" id="'.$id.'button" onclick="xchg_list_display(\''.$id.'\')"></td><td><b> '.$shipowner[0]['username']."</b> <i>($count ship".($count>1?'s':'').')</i>';
			$txt.='<div class="shiplist" id="'.$id.'">';
			foreach ($shipowner as $ship) {
				$txt.="$ship[shipname] - <i>$ship[typename]</i> - <i>($ship[fighters] fighters)</i>";
				if ($node != 0)
					$txt.=" <input type='button' value='^attack^' class='shipaction' onclick='xajax_normal_attack(lsn,$ship[ship_id])'>";
				$txt.="<br>";
			}
			$txt.='</div></td></tr>';
		}
		$txt.='</table>';
	}
	else $txt.='^There are no other ships except yours^.';
	if ($translate) text_translate($txt,null,$userid);
	return $txt;
}
function print_utilities($node) {
	db_connect();
	$q=dbq("SELECT utilities FROM map WHERE map.id=".(int)$node);
	list($util)=mysql_fetch_row($q);
	$txt='';
	$button_format = '<input type="button" value="^%1$s^" class="shipaction" onclick="xajax_show_utility(lsn,\'%1$s\')"> ';
	if ($util & SHIP_SHOP) $txt.=sprintf($button_format,'SHIP_SHOP');
	if ($util & EQUIPMENT_SHOP) $txt.=sprintf($button_format,'EQUIPMENT_SHOP');
	if ($util & STAR_PORT) $txt.=sprintf($button_format,'STAR_PORT');
	// this is needed in case there is no utility (no double line)
	//if (!empty($txt)) $txt='<hr>'.substr($txt,0,-4); // use this if there is <br> after buttons
	if (!empty($txt)) $txt='<hr>'.$txt;
	return $txt;
}
function print_ship_details($shipid) { // this is not used ATM
	$q = dbq("SELECT * FROM ships WHERE id=$shipid");
	$ship = mysql_fetch_assoc($q);
	$txt = '<table>';
	foreach ($ship as $info=>$val)
		$txt.="<tr><td>$info<td>$val</tr>";
	$txt.='</table';
	return str_replace("'","\'",$txt);
}
function print_ship_type_details($ship) {
	// if $ship is not array, consider it the ID of the ship type
	if (!is_array($ship)) {
		$q = dbq("SELECT * FROM ship_types WHERE id=$ship");
		$ship = mysql_fetch_assoc($q);
	}
	$txt = '<table>';
	foreach ($ship as $info=>$val)
		$txt.="<tr><td>$info<td>$val</tr>";
	$txt.='</table>';
	return $txt;
}
function print_utility_details($session, $utility) {
	db_connect();
	$q=dbq("SELECT map.utilities, users.id, users.ship FROM map, ships, users WHERE users.session='".dbesc($session)."'AND ships.id=users.ship AND map.id=ships.node");
	list($utilities, $userid, $commanded)=mysql_fetch_row($q);
	db("SELECT * FROM ship_types");
	$ships = dbr(1);
	if (!($utilities & $utility)) return 'you suck'; // user tried to enter a utility that does not exist on the planet
	$txt='';
	switch ($utility) {
		case SHIP_SHOP:
			$txt.='Spaceship shop<hr>';
			$txt.='<table class="shop">';
			// add ships to the parameters of the (buy_all) xajax function
			$buyallbutton='xajax_buy_ships(lsn';
			foreach ($ships as $k=>$ship) $buyallbutton.=", $ship[id], document.getElementById('ship$k').value, document.getElementById('number$k').value";
			$buyallbutton.=','.CONFIRM.')';
			// header
			$txt.='<tr><th class="shop">image</th><th class="shop">type</th><th class="shop">price</th><th class="shop">ship name</th><th class="shop">quantity</th><th class="shop"><input type="button" value="Buy all" onclick="'.$buyallbutton.'"></th><th class="shop">cost</th></tr>';
			//ships
			foreach ($ships as $k=>$ship) {
				$txt.="<tr><td class='shop'><img src='$ship[image]'></td>";
				$txt.="<td class='shop'>$ship[name]<br><s onMouseover=\"tooltip('".str_replace("'", "\'", print_ship_type_details($ship))."')\" onClick='locktooltip(true)'>(ship details)</s></td>";
				$txt.="<td class='shop'>$ship[price]</td>";
				$txt.="<td class='shop'><input id='ship$k' type='text' value='$ship[abvr]' size='10'></td>";
				$txt.="<td class='shop'><input id='number$k' type='text' name='quantity' value='0' maxlength='3' size='2' onKeyPress=\"return numbersonly(event)\" onKeyUp=\"calculate(this.value,$ship[price],'cost$k','costtotal')\"></td>";
				$txt.="<td class='shop'><input type='button' value='Buy' onclick=\"xajax_buy_ships(lsn, $ship[id], document.getElementById('ship$k').value, document.getElementById('number$k').value,".CONFIRM.")\"></td>";
				$txt.="<td class='shop'><div id='cost$k' class='shipcost'>0</div></td></tr>";
			}
			// footer
			$txt.='<tr><th class="shop"></th><th class="shop"></th><th class="shop"></th><th class="shop"></th><th class="shop"></th><th class="shop"><input type="button" value="Buy all" onclick="'.$buyallbutton.'"></th><th class="shop"><div id="costtotal" class="shipcost">0</div></th></tr>';
			$txt.='</table>';
			break;
		case EQUIPMENT_SHOP:
			db("SELECT variable, value FROM config");
			$cfg = array();
			foreach (dbr() as $var)
				$cfg[$var[0]] = $var[1];
			$button	= "<input type='button' class='buybutton' value='%s' onclick=\"xajax_buysell_resources(lsn,%u,document.getElementById('number%s').value,%u)\">";
			$input	= '<input id="number%1$s" type="text" name="quantity" value="0" maxlength="4" size="3" onKeyPress=\'return numbersonly(event)\' onKeyUp=\'calculate(this.value,%2$d,"cost%1$ssell","hidden_text")\'>';
			$cost	= '<div id="cost%1$ssell" class="shipcost">0</div>';

			$txt.='Equipment and colonists shop<hr>';
			$txt.='Here you can buy fighters for your ships, buy bombs and genesis devices<br>';
			$txt.='<table class="shop">';
			$txt.="<tr><th class='shop'>Resource</th>";
			$txt.="<th class='shop'>Sell price</th>";
			$txt.="<th class='shop'>Quantity</td>";
			$txt.="<th class='shop'></th>";
			$txt.="<th class='shop'>cost</th>";
			
			$txt.="<tr><td class='shop'>Colonists</td>";
			$txt.="<td class='shop'>$cfg[colonists_sell_price]</td>";
			$txt.="<td class='shop'>".sprintf($input, 'colonists', $cfg['colonists_sell_price'])."</td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', COLONISTS, 'colonists', CONFIRM | BUY | SHIP | $utility)."</td>";
			$txt.="<td class='shop'>".sprintf($cost, 'colonists')."</td>";
			
			$txt.="<tr><td class='shop'>Fighters</td>";
			$txt.="<td class='shop'>$cfg[fighters_sell_price]</td>";
			$txt.="<td class='shop'>".sprintf($input, 'fighters', $cfg['fighters_sell_price'])."</td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', FIGHTERS, 'fighters', CONFIRM | BUY | SHIP | $utility)."</td>";
			$txt.="<td class='shop'>".sprintf($cost, 'fighters')."</td>";
			
			$txt.="<tr><td class='shop'>Genesis device</td>";
			$txt.="<td class='shop'>$cfg[genesis_sell_price]</td>";
			$txt.="<td class='shop'>".sprintf($input, 'genesis', $cfg['genesis_sell_price'])."</td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', GENESIS_DEVICE, 'genesis', CONFIRM | BUY | SHIP | $utility)."</td>";
			$txt.="<td class='shop'>".sprintf($cost, 'genesis')."</td>";

			$txt.='<div id="hidden_text" style="display:none">';
			break;
		case STAR_PORT:
			db("SELECT variable, value FROM config");
			$cfg = array();
			foreach (dbr() as $var)
				$cfg[$var[0]] = $var[1];
			db("SELECT ships.id, (ships.cargo_bays - ".query_ship_cargo().") AS free_cargo_bays FROM ships WHERE (id=$commanded OR towed_by=$commanded) AND user_id=$userid");
			$total_free_bays = $current_free_bays = 0;
			foreach (dbr() as $val) {
				if ($val[0] == $commanded)
					$current_free_bays = $val[1];
				$total_free_bays += $val[1];
			}
			$button = "<input type='button' class='buybutton' value='%s' onclick=\"xajax_buysell_resources(lsn,%u,document.getElementById('number%s').value,%u)\">";
			
			$txt.='Starport<hr>';
			$txt.='Here you can buy and sell resouces';
			$txt.='<table class="shop">';
			$txt.="<tr><th class='shop'>Resource</th>";
			$txt.="<th class='shop'>Sell price</th>";
			$txt.="<th class='shop'>Buy price</th>";
			$txt.="<th class='shop'>Quantity</td>";
			$txt.="<th class='shop'></th>";
			$txt.="<th class='shop'>sell cost</th>";
			$txt.="<th class='shop'>buy cost</th></tr>";
			
			$txt.="<tr><td class='shop'>Metal</td>";
			$txt.="<td class='shop'>$cfg[metal_sell_price]</td>";
			$txt.="<td class='shop'>$cfg[metal_buy_price]</td>";
			$txt.="<td class='shop'><input id='numbermetal' type='text' name='quantity' value='0' maxlength='4' size='3' onKeyPress=\"return numbersonly(event)\" onKeyUp=\"calculate(this.value,$cfg[metal_sell_price],'costmetalsell','costtotalsell');calculate(this.value,$cfg[metal_buy_price],'costmetalbuy','costtotalbuy');calculate(this.value,1,'metalquantity','totalquantity')\"><div id='metalquantity' style='display:none'></td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', METAL, 'metal', CONFIRM | BUY | SHIP | $utility)." ".sprintf($button, 'Sell', METAL, 'metal', CONFIRM | SELL | SHIP | $utility)."</td>";
			$txt.="<td class='shop'><div id='costmetalsell' class='shipcost'>0</div></td>";
			$txt.="<td class='shop'><div id='costmetalbuy' class='shipcost'>0</div></td></tr>";
			
			$txt.="<tr><td class='shop'>Antimatter</td>";
			$txt.="<td class='shop'>$cfg[antimatter_sell_price]</td>";
			$txt.="<td class='shop'>$cfg[antimatter_buy_price]</td>";
			$txt.="<td class='shop'><input id='numberantimatter' type='text' name='quantity' value='0' maxlength='4' size='3' onKeyPress=\"return numbersonly(event)\" onKeyUp=\"calculate(this.value,$cfg[antimatter_sell_price],'costantimattersell','costtotalsell');calculate(this.value,$cfg[antimatter_buy_price],'costantimatterbuy','costtotalbuy');calculate(this.value,1,'antimatterquantity','totalquantity')\"><div id='antimatterquantity' style='display:none'></td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', ANTIMATTER, 'antimatter', CONFIRM | BUY | SHIP | $utility)." ".sprintf($button, 'Sell', ANTIMATTER, 'antimatter', CONFIRM | SELL | SHIP | $utility)."</td>";
			$txt.="<td class='shop'><div id='costantimattersell' class='shipcost'>0</div></td>";
			$txt.="<td class='shop'><div id='costantimatterbuy' class='shipcost'>0</div></td></tr>";
			
			$txt.="<tr><td class='shop'>Organics</td>";
			$txt.="<td class='shop'>$cfg[organics_sell_price]</td>";
			$txt.="<td class='shop'>$cfg[organics_buy_price]</td>";
			$txt.="<td class='shop'><input id='numberorganics' type='text' name='quantity' value='0' maxlength='4' size='3' onKeyPress=\"return numbersonly(event)\" onKeyUp=\"calculate(this.value,$cfg[organics_sell_price],'costorganicssell','costtotalsell');calculate(this.value,$cfg[organics_buy_price],'costorganicsbuy','costtotalbuy');calculate(this.value,1,'organicsquantity','totalquantity')\"><div id='organicsquantity' style='display:none'></td>";
			$txt.="<td class='shop'>".sprintf($button, 'Buy', ORGANICS, 'organics', CONFIRM | BUY | SHIP | $utility)." ".sprintf($button, 'Sell', ORGANICS, 'organics', CONFIRM | SELL | SHIP | $utility)."</td>";
			$txt.="<td class='shop'><div id='costorganicssell' class='shipcost'>0</div></td>";
			$txt.="<td class='shop'><div id='costorganicsbuy' class='shipcost'>0</div></td></tr>";

			//$all_button = "<input type='button' class='buybutton' value='Buy all' onclick=\"xajax_buysell_resources(lsn,".METAL.",document.getElementById('numbermetal').value,".ANTIMATTER.",document.getElementById('numberantimatter').value,".ORGANICS.",document.getElementById('numberorganics').value,".(CONFIRM | BUY | SHIP).")\">";
			$all_button = "<input type='button' class='buybutton' value='%s all' onclick=\"xajax_buysell_resources(lsn,".METAL.",document.getElementById('numbermetal').value,".ANTIMATTER.",document.getElementById('numberantimatter').value,".ORGANICS.",document.getElementById('numberorganics').value,%u)\">";

			$txt.="<tr><th class='shop'></th>";
			$txt.="<th class='shop'></th>";
			$txt.="<th class='shop'></th>";
			$txt.="<th class='shop'><div id='totalquantity'>0</div></td>";
			$txt.="<th class='shop'>".sprintf($all_button, 'Buy', (CONFIRM | BUY | SHIP | $utility)).' '.sprintf($all_button, 'Sell', (CONFIRM | SELL | SHIP | $utility))."</th>";
			$txt.="<th class='shop'><div id='costtotalsell' class='shipcost'>0</div></th>";
			$txt.="<th class='shop'><div id='costtotalbuy' class='shipcost'>0</div></th></tr>";
			$txt.='</table>';
			$txt.='Your current ship has <b>'.$current_free_bays.'</b> free cargo bay'.(($current_free_bays!=1)?'s':'').'<br>';
			$txt.='Your fleet has <b>'.$total_free_bays.'</b> free cargo bay'.(($total_free_bays!=1)?'s':'');
			break;
		case AUCTION:
		case UPGRADE_SHOP:
		case COLONIST_SHOP:
		case BOUNTY_CENTER:
			$txt.='not implemented';
			break;
		default: // utility not found - impossible
			$txt.='<br><b>internal error</b> ... please contact the webmaster about this';
	}
	return $txt;
}
function print_resources($session, $metal=-1, $antimatter=-1, $minemode=-1) {
	if ( ($metal == -1) || ($antimatter == -1) || ($minemode == -1) ) {
		db_connect();
		$q=dbq("SELECT map.metal, map.antimatter, ships.mine_mode FROM map, ships, users WHERE users.session='".dbesc($session)."' AND users.ship=ships.id AND map.id=ships.node");
		list($metal, $antimatter, $minemode)=mysql_fetch_row($q);
	}
	$minebutton = '<input type="button" class="minebutton" value="%s" onClick="xajax_mine(lsn, %u)"> ';
	$txt='';
	if ( ($metal != 0) || ($antimatter != 0) ) {
		$txt.='<hr><table class="planetresources">';
		if ($metal != 0) {
			// metal row
			$txt.="<tr class='planetresources'><td class='planetresources'>Metal: <b>$metal</b></td><td class='planetresources'>".sprintf($minebutton, 'Fleet mining', METAL | FLEET);
			if ($minemode & METAL)
				$txt.="<i>(Currently mining)</i>";
			else
				$txt.=sprintf($minebutton, 'Mine', METAL | SHIP);
			$txt.="</td></tr>";
		} 
		if ($antimatter != 0) {
			// antimatter row
			$txt.="<tr class='planetresources'><td class='planetresources'>Antimatter: <b>$antimatter</b></td><td class='planetresources'>".sprintf($minebutton, 'Fleet mining', ANTIMATTER | FLEET);
			if ($minemode & ANTIMATTER)
				$txt.="<i>(Currently mining)</i>";
			else
				$txt.=sprintf($minebutton, 'Mine', ANTIMATTER | SHIP);
			$txt.="</td></tr>";
		}
		$txt.="</table>";
	}
	return $txt;
}
function print_solar_system($session) {
	db_connect();
	$q=dbq("SELECT ships.node, users.id, ships.mine_mode FROM ships, users WHERE users.session='".dbesc($session)."' AND users.ship=ships.id");
	$row=mysql_fetch_row($q);
	if (empty($row)) return false;
	$node = $row[0];
	$id = $row[1];
	$minemode = $row[2];
	
	$q=dbq("SELECT name, metal, antimatter FROM map WHERE id=$node");
	list($nodename, $metal, $antimatter)=mysql_fetch_row($q);
	
	$neighbors					= print_neighbors_links($node);
	list($autowarp,$autowarpon)	= print_autowarp($id);
	$planets					= print_planets($node,$id);
	$ships						= print_ships($node,$id,false);
	$utilities					= print_utilities($node);
	$resources					= print_resources($session, $metal, $antimatter, $minemode);

	$txt='';
	$txt.="Solar System: <b>$node</b> - <b>$nodename</b>";
	if ($autowarpon)
		$txt.="<div id='normalwarp' style='display:none'>";
	else
		$txt.="<div id='normalwarp'>";
	$txt.="Warp-links: $neighbors</div>";
	$txt.="<div id='autowarp'>$autowarp</div>";
	$txt.="<div id='resources'>$resources</div";
	$txt.=$utilities;
	$txt.='<hr>'.$planets;
	$txt.="<hr><div id='ships'>$ships</div>";
	
	text_translate($txt,$session);
	return $txt;
}
function print_menu_details($session) {
	$txt='';
	$txt.='time: <b>'.date('jS M H:i').'</b><br>';
	$id=get_user_name($session,$name);
//	$info=get_user_info($id);
	
	db_connect();
	db("SELECT turn_nr, max_turns, credits, ship_types.name AS shiptype, ships.name AS shipname,
		ships.fighters, ships.max_fighters, ships.shield, ships.max_shield,	".query_ship_cargo('cargo').", ships.cargo_bays,
		ships.colonists, ships.metal, ships.antimatter, ships.organics, ships.turrets, ships.genesis
	FROM users, ships, ship_types 
	WHERE users.id=".(int)$id." AND users.ship=ships.id AND ship_types.id=ships.type");
	list($info) = dbr(1);
	
	$cargotooltip ="<table class='menutooltip'>";
	$cargotooltip.="<tr><th class='menutooltip'>colonists</th><td class='menutooltip'>".nr_format($info['colonists']).'</td></tr>';
	$cargotooltip.="<tr><th class='menutooltip'>metal</th><td class='menutooltip'>".nr_format($info['metal']).'</td></tr>';
	$cargotooltip.="<tr><th class='menutooltip'>antimatter</th><td class='menutooltip'>".nr_format($info['antimatter']).'</td></tr>';
	$cargotooltip.="<tr><th class='menutooltip'>organics</th><td class='menutooltip'>".nr_format($info['organics']).'</td></tr>';
	$cargotooltip.="<tr><th class='menutooltip'>genesis device</th><td class='menutooltip'>".nr_format($info['genesis']).'</td></tr>';
	$cargotooltip.="<tr><th class='menutooltip'>free cargo bays</th><td class='menutooltip'>".nr_format($info['cargo_bays']-$info['cargo']).'</td></tr>';
	$cargotooltip.='</table>';
	$txt.="user-name: <b>$name</b><br>";
	$txt.="turns: <b>$info[turn_nr]</b> / $info[max_turns]<br>";
	$txt.='credits: <b>'.nr_format($info['credits']).'</b>';
	$txt.="<div class='menu_title'>Commanded ship</div>";
	$txt.="class: <b>$info[shiptype]</b><br>";
	$txt.="name: <b>$info[shipname]</b><br>";
	$txt.='fighters: <b>'.nr_format($info['fighters']).'</b> / '.nr_format($info['max_fighters']).'<br>';
	$txt.='turrets: <b>'.nr_format($info['turrets']).'</b><br>';
	$txt.='shield: <b>'.nr_format($info['shield']).'</b> / '.nr_format($info['max_shield']).'<br>';
	$txt.="<em onMouseover=\"tooltip('".str_replace("'","\'",$cargotooltip)."')\" onClick='locktooltip(true)'>cargo</em>: <b>".nr_format($info['cargo'])."</b> / ".nr_format($info['cargo_bays']);
	return $txt;
}
function print_message_buttons() {
	$txt='';
	$txt.='<input type="button" class="msgbutton" value="Inbox" onclick="xajax_messages(lsn,'.INBOX.')">';
	$txt.='<input type="button" class="msgbutton" value="Outbox" onclick="xajax_messages(lsn,'.OUTBOX.')">';
	$txt.='<input type="button" class="msgbutton" value="Notes" onclick="xajax_messages(lsn,'.NOTES.')">';
	//$txt.='<br>';
	$txt.='<input type="button" class="msgbutton" value="Compose message" onclick="xajax_message_compose(lsn)">';
	$txt.='<input type="button" class="msgbutton" value="Create note" onclick="xajax_message_compose_note(lsn)">';
	return $txt;
}
function print_messages($session,$box) {
	db_connect();
	$txt='';
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid) = mysql_fetch_row($q);
	if (!$userid)
		return false;
	// from_name = user who sent the message
	// reply_at = the message that this is a reply for
	if ($box == INBOX)
		db("SELECT messages.id, users.name AS from_name, subject, message, date, viewed, reply_at FROM messages, users WHERE messages.from=users.id AND user_id=$userid ORDER BY viewed DESC, messages.id DESC");
	elseif ($box == OUTBOX)
		db("SELECT messages.id, users.name AS to_name, subject, message, date, viewed, reply_at FROM messages, users WHERE viewed='no' AND messages.user_id=users.id AND messages.from=$userid ORDER BY messages.id DESC");
	elseif ($box==NOTES)
		db("SELECT id, subject, message, date, viewed FROM messages WHERE messages.from IS NULL AND user_id=".$userid);
	else { // default
		db("SELECT * FROM messages WHERE user_id=".$userid);
		$replies = 0;
		$messages = 0;
		$viewed = 0;
		$notes = 0;
	}
	$allmsg = dbr(1);

	if ($box == INBOX) db("UPDATE messages SET viewed='yes' WHERE user_id=$userid");
	if ($box == OUTBOX) $txt.='<i class="message">The messages you see here are messages you sent that haven\'t been read yet.</i><br>';
	if ($box != DEFAULT_MSG) $txt.='found '.count($allmsg).' message(s)<br>';
	
	//$table_format='<table border="1" class="expandlist"><tr><td colspan="2">^Your ships^:</td></tr>';
	$table_format='<table border="0" class="expandlist">';
	$table_format.='<tr><td class="expandbutton"><input type="button" class="%s" id="msg%sbutton" onclick="xchg_list_display(\'msg%2$s\')"></td><td class="msglist"><span class="%smsgsubject">%s</span><br>';
	//$table_format.='<tr><td>subject: %s</td></tr>';
	$table_format.='<div id="msg%2$s" class="%3$smsglist">';
	if ($box == INBOX)
		$table_format.='<i class="message">from:</i> %s<br>';
	elseif ($box == OUTBOX)
		$table_format.='<i class="message">to:</i> %s<br>';
	$table_format.='<i class="message">date:</i> %s<br>';
	$table_format.='<i class="message">message:</i><br>%s';
	$table_format.='<br><input type="button" class="msgbutton" value="Delete" onclick="xajax_message_delete(lsn, %2$s, %s)">';
	if ($box == INBOX)
		$table_format.='<input type="button" class="msgbutton" value="Reply" onclick="xajax_message_compose(lsn, %2$s)">';
	elseif ($box == NOTES)
		$table_format.='<input type="button" class="msgbutton" value="Edit" onclick="xajax_message_compose_note(lsn, %2$s)">';
	$table_format.='<br><br></div></td></tr></table>';
	foreach ($allmsg as $msg) {
		$msg['message'] = format_message_output($msg['message']);
		if ($msg['viewed']=='yes') $new = '';
		else $new = 'new';
		if ($box==INBOX)
			$txt.=sprintf($table_format, ($new?'collapse':'expand'), $msg['id'], $new, $msg['subject'].($new?'<b> - new</b>':''), $msg['from_name'].($msg['reply_at']?' - <input type="button" class="msgbutton" value="this is a reply" onclick="xajax_message_dialogue(lsn,'.$msg['id'].')">':''), $msg['date'], $msg['message'], $box);
		elseif ($box==OUTBOX)
			$txt.=sprintf($table_format, 'expand', $msg['id'], '', $msg['subject'], $msg['to_name'], $msg['date'], $msg['message'], $box);
		elseif ($box==NOTES)
			$txt.=sprintf($table_format, 'expand', $msg['id'], '', $msg['subject'], $msg['date'], $msg['message'], $box);
		else {
			if ($msg['from']) {
				$messages+=1;
				if ($msg['viewed']=='yes') $viewed+=1;
				if ($msg['reply_at']) $replies+=1;
			} else $notes+=1;
		}
	}
	if ($box==DEFAULT_MSG) {
	$txt.="You have stored $messages ".(($messages==1)?'message':'messages');
	$txt.=", $replies ".(($replies==1)?'reply':'replies');
	//$txt.="<br>You have viewed ".($viewed-$notes).' '.((($viewed-$notes)==1)?'message':'messages');
	$txt.='<br>New messages: '.($messages-$viewed);
	$txt.='<br>Notes: '.$notes;
	}
	return $txt;
}
function print_message_dialogue($msgid) {
	db_connect();
	$txt='';
	$table_format='<table class="message">';
	$table_format.='<tr><th class="message">subject:</th><td class="message">%s</td></tr>';
	$table_format.='<tr><th class="message">sender:</th><td class="message">%s</td></tr>';
	$table_format.='<tr><th class="message">date:</th><td class="message">%s</td></tr>';
	$table_format.='<tr><th class="message">message:</th><td class="message">%s</td></tr>';
	$table_format.='</table>';
	// get base message
	$q=dbq("SELECT messages.id, users.name AS from_name, subject, message, date, viewed, reply_at FROM messages, users WHERE messages.from=users.id AND messages.id=$msgid LIMIT 1");
	$msg = mysql_fetch_assoc($q);
	$txt.=sprintf($table_format, $msg['subject'], $msg['from_name'], $msg['date'], $msg['message']);
	
	$txt.='<br><i class="message">Below you can see all the messages leading to the above one.</i><br>';
	
	if (!$msg['reply_at']) return $txt;
	// get reply list
	do {
		$q=dbq("SELECT messages.id, users.name AS from_name, subject, message, date, viewed, reply_at FROM messages, users WHERE messages.from=users.id AND messages.id=".$msg['reply_at']);
		$msg = mysql_fetch_assoc($q);
		// print message
		$txt.='<br>'.sprintf($table_format, $msg['subject'], $msg['from_name'], $msg['date'], $msg['message']);
	} while ($msg['reply_at']);
	return $txt;
}
function print_message_compose_note($userid, $subject='', $msg='', $editid=0) {
	$txt='';
	$txt.='<table class="message">';
	$txt.='<tr><th class="message">subject:</th><td class="message"><input class="msgsubject" type="text" value="'.$subject.'" size="52" id="new_note_subject" onKeyUp="cut2size(this,63)" onKeyPress="return checksizemax(event,this,63);"> ';
		$txt.='<input type="button" class="msgbutton" value="Save" onclick="xajax_message_save_note(lsn,document.getElementById(\'new_note_subject\').value,document.getElementById(\'new_note_body\').value,'.$editid.')"></td></tr>';
	$txt.='<tr><th class="message">message:</th><td class="message"><textarea class="message" id="new_note_body" cols="52" rows="5" onKeyUp="cut2size(this,255)" onKeyPress="return checksizemax(event,this,255);">'.$msg.'</textarea></td></tr>';
	$txt.='</table>';
	return $txt;
}
function print_message_compose($userid, $reply=0) {
	$reply=(int)$reply;
	$subject='';
	$txt='';
	if ($reply) {
		db_connect();
		$q=dbq("SELECT users.name, messages.subject, messages.message FROM users, messages WHERE messages.id=$reply AND users.id=messages.from");
		list($replyname,$subject,$message)=mysql_fetch_row($q);
		$subject='Re: '.$subject;
		$txt.='<i class="message">the message you reply to:</i><br>'.format_message_output($message).'<br><br>';
	}
	$txt.='<table class="message">';
	$txt.='<tr><th class="message">to:</th><td class="message">';
		if ($reply)
			$txt.='<input class="msgto" type="text" value="'.$replyname.'" id="new_msg_to" size="30" DISABLED="DISABLED">';
		else
			$txt.='<input class="msgto" type="text" value="" id="new_msg_to" size="30">';
		$txt.=' <input type="button" class="msgbutton" value="Check user" onclick="xajax_check_user(lsn, document.getElementById(\'new_msg_to\').value)">';
		$txt.='<input type="button" class="msgbutton" value="Send" onclick="xajax_message_send(lsn,'.$reply.',document.getElementById(\'new_msg_to\').value,document.getElementById(\'new_msg_subject\').value,document.getElementById(\'new_msg_body\').value)">';
		$txt.='<input type="button" class="msgbutton" value="Save as note" onclick="xajax_message_save_note(lsn,document.getElementById(\'new_msg_subject\').value,document.getElementById(\'new_msg_body\').value)"></td></tr>';
	$txt.='<tr><th class="message">subject:</th><td class="message"><input class="msgsubject" type="text" value="'.$subject.'" size="52" id="new_msg_subject" onKeyUp="cut2size(this,63)" onKeyPress="return checksizemax(event,this,63);"></td></tr>';
	$txt.='<tr><th class="message">message:</th><td class="message"><textarea class="message" id="new_msg_body" cols="52" rows="5" onKeyUp="cut2size(this,255)" onKeyPress="return checksizemax(event,this,255);"></textarea></td></tr>';
	$txt.='</table>';
	return $txt;
}
function print_menuright($session) {
	$txt = '';
	db_connect();
	$q = dbq("SELECT ships.genesis FROM ships, users WHERE session='".dbesc($session)."' AND users.ship=ships.id");
	list($genesis) = mysql_fetch_row($q);
	if ($genesis > 0)
		$txt.='You own '.$genesis.' genesis device'.($genesis==1?'':'s').'<br><input type="button" class="menuright" value="Deploy Genesis Device" onclick="xajax_deploy_bomb(lsn, '.GENESIS_DEVICE.')"><br>';
	return $txt;
}
function print_menu($session) {
	$txt='';
	// menu details
	$txt.='<div id="menu_details">';
	$txt.=print_menu_details($session);
	$txt.='</div>';
	// menu
	$button = '<input type="button" class="menu" value="%s" onclick="%s"><br>';
	$title = '<div class="menu_title">%s</div>';
	
	$txt.=sprintf($title, 'Menu');
	$txt.=sprintf($button, 'Solar System', 'xajax_show_ss(lsn)');
	$txt.=sprintf($button, 'Ships', 'xajax_show_all_ships(lsn, all_ships_sort)');
	$txt.=sprintf($button, 'Planets', 'xajax_show_all_planets(lsn)');
	$txt.=sprintf($button, 'Messages', 'xajax_messages(lsn)');
	$txt.=sprintf($button, 'Change Password', 'xajax_change_pass(lsn)');
	$txt.=sprintf($button, 'Logout', 'self.location.replace(\'login.php?a=logout&s='.$session.'\')');

	$txt.=sprintf($title, 'Admin');
	$txt.=sprintf($button, 'Variables config', 'xajax_show_variables(lsn)');

	$txt.=sprintf($title, 'Debug');
	$txt.=sprintf($button, 're-init interface', 'init()');
	$txt.=sprintf($button, 'stop update', 'clearInterval(timer_handle);alert(\'The update timer has been stopped\')');
	$txt.=sprintf($button, 'list display memory', 'xajax_debug()');

	return $txt;
}
function print_all_ships($userid, &$sort) {
	$txt = '';
	db_connect();
	db("SELECT ship FROM users WHERE id=$userid");
	list($currentship) = dbrow();

	$query = "SELECT type, towed_by, ships.name AS shipname, ship_types.name AS typename, ships.id AS ship_id, ships.user_id, ships.fighters, node, map.name AS node_name
	FROM ships, ship_types, map
	WHERE type=ship_types.id AND ships.node=map.id AND ships.user_id=$userid";
	
	$txt.='Sort by ';
	switch ($sort) {
		case 2: // sort by ship node
			$queryorderby = 'node, type, shipname, ship_id';
			$order = 'node';
			$txt.='<input type="button" value="^ship type^" class="shipaction" onclick="xajax_show_all_ships(lsn, 1)">';
			break;
		default:
		case 1: // sort by ship type
			$sort = 1;
			$queryorderby = 'type, node, shipname, ship_id';
			$order = 'type';
			$txt.='<input type="button" value="^ship node^" class="shipaction" onclick="xajax_show_all_ships(lsn, 2)">';
			break;
	}
	$txt.='<hr>';
	db("$query ORDER BY $queryorderby");
	$ships = array();
	foreach (dbr(1) as $ship)
		$ships[$ship[$order]][]=$ship;
	$txt.='<table border="0" class="expandlist"><tr><td colspan="2">^Your ships^:</td></tr>';
	foreach ($ships as $shiporder) {
		$count=count($shiporder);
		$id='ownship'.$order.$shiporder[0][$order];
		$txt.='<tr><td class="expandbutton"><input type="button" class="expand" id="'.$id.'button" onclick="xchg_list_display(\''.$id.'\')"></td><td>';
		
		switch ($order) {
			case 'type':
				$txt.=$count.' <b>^'.$shiporder[0]['typename'].'^</b>'.($count>1?'s':'');
				break;
			case 'node':
				$txt.=$count.' ^ship'.($count>1?'s':'').' in system^ <b>'.$shiporder[0]['node'].'</b> - <b>'.$shiporder[0]['node_name'].'</b>';
				break;
		}
		
		$txt.='<div class="shiplist" id="'.$id.'">';
		$tow = $release = 0;
		foreach ($shiporder as $ship) {
			$txt.="$ship[shipname] <i>($ship[fighters] fighters)</i>";
			
			switch ($order) {
				case 'type':
					$txt.=' ^in system^ <b>'.$ship['node'].'</b> - <b>'.$ship['node_name'].'</b>';
					break;
				case 'node':
					$txt.=' - <b>^'.$shiporder[0]['typename'].'^</b>';
					break;
			}
			
			/*
			if ($ship['towed_by']==$currentship)
			{
				$txt.='<input type="button" value="^release^" class="shipaction" onclick="xajax_ship_release(lsn,'.$ship['ship_id'].')">';
				$release += 1;
			} else {
				$txt.='<input type="button" value="^tow^" class="shipaction" onclick="xajax_ship_tow(lsn,'.$ship['ship_id'].')">';
				$tow += 1;
			}
			if ( $ship['ship_id'] != $currentship )
				$txt.=' - <input type="button" value="^command^" class="shipaction" onclick="xajax_ship_command(lsn,'.$ship['ship_id'].')">';
			*/
			$txt.='<br>';
		}
		/*
		if ($tow) {
			$txt.='<input type="button" value="^tow all^" class="shipaction" onclick="xajax_ship_tow(lsn,'.$shiporder[0]['type'].', 0)">';
			if ($release)
				$txt.=' - ';
		}
		if ($release)
			$txt.='<input type="button" value="^release all^" class="shipaction" onclick="xajax_ship_release(lsn,'.$shiporder[0]['type'].', 0)">';
		*/
		$txt.='</div></td></tr>';
	}
	$txt.='</table>';
	text_translate($txt,null,$userid);
	return $txt;
}
function print_all_planets($userid) {
	$txt = '';
	db_connect();
	db("SELECT node, fighters, planets.name AS planet_name, map.name AS node_name
	FROM planets, map
	WHERE planets.user_id=$userid AND map.id=node
	ORDER BY node, planets.id");
	$planets = array();
	foreach (dbr(1) as $planet)
		$planets[$planet['node']][]=$planet;
	$txt.='<table border="0" class="expandlist"><tr><td colspan="2">^Your planets^:</td></tr>';
	foreach ($planets as $planetnode) {
		$count=count($planetnode);
		$id='ownplanetnode'.$planetnode[0]['node'];
		$txt.='<tr><td class="expandbutton"><input type="button" class="expand" id="'.$id.'button" onclick="xchg_list_display(\''.$id.'\')"></td><td>'.$count.' planet'.($count>1?'s':'').' ^in system^ <b>'.$planetnode[0]['node'].'</b> - <b>'.$planetnode[0]['node_name'].'</b>';
		$txt.='<div class="shiplist" id="'.$id.'">';
		foreach ($planetnode as $planet)
			$txt.=$planet['planet_name'].' <i>('.$planet['fighters'].' fighters)</i>';
		$txt.='</div></td></tr>';
	}
	$txt.='</table>';
	text_translate($txt,null,$userid);
	return $txt;
}
function ajax_error(&$objResponse,$errorstr='error') {
	$objResponse->assign("page_body", "innerHTML", nl2br(htmlspecialchars($errorstr)));
}
function ajax_map_display(&$objResponse, $value=true) {
	if ($value) {
		$objResponse->assign('minimap', 'style.display', 'block');
		$objResponse->assign('menuright', 'style.display', 'block');
		$objResponse->assign('details', 'style.width', '455px');
	} else {
		$objResponse->assign('minimap', 'style.display', 'none');
		$objResponse->assign('menuright', 'style.display', 'none');
		$objResponse->assign('details', 'style.width', '710px');
	}
	//$objResponse->assign('details', 'style.backgroundColor', 'red');
}
/***********************************************************************************************************************************************************
	DATABASE FUNCTIONS
***********************************************************************************************************************************************************/
function dbDIE() {
	print "MySQL ERROR: ".mysql_error()."\r\nBACKTRACE\r\n";
	var_dump(debug_backtrace());
	exit();
}
function db_connect() {
	/*if (!mysql_connect($cfg['dbserver'],$cfg['dbuser'],$cfg['dbpass'])) die('mysql_connect failed: '.mysql_error());
	if (!mysql_select_db($cfg['dbname'])) die('coud not select DB');*/
	global $BD_connected;
	if ((!isset($BD_connected)) || (!$BD_connected)) {
		@mysql_connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD) or die("No connection to the Database could be created.<p>The following error was reported:<br><b>".mysql_error()."</b>");
		mysql_select_db(DATABASE) or die("Coud not select database");
		$BD_connected=true;
	}
}
function db($string,$echo=false) {
	global $db_query_result;
	if ($echo) echo $string."\r\n";
	else $db_query_result = @mysql_query($string) or dbDIE();
}
function dbesc($string) {
	if ( version_compare(PHP_VERSION, '4.3.0', '<') )
		return mysql_escape_string($string);
	else
		return mysql_real_escape_string($string);
}
/* DATABASE QUERY
returns the resource
*/
function dbq($string) {
	$q= @mysql_query($string) or dbDIE();
	return $q;
}
function dbr($type = false) {
	global $db_query_result;
	$result=array();
	if (!$type) while ($row = mysql_fetch_row($db_query_result)) $result[]=$row; // incicii sunt numere
	else while ($row = mysql_fetch_assoc($db_query_result)) $result[]=$row; // indicii sunt numele field-urilor
	return $result;
}
function dbrow($type = false) {
	global $db_query_result;
	if (!$type)
		return mysql_fetch_row($db_query_result); // incicii sunt numere
	else
		return mysql_fetch_assoc($db_query_result); // indicii sunt numele field-urilor
}
/***********************************************************************************************************************************************************
	REDIRECT PAGE
***********************************************************************************************************************************************************/
function redirect2page($redirect_to,$post_data='',$additional_text=''){
	if (!empty($post_data) && is_string($post_data)) {
		$array2=explode('&', $post_data);
		$post_data=array();
		foreach($array2 as $val) {
			$pos=strpos($val,'=');
			$key=substr($val,0,$pos);
			$post_data[$key]=substr($val,$pos+1);
		}
	}
	$additional_text=nl2br(htmlspecialchars($additional_text));
	include 'redirect.php';
	die();
}
?>