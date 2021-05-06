<?php
require_once ("xajax_core/xajax.inc.php");

// set where the functions are defined
$xajax = new xajax("ajax.server.php");

#$xajax->setFlag('debug',true);

// here are all the exported functions
$function_list = array(
'move_to_node',
'init',
'debug',
'change_pass',
'show_ss',
'set_autowarp',
'autowarp_to_node',
'show_planet',
'rename_planet',
'ship_tow',
'ship_release',
'ship_command',
'show_utility',
'buy_ships',
'messages',
'message_dialogue',
'message_compose',
'message_compose_note',
'check_user',
'message_save_note',
'message_send',
'message_delete',
'transfer',
//'set_tax',
'attack',
'mine',
'buysell_resources',
'normal_attack',
'deploy_bomb',
'set_colonists',
'show_variables',
'update',
'show_all_ships',
'show_all_planets'
);
foreach ($function_list as $function_name)
	$xajax->register(XAJAX_FUNCTION, $function_name);
?>