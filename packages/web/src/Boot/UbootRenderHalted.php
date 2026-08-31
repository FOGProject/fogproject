<?php
/**
 * Signal that UbootBootMenu has nothing more to render
 *
 * PHP version 7.4+
 *
 * @category UbootRenderHalted
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Boot;

/**
 * Signal that UbootBootMenu has nothing more to render
 *
 * The in-process equivalent of the exit() a directly-served HTTP request
 * uses to stop after _printImageIgnored(): BootMenuBase::getTasking() has no
 * return after calling that hook (IpxeBootMenu deliberately falls through
 * instead -- an iPXE script is a sequence and a later chain simply wins), so
 * something has to stop extlinux's single-document output from being
 * overwritten by whatever getTasking() would otherwise render next. exit()
 * did that for a live HTTP response; it also kills the PHP process, which is
 * fine for a request that has nothing left to do and fatal for
 * UbootBootMenu::renderForHost(), which is called in a loop from
 * UbootTftpSync against many hosts in one process. This exception carries
 * the same "stop here" meaning without taking the process down with it.
 *
 * @category UbootRenderHalted
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UbootRenderHalted extends \Exception
{
}
