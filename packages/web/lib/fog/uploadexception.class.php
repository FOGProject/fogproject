<?php
/**
 * Upload exception handler.
 *
 * PHP version 5
 *
 * @category UploadException
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
/**
 * Upload exception handler.
 *
 * @category UploadException
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class UploadException extends Exception
{
    /**
     * Initializes the upload exception.
     *
     * @param int $code The error to check.
     *
     * @return void
     */
    public function __construct($code)
    {
        $message = self::_codeToMessage($code);
        parent::__construct($message, $code);
    }
    /**
     * Sets to user friendle message.
     *
     * @param int $code The error to check.
     *
     * @return string
     */
    private static function _codeToMessage($code)
    {
        $message = '';
        switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = sprintf(
                '%s %s',
                _('The uploaded file exceeds the upload_max_filesize'),
                _('directive in php.ini')
            );
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = sprintf(
                '%s %s',
                _('The uploaded file exceeds the max_file_size'),
                _('directive specified in the HTML form')
            );
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = _('The uploaded file was only partially uploaded');
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = _('No file was uploaded');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = _('Missing a temporary folder');
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = _('Failed to write file to disk');
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = _('File upload stopped by an extension');
            break;
        default:
            $message = sprintf(
                '%s. %s: %s',
                _('Unknown upload error occurred'),
                _('Return code'),
                $code
            );
            break;
        }

        return $message;
    }
}
