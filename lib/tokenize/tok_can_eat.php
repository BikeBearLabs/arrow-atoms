<?php

namespace AA;

if (!defined('ABSPATH')) exit;

function tok_can_eat($haystack, $needle, $i) {
	return substr($haystack, $i, strlen($needle)) === $needle;
}
