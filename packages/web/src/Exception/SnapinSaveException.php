<?php
/**
 * Thrown when persisting a Snapin to the database fails after the upload
 * step succeeded. Distinguishes "save failed" (HTTP 500 in both the UI and
 * the /api/snapin/createwithfile endpoint) from transport-layer
 * RuntimeExceptions (SSH/SFTP), which the UI's addPost preserves as 400
 * for backwards compatibility while the API maps to 500.
 *
 * PHP version 7.4+
 *
 * @category SnapinSaveException
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG\Exception;

class SnapinSaveException extends \RuntimeException
{
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\SnapinSaveException', 'SnapinSaveException');
