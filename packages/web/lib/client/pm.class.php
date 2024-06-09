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
    public function json(): array
    {
        $find = [
            'id' => self::$Host->get('powermanagementtasks'),
            'onDemand' => [1]
        ];
        Route::ids(
            'powermanagement',
            $find,
            'action'
        );
        $actions = json_decode(
            Route::getData(),
            true
        );
        $action = '';
        if (in_array('shutdown', $actions)) {
            $action = 'shutdown';
        } elseif (in_array('reboot', $actions)) {
            $action = 'restart';
        }
        Route::deletemass(
            'powermanagement',
            [
                'onDemand' => [1],
                'hostID' => self::$Host->get('id'),
                'action' => ['shutdown', 'reboot']
            ]
        );
        $PMFind = [
            'hostID' => self::$Host->get('id'),
            'onDemand' => [0, ''],
            'action' => ['shutdown', 'reboot']
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
        foreach ($PMTasks->data as $PMTask) {
            $min = trim($PMTask->min);
            $hour = trim($PMTask->hour);
            $dom = trim($PMTask->dom);
            $month = trim($PMTask->month);
            $dow = trim($PMTask->dow);
            if ($dow < 0) {
                $dow = 7;
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
        }
        return $data;
    }
}
