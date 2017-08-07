<?php
/**
 * Powermanagement Client information
 *
 * PHP version 5
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Powermanagement Client information
 *
 * @category Powermanagement
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class PM extends FOGClient
{
    /**
     * Module associated shortname
     *
     * @var string
     */
    public $shortName = 'powermanagement';
    /**
     * Sends the powermanagement stuff in json format
     *
     * @return array
     */
    public function json()
    {
        $actions = self::getSubObjectIDs(
            'PowerManagement',
            array(
                'id' => self::$Host->get('powermanagementtasks'),
                'onDemand' => '1'
            ),
            'action'
        );
        $action = '';
        if (in_array('shutdown', $actions)) {
            $action = 'shutdown';
        } elseif (in_array('reboot', $actions)) {
            $action = 'restart';
        }
        self::getClass('PowerManagementManager')
            ->destroy(
                array(
                    'onDemand' => '1',
                    'hostID' => self::$Host->get('id')
                )
            );
        $PMTasks = self::getClass('PowerManagementManager')
            ->find(
                array(
                    'hostID' => self::$Host->get('id'),
                    'onDemand' => array(
                        '0',
                        0,
                        null,
                        ''
                    ),
                    'action' => array(
                        'shutdown',
                        'reboot',
                    )
                )
            );
        $data = array(
            'onDemand' => $action,
            'tasks' => array(),
        );
        foreach ((array)$PMTasks as &$PMTask) {
            $min = trim($PMTask->get('min'));
            $hour = trim($PMTask->get('hour'));
            $dom = trim($PMTask->get('dom'));
            $month = trim($PMTask->get('month'));
            $dow = trim($PMTask->get('dow'));
            if (is_int($dow)) {
                if ($dow < 0) {
                    $dow = 7;
                }
            }
            $cron = sprintf(
                '%s %s %s %s %s',
                $min,
                $hour,
                $dom,
                $month,
                $dow
            );
            $action = $PMTask->get('action');
            $data['tasks'][] = array(
                'cron' => $cron,
                'action' => $action
            );
        }
        return $data;
    }
}
