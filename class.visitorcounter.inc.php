<?php
require_once ('functions.inc.php');

class VisitorCounter
{
	//var $sessionTimeInMin = 1440; // time session will live, in minutes (1440 - 1 day)
	var $counter_table = 'visitors';

	function VisitorCounter()
	{
		db_connect();
	}
	
	function Visitor()
	{
		$ip = $_SERVER['REMOTE_ADDR'];

		if ($this->visitorExists($ip))
		{
			$this->updateVisitor($ip);
			return false;
		} else
		{
			return $this->addVisitor($ip);
		}
	}

	function visitorExists($ip)
	{
		$sql = "select * from $this->counter_table where ip = '".dbesc($ip)."'";
		$res = dbq($sql);
		if (mysql_num_rows($res) > 0)
		{
			return true;
		} elseif (mysql_num_rows($res) == 0)
		{
			return false;
		}
	}
/*
	function cleanVisitors()
	{
		db("DELETE FROM $this->counter_table WHERE (".time()." - lastvisit) >= ".$this->sessionTimeInMin * 60);
	}
*/
	function updateVisitor($ip)
	{
		db("update $this->counter_table set lastvisit = '".time()."' where ip = '".dbesc($ip)."'");
	}

	function addVisitor($ip)
	{
		db("insert into $this->counter_table (ip ,lastvisit) value('".dbesc($ip)."', '".time()."')");
		return mysql_insert_id();
	}

	function getAmountVisitors()
	{
		db("select count(*) from $this->counter_table");
		$row = dbrow();
		return $row[0];
	}

	function show()
	{
		$nr = $this->getAmountVisitors();
		return $nr.' visitor'.($nr!=1?'s':'');
	}
}
?>