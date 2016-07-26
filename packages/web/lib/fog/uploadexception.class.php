<?php
class UploadException extends Exception {
    public function __construct($code) {
        $message = self::codeToMessage($code);
        parent::__construct($message,$code);
    }
    private static function codeToMessage($code) {
        $message = '';
        switch($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = _('The uploaded file exceeds the upload_max_filesize directive in php.ini');
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = _('The uploaded file exceeds the max_file_size directive specified in the HTML form');
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
            $message = _('Unknown upload error occurred.  Return code: ').$code;
            break;
        }
        return $message;
    }
}
