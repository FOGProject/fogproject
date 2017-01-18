<?php
/**
 * Handles display manager
 *
 * PHP version 5
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Handles display manager
 *
 * @category DisplayManager
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DisplayManager extends FOGClient implements FOGClientSend
{
    /**
     * Function returns data that will be translated to json
     *
     * @return array
     */
    public function json()
    {
        if (!$this->Host instanceof Host) {
            $this->Host = new Host();
        }
        return array(
            'x' => $this->Host->getDispVals('width'),
            'y' => $this->Host->getDispVals('height'),
            'r' => $this->Host->getDispVals('refresh'),
        );
    }
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        if (!$this->Host instanceof Host) {
            $this->Host = new Host();
        }
        $this->send = base64_encode(
            sprintf(
                '%dx%dx%d',
                $this->Host->getDispVals('width'),
                $this->Host->getDispVals('height'),
                $this->Host->getDispVals('refresh')
            )
        );
    }
}
