<?php
function debug() {
	$objResponse = new xajaxResponse();
	//$objResponse->assign('minimap', 'style.display', 'none');
	$script='';
	$script.='var string="";';
	$script.='for (var key in listdisplaymemory) {';
	$script.='string+=key+" -> "+listdisplaymemory[key]+"\r\n";';
	$script.='} alert(string);';
	$objResponse->script($script);
	return $objResponse;
}
function move_to_node($session, $node) {
	$node=(int)$node;
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$q=dbq("SELECT id, ship, turn_nr FROM users WHERE users.session='".dbesc($session)."'");
	list($userid,$shipid,$turns)=mysql_fetch_row($q);
	if ($turns <= 0) {
		$objResponse->alert('You need more turns to move.');
		return $objResponse;
	}
	// cansel autowarp
	db("UPDATE users SET autowarp=NULL, turn_nr=turn_nr-1 WHERE id=".(int)$userid);
	// move all ships
	db("UPDATE ships SET node=".$node.", mine_mode=".NOT_MINING." WHERE user_id=".(int)$userid." AND (towed_by=".(int)$shipid." OR id=".(int)$shipid.")");
	
	if (mysql_affected_rows()<1) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
	} else {
		$objResponse->assign("minimap", "src", 'graph.php?node='.$node);
		$objResponse->assign('details', 'innerHTML', print_solar_system($session));
		$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
		$objResponse->script('show_list_memory();');
	}
	return $objResponse;
}
function init($session) {
	$objResponse = new xajaxResponse();
	
	require_once "functions.inc.php";
	db_connect();
	db("SELECT id FROM users WHERE session='".dbesc($session)."'");
	$userid = dbr();
	if (!$userid) {
		if ($session!=-1) ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	$userid = $userid[0][0];
	db("SELECT ships.node FROM users, ships WHERE users.id=$userid AND ships.id=users.ship");
	$node=dbr();
	if (!$node) {
		if ($session!=-1) {
			$q = dbq("SELECT COUNT(*) FROM ships WHERE user_id=$userid");
			list($nr) = mysql_fetch_row($q);
			if ($nr > 0) {
				ajax_error($objResponse,"It seems you do not have a ship under your command\r\nTry to log in again ...\r\nThis is a bug, contact the webmaster\r\nLine ".__LINE__.".");
				$q = dbq("SELECT id FROM ships WHERE user_id=$userid LIMIT 1");
				list($newshipid) = mysql_fetch_row($q);
				db("UPDATE users SET ship=$newshipid WHERE id=$userid");
			} else
				ajax_error($objResponse,"It seems you do not have any ship ... this is a bug, contact the webmaster\r\nLine ".__LINE__.".");
		}
		return $objResponse;
	}
	$node=$node[0][0];
	$objResponse->assign('details', 'innerHTML', print_solar_system($session));
	$objResponse->script('show_list_memory();');
	$objResponse->script('popup();');
	$objResponse->assign('minimap', 'src', 'graph.php?node='.$node);
	$objResponse->assign('menu', 'innerHTML', print_menu($session));
	ajax_map_display($objResponse, true);
	$objResponse->assign('menuright', 'innerHTML', print_menuright($session));
	return $objResponse;
}
function change_pass($session) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', print_change_pass($session));
	
	return $objResponse;
}
function show_ss($session) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	
	$ss=print_solar_system($session);
	$objResponse->assign('menuright', 'innerHTML', print_menuright($session));
	//for ($i=0;$i<5000;$i+=1) $ss.=' .'; // test the scroll
	$objResponse->assign('details', 'innerHTML', $ss);
	$objResponse->script('show_list_memory();');
	ajax_map_display($objResponse, true);
	if ($ss===false) ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
	
	return $objResponse;
}
function set_autowarp($session,$b){
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	get_map_array($graph);
	
	db_connect();
	$q=dbq("SELECT users.id, ships.node, users.turn_nr FROM ships, users WHERE session='".dbesc($session)."' AND ships.id=users.ship");
	list($id,$initialNode,$turns)=mysql_fetch_row($q);
	if ($turns <= 0) {
		$objResponse->alert('You need more turns to generate a route.');
		return $objResponse;
	}
	$a = $initialNode;
	// $b is user-input
	if (!is_numeric($b)) $a=(int)$b;
	$b=(int)$b;
	
	if ($a==$b)
		$path=NULL;
	else {
		$dist=array();
		$stack=array($a);
		$st_f_dist=array(0);
		while (true) {
			$node=array_shift($stack);
			$node_f_d=array_shift($st_f_dist);
			if (is_null($node)) break; // daca nu au mai ramas elemente in stiva
			if (isset($dist[$node])) continue; // daca nodul curent a mai fost vizitat
			$dist[$node]=$node_f_d+1;
			if ($node==$b) break; // daca am ajuns la nodul final (cel cautat)
			foreach ($graph[$node]['links'] as $n) {
				$stack[]=$n;
				$st_f_dist[]=$dist[$node];
			}
		}
		if ($node==$b) {
			$path='';
			while ($node!=$a) {
				$path=$node.','.$path;
				foreach ($graph[$node]['links'] as $n) {
					if ( (isset($dist[$n])) && ($dist[$n]==($dist[$node]-1)) ) {
						$node=$n;
						break;
					}
				}
			}
			$path=substr($path,0,-1);
		} else $path=null;
	}

	if (strlen($path)==0)
		db("UPDATE users SET autowarp=NULL WHERE id=$id");
	else
		db("UPDATE users SET autowarp='$path', turn_nr=turn_nr-1 WHERE id=$id");
		
	list($autowarptxt, $autowarpon) = print_autowarp($id, $path);

	if ($autowarpon)
		$objResponse->assign('normalwarp', 'style.display', 'none');
	else
		$objResponse->assign('normalwarp', 'style.display', 'block');
	$objResponse->assign('autowarp', 'innerHTML', $autowarptxt);
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	return $objResponse;
}
function autowarp_to_node($session) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();

	db_connect();
	$q=dbq("SELECT turn_nr,autowarp,users.id FROM users, ships WHERE session='".dbesc($session)."' AND users.id=ships.user_id");
	list($turn,$path,$id)=mysql_fetch_row($q);
	
	if ($turn <= 0) {
		$objResponse->alert('You need more turns to move.');
		return $objResponse;
	}
	
	$path=explode(',',trim($path));
	$node=(int)array_shift($path);
	$path=implode(',',$path);

	$q=dbq("SELECT ship FROM users WHERE session='".dbesc($session)."'");
	list($shipid)=mysql_fetch_row($q);
	// move all ships
	db("UPDATE ships SET node=".(int)$node.", mine_mode=".NOT_MINING." WHERE user_id=".(int)$id." AND (towed_by=".(int)$shipid." OR id=".(int)$shipid.")");
	
	if (strlen($path)==0) db("UPDATE users SET autowarp=NULL, turn_nr=turn_nr-1 WHERE session='".dbesc($session)."'");
	else db("UPDATE users SET autowarp='$path', turn_nr=turn_nr-1 WHERE session='".dbesc($session)."'");

	$objResponse->assign("minimap", "src", 'graph.php?node='.$node);
	$objResponse->assign('details', 'innerHTML', print_solar_system($session));
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function show_planet($planetid,$session) {
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	
	$objResponse = new xajaxResponse();
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', print_planet_details($planetid,$userid));
	return $objResponse;
}
function rename_planet($planetid, $session, $string) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$string=dbesc(substr($string,0,30));
	$planetid=(int)$planetid;
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	db("UPDATE planets SET name='$string' WHERE id=$planetid AND user_id=$userid");
	if (mysql_affected_rows() < 1) // the query did not modify anything (renamed with same name or wrong userid)
		return $objResponse;
	$objResponse->assign('details', 'innerHTML', print_planet_details($planetid,$userid));
	return $objResponse;
}
function ship_tow($session, $tow, $ship = true) {
	require_once "functions.inc.php";
	db_connect();
	$tow=(int)$tow;
	$q=dbq("SELECT ships.node, users.id FROM ships, users WHERE users.session='".dbesc($session)."' AND users.ship=ships.id");
	list($node,$userid)=mysql_fetch_row($q);
	if ($ship) // tow one ship
		db("UPDATE ships, users SET ships.towed_by=users.ship WHERE ships.id=$tow AND users.id=$userid AND ships.node=$node");
	else // tow  all ships of $tow type
		db("UPDATE ships, users SET ships.towed_by=users.ship WHERE ships.type=$tow AND users.id=$userid AND ships.node=$node");
	
	$objResponse = new xajaxResponse();
	$objResponse->assign('ships', 'innerHTML', print_ships($node,$userid));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function ship_release($session, $tow, $ship = true) {
	require_once "functions.inc.php";
	db_connect();
	$tow=(int)$tow;
	$q=dbq("SELECT ships.node, users.id FROM ships, users WHERE users.session='".dbesc($session)."' AND users.ship=ships.id");
	list($node,$userid)=mysql_fetch_row($q);
	if ($ship) // one ship
		db("UPDATE ships SET ships.towed_by=NULL WHERE ships.id=$tow AND ships.node=$node");
	else // all ships of $tow type
		db("UPDATE ships SET ships.towed_by=NULL WHERE ships.type=$tow AND ships.node=$node");
	
	$objResponse = new xajaxResponse();
	$objResponse->assign('ships', 'innerHTML', print_ships($node,$userid));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function ship_command($session, $shipid) {
	require_once "functions.inc.php";
	db_connect();
	$shipid=(int)$shipid;
	$sess=dbesc($session);
	db("UPDATE ships, users SET ships.towed_by=NULL, users.ship=$shipid WHERE users.id=ships.user_id AND ships.id=$shipid AND users.session='$sess'");
	$q=dbq("SELECT ships.node, users.id FROM ships, users WHERE users.session='$sess' AND users.ship=ships.id");
	list($node,$userid)=mysql_fetch_row($q);
	// poate ar trebui sa scot tow de pe nave cand schimb comanda (commanded are towed_by=NULL este oare destul in toate cazurile ?)
	$objResponse = new xajaxResponse();
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	$objResponse->assign('menuright', 'innerHTML', print_menuright($session));
	$objResponse->assign('ships', 'innerHTML', print_ships($node,$userid));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function show_utility($session, $name) {
	require_once "functions.inc.php";
	//db_connect();
	//$sess=dbesc($session);
	//$q=dbq("SELECT ships.node, users.id FROM ships, users WHERE users.session='$sess' AND users.ship=ships.id");
	//list($node,$userid)=mysql_fetch_row($q);
	if (!defined($name))
		return $objResponse->alert("there is no such utility\r\nReport this to the webmaster");
	$utility = constant($name);
	$objResponse = new xajaxResponse();
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', print_utility_details($session, $utility));
	return $objResponse;
}
function buy_ships() {
	require_once "functions.inc.php";
	db_connect();
	$objResponse = new xajaxResponse();
	$args = func_get_args();
	$nr = func_num_args()-1; // the last argument is user agreement
	$ships = array();
	$i = 0;
	$session = $args[$i++];
	$sess = dbesc($session);
	while ($i<$nr)
		$ships[]=array('id'=>$args[$i++], 'name'=>substr(ltrim($args[$i++]),0,30), 'nr'=>(int)$args[$i++]);
	$agreed = (int)$args[$i++];
	//delete ships that have nr=0 (ships that are not bought)
	$emptyname = false; // if i found an empty ship name
	foreach ($ships as $k=>$ship) {
		if ($ship['nr']<=0) unset($ships[$k]);
		elseif (empty($ship['name'])) {
			unset($ships[$k]);
			$emptyname = true;
		}
	}
	if (count($ships)<1) {
		$txt="<input type='button' value='X' class='Xbutton' onclick='popup()'>";
		$txt.= 'You must buy at least one ship.';
		if ($emptyname) $txt.='<br>Ships with empty name are ignored.';
		
		$objResponse->script("popup('".addslashes($txt)."');");
		return $objResponse;
	}
	//get user info
	$q = dbq("SELECT users.id, ships.node FROM users, ships WHERE session='$sess' AND users.ship=ships.id");
	list($userid, $node) = mysql_fetch_row($q);
	//check if CONFIRMED or not
	if (($agreed & CONFIRMED) && !($agreed & CONFIRM)) {
		$txt='ok ... but this is not finished yet :(<br>';
		$txt.='<input type="button" value="^SHIP_SHOP^" class="shipaction" onclick="xajax_show_utility(lsn,\'SHIP_SHOP\')">';
		text_translate($txt,$session);
		
		db("SELECT * FROM ship_types");
		$types = array();
		foreach (dbr(1) as $v)
			$types[$v['id']] = $v;
		
		function insert_ship(&$types, $userid, $node, $name, $id) {
			$type = $types[$id];
			$name = dbesc($name);
			$txt='(';
			$txt.="$userid, $node, $id, '$name', $type[max_fighters], $type[shield_regen], $type[max_shield], $type[upgrades], $type[cargo], $type[detector], $type[stealth], $type[mine_speed], $type[turrets]";
			$txt.='),';
			return $txt;
		}
		
		$txt = '';
		foreach ($ships as $ship)
			if ($ship['nr'] > 1) {
				for ($i=1; $i<=$ship['nr']; $i+=1)
					$txt.= insert_ship($types, $userid, $node, htmlspecialchars( $ship['name'].sprintf('%0'.strlen($ship['nr']).'d',$i) ), $ship['id'] );
			} else {
				$txt.= insert_ship($types, $userid, $node, htmlspecialchars($ship['name']), $ship['id'] );
			}
		//insert all bought ships
		db("INSERT INTO ships (user_id, node, type, name, max_fighters, shield_regen, max_shield, upgrades_left, cargo_bays, detector, stealth, mine_speed, turrets) VALUES ".substr($txt,0,-1));
//		db("UPDATE users SET credits");
		$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
		$objResponse->alert("No credits have been taken. This is for testing purposes.");
		$objResponse->script("popup();");
		//$objResponse->assign('details', 'innerHTML', $txt);
	} elseif (($agreed & CONFIRM) && !($agreed & CONFIRMED)) {
		$txt='Are you sure you want to buy the following ships ?<br>';
		// put OK and CANCEL buttons
		$txt.='<center><input type="button" value="^YES^" class="popupbutton" onclick="xajax_buy_ships(lsn';
		foreach ($ships as $ship)
			$txt.=",$ship[id],'".addslashes($ship['name'])."',$ship[nr]";
		$txt.=','.CONFIRMED.')"> <input type="button" value="^NO^" class="popupbutton" onclick="popup()"></center>';
		
		/*// debug - print values to $txt
		ob_start();
		echo '<pre>';
		print_r($ships);
		echo "</pre>";
		$txt.=ob_get_contents();
		ob_end_clean();
		$txt.='<br>';*/
		text_translate($txt,$session);
		$txt.='<pre>';
		foreach ($ships as $ship)
			if ($ship['nr'] > 1) {
				for ($i=1; $i<=$ship['nr']; $i+=1)
					$txt.=htmlspecialchars( $ship['name'].sprintf('%0'.strlen($ship['nr']).'d',$i) ).'<br>';
			} else {
				$txt.=htmlspecialchars($ship['name']).'<br>';
			}
		$txt=substr($txt,0,-4); // delete the last <br>
		$txt.='</pre>';
		// the following must be done so that the JS function will recive a valid string
		$txt = str_replace(array("\r\n", "\n", "\r"), '<br>', $txt);
		$objResponse->script("popup('".addslashes($txt)."');");
	} else {
		$objResponse->script("popup();");
		$objResponse->assign('details', 'innerHTML', 'ERROR. report this to the webmaster<br>note: agreed = '.$agreed.' line = '.__LINE__);
	}
	return $objResponse;
}
function messages($session,$box=null) {
	require_once "functions.inc.php";
	if ($box===null) {
		$first = true;
		$box = DEFAULT_MSG;
	} else $first = false;
	//db_connect();
	//$sess=dbesc($session);
	$objResponse = new xajaxResponse();
	$txt='Messages';
	switch ($box) {
		case INBOX: 
			$txt.=' - Inbox';
			break;
		case OUTBOX:
			$txt.=' - Outbox';
			break;
		case NOTES:
			$txt.=' - Notes';
			break;
		default:
			$txt.=':';
	}
	if ($first) {
		ajax_map_display($objResponse, false);
		$objResponse->assign('details', 'innerHTML', '<div id="msg_title">'.$txt.'</div><hr><div id="msg_buttons">'.print_message_buttons().'</div><br><div id="msg_body">'.print_messages($session,$box).'</div>');
	} else {
		$objResponse->assign('msg_title', 'innerHTML', $txt);
		$objResponse->assign('msg_body', 'innerHTML', print_messages($session,$box));
	}
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function message_dialogue($session,$msgid) {
	$msgid = (int)$msgid;
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$q=dbq("SELECT COUNT(*) FROM messages, users WHERE messages.id=$msgid AND users.session='".dbesc($session)."' AND users.id=messages.user_id");
	list($nr) = mysql_fetch_row($q);
	if ($nr==1) 
		$objResponse->assign('msg_body', 'innerHTML', print_message_dialogue($msgid));
	else
		$objResponse->assign('details', 'innerHTML', "Coud not process...\r\Line ".__LINE__.".");
	return $objResponse;
}
function message_compose($session, $reply=0) {
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid) = mysql_fetch_row($q);
	$objResponse = new xajaxResponse();
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	$objResponse->assign('msg_title', 'innerHTML', 'Compose message');
	$objResponse->assign('msg_body', 'innerHTML', print_message_compose($userid, (int)$reply));
	return $objResponse;
}
function message_compose_note($session, $editid=0) {
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid) = mysql_fetch_row($q);
	$objResponse = new xajaxResponse();
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	if ($editid) {
		$q=dbq("SELECT subject, message FROM messages WHERE id=$editid AND user_id=$userid AND ISNULL(messages.from)");
		list($subject, $msg) = mysql_fetch_row($q);
	} else {
		$subject = '';
		$msg ='';
	}
	$objResponse->assign('msg_title', 'innerHTML', 'Note making');
	$objResponse->assign('msg_body', 'innerHTML', print_message_compose_note($userid, $subject, $msg, $editid));
	return $objResponse;
}
function check_user($session, $username) {
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id, name FROM users WHERE name='".dbesc($username)."'");
	list($id, $name) = mysql_fetch_row($q);
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($selfid) = mysql_fetch_row($q);
	$objResponse = new xajaxResponse();
	if ($id == $selfid) $objResponse->alert("Yes ... i can find you in the database :P");
	elseif ($selfid) $objResponse->alert($id?"There is a user named $name in the database":"There is no registered user named $username");
	else ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
	return $objResponse;
}
function message_send($session, $reply, $to, $subj, $msg) {
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	$q=dbq("SELECT id FROM users WHERE name='".dbesc($to)."'");
	list($toid)=mysql_fetch_row($q);
	$subj=substr(trim($subj),0,63);
	$msg=substr(trim($msg),0,255);
	$reply=(int)$reply;
	$objResponse = new xajaxResponse();
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	if (empty($toid)) {
		$objResponse->alert("I coud not find the user ".$to);
		return $objResponse;
	}
	if (empty($subj)) {
		$objResponse->alert("You need a subject for the message");
		return $objResponse;
	}
	if ($userid == $toid) {
		$objResponse->alert("Can't send a message to yourself, save it as a note.");
		return $objResponse;
	}
	if (empty($reply)) // if this is not a reply
		db("INSERT INTO messages (user_id, messages.from, date, subject, message) VALUES ($toid, $userid, NOW(), '".dbesc($subj)."', '".dbesc($msg)."')");
	else {
		// check if the user owns the message he replies to
		$q=dbq("SELECT user_id FROM messages WHERE id=$reply");
		list($id)=mysql_fetch_row($q);
		if ($id!=$userid) {
			$objResponse->alert("You do not own the message you try to reply to");
			ajax_error($objResponse,"Please contact the webmaster about this\r\nLine ".__LINE__.".");
			return $objResponse;
		}
		else db("INSERT INTO messages (reply_at, user_id, messages.from, date, subject, message) VALUES ($reply, $toid, $userid, NOW(), '".dbesc($subj)."', '".dbesc($msg)."')");
	}
	if (mysql_affected_rows()<1) 
		ajax_error($objResponse,"Coud not process...\r\nInsert error\r\nLine ".__LINE__.".");
	else $objResponse->alert("Message sent to ".$to);
	return $objResponse;
}
function message_save_note($session, $subj, $msg, $editid=0) {
	$editid=(int)$editid;
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	$subj=substr(trim($subj),0,63);
	$msg=substr(trim($msg),0,255);
	$objResponse = new xajaxResponse();
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	if (empty($subj)) {
		$objResponse->alert("You need a subject for the message");
		return $objResponse;
	}
	if (empty($editid)) {
		db("INSERT INTO messages (user_id, viewed, date, subject, message) VALUES ($userid, 'yes', NOW(), '".dbesc($subj)."', '".dbesc($msg)."')");
	} else {
		db("UPDATE messages SET date=NOW(), subject='".dbesc($subj)."', message='".dbesc($msg)."' WHERE id=$editid AND user_id=$userid");
	}
	if (mysql_affected_rows()<1) 
		ajax_error($objResponse,"Coud not process...\r\n".(($editid)?'Update':'Insert')." error\r\nLine ".__LINE__.".");
	else $objResponse->alert("Note saved.");
	return $objResponse;
}
function message_delete($session, $msgid, $box) {
	$msgid=(int)$msgid;
	$objResponse = new xajaxResponse();
	require_once "functions.inc.php";
	db_connect();
	$q=dbq("SELECT id FROM users WHERE session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	db("DELETE FROM messages WHERE id=$msgid AND (user_id=$userid OR (messages.from=$userid AND viewed='no'))");
	if (mysql_affected_rows()<1) {
		ajax_error($objResponse,"Coud not process...\r\nDelete error\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	$objResponse->alert("Message deleted");
	$objResponse->assign('msg_body', 'innerHTML', print_messages($session,$box));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
function transfer($session, $planetid, $cargoTypeAndDirection){
	$planetid=(int)$planetid;
	$objResponse = new xajaxResponse();
	require_once "functions.inc.php";
	$cargodirection = $cargoTypeAndDirection & (_2FLEET | _2PLANET);
	$cargotype = $cargoTypeAndDirection ^ $cargodirection;
	switch ($cargotype) {
		case COLONISTS:
			$type='colonists';
			break;
		case FIGHTERS:
			$type='fighters';
			break;
		case METAL:
			$type='metal';
			break;
		case ANTIMATTER:
			$type='antimatter';
			break;
		case ORGANICS:
			$type='organics';
			break;
		default: // wrong cargo type
			return ajax_error($objResponse, "Unidentified cargo type\r\nContact the webmaster about this\r\nLine ".__LINE__.".");
	}
	db_connect();
	$q=dbq("SELECT users.id, users.ship FROM users WHERE session='".dbesc($session)."'");
	list($userid, $commandship)=mysql_fetch_row($q);
	if (empty($userid)) {
		ajax_error($objResponse,"Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	db("LOCK TABLES ships AS ships_w WRITE, planets AS planets_w WRITE, ships READ, planets READ");
	if ($cargodirection == _2FLEET) {
		$q=dbq("SELECT $type FROM planets WHERE id=$planetid AND user_id=$userid");
		list($nr)=mysql_fetch_row($q);
		if ($nr <= 0) {
			$objResponse->alert("The planet has no ".$type);
			return $objResponse;
		}
		if ($cargotype == FIGHTERS)
			$query = "SELECT ships.id, ships.fighters, ships.fighters AS cargo, ships.max_fighters AS cargo_bays";
		else
			$query = "SELECT ships.id, ships.$type, ".query_ship_cargo('cargo').", ships.cargo_bays";
		$query.= " FROM ships, planets";
		$query.= " WHERE ships.user_id=$userid AND planets.id=$planetid AND ships.node=planets.node AND (towed_by=$commandship OR ships.id=$commandship)";
		db($query);
		$ships=dbr(1);
		$cargo_space = 0;
		foreach ($ships as $ship)
			$cargo_space += $ship['cargo_bays'] - $ship['cargo'];
		if ($cargo_space <=0) {
			$objResponse->alert("All ships are at maximum capacity");
			db("UNLOCK TABLES");
			return $objResponse;
		}
		db("UPDATE planets AS planets_w SET $type=".max($nr - $cargo_space, 0).' WHERE id='.$planetid);
		$cargo = min($cargo_space, $nr);
		$affected = 0;
		foreach ($ships as $ship) {
			if ($cargo <= 0) break;
			db("UPDATE ships AS ships_w SET $type=".($ship[$type] + min($ship['cargo_bays'] - $ship['cargo'], $cargo) ).' WHERE id='.$ship['id']);
			$cargo -= min($ship['cargo_bays'] - $ship['cargo'], $cargo);
			$affected += mysql_affected_rows();
		}
		$cargo = min($cargo_space, $nr);
		if ($affected == 1)
			$objResponse->alert("One ship has loaded a total of $cargo $type");
		else
			$objResponse->alert($affected." ships have loaded a total of $cargo $type");
	} else { //if ($cargodirection == _2PLANET)
		db("SELECT ships.id, ships.$type FROM ships, planets WHERE ships.user_id=$userid AND planets.id=$planetid AND ships.node=planets.node AND (towed_by=$commandship OR ships.id=$commandship)");
		$ships = dbr(1);
		$cargo = 0;
		foreach ($ships as $ship)
			$cargo += $ship[$type];
		//if ($cargo >= 4294967295) return;
		db("UPDATE planets AS planets_w SET $type = $type + $cargo WHERE id=".$planetid);
		foreach ($ships as $ship)
			db("UPDATE ships AS ships_w SET $type=0 WHERE id=".$ship['id']);
		$objResponse->alert((count($ships)==1?'One ship':count($ships).' ships')." sent $cargo $type to the planet.");
	}
	db("UNLOCK TABLES");
	$objResponse->assign('details', 'innerHTML', print_planet_details($planetid, $userid));
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	
	return $objResponse;
}
/*function set_tax($session, $planetid, $value) {
	$value = min(99, max(0, (int)$value));
	$planetid = (int)$planetid;

	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();

	db_connect();
	// set to a big value (that can not be normaly be there) - to bypass the 'smart' MySQL
	db("UPDATE planets, users, ships SET planets.tax=101
	WHERE session='".dbesc($session)."' AND users.id=planets.user_id AND planets.id=$planetid AND users.id=ships.user_id AND ships.node=planets.node");
	// set to the desired value
	db("UPDATE planets, users, ships SET planets.tax=$value
	WHERE session='".dbesc($session)."' AND users.id=planets.user_id AND planets.id=$planetid AND users.id=ships.user_id AND ships.node=planets.node");
	if (mysql_affected_rows() > 0) {
		$objResponse->assign('tax', 'value', $value);
		$objResponse->alert("Tax has been set to $value%");
	} else
		$objResponse->alert('The tax has not been modified.');
	return $objResponse;
}*/
function normal_attack($session, $dShipId) {
//attack between 2 fleets OR 1 fleet and a ship OR 2 ships
	// variables with "a" describe the Attacker
	// variables with "d" describe the Defender
	$dShipId = (int)$dShipId;
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$txt='';
	//get details abiut the users
	$q=dbq("SELECT users.id, users.name, users.ship, turn_nr, node FROM users, ships WHERE session='".dbesc($session)."' AND ships.id=users.ship");
	list($aId, $aName, $aCommandedShipId, $turn_nr, $node)=mysql_fetch_row($q);
	$q=dbq("SELECT users.id, users.name FROM users, ships WHERE ships.id=$dShipId AND users.id=ships.user_id");
	list($dId, $dName)=mysql_fetch_row($q);
	//check if the fight can happen
	if ($node > 0) {
		//log the attack
		db("UPDATE system SET value=value+1 WHERE name='normal_attacks'");
		//get the ships
		$selectQuery = "SELECT id, name, fighters, turrets, shield, stealth, detector FROM ships ";
		db($selectQuery."WHERE ships.user_id=$aId AND ships.node=$node AND (ships.id=$aCommandedShipId OR ships.towed_by=$aCommandedShipId)");
		$aShips = dbr(1);
		db($selectQuery."WHERE ships.user_id=$dId AND ships.node=$node");
		$dShips = dbr(1);
		//re-order the ships for a bit of diversity :P
		shuffle($aShips);
		shuffle($dShips);
		//group the ships
		$fights=array();
		$aShipsNr = count($aShips);
		$dShipsNr = count($dShips);
		$nrMin = min($aShipsNr, $dShipsNr);
		$nrMax = max($aShipsNr, $dShipsNr);
		for ($i=0; $i<$nrMin; $i+=1)
			$fights[]=array(array(&$aShips[$i]), array(&$dShips[$i]));
		$side = ($i<$aShipsNr)?'a':($i<$dShipsNr)?'d':'';
		if (!empty($side)) {
			$j = 0;
			while ($i < $nrMax) {
				if ($j >= $nrMin)
					$j = 0;
				list($a, $d) = $fights[$j];
				${$side}[] = &${$side.'Ships'}[$i];
				$fights[$j] = array($a, $d);
				$i += 1;
				$j += 1;
			}
		}
		
		//$txt.='$fights = '.s_var_dump($fights, true).'<br>';
		
		//print the fights
		$txt.='Fights:<table border=1>';
		$txt.='<tr><td></td><td>Attackers</td><td>Defenders</td></tr>';
		foreach ($fights as $k=>$fight) {
			list ($attacker, $defender) = $fight;
			$txt.="<tr><td>#$k</td><td>";
			foreach ($attacker as $ship)
				$txt.=$ship['name'].'<br>';
			$txt.='</td><td>';
			foreach ($defender as $ship)
				$txt.=$ship['name'].'<br>';
			$txt.='</td></tr>';
		}
		$txt.='</table>';
		//execute the fights
		$txt.='Details:<br>';
		foreach ($fights as $k=>$fight) {
			$txt.="<big>fight #$k:</big><br>";
			//list ($attacker, $defender) = $fight;
			$attacker = &$fight[0];
			$defender = &$fight[1];
			$aNr = count($attacker);
			$dNr = count($defender);
			$aDmg = $dDmg = 0;
			$aStealth = $dStealth = $aDetector = $dDetector = 0;
			foreach ($attacker as $ship) {
				$aDmg += $ship['fighters'];
				$aStealth = max($aStealth, $ship['stealth']);
				$aDetector = max($aDetector, $ship['detector']);
			}
			foreach ($defender as $ship) {
				$dDmg += $ship['fighters'];
				$dStealth = max($dStealth, $ship['stealth']);
				$dDetector = max($dDetector, $ship['detector']);
			}
			$txt.="potential attacker firepower $aDmg<br>";
			$txt.="potential defender firepower $dDmg<br>";

			$X = min($aDmg, $dDmg);
			$X -= mt_rand(0, (int)floor(0.1 * $X));
			$aDmg -= $X;
			$dDmg -= $X;
			$aDmg = floor($aDmg / $dNr) * $dNr;
			$dDmg = floor($dDmg / $aNr) * $aNr;
			
			$txt.="<br>";
			
			$txt.="attacker firepower $aDmg<br>";
			$txt.="defender firepower $dDmg<br>";
			$txt.='<hr>';
			$aliveDefenders = $dNr;
			//defender takes damage
			for ($i=0; $i<$dNr; $i+=1) {
				$ship = &$defender[$i];
				$dmg = (int)floor($aDmg / $dNr);
				$txt.="Defender's ship <b>$ship[name]</b> is attacked by $dmg fighters.<br>";
				$dmg -= $defender[$i]['turrets'] - mt_rand(0, (int)floor(0.08 * $defender[$i]['turrets']));
				$remainingTurrets = max(0, -1 * $dmg);
				$dmg = max(0, $dmg);
				$txt.="The $ship[turrets] turrets counter the fighters. There are $dmg fighters attacking now.<br>";
				if ($aStealth > $dDetector)
					$defender[$i]['shield'] -= mt_rand((int)floor(0.05 * $defender[$i]['shield']), (int)floor(0.1 * $defender[$i]['shield']));
				elseif ($dStealth > $aDetector)
					$dmg -= mt_rand((int)floor(0.05 * $defender[$i]['shield']), (int)floor(0.1 * $defender[$i]['shield']));
				$dmg = max(0, $dmg);
				$txt.="After the stealth modifier there are $dmg fighters attacking.<br>";
				$defender[$i]['shield'] -= $dmg;
				if ($defender[$i]['shield'] < 0) {
					$defender[$i]['dead'] = true;
					$aliveDefenders -= 1;
					$txt.="Defender's ship <b>$ship[name]</b> was destroyed<br>";
				} else {
					$defender[$i]['dead'] = false;
					$txt.="The ship <b>$ship[name]</b>'s shield absorbs all the damage. The ship has now $ship[shield] shield<br>";
					$dDmg += $remainingTurrets;
					$txt.="The defender's remaining $remainingTurrets turrets now attack.";
				}
				$txt.='<br>';
			}
			$txt.='<hr>';
			//attacker takes damage
			if ($aliveDefenders > 0) {
				$aliveAttackers = $aNr;
				$aDmg = 0;
				for ($i=0; $i<$aNr; $i+=1) {
					$ship = &$attacker[$i];
					$dmg = (int)floor($dDmg / $aNr);
					$txt.="Attacker's ship <b>$ship[name]</b> is attacked by $dmg fighters / turrets.<br>";
					$dmg -= $attacker[$i]['turrets'] - mt_rand(0, (int)floor(0.08 * $attacker[$i]['turrets']));
					$remainingTurrets = max(0, -1 * $dmg);
					$dmg = max(0, $dmg);
					$txt.="The $ship[turrets] turrets counter the attack. There are $dmg fighters / turrets attacking now.<br>";
					$attacker[$i]['shield'] -= $dmg;
					if ($attacker[$i]['shield'] < 0) {
						$attacker[$i]['dead'] = true;
						$aliveAttackers -= 1;
						$txt.="Attacker's ship <b>$ship[name]</b> was destroyed<br>";
					} else {
						$attacker[$i]['dead'] = false;
						$aDmg += $remainingTurrets;
						$txt.="The ship <b>$ship[name]</b>'s shield absorbs all the damage. The ship has now $ship[shield] shield<br>";
					}
				}
				if ($aliveAttackers > 0) {
					$txt.='<hr>';
					//defender takes damage again
					for ($i=0; $i<$dNr; $i+=1) {
						if ($defender[$i]['dead'])
							continue;
						$ship = &$defender[$i];
						$dmg = (int)floor($aDmg / $aliveDefenders);
						$txt.="Defender's ship <b>$ship[name]</b> is attacked by $dmg turrets.<br>";
						$defender[$i]['shield'] -= $dmg;
						if ($defender[$i]['shield'] < 0) {
							$txt.="Defender's ship <b>$ship[name]</b> was destroyed<br>";
							$defender[$i]['dead'] = true;
							$aliveDefenders -= 1;
						} else {
							$defender[$i]['dead'] = false;
							$txt.="The ship <b>$ship[name]</b>'s shield absorbs all the damage. The ship has now $ship[shield] shield<br>";
						}
					}
				}
			}
		}
		//$txt.='$attacker = '.s_var_dump($aShips, true).'<br>';
		//$txt.='$defender = '.s_var_dump($dShips, true).'<br>';

		$reassign = false;
		foreach ($aShips as $ship)
			if ( isset($ship['dead']) && $ship['dead'] ) {
				db("DELETE FROM ships WHERE id=$ship[id] LIMIT 1");
				//if the attacker lost the commanded ship
				if ($ship['id'] == $aCommandedShipId)
					$reassign = true;
				$aShipsNr -= 1;
			} else
				db("UPDATE ships SET fighters=$ship[fighters], shield=$ship[shield] WHERE id=$ship[id]");
		if ($reassign && ($aShipsNr > 0)) {
			$q = dbq("SELECT id FROM ships WHERE user_id=$aId LIMIT 1");
			list($newshipid) = mysql_fetch_row($q);
			db("UPDATE users SET ship=$newshipid WHERE id=$aId");
		}
		$reassign = false;
		foreach ($dShips as $ship)
			if (isset($ship['dead']) && $ship['dead']){
				db("DELETE FROM ships WHERE id=$ship[id] LIMIT 1");
				//if the defender lost the commanded ship
				if ($ship['id'] == $aCommandedShipId)
					$reassign = true;
				$dShipsNr -= 1;
			}else
				db("UPDATE ships SET fighters=$ship[fighters], shield=$ship[shield] WHERE id=$ship[id]");
		if ($reassign && ($dShipsNr > 0)) {
			$q = dbq("SELECT id FROM ships WHERE user_id=$dId LIMIT 1");
			list($newshipid) = mysql_fetch_row($q);
			db("UPDATE users SET ship=$newshipid WHERE id=$dId");
		}
		// i assume not both have been destroyed
		if ($aShipsNr <= 0)
			$destroyedId = $aId;
		elseif ($dShipsNr <= 0)
			$destroyedId = $dId;
		else
			$destroyedId = NULL;
		if ( ($aShipsNr + $dShipsNr) <= 0 ) // just to check a potential bug
			$txt.='<hr>There seems to be a bug ... both the defender and the attacker lost all ships ... this is a serious bug that will prevent you from logging in. Send this battle report to the webmaster.';
		// make new ship for the person with no more ships
		if ($destroyedId != NULL){
			$q = dbq("SELECT COUNT(*) FROM ships WHERE user_id=$destroyedId");
			list($nr) = mysql_fetch_row($q);
			if ($nr < 1){
				db("INSERT INTO ships (user_id, node, type, fighters, max_fighters, turrets, shield, max_shield, shield_regen, cargo_bays, mine_speed, upgrades_left, name)
				VALUES ($destroyedId, 0, 1, 5, 10, 5, 5, 10, 1, 50, 7, 1, 'Destroyed')");
				$newshipid = mysql_insert_id();
				db("UPDATE users SET ship=$newshipid WHERE id=$destroyedId");
			}
		}

	} else {
		$txt.='Attacking while in solar system 0 is forbidden';
	}
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', $txt);
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	return $objResponse;
}
function mine($session, $flags) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$q = dbq("SELECT users.id, users.ship FROM users WHERE session='".dbesc($session)."'");
	list($id, $commanded) = mysql_fetch_row($q);
	if ( ((bool)($flags & METAL)) && ((bool)($flags & ANTIMATTER)) )
		$objResponse->alert('Error on flag processing !');
	else
		if ($flags & SHIP) {
			db("UPDATE ships SET mine_mode=".($flags & (METAL | ANTIMATTER))." WHERE id=$commanded AND user_id=$id");
		} elseif ($flags & FLEET) {
			db("UPDATE ships SET mine_mode=".($flags & (METAL | ANTIMATTER))." WHERE (id=$commanded OR towed_by=$commanded) AND user_id=$id");
		} else
			$objResponse->alert('Error on flag processing');
	$objResponse->assign('resources', 'innerHTML', print_resources($session));
	return $objResponse;
}
function buysell_resources() {
	$objResponse = new xajaxResponse();

	function get_type($resource) {
		switch ($resource) {
			case METAL:
				$type = 'metal';
				break;
			case ANTIMATTER:
				$type = 'antimatter';
				break;
			case ORGANICS:
				$type = 'organics';
				break;
			case COLONISTS:
				$type = 'colonists';
				break;
			case FIGHTERS:
				$type = 'fighters';
				break;
			case GENESIS_DEVICE:
				$type = 'genesis';
				break;
			default:
				$type = false;
		}
		return $type;
	}

	require_once "functions.inc.php";
	$args = func_get_args();
	$nr = func_num_args()-1; // the last argument is for flags
	$resources = array();
	$i = 0;
	$session = $args[$i++];
	db_connect();
	while ($i < $nr)
		$resources[] = array('name'=>(int)$args[$i++], 'quantity'=>(int)ltrim($args[$i++]));
	$flags = (int)$args[$i++];
	$q = dbq("SELECT id, ship, credits FROM users WHERE session='".dbesc($session)."'");
	list($userid, $commanded, $credits) = mysql_fetch_row($q);
//	if ($flags & SHIP)
//		db("SELECT * FROM ships WHERE id=$commanded AND user_id=$userid");
//	elseif ($flags & FLEET)
//		db("SELECT * FROM ships WHERE user_id=$userid AND (id=$commanded OR towed_by=$commanded)");
//	else {
//		ajax_error($objResponse, "invalid flags\r\nLine ".__LINE__.".");
//		return $objResponse;
//	}
	db("SELECT ships.id, ".query_ship_cargo('cargo').", ships.cargo_bays, ships.metal, ships.antimatter, ships.organics, ships.colonists, ships.max_fighters, ships.fighters, ships.genesis 
	FROM ships WHERE user_id=$userid AND (id=$commanded OR towed_by=$commanded)");
	$ships = array();
	foreach (dbr(1) as $ship)
		$ships[$ship['id']] = $ship;
	db("SELECT variable, value FROM config");
	$cfg = array();
	foreach (dbr() as $var) $cfg[$var[0]]=$var[1];
	$decreases_cargo = array('metal'=>true, 'antimatter'=>true, 'organics'=>true, 'colonists'=>true, 'fighters'=>false, 'genesis'=>true); // must include all types of cargo
	foreach ($resources as $resource) {
		$type = get_type($resource['name']);
		if ($type === false) {
			ajax_error($objResponse, "invalid resource\r\nLine ".__LINE__.".");
			return $objResponse;
		}
		if ($flags & SHIP) {
			if ($flags & BUY) {
				if ( $credits < ($cfg[$type.'_sell_price'] * $resource['quantity']) ) {
					$objResponse->alert('You do not have enough credits.');
					return $objResponse;
				} elseif ( ($decreases_cargo[$type]) && (($ships[$commanded]['cargo_bays'] - $ships[$commanded]['cargo']) < $resource['quantity']) ) {
					$objResponse->alert('Your ship can not hold this much cargo.');
					return $objResponse;
				} elseif ( ($type == 'fighters') && (($resource['quantity'] + $ships[$commanded][$type]) > $ships[$commanded]['max_fighters']) ) {
					$objResponse->alert('Your ship can not hold this much fighters.');
					return $objResponse;
				} elseif ($decreases_cargo[$type]) { // it's a resource
					$ships[$commanded]['cargo'] += $resource['quantity'];
				}
				$credits -= $cfg[$type.'_sell_price'] * $resource['quantity'];
				$ships[$commanded][$type] += $resource['quantity'];
			} else { //SELL - only resources
				if (!$decreases_cargo[$type]) {
					$objResponse->alert('You are not allowed to sell this.');
					return $objResponse;
				} elseif ($type == 'colonists') {
					$objResponse->alert('Selling colonists (people) is a no no.');
					return $objResponse;
				} elseif ($type == 'genesis') {
					$objResponse->alert('Selling genesis devices is considered illegal.');
					return $objResponse;
				} elseif ($ships[$commanded][$type] < $resource['quantity']) {
					$objResponse->alert('Your can not sell more then you own.');
					return $objResponse;
				} else {
					$ships[$commanded][$type] -= $resource['quantity'];
					$credits += $cfg[$type.'_buy_price'] * $resource['quantity'];
				}
			}
		}
	}
	if (($flags & CONFIRMED) && !($flags & CONFIRM)) {
		db("UPDATE users SET credits=$credits WHERE id=$userid");
		$buffer = '';
		foreach ($decreases_cargo as $cargo=>$w00tever)
			$buffer.=$cargo.'='.$ships[$commanded][$cargo].',';
		$buffer = substr($buffer, 0, -1);
		db("UPDATE ships SET $buffer WHERE id=$commanded");
		
		$objResponse->script("popup();");
		$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
		$objResponse->assign('details', 'innerHTML', print_utility_details($session, $flags & UTILITY_FLAGS));
	} elseif (($flags & CONFIRM) && !($flags & CONFIRMED)) {
		if ($flags & BUY)
			$txt='Are you sure you want to buy the following ?<br>';
		else
			$txt='Are you sure you want to sell the following ?<br>';
		// put OK and CANCEL buttons
		$txt.='<center><input type="button" value="^YES^" class="popupbutton" onclick="xajax_buysell_resources(lsn';
		foreach ($resources as $resource)
			$txt.=",$resource[name],$resource[quantity]";
		$txt.=','.(CONFIRMED | $flags ^ CONFIRM).')"> <input type="button" value="^NO^" class="popupbutton" onclick="popup()"></center>';
		foreach ($resources as $resource)
			$txt.=$resource['quantity'].' '.get_type($resource['name']).'<br>';
		$txt.="<hr>After this you will have:<br>$credits credits<br>";
		foreach ($resources as $resource) {
			$type = get_type($resource['name']);
			$txt.=$ships[$commanded][$type]." $type<br>";
		}
		text_translate($txt,$session);
		// the following must be done so that the JS function will recive a valid string
		$txt = str_replace(array("\r\n", "\n", "\r"), '<br>', $txt);
		$objResponse->script("popup('".addslashes($txt)."');");
	} else {
		$objResponse->script("popup();");
		$objResponse->assign('details', 'innerHTML', 'ERROR. report this to the webmaster<br>note: agreed = '.$agreed.' line: '.__LINE__);
	}
	return $objResponse;
}
function deploy_bomb($session, $type) {
	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$q = mysql_query("SELECT node, users.id, users.ship FROM users, ships WHERE session='".dbesc($session)."' AND users.ship=ships.id");
	list($node, $userid, $commanded) = mysql_fetch_row($q);

	if ($node == 0) {
		$objResponse->alert('Deploying in solar system 0 is forbidden.');
		return $objResponse;
	}
	
	db("SELECT variable, value FROM config");
	$cfg = array();
	foreach (dbr() as $var) $cfg[$var[0]]=$var[1];

	switch ($type) {
		case GENESIS_DEVICE:
			$q = mysql_query("SELECT genesis FROM ships WHERE id=$commanded");
			list($genesis) = mysql_fetch_row($q);
			if ($genesis > 0) {
				$genesis -= 1;
				$image = mt_rand(1, $cfg['max_planet_img']).'.jpg';
				db("INSERT INTO planets (node, user_id, colonists, name, image) VALUES ($node, $userid, 100, 'Just Created', '$image')");
				db("UPDATE ships SET genesis=$genesis WHERE id=$commanded");
				$objResponse->alert('Planet created successfully.');
				$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
				$objResponse->assign('menuright', 'innerHTML', print_menuright($session));
				$objResponse->assign('details', 'innerHTML', print_solar_system($session));
			} else {
				db("UPDATE ships SET genesis=0 WHERE id=$commanded");
				$objResponse->alert('This ship does not carry a genesis device.');
				$objResponse->assign('menuright', 'innerHTML', print_menuright($session));
			}
			
			break;
		default:
			ajax_error($objResponse,"Bomb type not found\r\nLine ".__LINE__.".");
	}
	return $objResponse;
}
function set_colonists($session, $planetid, $col_fighters, $col_organics) {
	$planetid = (int)$planetid;

	require_once "functions.inc.php";
	$objResponse = new xajaxResponse();
	db_connect();
	$q = dbq("SELECT id FROM users WHERE users.session='".dbesc($session)."'");
	list($userid)=mysql_fetch_row($q);
	if ($userid === false) {
		ajax_error($objResponse, "Coud not process...\r\nSession expired\r\nLine ".__LINE__.".");
		return $objResponse;
	}
	db("SELECT colonists FROM planets WHERE id=$planetid");
	list($colonists) = dbrow();

	clamp_colonists($colonists, $col_fighters, $col_organics);
	
	db("UPDATE planets, users SET col_fighters=$col_fighters, col_organics=$col_organics WHERE planets.id=$planetid AND planets.user_id=$userid");
	if (mysql_affected_rows() < 0)
		$objResponse->alert('The update FAILED !');
	else
		$objResponse->alert('You have successfully set the colonists work division.');
	$objResponse->assign('details', 'innerHTML', print_planet_details($planetid, $userid));
	return $objResponse;
}
function show_variables($session) {
	require_once "functions.inc.php";
	db_connect();
	db("SELECT * FROM config");
	$cfg = dbr(1);
	$txt='';
	foreach ($cfg as $var) {
		$txt.='<table border=0 width="100%" class="shop"><tr>';
		$txt.="<th class='shop'>variable</th><td colspan=2 class='shop'>$var[variable]</td>";
		$txt.='</tr><tr>';
		$txt.='<th width="30%" class="shop">value</th><th class="shop">min</th><th class="shop">max</th>';
		$txt.='</tr><tr>';
		$txt.="<td class='shop'>$var[value]</td><td class='shop' width='30%'>$var[min]</td><td class='shop'>$var[max]</td>";
		$txt.='</tr><tr>';
		$txt.="<th colspan=3 class='shop'>description</th>";
		$txt.='</tr><tr>';
		$txt.="<td colspan=3 class='shop'>$var[description]</td>";
		$txt.='</tr></table><hr>';
	}
	$txt.='</table>';
	$objResponse = new xajaxResponse();
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', $txt);
	return $objResponse;
}
function update($session, $count) {
	require_once "functions.inc.php";
	require_once ('class.visitorcounter.inc.php');
	$counter = new VisitorCounter;
	$objResponse = new xajaxResponse();
	$objResponse->assign('menu_details', 'innerHTML', print_menu_details($session));
	$objResponse->assign('visitor_counter', 'innerHTML', $counter->show());
	return $objResponse;
}
function show_all_ships($session, $sort) {
	require_once "functions.inc.php";
	db_connect();
	db('SELECT id FROM users WHERE session="'.dbesc($session).'"');
	list($userid) = dbrow();
	$objResponse = new xajaxResponse();
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', print_all_ships($userid, $sort));
	$objResponse->script('show_list_memory();');
	$objResponse->script("all_ships_sort = $sort;");
	return $objResponse;
}
function show_all_planets($session) {
	require_once "functions.inc.php";
	db_connect();
	db('SELECT id FROM users WHERE session="'.dbesc($session).'"');
	list($userid) = dbrow();
	$objResponse = new xajaxResponse();
	ajax_map_display($objResponse, false);
	$objResponse->assign('details', 'innerHTML', print_all_planets($userid));
	$objResponse->script('show_list_memory();');
	return $objResponse;
}
require("ajax.inc.php");
$xajax->processRequest();
?>