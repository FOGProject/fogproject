<?php
/**
 * The one place that knows which directories hold FOG's logs.
 *
 * PHP version 7.4+
 *
 * @category FOGLogPaths
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

namespace FOG;

/**
 * The one place that knows which directories hold FOG's logs.
 *
 * This list used to be written out three times -- StorageNode::_getData()
 * asked for the directories, status/getfiles.php permitted enumerating them,
 * and status/logtoview.php permitted reading them -- and the three had to
 * agree with nothing checking that they did. They fail at different stages
 * with different symptoms, so adding a log directory to one, or two, of them
 * produces a selector with no entry, or an entry that reads "Invalid Folder",
 * rather than an error naming the real problem. Adding the plugin runner's
 * log hit both in turn.
 *
 * The two views are deliberately NOT one flat list:
 *
 * - directories() feeds the two enumeration sites, which glob. It carries
 *   '/var/log/php*' as a wildcard.
 * - readable() feeds the reader, which matches ANCHORED and EXACT against a
 *   requested file's dirname. A wildcard cannot match there, which is why the
 *   php-fpm directories are spelled out, and every path needs its trailing
 *   separator.
 *
 * The php-fpm variants are held as literal data rather than derived by
 * globbing the filesystem on purpose: readable() is an authorization list, and
 * deriving it from what happens to exist on the box would silently widen it on
 * a host with a newer php-fpm and narrow it on a host with none.
 *
 * @category FOGLogPaths
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class FOGLogPaths
{
    /**
     * Subdirectories of FOG's log directory that hold service logs.
     *
     * THIS is the list that grows. A service logging somewhere other than the
     * top level is added here and nowhere else -- '' is the top level itself,
     * 'plugins' is the plugin runner's, which is separate because that daemon
     * runs as the web user and rotation needs write on the directory
     * (ADR 0010). 'fos' is where the web tier records what FOS told it, and
     * is separate for the same reason: that writer IS the web tier, and the
     * top level belongs to root's daemons.
     *
     * @var array
     */
    const FOG_SUBDIRS = [
        '',
        'plugins',
        'fos',
    ];
    /**
     * The path FOG's logs are reached by in the enumeration views.
     *
     * The installer symlinks this to $servicelogs. Kept as the single
     * spelling there because enumerating both would glob the same files twice
     * through the link.
     *
     * @var string
     */
    const FOG_LINK = '/var/log/fog';
    /**
     * Web server and PHP log directories, wildcard form, for enumeration.
     *
     * @var array
     */
    const SERVER_GLOBS = [
        '/var/log/apache2',
        '/var/log/httpd',
        '/var/log/nginx',
        '/var/log/php*',
    ];
    /**
     * The same directories, literal and separator-terminated, for the reader.
     *
     * @var array
     */
    const SERVER_LITERALS = [
        '/var/log/httpd/',
        '/var/log/apache2/',
        '/var/log/nginx/',
        '/var/log/php-fpm/',
        '/var/log/php5.6-fpm/',
        '/var/log/php5-fpm/',
        '/var/log/php7.0-fpm/',
    ];
    /**
     * Directories to enumerate, for StorageNode::_getData() and getfiles.php.
     *
     * No trailing separator, wildcards allowed. Order is not significant --
     * StorageNode::getLogfiles() natcasesorts the resulting file list.
     *
     * @return array
     */
    public static function directories()
    {
        $paths = [];
        foreach (self::FOG_SUBDIRS as $sub) {
            $paths[] = rtrim(self::FOG_LINK . DS . $sub, DS);
        }

        return array_values(
            array_unique(
                array_merge($paths, self::SERVER_GLOBS)
            )
        );
    }
    /**
     * Directories whose files may be read, for logtoview.php.
     *
     * Separator-terminated and wildcard-free, because the caller matches
     * '#^<dirname>/$#' against this list. Both spellings of FOG's log
     * directory are present: the viewer reaches a file through the symlink,
     * while FOG_LOG_DIR is the real path, and either can arrive depending on
     * how the file was named upstream.
     *
     * @return array
     */
    public static function readable()
    {
        $bases = [self::FOG_LINK, rtrim(FOG_LOG_DIR, DS)];
        $paths = [];
        foreach ($bases as $base) {
            foreach (self::FOG_SUBDIRS as $sub) {
                $paths[] = rtrim($base . DS . $sub, DS) . DS;
            }
        }

        return array_values(
            array_unique(
                array_merge($paths, self::SERVER_LITERALS)
            )
        );
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\FOGLogPaths', 'FOGLogPaths');
