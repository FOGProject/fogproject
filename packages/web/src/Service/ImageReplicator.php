<?php
/**
 * Image replicator service.
 *
 * PHP version 7.4+
 *
 * @category ImageReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG\Service;

/**
 * Image replicator service.
 *
 * The sequence lives in FOGReplicator, shared with SnapinReplicator. All that is
 * here is what genuinely differs.
 *
 * The messages are literal _() calls rather than something the base builds
 * from a noun, and that is deliberate: gettext extracts msgids from the
 * source text, so _("There are no $noun available!") would never translate
 * and would never appear in the .pot -- silently, forever.
 *
 * @category ImageReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class ImageReplicator extends FOGReplicator
{
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'IMAGEREPSLEEPTIME';
    /**
     * Everything that differs from the other replicator.
     *
     * @return array
     */
    protected function descriptor()
    {
        return [
            'prefix' => 'IMAGEREPLICATOR',
            'log' => 'fogreplicator.log',
            'dev' => '/dev/tty1',
            'route' => 'image',
            'assocRoute' => 'imageassociation',
            'assocField' => 'imageID',
            'model' => 'Image',
            'extraPaths' => [
                'postdownloadscripts',
                'dev/postinitscripts'
            ],
            'msg' => [
                'disabled' => _(' * Image replication is globally disabled'),
                'starting' => _('Starting Image Replication'),
                'kind' => _('image replication'),
                'none' => _('There are no images available!'),
                'associate' => _('images to a storage group'),
                'notSyncing' => _('Not syncing Image')
            ]
        ];
    }
}

/*
 * Compatibility alias. Every consumer of this class' name -- core,
 * bundled plugins and third-party plugins alike -- keeps working
 * unqualified through this, so no call site had to be edited.
 * Supported for all of 1.6; see docs/adr/0013.
 */
class_alias(__NAMESPACE__ . '\\ImageReplicator', 'ImageReplicator');
