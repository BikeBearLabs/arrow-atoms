<?php

namespace AA;

if (!defined('ABSPATH')) die;

require_once __DIR__ . '/../../lib/of/get.php';
require_once __DIR__ . '/../../lib/suspend/unsuspend_content.php';
require_once __DIR__ . '/../../lib/suspend/suspend.php';

add_action('init', function () {
	/** 
	 * @param mixed[] $attributes
	 * @param string $content
	 * @param \WP_Block $block
	 */
	register_block_type_from_metadata(__DIR__, [
		'render_callback' => function ($attributes, $content, $block) {
			if (
				($block->context['aa/foreach/of'] ?? null)
				|| ($block->context['aa/if/of'] ?? null)
			)
				return suspend($attributes, $content, $block);

			return unsuspend_content($content);
		},
	]);
});
