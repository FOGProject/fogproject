<?php
class PeerTorrent extends FOGController {
    protected $databaseTable = 'peer_torrent';
    protected $databaseFields = array(
        'id' => 'id',
        'peerID' => 'peer_id',
        'torrentID' => 'torrent_id',
        'uploaded' => 'uploaded',
        'downloaded' => 'downloaded',
        'left' => 'left',
        'lastUpdated' => 'last_updated',
        'stopped' => 'stopped',
    );
    protected $databaseFieldsRequired = array(
        'peerID',
        'torrentID',
    );
}
