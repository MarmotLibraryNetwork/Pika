<?php
/**
 * Scans .tpl files for inline <script> blocks containing raw JS "{" characters
 * that are not wrapped in {literal}...{/literal}.
 *
 * Smarty treats "{" as the start of a tag unless it's immediately followed by
 * whitespace (auto_literal) or it's part of a recognized tag/comment. A bare
 * JS brace (object literals, callback bodies, DataTables config, etc.) that
 * Smarty can't parse as a tag causes a fatal compile error. This script finds
 * candidates before they reach production.
 *
 * Usage:
 *   php checkSmartyScriptBraces.php [path-to-scan]
 *
 * Defaults to scanning vufind/web/interface/themes. Exits 1 if any risky
 * braces are found (suitable for CI / pre-commit), 0 if clean.
 */

$root = $argv[1] ?? dirname(__DIR__) . '/web/interface/themes';
if (!is_dir($root)){
	fwrite(STDERR, "Not a directory: $root\n");
	exit(2);
}

// Smarty tag names (built-in + this project's custom plugins/blocks registered
// in sys/Interface.php and interface/plugins/) that legitimately start with "{"
// followed by a non-whitespace character. Anything else is a candidate.
//NOTE: custom plugin/blocks need to be added to prevent false-positives.
const SMARTY_TAG_PATTERN = '/^(\/?(if|elseif|else|foreach|foreachelse|sectionelse|assign|assign_by_ref|literal|ldelim|rdelim|include|include_php|translate|capture|section|call|function|strip|nocache|counter|cycle|math|extends|block|php|eval|fetch|mailto|textformat|debug|config_load|insert|append|display_if_inconsistent|display_if_set|css|formatJSON|img|img_assign|implode|js|char|html_)\b|\$|\*|\/\*)/i';

function inSpan($pos, $spans){
	foreach ($spans as [$s, $e]){
		if ($pos >= $s && $pos <= $e){
			return true;
		}
	}
	return false;
}

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

function protectedSpans($content){
	// {literal}...{/literal} spans
	$spans = [];
	if (preg_match_all('/\{literal\}/i', $content, $mStart, PREG_OFFSET_CAPTURE)){
		preg_match_all('/\{\/literal\}/i', $content, $mEnd, PREG_OFFSET_CAPTURE);
		$starts = array_map(fn($x) => $x[1], $mStart[0]);
		$ends   = array_map(fn($x) => $x[1], $mEnd[0]);
		$ei     = 0;
		foreach ($starts as $s){
			while ($ei < count($ends) && $ends[$ei] < $s){
				$ei++;
			}
			if ($ei < count($ends)){
				$spans[] = [$s, $ends[$ei]];
				$ei++;
			}
		}
	}
	// {* ... *} comment spans - braces inside comments are never compiled
	if (preg_match_all('/\{\*.*?\*\}/s', $content, $mComment, PREG_OFFSET_CAPTURE)){
		foreach ($mComment[0] as $c){
			$spans[] = [$c[1], $c[1] + strlen($c[0]) - 1];
		}
	}
	return $spans;
}

function scanFile($file, &$results){
	$content = file_get_contents($file);
	if ($content === false){
		return;
	}

	$protectedSpansList = protectedSpans($content);

	if (!preg_match_all('/<script\b([^>]*)>(.*?)<\/script\s*>/is', $content, $matches, PREG_OFFSET_CAPTURE)){
		return;
	}

	foreach ($matches[0] as $idx => $fullMatch){
		$attrs      = $matches[1][$idx][0];
		$body       = $matches[2][$idx][0];
		$bodyOffset = $matches[2][$idx][1];

		$hasSrc   = preg_match('/\bsrc\s*=/i', $attrs);
		$bodyTrim = trim($body);
		if ($bodyTrim === '' || ($hasSrc && $bodyTrim === '')){
			continue;
		}

		$lineBase = substr_count(substr($content, 0, $bodyOffset), "\n") + 1;
		$risky    = [];
		$offset   = 0;
		while (($p = strpos($body, '{', $offset)) !== false){
			$absPos = $bodyOffset + $p;
			$offset = $p + 1;
			if (inSpan($absPos, $protectedSpansList)){
				continue;
			}

			$next = $body[$p + 1] ?? '';
			if ($next === ''){
				continue;
			}
			if (preg_match('/^\s/', $next)){
				continue;
			} // auto_literal: "{ " is safe
			if ($next === '{'){
				continue;
			} // "{{" is safe

			$rest = substr($body, $p + 1, 30);
			if (preg_match(SMARTY_TAG_PATTERN, $rest)){
				continue;
			} // legit smarty tag/var/comment

			$lineNum = $lineBase + substr_count(substr($body, 0, $p), "\n");
			$context = str_replace(["\n", "\r"], ' ', substr($body, $p, 40));
			$risky[] = ['line' => $lineNum, 'context' => $context];
		}

		if (count($risky) > 0){
			$results[] = [
				'file'       => $file,
				'scriptLine' => $lineBase,
				'samples'    => $risky,
			];
		}
	}
}

$files   = findFiles($root);
$results = [];
foreach ($files as $file){
	scanFile($file, $results);
}

if (empty($results)){
	echo "OK: no unescaped script braces found in " . count($files) . " template files.\n";
	exit(0);
}

foreach ($results as $r){
	$count = count($r['samples']);
	echo "=== {$r['file']} (script ~line {$r['scriptLine']}, {$count} risky brace(s)) ===\n";
	foreach ($r['samples'] as $s){
		echo "  L{$s['line']}: {$s['context']}\n";
	}
}
echo "\nFLAGGED FILES: " . count($results) . "\n";
exit(1);