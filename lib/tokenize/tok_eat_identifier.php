<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/tok_eat_whole.php';

function tok_eat_identifier(string $haystack, &$i) {
	return tok_eat_whole($haystack, '/^(?:[\w]|-(?!>))+/', $i);
}
