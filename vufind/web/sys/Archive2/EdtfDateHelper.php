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

namespace Archive2;

use EDTF\EdtfFactory;
use EDTF\EdtfValue;
use EDTF\Model\ExtDate;
use EDTF\Model\Interval;
use EDTF\Model\Season;
use EDTF\Model\Set;

/**
 * Helpers for working with EDTF (Extended Date/Time Format) date strings
 * stored on Islandora 2 objects (e.g. field_edtf_date_created values such as
 * "1923", "192X", "1940~", or "1945/1998").
 *
 * Wraps the professional-wiki/edtf package; parse and humanize results are
 * memoized per request since collections repeat the same values heavily.
 */
class EdtfDateHelper {

	private static ?\EDTF\EdtfParser $parser = null;
	private static ?\EDTF\Humanizer $humanizer = null;
	/** @var array<string, EdtfValue|false> */
	private static array $parseCache = [];
	/** @var array<string, string> */
	private static array $humanizeCache = [];

	/**
	 * Parse an EDTF string into an EdtfValue, or null when invalid.
	 */
	private static function parse(string $edtf): ?EdtfValue {
		$edtf = trim($edtf);
		if ($edtf === ''){
			return null;
		}
		if (!array_key_exists($edtf, self::$parseCache)){
			self::$parser ??= EdtfFactory::newParser();
			try {
				$result = self::$parser->parse($edtf);
				self::$parseCache[$edtf] = $result->isValid() ? $result->getEdtfValue() : false;
			} catch (\Throwable $e){
				self::$parseCache[$edtf] = false;
			}
		}
		$value = self::$parseCache[$edtf];
		return $value === false ? null : $value;
	}

	/**
	 * Return a representative year for an EDTF string (the earliest year it
	 * covers), or null when the value is invalid or has no year.
	 */
	public static function parseYear(string $edtf): ?int {
		$value = self::parse($edtf);
		if ($value === null){
			return null;
		}
		if ($value instanceof ExtDate){
			return $value->getYear();
		}
		if ($value instanceof Season){
			return $value->getYear();
		}
		if ($value instanceof Interval && $value->hasStartDate()){
			return $value->getStartDate()->getYear();
		}
		if ($value instanceof Set && !$value->isEmpty()){
			$dates = $value->getDates();
			$first = reset($dates);
			if ($first instanceof ExtDate){
				return $first->getYear();
			}
		}
		try {
			return (int)gmdate('Y', $value->getMin());
		} catch (\Throwable $e){
			return null;
		}
	}

	/**
	 * Return a human-readable rendering of an EDTF string (e.g. "1940~" →
	 * "circa 1940"). Falls back to the raw value when it can't be parsed or
	 * humanized.
	 */
	public static function humanize(string $edtf): string {
		$edtf = trim($edtf);
		if ($edtf === ''){
			return '';
		}
		if (!array_key_exists($edtf, self::$humanizeCache)){
			$humanized = '';
			$value     = self::parse($edtf);
			if ($value !== null){
				self::$humanizer ??= EdtfFactory::newHumanizerForLanguage('en');
				try {
					$humanized = self::$humanizer->humanize($value);
					// Drop the verbose qualifier the humanizer appends
					// (e.g. "Circa 1940 (date is approximate)") — too long for tile captions
					$humanized = preg_replace('/\s*\(date is [^)]*\)\s*$/', '', $humanized);
				} catch (\Throwable $e){
					$humanized = '';
				}
			}
			self::$humanizeCache[$edtf] = $humanized !== '' ? $humanized : $edtf;
		}
		return self::$humanizeCache[$edtf];
	}

	/**
	 * Group per-year object counts into decade buckets for the timeline
	 * date-filter buttons.
	 *
	 * @param array<int|string, int> $yearCounts year => count of objects, as
	 *                                           returned by a Solr facet on its_edtf_year.
	 * @return array<string, array> decade start year => ['value', 'label', 'count'],
	 *                              sorted chronologically.
	 */
	public static function bucketYearsByDecade(array $yearCounts): array {
		$buckets = [];
		foreach ($yearCounts as $year => $count){
			$year = (int)$year;
			if ($year <= 0 || $count <= 0){
				continue;
			}
			$decade = $year - ($year % 10);
			if (!isset($buckets[$decade])){
				$buckets[$decade] = [
					'value' => (string)$decade,
					'label' => $decade . "'s",
					'count' => 0,
				];
			}
			$buckets[$decade]['count'] += $count;
		}
		ksort($buckets);
		return $buckets;
	}
}
