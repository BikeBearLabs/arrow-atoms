<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/injections.php';

function resolve_injection(
	array|null $injection,
) {
	if (!$injection) return ['value' => []];

	global $injections;
	/** @var string[] */
	$extends = $injection['extends'] ?? null;
	if (!$extends) return $injection;

	foreach (array_reverse($extends) as $extend) {
		$extension = $injections[$extend] ?? null;
		if (!$extension) continue;
		$extension = resolve_injection($extension);
		$injection['value'] = array_merge($extension['value'], $injection['value']);
	}

	unset($injection['extends']);
	return $injection;
}
