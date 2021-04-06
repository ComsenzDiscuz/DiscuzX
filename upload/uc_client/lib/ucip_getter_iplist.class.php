<?php

/*
	[UCenter] (C)2001-2099 Comsenz Inc.
	This is NOT a freeware, use is subject to license terms

	$Id: ucip_getter_iplist.class.php 959 2019-12-19 12:00:00Z community $
*/

class ucip_getter_iplist {

	public static function get($s) {
		if (empty($s['header']) || empty($s['list'])) {
			return $_SERVER['REMOTE_ADDR'];
		}
		$ip = $_SERVER['REMOTE_ADDR'];
		$rdns = gethostbyaddr($ip);
		foreach($s['list'] as $host) {
			if (ucip::check_ip($ip, $host)) {
				if ($s['header'] != 'HTTP_X_FORWARDED_FOR') {
					$ip = ucip::validate_ip($_SERVER[$s['header']]) ? $_SERVER[$s['header']] : $ip;
				} else {
					if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ",") > 0) {
						$exp = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
						$ip = ucip::validate_ip(trim($exp[0])) ? $exp[0] : $ip;
					} else {
						$ip = ucip::validate_ip($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $ip;
					}
				}
			}
		}
		return $ip;
	}

}