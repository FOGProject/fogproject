<?php
/**
 * Hashing service for snapins
 *
 * PHP version 7.4+
 *
 * @category SnapinHash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Service;

/**
 * Hashing service for snapins
 *
 * The walk lives in FOGItemScanner, shared with ImageSize. All that is here
 * is what genuinely differs: the table, and what to record about the file.
 *
 * The messages are literal _() calls rather than something the base builds
 * from a noun, and that is deliberate: gettext extracts msgids from the
 * source text, so _("Trying $noun hash for") would never translate and would
 * never appear in the .pot -- silently, forever.
 *
 * @category SnapinHash
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinHash extends FOGItemScanner
{
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'SNAPINHASHSLEEPTIME';
    /**
     * Everything that differs from the other scanner.
     *
     * @return array
     */
    protected function descriptor()
    {
        return [
            'prefix' => 'SNAPINHASH',
            'log' => 'fogsnapinhash.log',
            'dev' => '/dev/tty6',
            'zzz' => 1800,
            'route' => 'snapin',
            'assocRoute' => 'snapingroupassociation',
            'assocField' => 'snapinID',
            'model' => 'Snapin',
            'nodePathField' => 'snapinpath',
            'itemFileField' => 'file',
            'msg' => [
                'disabled' => _(' * Snapin hash is globally disabled'),
                'starting' => _('Starting Snapin Hashing Service'),
                'finding' => _('Finding any snapins associated'),
                'none' => _('No snapins associated with this group as master'),
                'plural' => _('snapins'),
                'singular' => _('snapin'),
                'tail' => _('to update hash values as needed'),
                'trying' => _('Trying Snapin hash for'),
                'getting' => _('Getting snapin hash and size for')
            ]
        ];
    }
    /**
     * Records the hash and size of the snapin file.
     *
     * @param object $item     The snapin row.
     * @param string $filepath The file.
     *
     * @return void
     */
    protected function updateItem($item, $filepath)
    {
        $hash = hash_file('sha512', $filepath);
        $size = self::getFilesize($filepath);
        self::outall(
            sprintf(
                ' | %s: %s',
                _('Hash'),
                $hash
            )
        );
        self::getClass($this->modelClass(), $item->id)
            ->set('hash', $hash)
            ->set('size', $size)
            ->save();
    }
    /**
     * Records that the snapin file is not there.
     *
     * Previously there was no such path at all: hash_file() was called on
     * whatever the record named, returned false when the file was missing
     * or the storage unmounted, and false was saved as the hash. A client
     * comparing against an empty hash fails the snapin every time, with
     * nothing in the log to say why. Clearing both columns says the file is
     * not currently there, which is true, and the next pass repairs it.
     *
     * @param object $item The snapin row.
     *
     * @return void
     */
    protected function clearItem($item)
    {
        self::getClass($this->modelClass(), $item->id)
            ->set('hash', '')
            ->set('size', 0)
            ->save();
    }
}
