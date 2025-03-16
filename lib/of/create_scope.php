<?php

namespace AA;

if (!defined('ABSPATH')) exit;

function create_scope(array $attributes, \WP_Block $block) {
	return array_merge(
		resolve_injection($attributes['injection'] ?? null)['value'] ?? [],
		['overrides' => $block->context['pattern/overrides'] ?? []]
	);
}
