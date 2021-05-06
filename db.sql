-- phpMyAdmin SQL Dump
-- version 2.7.0-pl1
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Jun 29, 2008 at 12:06 AM
-- Server version: 4.1.20
-- PHP Version: 4.3.9
-- 
-- Database: `tbog`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `config`
-- 

DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `variable` varchar(38) NOT NULL default '',
  `value` smallint(6) NOT NULL default '0',
  `min` smallint(6) NOT NULL default '0',
  `max` smallint(6) NOT NULL default '0',
  `description` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`variable`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `map`
-- 

DROP TABLE IF EXISTS `map`;
CREATE TABLE `map` (
  `id` smallint(5) unsigned NOT NULL default '0',
  `name` varchar(30) NOT NULL default 'planet name',
  `x` smallint(5) unsigned NOT NULL default '0',
  `y` smallint(5) unsigned NOT NULL default '0',
  `link1` smallint(5) unsigned default NULL,
  `link2` smallint(5) unsigned default NULL,
  `link3` smallint(5) unsigned default NULL,
  `link4` smallint(5) unsigned default NULL,
  `link5` smallint(5) unsigned default NULL,
  `link6` smallint(5) unsigned default NULL,
  `utilities` tinyint(3) unsigned NOT NULL default '0',
  `metal` int(10) unsigned NOT NULL default '0',
  `antimatter` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `messages`
-- 

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `reply_at` int(10) unsigned default NULL,
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `from` mediumint(8) unsigned default NULL,
  `viewed` enum('yes','no') NOT NULL default 'no',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `subject` varchar(63) NOT NULL default '[none]',
  `message` varchar(255) NOT NULL default '[empty message]',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `names_list`
-- 

DROP TABLE IF EXISTS `names_list`;
CREATE TABLE `names_list` (
  `name` varchar(30) NOT NULL default ''
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `planets`
-- 

DROP TABLE IF EXISTS `planets`;
CREATE TABLE `planets` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `node` smallint(5) unsigned NOT NULL default '0',
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `colonists` int(10) unsigned NOT NULL default '0' COMMENT 'all colonists',
  `col_fighters` int(10) unsigned NOT NULL default '0',
  `col_organics` int(10) unsigned NOT NULL default '0',
  `fighters` int(10) unsigned NOT NULL default '0',
  `tax` tinyint(3) unsigned NOT NULL default '0' COMMENT '% taken from colonists production',
  `metal` int(10) unsigned NOT NULL default '0',
  `antimatter` int(10) unsigned NOT NULL default '0',
  `organics` int(10) unsigned NOT NULL default '0',
  `name` varchar(30) NOT NULL default 'planet name',
  `image` varchar(30) NOT NULL default '',
  `character` enum('aggressive','passive') NOT NULL default 'passive',
  PRIMARY KEY  (`id`),
  KEY `node` (`node`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `ship_types`
-- 

DROP TABLE IF EXISTS `ship_types`;
CREATE TABLE `ship_types` (
  `id` tinyint(3) unsigned NOT NULL auto_increment,
  `max_fighters` smallint(5) unsigned NOT NULL default '0',
  `shield_regen` tinyint(3) unsigned NOT NULL default '0',
  `max_shield` smallint(5) unsigned NOT NULL default '0',
  `upgrades` tinyint(3) unsigned NOT NULL default '0',
  `cargo` smallint(5) unsigned NOT NULL default '0',
  `detector` tinyint(4) NOT NULL default '0',
  `stealth` tinyint(4) NOT NULL default '0',
  `mine_speed` tinyint(3) unsigned NOT NULL default '0',
  `turrets` smallint(5) unsigned NOT NULL default '0',
  `name` varchar(30) NOT NULL default '',
  `abvr` varchar(5) NOT NULL default '',
  `price` mediumint(8) unsigned NOT NULL default '0',
  `image` varchar(30) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `ships`
-- 

DROP TABLE IF EXISTS `ships`;
CREATE TABLE `ships` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `user_id` mediumint(8) unsigned NOT NULL default '0',
  `node` smallint(5) unsigned NOT NULL default '0',
  `towed_by` int(10) unsigned default NULL,
  `type` tinyint(3) unsigned NOT NULL default '0',
  `fighters` smallint(5) unsigned NOT NULL default '0',
  `max_fighters` smallint(5) unsigned NOT NULL default '0',
  `turrets` smallint(5) unsigned NOT NULL default '0',
  `shield` smallint(5) unsigned NOT NULL default '0',
  `max_shield` smallint(5) unsigned NOT NULL default '0',
  `shield_regen` tinyint(3) unsigned NOT NULL default '0',
  `colonists` smallint(5) unsigned NOT NULL default '0',
  `metal` smallint(5) unsigned NOT NULL default '0',
  `antimatter` smallint(5) unsigned NOT NULL default '0',
  `organics` smallint(5) unsigned NOT NULL default '0',
  `cargo_bays` smallint(5) unsigned NOT NULL default '0' COMMENT 'max cargo capacity',
  `genesis` tinyint(3) unsigned NOT NULL default '0',
  `stealth` tinyint(3) unsigned NOT NULL default '0',
  `detector` tinyint(3) unsigned NOT NULL default '0',
  `mine_speed` tinyint(3) unsigned NOT NULL default '0',
  `mine_mode` tinyint(3) unsigned NOT NULL default '0',
  `upgrades_left` tinyint(3) unsigned NOT NULL default '0',
  `name` varchar(30) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `user_id` (`user_id`,`node`),
  KEY `towed` (`towed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `system`
-- 

DROP TABLE IF EXISTS `system`;
CREATE TABLE `system` (
  `name` char(20) NOT NULL default '',
  `value` int(11) NOT NULL default '0',
  PRIMARY KEY  (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `users`
-- 

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` mediumint(8) unsigned NOT NULL auto_increment,
  `name` varchar(30) character set latin1 collate latin1_general_ci NOT NULL default '',
  `pass` varchar(128) character set latin1 collate latin1_bin NOT NULL default '',
  `session` varchar(39) character set latin1 collate latin1_bin NOT NULL default '',
  `ship` int(10) unsigned NOT NULL default '0',
  `turn_nr` smallint(5) unsigned NOT NULL default '0',
  `max_turns` smallint(5) unsigned NOT NULL default '1',
  `credits` int(10) unsigned NOT NULL default '0',
  `autowarp` varchar(255) default NULL,
  `last_login` datetime NOT NULL default '0000-00-00 00:00:00',
  `last_ip` varchar(40) NOT NULL default 'no ip',
  `translate` varchar(20) NOT NULL default 'default',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `users_to_iplist`
-- 

DROP TABLE IF EXISTS `users_to_iplist`;
CREATE TABLE `users_to_iplist` (
  `user_id` int(10) unsigned NOT NULL default '0',
  `ip_id` int(10) unsigned NOT NULL default '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `visitors`
-- 

DROP TABLE IF EXISTS `visitors`;
CREATE TABLE `visitors` (
  `ip` varchar(40) NOT NULL default '',
  `id` int(10) unsigned NOT NULL auto_increment,
  `lastvisit` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`ip`),
  KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
