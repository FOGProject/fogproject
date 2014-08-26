<?php
require('../commons/base.inc.php');
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
!$_REQUEST['key'] ? $_REQUEST['key'] : '';
$downloaded = $_REQUEST['downloaded'] ? intval($_REQUEST['downloaded']) : 0;
$uploaded = $_REQUEST['uploaded'] ? intval($_REQUEST['uploaded']) : 0;
$left = $_REQUEST['left'] ? intval($_REQUEST['left']) : 0;
//Validate key as well
valdata('key');
//Do we have a valid client port?
if (!ctype_digit($_REQUEST['port']) || $_REQUEST['port'] < 1 || $_REQUEST['port'] > 65535)
	die(track('Invalid client port'));
//Hack to get comatibility with trackon
if ($_REQUEST['port'] == 999 && substr($_REQUEST['peer_id'], 0, 10) == '-TO0001-XX')
	die("d8:completei0e10:incompletei0e8:intervali600e12:min intervali60e5:peersld2:ip12:72.14.194.184:port3:999ed2:ip11:72.14.194.14:port3:999ed2:ip12:72.14.194.654:port3:999eee");
$Peer = current($FOGCore->getClass('PeerManager')->find(array('hash' => bin2hex($_REQUEST['peer_id']))));
if (!$Peer || !$Peer->isValid())
{
	$Peer = new Peer(array(
		'hash' => bin2hex($_REQUEST['peer_id']),
		'agent' => substr($_SERVER['HTTP_USER_AGENT'],0,80),
		'ip' => ip2long($_SERVER['REMOTE_ADDR']),
		'key' => sha1($_REQUEST['key']),
		'port' => intval($_REQUEST['port']),
	));
}
else
{
	$Peer->set('agent',substr($_SERVER['HTTP_USER_AGENT'],0,80))
	     ->set('ip',$Peer->get('ip'))
		 ->set('port',$Peer->get('port'));
}
$Peer->save();
$pk_peer = $Peer->get('id');
$Torrent = current($FOGCore->getClass('TorrentManager')->find(array('hash' => bin2hex($_REQUEST['info_hash']))));
if (!$Torrent || !$Torrent->isValid())
{
	$Torrent = new Torrent(array(
		'hash' => bin2hex($_REQUEST['info_hash']),
	));
}
$Torrent->save();
$pk_torrent = $Torrent->get('id');

//User agent is required
!$_SERVER['HTTP_USER_AGENT'] ? $_SERVER['HTTP_USER_AGENT'] = 'N/A' : null;
!$_REQUEST['uploaded'] ? $_REQUEST['uploaded'] : 0;
!$_REQUEST['downloaded'] ? $_REQUESt['downloaded'] : 0;
!$_REQUEST['left'] ? $_REQUEST['left'] : 0;

$PeerTorrent = current($FOGCore->getClass('PeerTorrentManager')->find(array('peerID' => $Peer->get('id'))));
if (!$PeerTorrent || !$PeerTorrent->isValid())
{
	$PeerTorrent = new PeerTorrent(array(
		'peerID' => $Peer->get('id'),
		'torrentID' => $Torrent->get('id'),
		'uploaded' => intval($_REQUEST['uploaded']),
		'downloaded' => intval($_REQUEST['downloaded']),
		'left' => intval($_REQUEST['left']),
		'lastUpdated' => gmdate('Y-m-d H:i:s'),
		'stopped' => 0,
	));
}
else
{
	$PeerTorrent->set('uploaded', intval($PeerTorrent->get('uploaded')))
				->set('downloaded', intval($PeerTorrent->get('downloaded')))
				->set('left', intval($PeerTorrent->get('left')))
				->set('lastUpdated', $PeerTorrent->get('lastUpdated'));
}
$PeerTorrent->save();
$pk_peer_torrent = $PeerTorrent->get('id');

//Did the client stop the torrent?
if ($_REQUEST['event'] && $_REQUEST['event'] === 'stopped')
{
	$PeerTorrent->set('stopped',1)->save();
	//The RFC says its OK to return an empty string when stopping a torrent however some clients will whine about it so we return an empty dictionary
	die(track(array(),0,0));
}

$numwant = __MAX_PPR; //Can be modified by client

//Set number of peers to return
if ($_REQUEST['numwant'] && ctype_digit($_REQUEST['numwant']) && $_REQUEST['numwant'] <= __MAX_PPR && $_REQUEST['numwant'] >= 0)
	$numwant = (int)$_REQUEST['numwant'];

foreach($FOGCore->getClass('PeerTorrentManager')->find() AS $PeerTorrentNew)
{
	$PeerNew = new Peer($PeerTorrentNew->get('peerID'));
	$interval = new DateTime('+'.__INTERVAL+__TIMEOUT.' seconds',new DateTimeZone('GMT'));
	if ($PeerTorrentNew->get('torrentID') == $Torrent->get('id') && !$PeerTorrentNew->get('stopped') && strtotime($PeerTorrentNew->get('lastUpdated')) <= strtotime($interval->format('Y-m-d H:i:s')) && $Peer && $PeerNew->isValid() && $PeerNew->get('id') != $pk_peer)
		$reply[] = array(long2ip($PeerNew->get('ip')),$PeerNew->get('port'),$PeerNew->get('hash'));

	
}
$seeders = 0;
$leechers = 0;
foreach($FOGCore->getClass('PeerTorrentManager')->find() AS $PeerTorrentNew)
{
	$Peer = new Peer($PeerTorrentNew->get('peerID'));
	$interval = new DateTime('+'.__INTERVAL+__TIMEOUT.' seconds',new DateTimeZone('GMT'));
	if ($PeerTorrentNew->get('torrentID') == $Torrent->get('id') && !$PeerTorrentNew->get('stopped') && strtotime($PeerTorrentNew->get('lastUpdated')) <= strtotime($interval->format('Y-m-d H:i:s')))
		($PeerTorrentNew->get('left') > 0 ? $leechers++ : ($PeerTorrentNew->get('left') == 0 ? $seeders++ : null));
}
die(track($reply, $seeders, $leechers));
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
		if (!isset($_REQUEST['no_peer_id'])) { //Send out peer_ids in the reply
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
	if (!isset($_REQUEST[$g])) {
		die(track('Invalid request, missing data'));
	}
	if (!is_string($_REQUEST[$g])) {
		die(track('Invalid request, unknown data type'));
	}
	if ($fixed_size && strlen($_REQUEST[$g]) != 20) {
		die(track('Invalid request, length on fixed argument not correct'));
	}
	if (strlen($_REQUEST[$g]) > 80) { //128 chars should really be enough
		die(track('Request too long'));
	}
}
