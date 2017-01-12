<?php
/**
 * Updates client files
 * NOTE: Only for legacy client relations
 *
 * PHP version 5
 *
 * @category UpdateClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Updates client files
 * NOTE: Only for legacy client relations
 *
 * @category UpdateClient
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UpdateClient extends FOGClient implements FOGClientSend
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'clientupdater';
    /**
     * The actions allowable
     *
     * @var array
     */
    private static $_actions = array(
        'ask',
        'get',
        'list');
    /**
     * File based actions
     *
     * @var array
     */
    private static $_fileActions = array(
        'ask',
        'get'
    );
    /**
     * Sends the data to the client
     *
     * @return void
     */
    public function send()
    {
        $action = trim(strtolower($_REQUEST['action']));
        if (!in_array($action, self::$_actions)) {
            throw new Exception(
                sprintf(
                    '#!er: %s',
                    _('Needs action string of ask, get, or list')
                )
            );
        }
        if (in_array($action, self::$_fileActions) && !$_REQUEST['file']) {
            throw new Exception(
                sprintf(
                    '#!er: %s, %s',
                    _('If action of ask or get'),
                    _('we need a file name in the request')
                )
            );
        }
        $file = base64_decode($_REQUEST['file']);
        $findWhere = array('name'=>$file);
        if ($action == 'list') {
            $findWhere = '';
        }
        $ClientUpdateFiles = self::getClass('ClientUpdaterManager')
            ->find($findWhere);
        switch ($action) {
        case 'ask':
            $ClientUpdateFile = array_shift($ClientUpdateFiles);
            if (!($ClientUpdateFile instanceof ClientUpdater
                && $ClientUpdateFile->isValid())
            ) {
                throw new Exception(
                    sprintf(
                        '#!er: %s',
                        _('Invalid data found')
                    )
                );
            }
            $this->send = $ClientUpdateFile
                ->get('md5');
            if (self::$newService) {
                $this->send = "#!ok\n#md5=$this->send";
            }
            break;
        case 'get':
            $ClientUpdateFile = array_shift($ClientUpdateFiles);
            if (!($ClientUpdateFile instanceof ClientUpdater
                && $ClientUpdateFile->isValid())
            ) {
                throw new Exception(
                    sprintf(
                        '#!er: %s',
                        _('Invalid data found')
                    )
                );
            }
            $filename = basename(
                $ClientUpdateFile->get('name')
            );
            if (!self::$newService) {
                header(
                    sprintf(
                        '%s: %s, %s=%d, %s=%d',
                        'Cache-control',
                        'must-revalidate',
                        'post-check',
                        0,
                        'pre-check',
                        0
                    )
                );
                header('Content-Description: File Transfer');
                header('ContentType: application/octet-stream');
                header(
                    sprintf(
                        '%s: %s; %s=%s',
                        'Content-Disposition',
                        'attachment',
                        'filename',
                        $filename
                    )
                );
            }
            $this->send = $ClientUpdateFile->get('file');
            if (self::$newService) {
                $this->send = sprintf(
                    "#!ok\n#filename=$filename\n#updatefile=%s",
                    bin2hex($this->send)
                );
            }
            break;
        case 'list':
            foreach ((array)$ClientUpdateFiles as $i => &$ClientUpdate) {
                if (!$ClientUpdate->isValid()) {
                    continue;
                }
                $filename = base64_encode($ClientUpdate->get('name'));
                $filename .= "\n";
                $this->send .= $filename;
                if (self::$newService) {
                    if (!$i) {
                        $this->send = "#!ok\n";
                    }
                    $this->send .= "#update$i=$filename";
                }
                unset($ClientUpdate);
            }
            break;
        }
    }
}
