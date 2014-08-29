<?php
class Announce extends FOGBase
{
	private $downloaded, $uploaded, $left;
	private $peer, $torrent, $peer_torrent;
	private $numwant;

	public function __construct()
	{
		parent::__construct();
		header("Content-type: Text/Plain");
		$this->FOGCore->valdata('peer_id', true);
		$this->FOGCore->valdata('port');
		$this->FOGCore->valdata('info_hash',true);
		// Make sure data is setup:
		!$_REQUEST['key'] ? $_REQUEST['key'] : '';
		$this->downloaded = !$_REQUEST['downloaded'] ? 0 : intval($_REQUEST['downloaded']);
		$this->uploaded = !$_REQUEST['uploaded'] ? 0 : intval($_REQUEST['uploaded']);
		$this->left = !$_REQUEST['left'] ? 0 : intval($_REQUEST['left']);
		$this->FOGCore->valdata('key');
		$this->checkPort();
		$this->peer = $this->PeerGen();
		$this->torrent = $this->TorrentGen();
		$this->peer_torrent = $this->PeerTorrentGen();
		// User Agent is required.
		!$_SERVER['HTTP_USER_AGENT'] ? $_SERVER['HTTP_USER_AGENT'] = 'N/A' : null;
		if ($_REQUEST['event'] && $_REQUEST['event'] === 'stopped')
			$this->stopTorrent();
		if ($_REQUEST['numwant'] && ctype_digit($_REQUEST['numwant']) && $_REQUEST['numwant'] <= $this->FOGCore->getSetting('FOG_TORRENT_PPR') && $_REQUEST['numwant'] >= 0)
			$this->numwant = intval($_REQUEST['numwant']);
		$this->doTheWork();
	}
	/**
	* checkPort()
	* Checks the port setting.  If in valid returns as invalid.  
	* Sets up if port is 9999 and the peer_id is -TO0001-XX to use trackon methods.
	* @return void
	* dies with the relevant message if either are true.
	*/
	private function checkPort()
	{
		try
		{
			if (!ctype_digit($_REQUEST['port']) || $_REQUEST['port'] < 1 || $_REQUEST['port'] > 65535)
				throw new Exception('Invalid client port');
			else if ($_REQUEST['port'] == 999 && substr($_REQUEST['peer_id'], 0, 10) == '-TO0001-XX')
				throw new Exception('d8:completei0e10:incompletei0e8:intervali600e12:min intervali60e5:peersld2:ip12:72.14.194.184:port3:999ed2:ip11:72.14.194.14:port3:999ed2:ip12:72.14.194.654:port3:999eee');
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}
	/**
	* PeerGen()
	* Inserts the new peer or updates if it already exists.
	* Sets the peer variable.
	* @return $Peer return the peer.
	*/
	private function PeerGen()
	{
		$Peer = current($this->FOGCore->getClass('PeerManager')->find(array('hash' => bin2hex($_REQUEST['peer_id']))));
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
			$Peer->set('hash',bin2hex($_REQUEST['peer_id']))
				 ->set('agent',substr($_SERVER['HTTP_USER_AGENT'],0,80))
				 ->set('ip',ip2long($_SERVER['REMOTE_ADDR']))
				 ->set('port',intval($_REQUEST['port']));
		}
		$Peer->save();
		return $Peer;
	}

	/**
	* TorrentGen()
	* Inserts the new torrent or updates if it already exists.
	* Sets the torrent variable.
	* @return $Torrent returns the torrent.
	*/
	private function TorrentGen()
	{
		$Torrent = current($this->FOGCore->getClass('TorrentManager')->find(array('hash' => bin2hex($_REQUEST['info_hash']))));
		if (!$Torrent || !$Torrent->isValid())
		{
			$Torrent = new Torrent(array(
				'hash' => bin2hex($_REQUEST['info_hash']),
			));
		}
		else
		{
			$Torrent->set('hash',bin2hex($_REQUEST['info_hash']));
		}
		$Torrent->save();
		return $Torrent;
	}
	/**
	* PeerTorrentGen()
	* Inserts the new peer_torrent or updates if it already exists.
	* Sets the peer_torrent variable.
	* @return $PeerTorrent returns the PeerTorrent.
	*/
	private function PeerTorrentGen()
	{
		$PeerTorrent = current($this->FOGCore->getClass('PeerTorrentManager')->find(array('peerID' => $this->peer->get('id'))));
		if (!$PeerTorrent || !$PeerTorrent->isValid())
		{
			$PeerTorrent = new PeerTorrent(array(
				'peerID' => $this->peer->get('id'),
				'torrentID' => $this->torrent->get('id'),
				'downloaded' => $this->downloaded,
				'uploaded' => $this->uploaded,
				'left' => $this->left,
				'lastUpdated' => gmdate('Y-m-d H:i:s'),
				'stopped' => 0,
			));
		}
		else
		{
			$PeerTorrent->set('downloaded',$this->downloaded)
						->set('uploaded',$this->uploaded)
						->set('left',$this->left)
						->set('lastUpdated',gmdate('Y-m-d H:i:s'));
		}
		$PeerTorrent->save();
		return $PeerTorrent;
	}
	/**
	* stopTorrent()
	* stops the torrent if the event sent is to stop.
	* @return void
	*/
	private function stopTorrent()
	{
		try
		{
			$PeerTorrent = new PeerTorrent($this->peer_torrent);
			$PeerTorrent->set('stopped',1)->save();
			throw new Exception($this->FOGCore->track(array(),0,0));
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}
	/**
	* doTheWork()
	* Returns the info to the client.
	* @return void
	*/
	private function doTheWork()
	{
		try
		{
			$seeders = 0;
			$leechers = 0;
			foreach($this->FOGCore->getClass('PeerTorrentManager')->find() AS $PeerTorrentNew)
			{
				$PeerNew = new Peer($PeerTorrentNew->get('peerID'));
				$interval = new DateTime('+'.$this->FOGCore->getSetting('FOG_TORRENT_INTERVAL') + $this->FOGCore->getSetting('FOG_TORRENT_TIMEOUT').' seconds',new DateTimeZone('GMT'));
				if ($PeerTorrentNew->get('torrentID') == $this->torrent->get('id') && !$PeerTorrentNew->get('stopped') && strtotime($PeerTorrentNew->get('lastUpdated')) <= strtotime($interval->format('Y-m-d H:i:s')) && $PeerNew->isValid() && $PeerNew->get('id') != $this->peer->get('id'))
					$reply[] = array(long2ip($PeerNew->get('ip')),$PeerNew->get('port'),$PeerNew->get('hash'));
				if ($PeerTorrentNew->get('torrentID') == $this->torrent->get('id') && !$PeerTorrentNew->get('stopped') && strtotime($PeerTorrentNew->get('lastUpdated')) <= strtotime($interval->format('Y-m-d H:i:s')))
					($PeerTorrentNew->get('left') > 0 ? $leechers++ : ($PeerTorrentNew->get('left') == 0 ? $seeders++ : null));
			}
			throw new Exception($this->FOGCore->track($reply,$seeders,$leechers));
		}
		catch (Exception $e)
		{
			die($e->getMessage());
		}
	}
}
