<?php

if (!defined('ABSPATH')) exit;

function suspend(array $attributes, string $content, \WP_Block $block) {
	$block_name = $block->name;
	$intermediate_child_name = 'aa:intermediate/' . preg_replace('/^aa\//', '', $block_name);
	$attributes_json_string = serialize_block_attributes($attributes);

	return "<!-- $intermediate_child_name $attributes_json_string "
		. ($content ?
			"-->$content<!-- /$intermediate_child_name -->"
			: "/-->");
}
