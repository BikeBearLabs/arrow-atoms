<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/injections.php';

function get_injection(
	string $id,
) {
	global $injections;
	return $injections[$id];
}
