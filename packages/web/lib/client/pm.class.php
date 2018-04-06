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
            [
                'id' => self::$Host->get('powermanagementtasks'),
                'onDemand' => '1'
            ],
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
                [
                    'onDemand' => '1',
                    'hostID' => self::$Host->get('id')
                ]
            );
        $PMFind = [
            'pmHostID' => self::$Host->get('id'),
            'pmOndemand' => [0, ''],
            'pmAction' => ['shutdown', 'reboot']
        ];
        Route::listem(
            'powermanagement',
            $PMFind
        );
        $PMTasks = json_decode(
            Route::getData()
        );
        $data = [
            'onDemand' => $action,
            'tasks' => [],
        ];
        foreach ($PMTasks->data as &$PMTask) {
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
            $action = $PMTask->action;
            $data['tasks'][] = [
                'cron' => $cron,
                'action' => $action
            ];
            unset($PMTask);
        }
        return $data;
    }
}
