<?php

namespace AA;

if (!defined('ABSPATH')) exit;

function tok_eat($haystack, $needle, &$i) {
	for (
		$ii = 0;
		$ii < strlen($needle);
		$ii++
	) {
		if (
			$i + $ii >= strlen($haystack)
			|| $haystack[$i + $ii] !== $needle[$ii]
		)
			return '';
	}
	$i += strlen($needle);
	return $needle;
}
