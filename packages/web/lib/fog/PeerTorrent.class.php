<?php
/**
* Class Peer
*/
class PeerTorrent extends FOGController
{
	// Table
	public $databaseTable = 'peer_torrent';

	// Name -> Database field name
	public $databaseFields = array(
		'id' => 'id',
		'peerID' => 'peer_id',
		'torrentID' => 'torrent_id',
		'uploaded' => 'uploaded',
		'downloaded' => 'downloaded',
		'left' => 'left',
		'lastUpdated' => 'last_updated',
		'stopped' => 'stopped',
	);
}
