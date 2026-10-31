<?php
/** Public-data selector contract fixture; no endpoint and no native call. */
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__) . '/lib/fog-test-harness.php';
FogTestHarness::boot('directory-operation-contract');
FogTestHarness::fakeDb();

$in = json_decode(stream_get_contents(STDIN, 16384), true);
try {
    if (!is_array($in) || 'select' !== ($in['action'] ?? '')) {
        throw new \RuntimeException('fixture_action');
    }
    $out = FOG\Agent\DirectoryOperation::select(
        $in['observation'] ?? [],
        $in['name'] ?? '',
        $in['deploy'] ?? false,
        $in['reassigned'] ?? false,
        $in['target'] ?? ['status' => 'error'],
        $in['allow_create'] ?? false,
        $in['confirmed'] ?? false,
        $in['authorized'] ?? false
    );
    echo json_encode(['status' => 'ok', 'data' => $out]), "\n";
} catch (\RuntimeException $e) {
    echo json_encode(['status' => 'blocked', 'reason' => $e->getMessage()]), "\n";
}
