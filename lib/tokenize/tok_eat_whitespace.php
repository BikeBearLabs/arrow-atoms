<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/tok_eat_whole.php';

function tok_eat_whitespace($haystack, &$i, $required = false) {
	return tok_eat_whole($haystack, $required ? '/^\s+/' : '/^\s*/', $i);
}
