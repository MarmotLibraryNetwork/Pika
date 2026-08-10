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
 * D-4992  Digital Archive Migration: extract transcription text to plain-text files.
 *
 * One-off CLI utility. For every legacy Islandora object that has a transcript, write the
 * transcript text to a plain-text file whose name begins with the object PID.
 *
 * Why this exists / the catch:
 *   The Solr field mods_extension_marmotLocal_hasTranscription_transcriptionText_ms is handy for
 *   finding WHICH objects have transcripts, but Solr strips the line breaks. The real text with
 *   line breaks lives in Fedora's MODS datastream, where each break is stored as the XML character
 *   reference &#xD; (a carriage return). So we use Solr only to enumerate the PIDs, then pull the
 *   text from Fedora (via FedoraUtils / Tuque) and decode &#xD; into real line endings.
 *
 * Output:
 *   - transcripts_export/<pid>_<n>.txt   (colon in PID replaced with underscore; <n> = 1-based
 *     transcript number, since an object can have more than one transcript)
 *   - transcripts_export/manifest.csv    (pid, filename, transcript_index, byte_length)
 *
 * Run (on a host that can reach the Islandora Solr IP and Fedora):
 *   1. Comment out / remove the `exit;` guard below.
 *   2. php extractArchiveTranscripts.php <namespace>   # e.g. lafayette
 *      Re-run once per namespace to sweep up transcripts missed by earlier rounds. PIDs that
 *      already have transcript file(s) in the output dir are skipped, so runs are resumable and
 *      safe to repeat.
 *   3. tar -czf archive_transcripts.tgz transcripts_export/   # package for BiblioLabs
 *
 * @category Pika
 * @author   Pascal Brammeier
 */

exit; // prevent unintentional execution -- remove/comment this line to run

set_time_limit(0);
// Source - https://stackoverflow.com/a/74738932
// Posted by Westy92, modified by community. See post 'Timeline' for change history
// Retrieved 2026-07-08, License - CC BY-SA 4.0

error_reporting(E_ALL & ~E_DEPRECATED);
// Disable deprecation warnings

define('ROOT_DIR', __DIR__);

// ---- Bootstrap (mirrors findItemCallnumbers.php) -----------------------------------------------
// Composer autoloader
set_include_path(get_include_path() . PATH_SEPARATOR . '/usr/share/composer');
require_once 'vendor/autoload.php';

require_once ROOT_DIR . '/sys/PEAR_Singleton.php';
require_once ROOT_DIR . '/sys/Pika/Cache/Cache.php'; // required by FedoraUtils
require_once ROOT_DIR . '/sys/Pika/Logger.php';      // required by FedoraUtils
require_once ROOT_DIR . '/sys/Timer.php';
// Memcached is a PHP extension on the server; the shim below only matters on a Windows dev box.
if (!class_exists('Memcached') && file_exists('C:\usr\share\composer\Memcached.php')) {
	require_once 'C:\usr\share\composer\Memcached.php';
}
PEAR_Singleton::init();

$_SERVER['SERVER_NAME'] = 'marmot.localhost'; // used by readConfig() to pick the site

require_once ROOT_DIR . '/sys/ConfigArray.php';
global $configArray;
$configArray = readConfig();

global $timer;
$timer = new Timer(); // FedoraUtils::getInstance() calls $timer->logTime()

require_once ROOT_DIR . '/sys/Utils/FedoraUtils.php';

// ---- Configuration -----------------------------------------------------------------------------
// Islandora Solr, addressed by IP to avoid DNS issues (islandora.marmot.org -> 192.245.61.156).
const SOLR_SELECT_URL   = 'http://192.245.61.156:8080/solr/collection1/select';
const TRANSCRIPT_FIELD  = 'mods_extension_marmotLocal_hasTranscription_transcriptionText_ms';
const SOLR_PAGE_ROWS    = 50;
const OUTPUT_DIR        = ROOT_DIR . '/transcripts_export';
const EOL               = "\r\n"; // CRLF output, per D-4992 decision

// Optional first CLI arg: cap the number of PIDs processed (handy for a test run).
//$pidLimit = isset($argv[1]) && ctype_digit((string)$argv[1]) ? (int)$argv[1] : 0;

// First CLI arg: the PID namespace to process this round (e.g. "lafayette"). Re-run per namespace
// to sweep up transcripts missed by the original run; already-written PIDs are skipped below.
$namespace = isset($argv[1]) ? trim((string)$argv[1]) : '';
if ($namespace === '') {
	fwrite(STDERR, "Usage: php extractArchiveTranscripts.php <namespace>   (e.g. lafayette)\n");
	exit(1);
}

// ------------------------------------------------------------------------------------------------
// Step 1: enumerate the PIDs that have transcripts from Solr for this namespace.
// ------------------------------------------------------------------------------------------------
$pids = fetchTranscriptPids($namespace);
echo 'Found ' . count($pids) . " object PID(s) with transcripts in Solr for namespace '$namespace'.\n";

// ------------------------------------------------------------------------------------------------
// Step 1: enumerate the PIDs that have transcripts from Solr.
// ------------------------------------------------------------------------------------------------
//$pids = fetchTranscriptPids($pidLimit);
//echo 'Found ' . count($pids) . " object PID(s) with transcripts in Solr.\n";

// ------------------------------------------------------------------------------------------------
// Step 2 & 3: pull each transcript from Fedora MODS, convert to plain text, write a file.
// ------------------------------------------------------------------------------------------------
if (!is_dir(OUTPUT_DIR) && !mkdir(OUTPUT_DIR, 0775, true) && !is_dir(OUTPUT_DIR)) {
	fwrite(STDERR, 'Could not create output directory ' . OUTPUT_DIR . "\n");
	exit(1);
}

$fedoraUtils = FedoraUtils::getInstance();

$manifest        = [];
$objectsWithText = 0;
$filesWritten    = 0;
$emptyObjects    = 0;
$fetchFailures   = 0;
$alreadyDone     = 0;

foreach ($pids as $index => $pid) {
	$safePid = str_replace(':', '_', $pid);

	// Resumable: if this PID already has transcript file(s) written (from this or an earlier
	// round), skip it so we only fill in the gaps. Match both the raw extraction naming
	// ({safePid}_N.txt) and the final migration naming applied by renameTranscriptFiles.php
	// ({safePid}.txt and {safePid}-N.txt), so extraction and renaming can run in any order.
  if (!empty(glob(OUTPUT_DIR . '/' . $safePid . '{.txt,_*.txt,-*.txt}', GLOB_BRACE))) {
		$alreadyDone++;
		continue;
	}

	$object = $fedoraUtils->getObject($pid);
	if ($object === null) {
		$fetchFailures++;
		fwrite(STDERR, "  ! Could not fetch Fedora object: $pid\n");
		continue;
	}

	$mods = $fedoraUtils->getModsData($object);
	if (empty($mods)) {
		$fetchFailures++;
		fwrite(STDERR, "  ! No MODS datastream for: $pid\n");
		continue;
	}

	$rawTranscripts = extractRawTranscriptionTexts($mods);
	if (empty($rawTranscripts)) {
		// Solr said this object has a transcript, but we couldn't find one in MODS -- worth noting.
		$emptyObjects++;
		fwrite(STDERR, "  ! No transcriptionText found in MODS for: $pid\n");
		continue;
	}

	$wroteForThis = 0;
	foreach ($rawTranscripts as $i => $raw) {
		$text = transcriptToPlainText($raw);
		if ($text === '') {
			continue;
		}
		$number   = $i + 1;
		$fileName = $safePid . '_' . $number . '.txt';
		file_put_contents(OUTPUT_DIR . '/' . $fileName, $text);
		$filesWritten++;
		$wroteForThis++;
		$manifest[] = [$pid, $fileName, $number, strlen($text)];
	}

	if ($wroteForThis > 0) {
		$objectsWithText++;
	} else {
		$emptyObjects++;
	}

	if (($index + 1) % 100 === 0) {
		echo '  ...processed ' . ($index + 1) . ' / ' . count($pids) . " objects\n";
	}
}

// ------------------------------------------------------------------------------------------------
// Step 4: manifest + summary.
// ------------------------------------------------------------------------------------------------
// Append so each per-namespace round adds to the cumulative manifest; write the header only once.
$manifestPath   = OUTPUT_DIR . '/manifest.csv';
$manifestIsNew  = !file_exists($manifestPath) || filesize($manifestPath) === 0;
$manifestHandle = fopen($manifestPath, 'a');
if ($manifestIsNew) {
	fputcsv($manifestHandle, ['pid', 'filename', 'transcript_index', 'byte_length']);
}
foreach ($manifest as $row) {
	fputcsv($manifestHandle, $row);
}
fclose($manifestHandle);

echo "\nDone (namespace '$namespace').\n";
echo '  Objects with transcript text : ' . $objectsWithText . "\n";
echo '  Transcript files written     : ' . $filesWritten . "\n";
echo '  Skipped (already written)    : ' . $alreadyDone . "\n";
echo '  Objects with no usable text  : ' . $emptyObjects . "\n";
echo '  Fedora fetch failures        : ' . $fetchFailures . "\n";
echo '  Output directory             : ' . OUTPUT_DIR . "\n";
echo "\nNext: tar -czf archive_transcripts.tgz -C " . OUTPUT_DIR . " .\n";

// ================================================================================================
// Helpers
// ================================================================================================

/**
 * Page through the Islandora Solr index for a single PID namespace and return the PIDs whose
 * transcript field is populated. The original run keyed off the transcript field directly; this
 * namespace-scoped variant catches objects that earlier rounds missed.
 *
 * @param int $limit Stop after collecting this many PIDs (0 = no limit). commented out
 * @param string $namespace  PID namespace to filter on (e.g. "lafayette").
 * @return string[]
 */
function fetchTranscriptPids(string $namespace /*int $limit = 0*/): array {
	$pids  = [];
	$start = 0;
	do {
		$url = SOLR_SELECT_URL . '?' . http_build_query([
				//'q'      => TRANSCRIPT_FIELD . ':*', // original process filtered by the transcript field.
				// (Critically, this missed many existing transcripts)
				'q'      => '*:*',
				'fl'     => 'PID,' . TRANSCRIPT_FIELD,
				'fq'     => 'namespace_s:' . $namespace, // scope this round to one namespace
				'wt'     => 'json',
				'start'  => $start,
				'rows'   => SOLR_PAGE_ROWS,
				'indent' => 'false',
			]);

		$json = httpGet($url);
		if ($json === null) {
			fwrite(STDERR, "Solr request failed at start=$start; aborting PID collection.\n");
			break;
		}
		$data = json_decode($json, true);
		if (!isset($data['response']['docs'])) {
			fwrite(STDERR, "Unexpected Solr response at start=$start; aborting PID collection.\n");
			break;
		}

		$numFound = (int)($data['response']['numFound'] ?? 0);
		foreach ($data['response']['docs'] as $doc) {
			// Only keep objects that actually have transcript text (field isn't empty).
			if (!empty($doc['PID']) && !empty($doc[TRANSCRIPT_FIELD])) {
				$pids[] = $doc['PID'];
//				if ($limit > 0 && count($pids) >= $limit) {
//					return $pids;
//				}
			}
		}

		$start += SOLR_PAGE_ROWS;
	} while ($start < $numFound);

	return $pids;
}

/**
 * Simple HTTP GET via cURL. Returns the response body, or null on error / non-200.
 */
function httpGet(string $url): ?string {
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT        => 120,
		CURLOPT_FAILONERROR    => true,
	]);
	$body = curl_exec($ch);
	$err  = curl_error($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($body === false || $code !== 200) {
		fwrite(STDERR, "  HTTP GET failed ($code) for $url : $err\n");
		return null;
	}
	return $body;
}

/**
 * Pull every <marmot:transcriptionText> value out of the raw MODS XML string, in document order.
 *
 * We work on the RAW XML (not FedoraUtils::getModsValues, which decodes entities via the now
 * deprecated mb_convert_encoding HTML-ENTITIES path) so that the line-break character references
 * (&#xD;) survive intact for controlled decoding in transcriptToPlainText(). The pattern mirrors
 * FedoraUtils::getModsValue(): optional namespace prefix, tag boundary lookahead, dot-all body.
 *
 * @return string[] Raw (still entity-encoded) transcript strings.
 */
function extractRawTranscriptionTexts(string $mods): array {
	$pattern = '#<(?:marmot:)?transcriptionText(?=[\s>]).*?>(.*?)</(?:marmot:)?transcriptionText>#s';
	if (preg_match_all($pattern, $mods, $matches, PREG_PATTERN_ORDER)) {
		return $matches[1];
	}
	return [];
}

/**
 * Convert a raw MODS transcriptionText value into plain text with CRLF line endings.
 *
 * The source stores line breaks as the XML character reference &#xD; (carriage return); other
 * special characters arrive as XML/HTML entities (&amp;, &lt;, &#nnn;, ...). We decode all of
 * those to real characters, drop any stray markup, then normalize every line break to CRLF.
 */
function transcriptToPlainText(string $raw): string {
	// 1) Any literal <br> variants -> newline (defensive; the data is normally entity-encoded).
	$s = preg_replace('#<br\s*/?>#i', "\n", $raw);
	// 2) Strip any stray markup.
	$s = strip_tags($s);
	// 3) Decode XML/HTML entities: &#xD; -> \r, &#xA; -> \n, &amp; -> &, &apos; -> ', etc.
	$s = html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
	// 4) Normalize ALL line endings to CRLF.
	$s = preg_replace("/\r\n|\r|\n/", EOL, $s);
	return trim($s);
}
