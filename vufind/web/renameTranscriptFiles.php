<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2026  Marmot Library Network
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * D-4992 finalization: rename extracted transcript files to the previous migration convention.
 *
 * Run this AFTER extractArchiveTranscripts.php has produced the transcripts (any number of
 * per-namespace rounds), as the last step before packaging for BiblioLabs.
 *
 * Naming convention (matching the earlier migration batch):
 *   - Single transcript for a PID  -> drop the numeric suffix entirely:
 *         steamboatlibrary_4970_1.txt  ->  steamboatlibrary_4970.txt
 *   - Multiple transcripts for a PID -> 0-based index, separated from the PID with a dash:
 *         fortlewis_10397_1.txt  ->  fortlewis_10397-0.txt
 *         fortlewis_10397_3.txt  ->  fortlewis_10397-2.txt
 *     (The new index is the original 1-based transcript number minus one, so numbering "starts at 0".)
 *
 * Why this is driven by manifest.csv rather than the file names:
 *   The PID's colon was replaced with an underscore when files were written, so a name like
 *   steamboatlibrary_4970_1.txt has two "_<digits>" segments and the PID boundary can't be told
 *   apart from the transcript suffix by inspection alone (and a renamed steamboatlibrary_4970.txt
 *   would be misread on a second pass). manifest.csv records the exact pid, filename, and
 *   transcript_index for every file, which removes the ambiguity and makes this pass idempotent.
 *
 * Idempotent: rows whose file is already in final form are skipped, and manifest.csv is rewritten
 * with the final names, so re-running is a no-op.
 *
 * Run:
 *   1. Comment out / remove the `exit;` guard below.
 *   2. php renameTranscriptFiles.php
 *
 * @category Pika
 * @author   Pascal Brammeier
 */

exit; // prevent unintentional execution -- remove/comment this line to run

define('ROOT_DIR', __DIR__);
const OUTPUT_DIR   = ROOT_DIR . '/transcripts_export';
const MANIFEST_CSV = OUTPUT_DIR . '/manifest.csv';

if (!is_file(MANIFEST_CSV)) {
	fwrite(STDERR, 'manifest.csv not found in ' . OUTPUT_DIR . " -- run extractArchiveTranscripts.php first.\n");
	exit(1);
}

// ---- Read the manifest -------------------------------------------------------------------------
$handle = fopen(MANIFEST_CSV, 'r');
$header = fgetcsv($handle);
$rows   = [];
while (($cols = fgetcsv($handle)) !== false) {
	if (count($cols) < 4) {
		continue; // skip blank / malformed lines
	}
	$rows[] = [
		'pid'      => $cols[0],
		'filename' => $cols[1],
		'index'    => (int)$cols[2], // original 1-based transcript number
		'bytes'    => $cols[3],
	];
}
fclose($handle);

// ---- Count files per PID (single vs. multiple decides the naming form) --------------------------
$countByPid = [];
foreach ($rows as $row) {
	$countByPid[$row['pid']] = ($countByPid[$row['pid']] ?? 0) + 1;
}

// ---- Rename ------------------------------------------------------------------------------------
$renamed = 0;
$already = 0;
$missing = 0;
$blocked = 0;

foreach ($rows as $key => $row) {
	$safePid = str_replace(':', '_', $row['pid']);
	if ($countByPid[$row['pid']] === 1) {
		$newName = $safePid . '.txt';                       // single: no suffix
	} else {
		$newName = $safePid . '-' . ($row['index'] - 1) . '.txt'; // multiple: dash + 0-based index
	}
	$rows[$key]['newname'] = $newName;

	$oldName = $row['filename'];
	if ($oldName === $newName) {
		$already++;
		continue;
	}

	$oldPath = OUTPUT_DIR . '/' . $oldName;
	$newPath = OUTPUT_DIR . '/' . $newName;

	if (is_file($oldPath)) {
		if (is_file($newPath)) {
			// Shouldn't happen; don't clobber. Report and leave both in place for inspection.
			fwrite(STDERR, "  ! target already exists, not overwriting: $newName (from $oldName)\n");
			$blocked++;
			continue;
		}
		if (rename($oldPath, $newPath)) {
			$renamed++;
		} else {
			fwrite(STDERR, "  ! rename failed: $oldName -> $newName\n");
			$blocked++;
		}
	} elseif (is_file($newPath)) {
		$already++; // already renamed on a previous pass
	} else {
		fwrite(STDERR, "  ! file not found for {$row['pid']}: $oldName\n");
		$missing++;
	}
}

// ---- Rewrite the manifest with the final names -------------------------------------------------
$handle = fopen(MANIFEST_CSV, 'w');
fputcsv($handle, $header ?: ['pid', 'filename', 'transcript_index', 'byte_length']);
foreach ($rows as $row) {
	fputcsv($handle, [$row['pid'], $row['newname'] ?? $row['filename'], $row['index'], $row['bytes']]);
}
fclose($handle);

echo "Rename complete.\n";
echo '  Files renamed          : ' . $renamed . "\n";
echo '  Already in final form  : ' . $already . "\n";
echo '  Missing files          : ' . $missing . "\n";
echo '  Blocked (target exists): ' . $blocked . "\n";
echo '  manifest.csv updated   : ' . MANIFEST_CSV . "\n";
