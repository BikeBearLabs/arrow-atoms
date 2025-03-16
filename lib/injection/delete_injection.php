<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/injections.php';

function delete_injection(
	string $name,
) {
	global $injections;
	unset($injections[$name]);
}
