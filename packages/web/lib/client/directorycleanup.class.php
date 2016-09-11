<?php
/**
 * Cleans directories but only for legacy client
 *
 * PHP version 5
 *
 * @category DirectoryCleanup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Cleans directories but only for legacy client
 *
 * @category DirectoryCleanup
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class DirectoryCleanup extends FOGClient implements FOGClientSend
{
    /**
     * Stores the data to send
     *
     * @var string
     */
    protected $send;
    /**
     * Creates the send string and stores to send variable
     *
     * @return void
     */
    public function send()
    {
        $DirectoryCleanups = self::getClass('DirCleanerManager')->find();
        foreach ($DirectoryCleanups as $i => &$DirectoryCleanup) {
            if (!$DirectoryCleanup->isValid()) {
                continue;
            }
            $SendEnc = base64_encode($DirectoryCleanup->get('path'));
            $SendEnc .= "\n";
            if ($this->newService) {
                if (!$i) {
                    $Send[$i] = "#!ok\n";
                }
                $Send[$i] .= "#dir$i=$SendEnc";
            } else {
                $Send[$i] = $SendEnc;
            }
            unset($DirectoryCleanup);
        }
        unset($DirectoryCleanups);
        $this->send = implode((array)$Send);
        $this->send = trim($this->send);
        if (empty($this->send)) {
            $this->send = sprintf(
                '#!er: %s',
                _('No directories defined to be cleaned up')
            );
        }
    }
}
