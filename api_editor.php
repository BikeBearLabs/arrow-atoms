<?php

namespace AA;

if (!defined('ABSPATH')) die;

add_action('wp_enqueue_editor', function () {
	$handle = 'aa/api/editor';
	wp_register_script($handle, false);
	wp_enqueue_script($handle);
	$api = [
		'root' => rtrim(rest_url(), '/'),
		'nonce' => wp_create_nonce('wp_rest'),
	];
	$api_serialized = json_encode($api);
	$inline_script = <<<JS
		AA = $api_serialized;
		JS;
	wp_add_inline_script($handle, $inline_script, 'before');
});
