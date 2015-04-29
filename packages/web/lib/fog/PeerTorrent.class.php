<?php
class PeerTorrent extends FOGController {
	/** @var $databaseTable the table to work with */
	public $databaseTable = 'peer_torrent';
	/** @var $databaseFields the fields within the table */
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
