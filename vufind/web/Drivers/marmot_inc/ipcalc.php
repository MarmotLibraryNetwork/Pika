<?php
/**
 * Pika Discovery Layer
 * Copyright (C) 2020  Marmot Library Network
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

$debug = 0;

#-------------------------------------------------------

function bitcount($val)
{
	global $debug;

	if ($debug >= 50)
	printf("bitcount: Initial value is: %s\n", $val);

	$rv = 0;
	if ($val < 0)
	{ $val = $val + 0xffffffff + 1; }
	while ($val > 0)
	{
		if ($val % 2 != 0)
		{ $rv++; }
		$val = $val / 2;
	}
	return $rv;
}

#-------------------------------------------------------

function ip2int($val)
{
	global $debug;

	if ($val == ""){
		return (int) 0xffffffff;  // host mask
	}elseif (preg_match("/^([0-9]*)$/", $val)){
		if ($debug >= 50){
			printf("DEBUG: input val to convert (num): %s\n", $val);
		}

		$rv = 0;
		for ($i = 32; $i > 0; $i--){
			$rv = $rv * 2 + (($val > 0) ? 1 : 0);

			if ($val > 0){
				$val--;
			}
		}
	}else{
		if ($debug >= 50){
			printf("DEBUG: input val to convert (cidr): %s\n", $val);
		}

		$octets = explode('.', $val);
		while (count($octets) < 4){
			$octets[] = 0;
		}

		$rv = 0;
		foreach ($octets as $octet){
			$rv = $rv * 256 + $octet;
		}
	}

	if ($debug >= 50)
	printf("DEBUG: computed value: %ld\n", $rv);

	return (int) $rv;
}

#-------------------------------------------------------
function FindBestMatch($ip, $subnet_array)
{
	global $debug;

	$ip_i = ip2int($ip);

	$bestmatch = null;
	$bestmatchlen = -1;

	foreach ($subnet_array as $nm) {
		$subnet_and_mask = explode('/', $nm->ip);
		$subnet_i = ip2int($subnet_and_mask[0]);
		if (count($subnet_and_mask) == 2){
			$mask_i = ip2int($subnet_and_mask[1]);

			if ($debug >= 60){
				printf("DEBUG: ip = %08x sn = %08x mask = %08x\n", $ip_i, $subnet_i, $mask_i);
			}

			$v1 = ($subnet_i & $mask_i);
			if ($v1 != $subnet_i){
				//MDN:  Temporarily ignore the errors.  Could also try to correct them.
				//printf("ERROR: %s is NOT on a subnet boundary (%08x %08x/%08x)\n", $nm->ip, $v1, $subnet_i, $mask_i);
				//printf("ERROR:   difference: %d (%s %s)\n", $v1 - $subnet_i, $v1, $subnet_i);
			}elseif ($debug >= 20){
				printf("DEBUG: %s -> %08x %08x\n", $nm->ip, $subnet_i, $subnet_i);
			}

			$bitlen = bitcount($mask_i);
			if ($debug >= 50){
				printf("DEBUG: bitcount for %s is %d\n", $nm->ip, $bitlen);
			}

			if (($ip_i & $mask_i) == $subnet_i) {
				if ($debug >= 20){
					printf("DEBUG: %s matches %s\n", $ip, $nm->ip);
				}

				if ($bitlen > $bestmatchlen){
					$bestmatchlen = $bitlen;
					$bestmatch    = $nm;
				}
			}
		}else{
			if ($ip_i == $subnet_i){
				$bestmatch    = $nm;
				break;
			}
		}
	}
	return $bestmatch;
}

function getIpRange(  $cidr) {

	list($ip, $mask) = explode('/', $cidr);

	$maskBinStr =str_repeat("1", $mask ) . str_repeat("0", 32-$mask );      //net mask binary string
	$inverseMaskBinStr = str_repeat("0", $mask ) . str_repeat("1",  32-$mask ); //inverse mask

	$ipLong = ip2long( $ip );
	$ipMaskLong = bindec( $maskBinStr );
	$inverseIpMaskLong = bindec( $inverseMaskBinStr );
	$netWork = $ipLong & $ipMaskLong;

	//$start = $netWork+1;//ignore network ID(eg: 192.168.1.0)
	$start = $netWork; //MDN, start at the network id

	$end = ($netWork | $inverseIpMaskLong) -1 ; //ignore brocast IP(eg: 192.168.1.255)
	return array( $start, $end );
}
