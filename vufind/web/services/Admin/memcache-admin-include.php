<?php
/*
 * Pika Discovery Layer
 * Copyright (C) 2023  Marmot Library Network
 *
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
 * Created by PhpStorm.
 * User: pbrammeier
 * Date: 11/7/2014
 * Time: 10:36 AM
 */
/* Rewritten for integration into Pika

*/

class memcacheAdmin {

//	public $VERSION = '$Id: memcache.php,v 1.1.2.3 2008/08/28 18:07:54 mikl Exp $';

	const DATE_FORMAT = 'Y/m/d H:i:s',
		GRAPH_SIZE = 250,
		MAX_ITEM_DUMP = 50,
		PER_ROW = 4; // number of memcache variables to list on a single line.

	public $MEMCACHE_SERVERS = [
		'localhost:11211'
		//, additional servers
	],
		$output = '', // Results to be displayed to user
		$memcacheStats; // main stats property //TODO

	protected $time, $host, $port;


////////// END OF DEFAULT CONFIG AREA /////////////////////////////////////////////////////////////


///////////MEMCACHE FUNCTIONS /////////////////////////////////////////////////////////////////////
	function host_port($server){
		return explode(':', $server, 2);
	}

	function sendMemcacheCommands($command){

		$result = [];

		foreach ($this->MEMCACHE_SERVERS as $server){
			[$host, $port]   = $this->host_port($server);
			$result[$server] = $this->sendMemcacheCommand($host, $port, $command);
		}
		return $result;
	}

	function sendMemcacheCommand($host, $port, $command){

		$s = @fsockopen($host, $port);
		if (!$s){
			die("Can't connect to: $host:$port");
		}

		fwrite($s, $command . "\r\n");

		$buf = '';
		while ((!feof($s))){
			$buf .= fgets($s, 256);
			if (strpos($buf, "END\r\n") !== false  // stat says end
				|| (strpos($buf, "DELETED\r\n") !== false || strpos($buf, "NOT_FOUND\r\n") !== false) // delete says these
				|| strpos($buf, "OK\r\n") !== false){ // flush_all says ok
				break;
			}
		}
		fclose($s);
		return $this->parseMemcacheResults($buf);
	}

	function parseMemcacheResults($str){
		$res   = [];
		$lines = explode("\r\n", $str);
		$cnt   = count($lines);
		for ($i = 0;$i < $cnt;$i++){
			$line = $lines[$i];
			$l    = explode(' ', $line, 3);
			if (count($l) == 3){
				$res[$l[0]][$l[1]] = $l[2];
				if ($l[0] == 'VALUE'){ // next line is the value
					$res[$l[0]][$l[1]] = [];
					[$flag, $size] = explode(' ', $l[2]);
					$res[$l[0]][$l[1]]['stat']  = ['flag' => $flag, 'size' => $size];
					$res[$l[0]][$l[1]]['value'] = $lines[++$i];
				}
			}elseif ($line == 'DELETED' || $line == 'NOT_FOUND' || $line == 'OK'){
				return $line;
			}
		}
		return $res;
	}

	function dumpCacheSlab($server, $slabId, $limit){
		[$host, $port] = explode(':', $server);
		$resp = $this->sendMemcacheCommand($host, $port, 'stats cachedump ' . $slabId . ' ' . $limit);

		return $resp;

	}

	function flushServer($server){
		[$host, $port] = explode(':', $server);
		$resp = $this->sendMemcacheCommand($host, $port, 'flush_all');
		return $resp;
	}

	function getCacheItems(){
		$items       = $this->sendMemcacheCommands('stats items');
		$serverItems = [];
		$totalItems  = [];
		foreach ($items as $server => $itemlist){
			$serverItems[$server] = [];
			$totalItems[$server]  = 0;
			if (!isset($itemlist['STAT'])){
				continue;
			}

			$iteminfo = $itemlist['STAT'];

			foreach ($iteminfo as $keyinfo => $value){
				if (preg_match('/items\:(\d+?)\:(.+?)$/', $keyinfo, $matches)){
					$serverItems[$server][$matches[1]][$matches[2]] = $value;
					if ($matches[2] == 'number'){
						$totalItems[$server] += $value;
					}
				}
			}
		}
		return ['items' => $serverItems, 'counts' => $totalItems];
	}

	function getMemcacheStats($total = true){
		$resp = $this->sendMemcacheCommands('stats');
		if ($total){
			$res = [];
			foreach ($resp as $server => $r){
				foreach ($r['STAT'] as $key => $row){
					if (!isset($res[$key])){
						$res[$key] = null;
					}
					switch ($key){
						case 'pid':
							$res['pid'][$server] = $row;
							break;
						case 'uptime':
							$res['uptime'][$server] = $row;
							break;
						case 'time':
							$res['time'][$server] = $row;
							break;
						case 'version':
							$res['version'][$server] = $row;
							break;
						case 'pointer_size':
							$res['pointer_size'][$server] = $row;
							break;
						case 'rusage_user':
							$res['rusage_user'][$server] = $row;
							break;
						case 'rusage_system':
							$res['rusage_system'][$server] = $row;
							break;
						case 'curr_items':
							$res['curr_items'] += $row;
							break;
						case 'total_items':
							$res['total_items'] += $row;
							break;
						case 'bytes':
							$res['bytes'] += $row;
							break;
						case 'curr_connections':
							$res['curr_connections'] += $row;
							break;
						case 'total_connections':
							$res['total_connections'] += $row;
							break;
						case 'connection_structures':
							$res['connection_structures'] += $row;
							break;
						case 'cmd_get':
							$res['cmd_get'] += $row;
							break;
						case 'cmd_set':
							$res['cmd_set'] += $row;
							break;
						case 'get_hits':
							$res['get_hits'] += $row;
							break;
						case 'get_misses':
							$res['get_misses'] += $row;
							break;
						case 'evictions':
							$res['evictions'] += $row;
							break;
						case 'bytes_read':
							$res['bytes_read'] += $row;
							break;
						case 'bytes_written':
							$res['bytes_written'] += $row;
							break;
						case 'limit_maxbytes':
							$res['limit_maxbytes'] += $row;
							break;
						case 'threads':
							$res['rusage_system'][$server] = $row;
							break;
					}
				}
			}
			return $res;
		}
		return $resp;
	}

//////////////////////////////////////////////////////

//
// don't cache this page
//
//header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
//header("Cache-Control: post-check=0, pre-check=0", false);
//header("Pragma: no-cache");                                    // HTTP/1.0
// headers needed somewhere else? plb 10-29-2014

	function duration($ts){
//		global $this->time;
		$years = (int)((($this->time - $ts) / (7 * 86400)) / 52.177457);
		$rem   = (int)(($this->time - $ts) - ($years * 52.177457 * 7 * 86400));
		$weeks = (int)(($rem) / (7 * 86400));
		$days  = (int)(($rem) / 86400) - $weeks * 7;
		$hours = (int)(($rem) / 3600) - $days * 24 - $weeks * 7 * 24;
		$mins  = (int)(($rem) / 60) - $hours * 60 - $days * 24 * 60 - $weeks * 7 * 24 * 60;
		$str   = '';
		if ($years == 1){
			$str .= "$years year, ";
		}
		if ($years > 1){
			$str .= "$years years, ";
		}
		if ($weeks == 1){
			$str .= "$weeks week, ";
		}
		if ($weeks > 1){
			$str .= "$weeks weeks, ";
		}
		if ($days == 1){
			$str .= "$days day,";
		}
		if ($days > 1){
			$str .= "$days days,";
		}
		if ($hours == 1){
			$str .= " $hours hour and";
		}
		if ($hours > 1){
			$str .= " $hours hours and";
		}
		if ($mins == 1){
			$str .= " 1 minute";
		}else{
			$str .= " $mins minutes";
		}
		return $str;
	}

// create graphics
//
	function graphics_avail(){
		return extension_loaded('gd');
	}

	function bsize($s){
		foreach (['', 'K', 'M', 'G'] as $i => $k){
			if ($s < 1024){
				break;
			}
			$s /= 1024;
		}
		return sprintf("%5.1f %sBytes", $s, $k);
	}

// create menu entry
	function menu_entry($op, $title){
		return "<a class=\"btn btn-default\" href=\"?op=$op\">$title</a>";
	}

	function getHeader(){
		$header =
			'<style>
table {width: 100%;}
table td, table th { padding: 4px;}

.content span.green { background:rgb(34, 158, 36); padding:0 0.5em 0 0.5em}
.content span.red { background:rgb(160, 9, 47); padding:0 0.5em 0 0.5em }
</style>';
		return $header;
	}


	function getMenu(){
		echo '<div class="btn-group">',
		$this->menu_entry(1, 'View Host Stats'),
		$this->menu_entry(2, 'Variables'),
		'</div><hr class="clearfix">';
	}

	function bargraph(){
		$memcacheStats = $this->getMemcacheStats();
//		$memcacheStatsSingle = $this->getMemcacheStats(false);

		if (!$this->graphics_avail()){
			die;
		}

		$size  = self::GRAPH_SIZE; // image size
		$image = imagecreate($size + 50, $size + 10);

		$col_white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
		$col_red   = imagecolorallocate($image, 0xD0, 0x60, 0x30);
		$col_red   = imagecolorallocate($image, 160, 9, 47);
		$col_green = imagecolorallocate($image, 0x60, 0xF0, 0x60);
		$col_green = imagecolorallocate($image, 34, 158, 36);
		$col_black = imagecolorallocate($image, 0, 0, 0);

		imagecolortransparent($image, $col_white);

		$hits   = ($memcacheStats['get_hits'] == 0) ? 1 : $memcacheStats['get_hits'];
		$misses = ($memcacheStats['get_misses'] == 0) ? 1 : $memcacheStats['get_misses'];
		$total  = $hits + $misses;

		$this->fill_box($image, 30, $size, 50, -$hits * ($size - 21) / $total, $col_black, $col_green, sprintf("%.1f%%", $hits * 100 / $total));
		$this->fill_box($image, 130, $size, 50, -max(4, ($total - $hits) * ($size - 21) / $total), $col_black, $col_red, sprintf("%.1f%%", $misses * 100 / $total));

		ob_start();
		imagepng($image);
		$imagedata = ob_get_contents();
		ob_end_clean();
		return base64_encode($imagedata);
	}

	function piegraph(){
		$memcacheStats       = $this->getMemcacheStats();
		$memcacheStatsSingle = $this->getMemcacheStats(false);

		if (!$this->graphics_avail()){
			die;
		}

		$size  = self::GRAPH_SIZE; // image size
		$image = imagecreate($size/* + 50*/, $size /*+ 10*/);

		$col_white = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
		$col_red   = imagecolorallocate($image, 0xD0, 0x60, 0x30);
		$col_green = imagecolorallocate($image, 0x60, 0xF0, 0x60);
		$col_black = imagecolorallocate($image, 0, 0, 0);

		$col_red   = imagecolorallocate($image, 160, 9, 47);
		$col_green = imagecolorallocate($image, 34, 158, 36);

		imagecolortransparent($image, $col_white);

		$tsize      = $memcacheStats['limit_maxbytes'];
		$avail      = $tsize - $memcacheStats['bytes'];
		$x          = $y = $size / 2;
		$angle_from = 0;
		$fuzz       = 0.000001;

		foreach ($memcacheStatsSingle as $serv => $mcs){
			$free = $mcs['STAT']['limit_maxbytes'] - $mcs['STAT']['bytes'];
			$used = $mcs['STAT']['bytes'];


			if ($free > 0){
				// draw free
				$angle_to = ($free * 360) / $tsize;
				$perc     = sprintf("%.2f%%", ($free * 100) / $tsize);

				$this->fill_arc($image, $x, $y, $size, $angle_from, $angle_from + $angle_to, $col_black, $col_green, $perc);
				$angle_from = $angle_from + $angle_to;
			}
			if ($used > 0){
				// draw used
				$angle_to = ($used * 360) / $tsize;
				$perc     = sprintf("%.2f%%", ($used * 100) / $tsize);
				$this->fill_arc($image, $x, $y, $size, $angle_from, $angle_from + $angle_to, $col_black, $col_red, '(' . $perc . ')');
				$angle_from = $angle_from + $angle_to;
			}
		}
		ob_start();
		imagepng($image);
		$imagedata = ob_get_contents();
		ob_end_clean();
		return base64_encode($imagedata);
	}

	function __construct(){

		$_GET['op'] = $_GET['op'] ?? '1';
		$this->time = time();

		foreach ($_GET as $key => $g){
			$_GET[$key] = htmlentities($g);
		}


		// singleout
		// when singleout is set, it only gives details for that server.
		if (isset($_GET['singleout']) && $_GET['singleout'] >= 0 && $_GET['singleout'] < count($this->MEMCACHE_SERVERS)){
			$this->MEMCACHE_SERVERS = [$this->MEMCACHE_SERVERS[$_GET['singleout']]];
		}
		if (isset($_GET['server'])){
			$theserver = $this->MEMCACHE_SERVERS[(int)$_GET['server']];
			[$this->host, $this->port] = $this->host_port($theserver);
		}

		ob_start();

		echo $this->getHeader(),
		'<div class=content>',
		$this->getMenu();

		switch ($_GET['op']){

			case 6: // flush server
				$r = $this->flushServer($theserver);
				echo "<div class='alert " . ( $r == 'OK' ? 'alert-success' : 'alert-danger' ) . "'>Flush  $theserver : $r </div>";
				//break; // flush, then display stats

			case 1: // host stats
				$phpversion          = phpversion();
				$memcacheStats       = $this->getMemcacheStats();
				$memcacheStatsSingle = $this->getMemcacheStats(false);

				$mem_size  = $memcacheStats['limit_maxbytes'];
				$mem_used  = $memcacheStats['bytes'];
				$mem_avail = $mem_size - $mem_used;
				$startTime = time() - array_sum($memcacheStats['uptime']);

				$curr_items  = $memcacheStats['curr_items'];
				$total_items = $memcacheStats['total_items'];
				$hits        = ($memcacheStats['get_hits'] == 0) ? 1 : $memcacheStats['get_hits'];
				$misses      = ($memcacheStats['get_misses'] == 0) ? 1 : $memcacheStats['get_misses'];
				$sets        = $memcacheStats['cmd_set'];

				$req_rate  = sprintf("%.2f", ($hits + $misses) / ($this->time - $startTime));
				$hit_rate  = sprintf("%.2f", ($hits) / ($this->time - $startTime));
				$miss_rate = sprintf("%.2f", ($misses) / ($this->time - $startTime));
				$set_rate  = sprintf("%.2f", ($sets) / ($this->time - $startTime));

				echo
				'<div class="col-md-5 pull-right"><p>';

				$size_tag = 'width=' . (self::GRAPH_SIZE /*+ 50*/) . ' height=' . (self::GRAPH_SIZE /*+ 10*/);
				echo
				'<table class="table-bordered">
<thead><tr><th><h2 class="h3 text-center">Host Status Diagrams</h2></th></tr></thead>
<tbody>
		<tr><th>Cache Usage</th></tr>',

				$this->graphics_avail() ?
					'<tr>' .
					"<td><img alt='Pie graph of percent of free cache' $size_tag src=\"data:image/png;base64,{$this->piegraph()}\" style='margin: auto; display:block'></td>" .
					"</tr>\n"
					: '',

				'<tr>',
				'<td><span class="green box">&nbsp;</span>&nbsp;<strong>Free:</strong> ', $this->bsize($mem_avail) . sprintf(" (%.1f%%)", $mem_avail * 100 / $mem_size), "</td>\n",
				'</tr>',
				'<tr>',
				'<td><span class="red box">&nbsp;</span>&nbsp;<strong>Used:</strong> ', $this->bsize($mem_used) . sprintf(" (%.1f%%)", $mem_used * 100 / $mem_size), "</td>\n",
				'</tr>',

				'<tr><th>Hits &amp; Misses</td></tr>',

				$this->graphics_avail() ?
					"<tr><td><img alt=\"\" $size_tag src=\"data:image/png;base64,{$this->bargraph()}\"></td></tr>\n"
					: '',

				'<tr>',
				'<td><span class="green box">&nbsp;</span>&nbsp;<strong>Hits:</strong> ', $hits . sprintf(" (%.1f%%)", $hits * 100 / ($hits + $misses)), "</td>\n",
				'</tr>',

				'<tr>',
				'<td><span class="red box">&nbsp;</span>&nbsp;<strong>Misses:</strong> ', $misses . sprintf(" (%.1f%%)", $misses * 100 / ($hits + $misses)), "</td>\n",
				'</tr>',

				'</tbody></table></p></div>';


				echo
					'<div class="col-md-7"><p>
			<table class="table-bordered">
			<thead><tr><th colspan="2"><h2 class="h3 text-center">General Cache Information</h2></th></tr></thead>
			<tbody>
				<tr><th>PHP Version</th><td>' . $phpversion . '</td></tr>
				<tr><th>Memcached Host' . ((count($this->MEMCACHE_SERVERS) > 1) ? 's' : '') . "</th><td>";
				$i = 0;
				if (!isset($_GET['singleout']) && count($this->MEMCACHE_SERVERS) > 1){
					foreach ($this->MEMCACHE_SERVERS as $server){
						echo (++$i) . '. <a href="?singleout=' . $i . '">' . $server . '</a><br>';
					}
				}else{
					echo $this->MEMCACHE_SERVERS[0];
				}
				if (isset($_GET['singleout'])){
					echo '<a href="">(all servers)</a><br>';
				}
				echo "</td></tr>\n",
					"<tr><th>Total Memcache Cache</th><td>" . $this->bsize($memcacheStats['limit_maxbytes']) . "</td></tr>\n",
				'</tbody></table>
		</p></div>';

				echo '<div class="col-md-7"><p>';

				foreach ($this->MEMCACHE_SERVERS as $server){
					echo '<table class="table-bordered">'
						.'<thead><tr><th colspan="2"><h2 class="h3 text-center">Memcache Server Information</h2></th></tr></thead>'
						.'<tbody>';
					echo '<tr><th>' . $server . '</th><td><a href="?server=' . array_search($server, $this->MEMCACHE_SERVERS) . '&op=6" onclick="return confirm(\'Are you sure you want to Flush this cache?\');">[<b>Flush this server</b>]</a></td></tr>';
					echo '<tr><th>Start Time</th><td>', date(self::DATE_FORMAT, $memcacheStatsSingle[$server]['STAT']['time'] - $memcacheStatsSingle[$server]['STAT']['uptime']), '</td></tr>';
					echo '<tr><th>Uptime</th><td>', $this->duration($memcacheStatsSingle[$server]['STAT']['time'] - $memcacheStatsSingle[$server]['STAT']['uptime']), '</td></tr>';
					echo '<tr><th>Memcached Server Version</th><td>' . $memcacheStatsSingle[$server]['STAT']['version'] . '</td></tr>';
					echo '<tr><th>Used Cache Size</th><td>', $this->bsize($memcacheStatsSingle[$server]['STAT']['bytes']), '</td></tr>';
					echo '<tr><th>Total Cache Size</th><td>', $this->bsize($memcacheStatsSingle[$server]['STAT']['limit_maxbytes']), '</td></tr>';
					echo '</tbody></table>';
				}
				echo
				"</p></div><div class=\"col-md-7\"><p>
		<table class='table-bordered'>
		<thead><tr><th colspan='2'><h2 class='h3 text-center'>Cache Information</h2></th></tr></thead>

		<tbody>
			<tr><th>Current Items (total)</th><td>$curr_items ($total_items)</td></tr>
			<tr><th>Hits</th><td>{$hits}</td></tr>
			<tr><th>Misses</th><td>{$misses}</td></tr>
			<tr><th>Request Rate (hits, misses)</th><td>$req_rate cache requests/second</td></tr>
			<tr><th>Hit Rate</th><td>$hit_rate cache requests/second</td></tr>
			<tr><th>Miss Rate</th><td>$miss_rate cache requests/second</td></tr>
			<tr><th>Set Rate</th><td>$set_rate cache requests/second</td></tr>
		</tbody></table>
	</p></div>";
				break;

			case 2: // variables


				$cacheItems = $this->getCacheItems();
				$items      = $cacheItems['items'];
//			$totals = $cacheItems['counts'];
//			$maxDump = self::MAX_ITEM_DUMP;
				foreach ($items as $server => $entries){

					echo
					'<div class="info">',
						'<table class="table-bordered"><tbody>
			<tr><th colspan="2">' . $server . '</th></tr>
			<tr><th>Slab Id</th><th>Info</th></tr>';
					$m = 1;
					foreach ($entries as $slabId => $slab){
						$m       = ($m) ? 0 : 1;
						$dumpUrl = '?op=2&server=' . (array_search($server, $this->MEMCACHE_SERVERS)) . '&dumpslab=' . $slabId;
						echo
						"<tr>",
						"<td><a href=\"$dumpUrl\">$slabId</a></td>",
						"<td class=td-last><b>Item count:</b> ", $slab['number'], '<br><b>Age:</b>', $this->duration($this->time - $slab['age']), '<br> <b>Evicted:</b>', ((isset($slab['evicted']) && $slab['evicted'] == 1) ? 'Yes' : 'No');
						if ((isset($_GET['dumpslab']) && $_GET['dumpslab'] == $slabId) && (isset($_GET['server']) && $_GET['server'] == array_search($server, $this->MEMCACHE_SERVERS))){
							echo "<br><b>Items: item</b><br>";
							$items = $this->dumpCacheSlab($server, $slabId, $slab['number']);
							// maybe someone likes to do a pagination here :)
//						$i = 0;
							$variable_links = [];
							foreach ($items['ITEM'] as $itemKey => $itemInfo){
								$variable_links[] = '<a href="?op=4&server=' . (array_search($server, $this->MEMCACHE_SERVERS)) . '&key=' . base64_encode($itemKey) . '">' . $itemKey . '</a>';
							}
							echo implode(', &nbsp;', $variable_links);
						}

						echo "</td></tr>";
					}
					echo
					'</tbody></table>
			</div><hr/>';
				}
				break;


			case 4: //item dump
				if (!isset($_GET['key']) || !isset($_GET['server'])){
					echo "No key set!";
					break;
				}
				// I'm not doing anything to check the validity of the key string.
				// probably an exploit can be written to delete all the files in key=base64_encode("\n\r delete all").
				// somebody has to do a fix to this.
				$theKey = htmlentities(base64_decode($_GET['key']));

				$r = $this->sendMemcacheCommand($this->host, $this->port, 'get ' . $theKey);
				if (!empty($r)){

					echo
					'<div class="info">
			<table class="table-bordered"><tbody>
				<tr><th>Server<th>Key</th><th>Value</th><th>Delete</th></tr>
			 <tr class="tr-0"><td>', $theserver, "</td><td>", $theKey,
					" <br>flag:", $r['VALUE'][$theKey]['stat']['flag'],
					" <br>Size:", $this->bsize($r['VALUE'][$theKey]['stat']['size']),
					"</td><td>", chunk_split($r['VALUE'][$theKey]['value'], 40), "</td>",
					'<td><a href="?op=5&server=', (int)$_GET['server'], '&key=', base64_encode($theKey), "\">Delete</a></td>", '</tr>
			</tbody></table>
		</div>';
				}else{
					echo '<div class=info>No Value Returned</div>';
				}
				break;

			case 5: // item delete
				if (!isset($_GET['key']) || !isset($_GET['server'])){
					echo "No key set!";
					break;
				}
				$theKey = htmlentities(base64_decode($_GET['key']));
				$r      = $this->sendMemcacheCommand($this->host, $this->port, 'delete ' . $theKey);
				echo 'Deleting ' . $theKey . ':' . $r;
				break;

		}
		echo '</div>'; // end of div.content.
		$this->output = ob_get_contents();
		ob_end_clean();

	}

	function fill_box($im, $x, $y, $w, $h, $color1, $color2, $text = '', $placeindex = ''){
		global $col_black;
		$x1 = $x + $w - 1;
		$y1 = $y + $h - 1;

		imagerectangle($im, $x, $y1, $x1 + 1, $y + 1, $col_black);
		if ($y1 > $y){
			imagefilledrectangle($im, $x, $y, $x1, $y1, $color2);
		}else{
			imagefilledrectangle($im, $x, $y1, $x1, $y, $color2);
		}
		imagerectangle($im, $x, $y1, $x1, $y, $color1);
		if ($text){
			if ($placeindex > 0){

				if ($placeindex < 16){
					$px = 5;
					$py = $placeindex * 12 + 6;
					imagefilledrectangle($im, $px + 90, $py + 3, $px + 90 - 4, $py - 3, $color2);
					imageline($im, $x, $y + $h / 2, $px + 90, $py, $color2);
					imagestring($im, 2, $px, $py - 6, $text, $color1);

				}else{
					if ($placeindex < 31){
						$px = $x + 40 * 2;
						$py = ($placeindex - 15) * 12 + 6;
					}else{
						$px = $x + 40 * 2 + 100 * intval(($placeindex - 15) / 15);
						$py = ($placeindex % 15) * 12 + 6;
					}
					imagefilledrectangle($im, $px, $py + 3, $px - 4, $py - 3, $color2);
					imageline($im, $x + $w, $y + $h / 2, $px, $py, $color2);
					imagestring($im, 2, $px + 2, $py - 6, $text, $color1);
				}
			}else{
				imagestring($im, 4, $x + 5, $y1 - 16, $text, $color1);
			}
		}
	}

	function fill_arc($im, $centerX, $centerY, $diameter, $start, $end, $color1, $color2, $text = '', $placeindex = 0){
		$r = $diameter / 2;
		$w = deg2rad((360 + $start + ($end - $start) / 2) % 360);


		if (function_exists("imagefilledarc")){
			// exists only if GD 2.0.1 is avaliable
			imagefilledarc($im, $centerX + 1, $centerY + 1, $diameter, $diameter, $start, $end, $color1, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2, IMG_ARC_PIE);
			imagefilledarc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color1, IMG_ARC_NOFILL | IMG_ARC_EDGED);
		}else{
			imagearc($im, $centerX, $centerY, $diameter, $diameter, $start, $end, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($start + 1)) * $r, $centerY + sin(deg2rad($start)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end - 1)) * $r, $centerY + sin(deg2rad($end)) * $r, $color2);
			imageline($im, $centerX, $centerY, $centerX + cos(deg2rad($end)) * $r, $centerY + sin(deg2rad($end)) * $r, $color2);
			imagefill($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $color2);
		}
		if ($text){
			if ($placeindex > 0){
				imageline($im, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $diameter, $placeindex * 12, $color1);
				imagestring($im, 5, $diameter, $placeindex * 12, $text, $color1);
			}else{
				imagestring($im, 4, $centerX + $r * cos($w) / 2, $centerY + $r * sin($w) / 2, $text, $color1);
			}
		}
	}

}
