<?php
/**
 * Snapin replicator service.
 *
 * PHP version 7.4+
 *
 * @category SnapinReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
namespace FOG;

/**
 * Snapin replicator service.
 *
 * The sequence lives in FOGReplicator, shared with ImageReplicator. All that is
 * here is what genuinely differs.
 *
 * The messages are literal _() calls rather than something the base builds
 * from a noun, and that is deliberate: gettext extracts msgids from the
 * source text, so _("There are no $noun available!") would never translate
 * and would never appear in the .pot -- silently, forever.
 *
 * @category SnapinReplicator
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class SnapinReplicator extends FOGReplicator
{
    /**
     * Where to get the services sleeptime
     *
     * @var string
     */
    public static $sleeptime = 'SNAPINREPSLEEPTIME';
    /**
     * Everything that differs from the other replicator.
     *
     * @return array
     */
    protected function descriptor()
    {
        return [
            'prefix' => 'SNAPINREPLICATOR',
            'log' => 'fogsnapinrep.log',
            'dev' => '/dev/tty4',
            'route' => 'snapin',
            'assocRoute' => 'snapingroupassociation',
            'assocField' => 'snapinID',
            'model' => 'Snapin',
            // 'ssl/fog.csr' was dropped here. It is the MASTER's
            // client-communication CSR -- a request fulfilled years of
            // installs ago -- and a storage node has no use for it: since
            // the zoned PKI landed, a node generates its own keypair and
            // its own CSR in _requestNodeCert() and is issued a
            // certificate by the master's Web CA. The installer also moved
            // that file into pki/client/leaf/ with the rest of the client
            // leaf's material, so this entry named a path that no longer
            // exists. 'ssl/CA' stays: that is the CA certificate itself,
            // public trust material a node legitimately holds.
            'extraPaths' => [
                'ssl/CA'
            ],
            'msg' => [
                'disabled' => _(' * Snapin replication is globally disabled'),
                'starting' => _('Starting Snapin Replication'),
                'kind' => _('snapin replication'),
                'none' => _('There are no snapins available!'),
                'associate' => _('snapins to a storage group'),
                'notSyncing' => _('Not syncing Snapin')
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
class_alias(__NAMESPACE__ . '\\SnapinReplicator', 'SnapinReplicator');
