<?php
# start the timer
$time_start = microtime(true);

require_once "functions.inc.php";
db_connect();
$cfg = array();
db("SELECT variable, value FROM config");
foreach (dbr() as $var) $cfg[$var[0]]=$var[1];
define('NR_TURN_INCREASE',						$cfg['hourly_turn_increase']);					// number of turns added
define('PROC_MINESPEED_RAND',			(float) $cfg['random_percent_mine_speed'] / 100.0);		// small random factor taken from mine_speed
define('PROC_MINE_SPEED_METAL',			(float) $cfg['metal_mine_speed'] / 100.0);				// metal mine speed
define('PROC_MINE_SPEED_ANTIMATTER',	(float) $cfg['antimatter_mine_speed'] / 100.0);			// antimatter mine speed
define('PROC_COLONISTS_IDLE1',			(float) $cfg['colonists_reproduction_min'] / 100.0);		// new colonists minimum
define('PROC_COLONISTS_IDLE2',			(float) $cfg['colonists_reproduction_max'] / 100.0);		// new colonists maximum
define('PROC_COLONISTS_ORGANICS1',		(float) $cfg['colonists_organics_min'] / 100.0);			// how many colonists dye to make organics (minimum)
define('PROC_COLONISTS_ORGANICS2',		(float) $cfg['colonists_organics_max'] / 100.0);			// how many colonists dye to make organics (maximum)
define('PROC_COLONISTS_FIGHTERS1',		(float) $cfg['colonists_fighters_min'] / 100.0);			// how many colonists dye to make fighters (minimum)
define('PROC_COLONISTS_FIGHTERS2',		(float) $cfg['colonists_fighters_max'] / 100.0);			// how many colonists dye to make fighters (maximum)
define('PROC_NEW_FIGHTERS',				(float) $cfg['fighters_production'] / 100.0);			// % of new ships from colonists
define('PROC_NEW_ORGANICS',				(float) $cfg['organics_production'] / 100.0);			// % of new organics from colonists
define('FIGHTERS_PRODUCTION_COST_METAL',		$cfg['fighters_production_cost_metal']);		// multiplied with the fighters production
define('FIGHTERS_PRODUCTION_COST_ANTIMATTER',	$cfg['fighters_production_cost_antimatter']);	// multiplied with the fighters production
define('ORGANICS_PRODUCTION_COST_COLONISTS',	$cfg['organics_production_cost_colonists']);	// multiplied with the organics production

// (float)$cfg[''] / 100.0
db("UPDATE users SET turn_nr=IF((turn_nr+".NR_TURN_INCREASE.") < max_turns, (turn_nr+".NR_TURN_INCREASE."), max_turns)");
//db("UPDATE ships SET shield=IF((shield+shield_regen) < max_shield, (shield+shield_regen), max_shield)"); // this is done later

# i will store here the changes made to solar systems by the ships (like mining)
$node = array();
# check every ship
$query = dbq("SELECT id, node, shield, shield_regen, max_shield, colonists, metal, antimatter, organics, mine_mode, mine_speed, cargo_bays, ".query_ship_cargo('cargo')." FROM ships");
while ($ship = mysql_fetch_assoc($query)) {
	// echo '<pre>ship before update: ';
	// print_r($ship);
	# shield regenerate
	$ship['shield'] = min($ship['shield'] + $ship['shield_regen'], $ship['max_shield']);
	# get info about solar system (for minimg)
	$node_q = dbq("SELECT metal, antimatter FROM map WHERE id=".$ship['node']);
	$node = mysql_fetch_assoc($node_q);
	# mining
	if ($ship['mine_mode'] != NOT_MINING) {
		if ($ship['mine_mode'] == METAL)
			$ship['mine_speed'] *= PROC_MINE_SPEED_METAL;
		elseif ($ship['mine_mode'] == ANTIMATTER)
			$ship['mine_speed'] *= PROC_MINE_SPEED_ANTIMATTER;
			
		$ship['mine_speed'] -= mt_rand(0, (int)round(PROC_MINESPEED_RAND * $ship['mine_speed']));
		
		$mine_quantity = min($ship['cargo'] + $ship['mine_speed'], $ship['cargo_bays']) - $ship['cargo'];
		if ($ship['mine_mode'] == METAL) {
			$mine_quantity = min($mine_quantity, $node['metal']);
			$node['metal'] -= $mine_quantity;
			$ship['metal'] += $mine_quantity;
		} elseif ($ship['mine_mode'] == ANTIMATTER) {
			$mine_quantity = min($mine_quantity, $node['antimatter']);
			$node['antimatter'] -= $mine_quantity;
			$ship['antimatter'] += $mine_quantity;
		}
		# update solar system
		db("UPDATE map SET metal=$node[metal], antimatter=$node[antimatter] WHERE id=$ship[node]");
	}
	# update ship (do not change mine_speed)
	db("UPDATE ships SET metal=$ship[metal], antimatter=$ship[antimatter], shield=$ship[shield] WHERE id=$ship[id]");
	// echo 'ship after update: ';
	// print_r($ship);
	// echo '</pre>';
	// echo "<br>";
}

# i will store here the changes made to planets
$planets = array();
# check every planet
$query = dbq("SELECT id, colonists, col_fighters, col_organics, fighters, metal, antimatter, organics FROM planets");
while ($planet = mysql_fetch_assoc($query)) {
	// echo '<pre>planet before update: ';
	// print_r($planet);
	$planet['col_idle'] = $planet['colonists'] - $planet['col_organics'] - $planet['col_fighters'];
	# fighters production
	$new_fighters = $planet['col_fighters'] * PROC_NEW_FIGHTERS;
	$new_fighters = (int)min($new_fighters, (float)$planet['metal'] / (float)FIGHTERS_PRODUCTION_COST_METAL);
	$new_fighters = (int)min($new_fighters, (float)$planet['antimatter'] / (float)FIGHTERS_PRODUCTION_COST_ANTIMATTER);
	$planet['metal'] -= (int)($new_fighters * FIGHTERS_PRODUCTION_COST_METAL);
	$planet['antimatter'] -= (int)($new_fighters * FIGHTERS_PRODUCTION_COST_ANTIMATTER);
	$planet['fighters'] += $new_fighters;
	$planet['col_fighters'] = (int)((float)$new_fighters / (float)PROC_NEW_FIGHTERS);
	// echo "new_fighters = $new_fighters<br>";
	# organics production
	$new_organics = $planet['col_organics'] * PROC_NEW_ORGANICS;
	$new_organics = (int)min($new_organics, (float)$planet['colonists'] / (float)ORGANICS_PRODUCTION_COST_COLONISTS);
	$planet['colonists'] -= (int)($new_organics * ORGANICS_PRODUCTION_COST_COLONISTS);
	$planet['organics'] += $new_organics;
	$planet['col_organics'] = (int)((float)$new_organics / (float)PROC_NEW_ORGANICS);
	// echo "new_organics = $new_organics<br>";
	# colonist production
	$planet['colonists'] += mt_rand( $planet['col_idle'] * PROC_COLONISTS_IDLE1, $planet['col_idle'] * PROC_COLONISTS_IDLE2 );
	$planet['colonists'] -= mt_rand( $planet['col_organics'] * PROC_COLONISTS_ORGANICS1, $planet['col_organics'] * PROC_COLONISTS_ORGANICS2 );
	$planet['colonists'] -= mt_rand( $planet['col_fighters'] * PROC_COLONISTS_FIGHTERS1, $planet['col_fighters'] * PROC_COLONISTS_FIGHTERS2 );
	$planet['colonists'] = max($planet['colonists'], 0);
	clamp_colonists($planet['colonists'], $planet['col_fighters'], $planet['col_organics']);
	# update planet
	db("UPDATE planets SET colonists=$planet[colonists], col_fighters=$planet[col_fighters], col_organics=$planet[col_organics], fighters=$planet[fighters], metal=$planet[metal], antimatter=$planet[antimatter], organics=$planet[organics] WHERE id=$planet[id]");
	// echo 'planet after update: ';
	// print_r($planet);
	// echo '</pre>';
	// echo "<br>";
}

# log the update
db("UPDATE system SET value=value+1 WHERE name='update_nr'");
# log to a file
$data = @file_get_contents('tbog_log.txt');
if ($data === false)
	$data = '';
$time_end = microtime(true);
$time = $time_end - $time_start;
$data.=date("d.m.Y - h:i:s a")."\t sapi: ".php_sapi_name()."\t duration: $time\r\n";
file_put_contents('tbog_log.txt', $data);
echo "DONE";
?>