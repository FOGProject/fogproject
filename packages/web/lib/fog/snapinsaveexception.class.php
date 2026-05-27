<?php
/**
 * Distinguishes "DB save failed after file landed on Master" from
 * "validation failed before upload" and "SSH/FTP transport failed".
 * RuntimeException -> HTTP 500, but the API handler catches this
 * subclass specifically so callers know the file is already on disk.
 *
 * @category SnapinSaveException
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinSaveException extends \RuntimeException
{
}
