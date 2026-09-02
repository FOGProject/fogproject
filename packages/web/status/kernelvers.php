<?php
/**
 * Reports the boot files this node actually holds, and why a value is absent.
 *
 * PHP version 7.4+
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

use FOG\Base\FOGCore;
use FOG\Base\FOGPage;

/**
 * Reports the boot files this node actually holds.
 *
 * This used to report six hardcoded filenames out of BASEPATH/service/ipxe,
 * read their version and release from extended attributes through
 * shell_exec, and print `Unknown` whenever that produced nothing. At least
 * seven unrelated problems arrived looking identical -- no attr binary,
 * SELinux refusing the exec, a mount without user_xattr, an attribute never
 * set, a permissions failure, disabled shell functions, and a parse artifact
 * from omitting -q -- and the actual cause went to stderr and was dropped.
 * A file the admin had installed themselves was worse than unknown: an
 * in-place overwrite leaves FOG's old xattrs in place, so it reported FOG's
 * release as though it were genuine.
 *
 * Three things changed. The directory is the configured one, so a server
 * whose FOG_TFTP_PXE_KERNEL_DIR has moved is reported rather than silently
 * showing nothing installed. Every file in it is reported, not six names, so
 * per-release siblings and custom kernels appear. And the kernel version is
 * read out of the image's own header, which needs no binary and no
 * extended attribute -- leaving only the FOS release tag dependent on attr,
 * and saying which of the specific reasons applies when that cannot be read.
 *
 * @category KernelVersion
 * @package  FOGProject
 * @author   Tom Elliott <tommygunsster@gmail.com>
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
require '../commons/base.inc.php';
ignore_user_abort(true);
set_time_limit(0);
header('Content-Type: text/event-stream');
$url = filter_input(INPUT_POST, 'url');
if (isset($_POST['url'])) {
    // Prevent an unauthenticated user from making arbitrary requests.
    FOGCore::checkAuthAndCSRF();

    $url = filter_input(INPUT_POST, 'url');
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo _('Invalid URL');
        exit;
    }

    $parts = parse_url($url);
    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
    $allowed_schemes = ['http','https'];
    if (!in_array($scheme, $allowed_schemes, true)) {
        http_response_code(400);
        echo _('Unsupported URL Scheme');
        exit;
    }

    $res = $FOGURLRequests
        ->process($url);
    foreach ((array) $res as &$response) {
        echo $response;
        unset($response);
    }

    exit;
}
/**
 * Every file in the configured boot directory, with what is known about it.
 *
 * @return array
 */
$bootFileRows = function () {
    $dir = trim((string)FOGCore::getSetting('FOG_TFTP_PXE_KERNEL_DIR'));
    if ('' === $dir || !is_dir($dir) || !is_readable($dir)) {
        return [];
    }
    $names = @scandir($dir);
    if (false === $names) {
        return [];
    }
    $rows = [];
    foreach ($names as $name) {
        if ('.' === $name || '..' === $name) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            continue;
        }
        $info = FOGPage::bootFileInfo($path);
        if (FOGPage::BOOT_ROLE_OTHER === $info['role']) {
            // FOG's own web assets and signing working files. Reporting
            // boot.php and bg.png as boot files is noise, not honesty.
            continue;
        }
        $rows[] = [
            'name' => $name,
            'role' => $info['role'],
            'version' => $info['kernelVersion'],
            'release' => $info['releaseTag'],
            'note' => $info['tagReason'],
            /**
             * mtime. The old panel used ctime and called it "Installed
             * Date", but restorePreservedCustomizations() chowns this whole
             * directory on every install, which moves ctime on files it
             * never touched -- so every file claimed to be installed on the
             * date of the last upgrade.
             */
            'installed' => FOGCore::formatTime(
                '@' . $info['mtime'],
                'Y-m-d H:i:s'
            ),
            'size' => $info['size']
        ];
    }
    usort(
        $rows,
        function ($a, $b) {
            if ($a['role'] !== $b['role']) {
                $order = [
                    FOGPage::BOOT_ROLE_KERNEL => 0,
                    FOGPage::BOOT_ROLE_INIT => 1,
                    FOGPage::BOOT_ROLE_PAYLOAD => 2
                ];

                return ($order[$a['role']] ?? 3) - ($order[$b['role']] ?? 3);
            }
            $adot = substr_count($a['name'], '.');
            $bdot = substr_count($b['name'], '.');
            if ($adot !== $bdot) {
                return $adot - $bdot;
            }

            return strnatcasecmp($b['name'], $a['name']);
        }
    );

    return $rows;
};
$roleLabels = [
    FOGPage::BOOT_ROLE_KERNEL => _('FOS Kernel'),
    FOGPage::BOOT_ROLE_INIT => _('FOS Init'),
    FOGPage::BOOT_ROLE_PAYLOAD => _('Boot Payload')
];
$rows = $bootFileRows();
// The role's display name travels with the row: the browser must not hold a
// second copy of the role vocabulary that can drift from the server's.
foreach ($rows as &$row) {
    $row['role_label'] = $roleLabels[$row['role']] ?? $row['role'];
    unset($row);
}
if (isset($_POST['ko'])) {
    $e = function ($v) {
        return htmlspecialchars(
            (string)$v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    };
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h4 class="card-title">';
    echo _('Node Version');
    echo '</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    echo $e(FOG_VERSION);
    echo '</div>';
    echo '</div>';
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h4 class="card-title">';
    echo _('Boot Files');
    echo '</h4>';
    echo '</div>';
    echo '<div class="card-body">';
    if (!count($rows)) {
        echo '<div class="alert alert-warning">';
        echo _('No boot files found. Check FOG_TFTP_PXE_KERNEL_DIR.');
        echo '</div>';
    } else {
        echo '<table class="table table-striped">';
        echo '<tbody>';
        echo '<tr>';
        echo '<th>' . _('File') . '</th>';
        echo '<th>' . _('Role') . '</th>';
        echo '<th>' . _('Version') . '</th>';
        echo '<th>' . _('FOS Release') . '</th>';
        echo '<th>' . _('Installed Date') . '</th>';
        echo '</tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $e($row['name']) . '</td>';
            echo '<td>' . $e($roleLabels[$row['role']] ?? $row['role'])
                . '</td>';
            echo '<td>'
                . (
                    '' !== $row['version'] ?
                    $e($row['version']) :
                    '<span class="text-muted">' . _('not readable')
                    . '</span>'
                )
                . '</td>';
            echo '<td>'
                . (
                    '' !== $row['release'] ?
                    $e($row['release']) :
                    '<span class="text-muted">' . $e($row['note'])
                    . '</span>'
                )
                . '</td>';
            echo '<td>' . $e($row['installed']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
    echo '</div>';
    echo '</div>';
    exit;
}
$send_vars = [
    'node_vers' => FOG_VERSION,
    'node_version_lang' => _('Node Version'),
    'boot_files_lang' => _('Boot Files'),
    'file_lang' => _('File'),
    'role_lang' => _('Role'),
    'version_lang' => _('Version'),
    'release_lang' => _('FOS Release'),
    'ins_lang' => _('Installed Date'),
    'unreadable_lang' => _('not readable'),
    'empty_lang' => _('No boot files found. Check FOG_TFTP_PXE_KERNEL_DIR.'),
    'rows' => $rows
];
echo json_encode($send_vars);
exit;
