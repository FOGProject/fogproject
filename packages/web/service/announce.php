<?php
require('../commons/base.inc.php');
/*
* Bitstorm 2 - A small and fast Bittorrent tracker
* Copyright 2011 Peter Caprioli
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

 /*************************
 ** Configuration start **
 *************************/

//Peer announce interval (Seconds)
define('__INTERVAL', 1800);

//Time out if peer is this late to re-announce (Seconds)
define('__TIMEOUT', 120);

//Minimum announce interval (Seconds)
//Most clients obey this, but not all
define('__INTERVAL_MIN', 60);

// By default, never encode more than this number of peers in a single request
define('__MAX_PPR', 20);

 /***********************
 ** Configuration end **
 ***********************/

//Use the correct content-type
header("Content-type: Text/Plain");

//Inputs that are needed, do not continue without these
valdata('peer_id', true);
valdata('port');
valdata('info_hash', true);

//Make sure we have something to use as a key
if (!isset($_GET['key'])) {
	$_GET['key'] = '';
}

//$downloaded = isset($_GET['uploaded']) ? intval($_GET['uploaded']) : 0;
$downloaded = isset($_GET['downloaded']) ? intval($_GET['downloaded']) : 0;
$uploaded = isset($_GET['uploaded']) ? intval($_GET['uploaded']) : 0;
$left = isset($_GET['left']) ? intval($_GET['left']) : 0;

//Validate key as well
valdata('key');

//Do we have a valid client port?
if (!ctype_digit($_GET['port']) || $_GET['port'] < 1 || $_GET['port'] > 65535) {
	die(track('Invalid client port'));
}

//Hack to get comatibility with trackon
if ($_GET['port'] == 999 && substr($_GET['peer_id'], 0, 10) == '-TO0001-XX') {
	die("d8:completei0e10:incompletei0e8:intervali600e12:min intervali60e5:peersld2:ip12:72.14.194.184:port3:999ed2:ip11:72.14.194.14:port3:999ed2:ip12:72.14.194.654:port3:999eee");
}

$DB->query('INSERT INTO `peer` (`hash`, `user_agent`, `ip_address`, `key`, `port`) '
	. "VALUES ('" . $DB->sanitize(bin2hex($_GET['peer_id'])) . "', '" . $DB->sanitize(substr($_SERVER['HTTP_USER_AGENT'], 0, 80)) 
	. "', INET_ATON('" . $DB->sanitize($_SERVER['REMOTE_ADDR']) . "'), '" . $DB->sanitize(sha1($_GET['key'])) . "', " . intval($_GET['port']) . ") "
	. 'ON DUPLICATE KEY UPDATE `user_agent` = VALUES(`user_agent`), `ip_address` = VALUES(`ip_address`), `port` = VALUES(`port`), `id` = LAST_INSERT_ID(`peer`.`id`)') 
	or die(track('Cannot update peer: '.$DB->sqlerror()));
$pk_peer = $DB->insert_id();

$DB->query("INSERT INTO `torrent` (`hash`) VALUES ('" . $DB->sanitize(bin2hex($_GET['info_hash'])) . "') "
 	. "ON DUPLICATE KEY UPDATE `id` = LAST_INSERT_ID(`id`)") or die(track('Cannot update torrent' . $DB->sqlerror())); // ON DUPLICATE KEY UPDATE is just to make mysql_insert_id work
$pk_torrent = $DB->insert_id();

//User agent is required
if (!isset($_SERVER['HTTP_USER_AGENT'])) {
	$_SERVER['HTTP_USER_AGENT'] = "N/A";
}
if (!isset($_GET['uploaded'])) {
	$_GET['uploaded'] = 0;
}
if (!isset($_GET['downloaded'])) {
	$_GET['downloaded'] = 0;
}
if (!isset($_GET['left'])) {
	$_GET['left'] = 0;
}

$DB->query('INSERT INTO `peer_torrent` (`peer_id`, `torrent_id`, `uploaded`, `downloaded`, `left`, `last_updated`) '
	. 'SELECT ' . $pk_peer . ', `torrent`.`id`, ' . intval($_GET['uploaded']) . ', ' . intval($_GET['downloaded']) . ', ' . intval($_GET['left']) . ', UTC_TIMESTAMP() '
	. 'FROM `torrent` '
	. "WHERE `torrent`.`hash` = '" . $DB->sanitize(bin2hex($_GET['info_hash'])) . "' "
	. 'ON DUPLICATE KEY UPDATE `uploaded` = VALUES(`uploaded`), `downloaded` = VALUES(`downloaded`), `left` = VALUES(`left`), `last_updated` = VALUES(`last_updated`), '
	. '`id` = LAST_INSERT_ID(`peer_torrent`.`id`)')
	or die(track($DB->sqlerror()));
$pk_peer_torrent = $DB->insert_id();

//Did the client stop the torrent?
if (isset($_GET['event']) && $_GET['event'] === 'stopped') {
	$DB->query("UPDATE `peer_torrent` SET `stopped` = TRUE WHERE `id` = " . $pk_peer_torrent) or die (track($DB->sqlerror()));
	die(track(array(), 0, 0)); //The RFC says its OK to return an empty string when stopping a torrent however some clients will whine about it so we return an empty dictionary
}

$numwant = __MAX_PPR; //Can be modified by client

//Set number of peers to return
if (isset($_GET['numwant']) && ctype_digit($_GET['numwant']) && $_GET['numwant'] <= __MAX_PPR && $_GET['numwant'] >= 0) {
	$numwant = (int)$_GET['numwant'];
}

$DB->query('SELECT INET_NTOA(peer.ip_address) AS ip, peer.port, peer.hash AS id '
	. 'FROM peer_torrent '
	. 'JOIN peer ON peer.id = peer_torrent.peer_id '
	. 'WHERE peer_torrent.torrent_id = ' . $pk_torrent . ' AND peer_torrent.stopped = FALSE '
	. 'AND peer_torrent.last_updated >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (__INTERVAL + __TIMEOUT) . ' SECOND) '
	. 'AND peer.id != ' . $pk_peer . ' '
	. 'ORDER BY RAND() '
	. 'LIMIT ' . $numwant) or die(track($DB->sqlerror()));

$reply = array(); //To be encoded and sent to the client

while ($r = $DB->fetch()->get()){
	$reply[] = array($r['ip'], $r['port'], $r['id']); //ip, port, peerid
}

$DB->query('SELECT IFNULL(SUM(peer_torrent.left > 0), 0) AS leech, IFNULL(SUM(peer_torrent.left = 0), 0) AS seed '
	. 'FROM peer_torrent '
	. 'WHERE peer_torrent.torrent_id = ' . $pk_torrent . ' AND `peer_torrent`.`stopped` = FALSE '
	. 'AND peer_torrent.last_updated >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (__INTERVAL + __TIMEOUT) . ' SECOND) '
	. 'GROUP BY `peer_torrent`.`torrent_id`') or die(track($DB->sqlerror()));

$seeders = 0;
$leechers = 0;

if ($r = $DB->fetch()->get())
{
	$seeders = $r['seed'];
	$leechers = $r['leech'];
}

die(track($reply, $seeders[0], $leechers[0]));

//Bencoding function, returns a bencoded dictionary
//You may go ahead and enter custom keys in the dictionary in
//this function if you'd like.
function track($list, $c=0, $i=0) {
	global $FOGCore;
	if (is_string($list)) { //Did we get a string? Return an error to the client
		return 'd14:failure reason'.strlen($list).':'.$list.'e';
	}
	$p = ''; //Peer directory
	foreach($list as $d) { //Runs for each client
		$pid = '';
		if (!isset($_GET['no_peer_id'])) { //Send out peer_ids in the reply
			$real_id = $FOGCore->hex2bin($d[2]);
			$pid = '7:peer id'.strlen($real_id).':'.$real_id;
		}
		$p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$pid.'4:porti'.$d[1].'ee';
	}
	//Add some other paramters in the dictionary and merge with peer list
	$r = 'd8:intervali'.__INTERVAL.'e12:min intervali'.__INTERVAL_MIN.'e8:completei'.$c.'e10:incompletei'.$i.'e5:peersl'.$p.'ee';
	return $r;
}

//Do some input validation
function valdata($g, $fixed_size=false) {
	if (!isset($_GET[$g])) {
		die(track('Invalid request, missing data'));
	}
	if (!is_string($_GET[$g])) {
		die(track('Invalid request, unknown data type'));
	}
	if ($fixed_size && strlen($_GET[$g]) != 20) {
		die(track('Invalid request, length on fixed argument not correct'));
	}
	if (strlen($_GET[$g]) > 80) { //128 chars should really be enough
		die(track('Request too long'));
	}
}

?>
