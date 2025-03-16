<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/injections.php';

function add_injection(
	array $injection,
) {
	global $injections;

	$id = md5(json_encode($injection));
	if (isset($injections[$id])) return $id;

	$injections[$id] = $injection;
	return $id;
}
