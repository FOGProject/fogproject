<?php
/**
 * Handles the session in db.
 *
 * PHP version 5
 *
 * @category MulticastSession
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles the session in db.
 *
 * @category MulticastSession
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSession extends FOGController
{
    /**
     * The multicast sessions table.
     *
     * @var string
     */
    protected $databaseTable = 'multicastSessions';
    /**
     * The multicast sessions common and column names.
     *
     * @var string
     */
    protected $databaseFields = array(
        'id' => 'msID',
        'name' => 'msName',
        'port' => 'msBasePort',
        'logpath' => 'msLogPath',
        'image' => 'msImage',
        'clients' => 'msClients',
        'sessclients' => 'msSessClients',
        'interface' => 'msInterface',
        'starttime' => 'msStartDateTime',
        'percent' => 'msPercent',
        'stateID' => 'msState',
        'completetime' => 'msCompleteDateTime',
        'isDD' => 'msIsDD',
        'storagegroupID' => 'msNFSGroupID',
        'anon3' => 'msAnon3',
        'anon4' => 'msAnon4',
        'anon5' => 'msAnon5',
    );
    /**
     * Additional Fields
     *
     * @var array
     */
    protected $additionalFields = array(
        'imagename',
        'state'
    );
    /**
     * Database -> Class field relationships
     *
     * @var array
     */
    protected $databaseFieldClassRelationships = array(
        'Image' => array(
            'id',
            'image',
            'imagename'
        ),
        'TaskState' => array(
            'id',
            'stateID',
            'state'
        )
    );
    /**
     * Get's the session's associated image object.
     *
     * @return object
     */
    public function getImage()
    {
        return new Image($this->get('image'));
    }
    /**
     * Get's the session's task state.
     *
     * @return object
     */
    public function getTaskState()
    {
        return new TaskState($this->get('stateID'));
    }
    /**
     * Cancels this particular session.
     *
     * @return void
     */
    public function cancel()
    {
        $find = ['msID' => $this->get('id')];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'taskID'
        );
        $taskIDs = json_decode(Route::getData(), true);
        self::getClass('TaskManager')->update(
            ['id' => $taskIDs],
            '',
            ['stateID' => self::getCancelledState()]
        );
        self::getClass('MulticastSessionAssociationManager')
            ->destroy(['msID' => $this->get('id')]);
        return $this
            ->set('stateID', self::getCancelledState())
            ->set('name', '')
            ->set('clients', 0)
            ->set('completetime', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
    }
    /**
     * Completes this particular session.
     *
     * @return void
     */
    public function complete()
    {
        $find = ['msID' => $this->get('id')];
        Route::ids(
            'multicastsessionassociation',
            $find,
            'taskID'
        );
        $taskIDs = json_decode(Route::getData(), true);
        self::getClass('TaskManager')->update(
            ['id' => $taskIDs],
            '',
            ['stateID' => self::getCompleteState()]
        );
        self::getClass('MulticastSessionAssociationManager')
            ->destroy(['msID' => $this->get('id')]);
        return $this
            ->set('stateID', self::getCompleteState())
            ->set('name', '')
            ->set('clients', 0)
            ->set('completetime', self::niceDate()->format('Y-m-d H:i:s'))
            ->save();
    }
}
