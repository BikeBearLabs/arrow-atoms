<?php

namespace AA;

if (!defined('ABSPATH')) exit;

function tok_eat_whole($haystack, $regex, &$i) {
	preg_match($regex, substr($haystack, $i), $matches, 0);
	if (empty($matches)) return '';
	$ate = $matches[0];
	$i += strlen($ate);
	return $ate;
}
