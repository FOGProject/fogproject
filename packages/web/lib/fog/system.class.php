<?php
class System {
    public function __construct() {
<<<<<<< HEAD
        define('FOG_VERSION', '8540');
        define('FOG_SCHEMA', 231);
        define('FOG_BCACHE_VER',89);
=======
        define('FOG_VERSION', '');
        define('FOG_SCHEMA', 231);
        define('FOG_BCACHE_VER',88);
>>>>>>> 
        define('FOG_SVN_REVISION',5892);
        define('FOG_SVN_LAST_UPDATE', '$LastChangedDate: 2015-01-01 14:16:56 -0500 (Thu, 01 Jan 2015) $');
        define('FOG_SVN_LAST_UPDATE', '$LastChangedDate: 2015-01-01 14:16:56 -0500 (Thu, 01 Jan 2015) $');
        define('FOG_CLIENT_VERSION', '0.11.3');
        define('FOG_SVN_LAST_UPDATE', '$LastChangedDate: 2015-01-01 14:16:56 -0500 (Thu, 01 Jan 2015) $');
        define('FOG_CLIENT_VERSION', '0.11.3');
        define('PHP_VERSION_REQUIRED', '5.3.0');
        define('PHP_COMPATIBLE', version_compare(PHP_VERSION, PHP_VERSION_REQUIRED, '>='));
        define('SPACE_DEFAULT_STORAGE', '/images');
        if (PHP_COMPATIBLE === false) {
            die(sprintf(_('Your systems PHP version is not sufficient. You have version %s, version %s is required.'), PHP_VERSION, PHP_VERSION_REQUIRED));
            exit;
        }
    }
}
