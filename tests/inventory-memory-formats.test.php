<?php
/**
 * Inventory::getMem()/getMemory() read one `mem` column written by two
 * producers that disagree on shape.
 *
 * The legacy base64 client posts a multi-token string (the tail of a
 * dmidecode/wmic line) with the size in KB at index 1 -- that is the only
 * shape the original parser ever handled, since it always split on
 * whitespace and read index 1. The fog-agent client (internal/inventory.
 * Inventory.Mem, design 0006) instead reports a bare decimal string in MB,
 * with nothing to split on -- so index 1 was never set and every
 * agent-reported host showed 0 B on its Inventory tab (found verifying that
 * tab live against host 239, whose stored `mem` was the bare string "968").
 *
 * DB-free: both methods are called directly with a raw string, exactly what
 * they receive from a hydrated Inventory row's `mem` field.
 *
 * Usage: php tests/inventory-memory-formats.test.php
 * Exit status 0 = pass, 1 = fail.
 */

require __DIR__ . '/lib/fog-test-harness.php';

FogTestHarness::boot('inventory-memory-formats');

$t = new FogChecks();

// Legacy client format: two whitespace-separated tokens, KB at index 1.
// "16777216" KB = 16 GiB.
$t->check(
    'legacy two-token KB string formats as GiB',
    strpos(\FOG\Items\Inventory::getMemory('Size: 16777216'), 'GiB') !== false
);

// fog-agent format: a bare decimal string, MB, per internal/inventory.go.
// 968 MB (host 239's actual reported value) must not read as zero -- that
// was the live bug: the pre-fix parser found no second whitespace token and
// silently returned 0.
//
// It renders in MiB rather than as a fraction of a GiB because
// formatByteSize now picks the unit with a binary logarithm. This
// assertion read '0.95 GiB' when it was written, which was the digit-count
// unit picker showing through (tests/format-byte-size.test.php); the
// parsing this file guards is unchanged either way, and 968 is the number
// that has to survive.
$t->check(
    '968 MB from fog-agent reads as 968.00 MiB, not zero',
    \FOG\Items\Inventory::getMemory('968') === '968.00 MiB'
);
$t->check(
    '4096 MB from fog-agent reads as 4.00 GiB',
    \FOG\Items\Inventory::getMemory('4096') === '4.00 GiB'
);

// Empty/unset value stays the documented zero, both call shapes.
$t->check(
    'empty string stays 0.00',
    \FOG\Items\Inventory::getMemory('') === 0.00
);

$t->finish();
