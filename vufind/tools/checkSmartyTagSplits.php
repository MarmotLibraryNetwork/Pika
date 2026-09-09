<?php
/**
 * Scans .tpl files for Smarty tags broken by whitespace immediately after the
 * left delimiter, e.g.:
 *   <td>{
 *       $pikaList->dateUpdated|date_format}
 *   </td>
 *
 * Smarty's auto_literal setting (on by default) treats a "{" immediately
 * followed by whitespace/newline as literal text rather than the start of a
 * tag, so the whole tag -- including the trailing "}" -- prints verbatim
 * instead of being evaluated. This is silent: no compile error, just wrong
 * output. It's harmless on the old Smarty 2.6.33 engine (still in use on the
 * 2026.03.x patch line as of this writing) but breaks under Smarty 3+, which
 * is what caught D-5314's Admin/NYTLists "Last Updated" column showing raw
 * Smarty code instead of a date after the Smarty 5 upgrade landed on
 * 2026.04.0.
 *
 * Deliberately NOT flagged (verified live against the vendored Smarty 5.8.4
 * engine in install/composer.lock): whitespace before the *closing* "}", e.g.
 * "{if (1) }" or "{$foo }" -- both render correctly, so checking that side
 * only produces false positives.
 *
 * Usage:
 *   php checkSmartyTagSplits.php [path-to-scan]
 *
 * Defaults to scanning vufind/web/interface/themes. Exits 1 if any splits are
 * found (suitable for CI / pre-commit), 0 if clean.
 */

$root = $argv[1] ?? dirname(__DIR__) . '/web/interface/themes';
if (!is_dir($root)){
	fwrite(STDERR, "Not a directory: $root\n");
	exit(2);
}

// $var references or Smarty built-in tag/function names that legitimately
// follow a left delimiter. A "{" followed by whitespace and then one of
// these is almost certainly a broken tag, not intentional literal output.
const TAG_START_PATTERN = '/^\s+(\$[A-Za-z_]|\/?(if|elseif|else|foreach|foreachelse|sectionelse|assign|assign_by_ref|literal|ldelim|rdelim|include|include_php|translate|capture|section|call|function|strip|nocache|counter|cycle|math|extends|block|php|eval|fetch|mailto|textformat|debug|config_load|insert|append|display_if_inconsistent|display_if_set|css|formatJSON|img|img_assign|implode|js|char|html_)\b)/i';

function findFiles($root){
	$dirIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$files   = [];
	foreach ($dirIter as $f){
		if ($f->isFile() && preg_match('/\.tpl$/i', $f->getFilename())){
			$files[] = $f->getPathname();
		}
	}
	sort($files);
	return $files;
}

// Blank out spans we don't want to scan, preserving line numbers (replace
// with matching newlines so later line-number math stays correct).
function blankSpans($content, $pattern){
	return preg_replace_callback($pattern, function($m){
		return str_repeat("\n", substr_count($m[0], "\n"));
	}, $content);
}

function stripNoise($content){
	$content = blankSpans($content, '/<(script|style)\b[^>]*>.*?<\/\1\s*>/is');
	$content = blankSpans($content, '/\{literal\}.*?\{\/literal\}/is');
	$content = blankSpans($content, '/\{\*.*?\*\}/s');
	return $content;
}

function scanFile($file, &$results){
	$raw = file_get_contents($file);
	if ($raw === false){
		return;
	}
	$content = stripNoise($raw);
	$lines   = explode("\n", $raw);

	$risky  = [];
	$seen   = [];
	$offset = 0;
	while (($p = strpos($content, '{', $offset)) !== false){
		$offset = $p + 1;
		$rest   = substr($content, $p + 1, 60);
		if (!preg_match(TAG_START_PATTERN, $rest)){
			continue;
		}
		$lineNum = substr_count(substr($content, 0, $p), "\n") + 1;
		if (isset($seen[$lineNum])){
			continue;
		}
		$seen[$lineNum] = true;
		$risky[]        = ['line' => $lineNum, 'context' => trim($lines[$lineNum - 1] ?? '')];
	}

	if (count($risky) > 0){
		$results[] = ['file' => $file, 'samples' => $risky];
	}
}

$files   = findFiles($root);
$results = [];
foreach ($files as $file){
	scanFile($file, $results);
}

if (empty($results)){
	echo "OK: no split Smarty tags found in " . count($files) . " template files.\n";
	exit(0);
}

foreach ($results as $r){
	echo "=== {$r['file']} ===\n";
	foreach ($r['samples'] as $s){
		echo "  L{$s['line']}: {$s['context']}\n";
	}
}
echo "\nFLAGGED FILES: " . count($results) . "\n";
exit(1);
