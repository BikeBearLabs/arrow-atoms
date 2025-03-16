<?php

namespace AA;

if (!defined('ABSPATH')) die;

require_once __DIR__ . '/../../lib/of/get.php';
require_once __DIR__ . '/../../lib/suspend/unsuspend_content.php';
require_once __DIR__ . '/../../lib/suspend/suspend.php';
require_once __DIR__ . '/../../lib/of/create_scope.php';

add_action('init', function () {
	/** 
	 * @param mixed[] $attributes
	 * @param string $content
	 * @param \WP_Block $block
	 */
	register_block_type_from_metadata(__DIR__, [
		'render_callback' => function ($attributes, $content, $block) {
			if ($block->context['aa/foreach/of'] ?? null)
				return suspend($attributes, $content, $block);

			$value = null;
			if (is_string($of = $attributes['of'] ?? null))
				$value = get($of, create_scope($attributes, $block));
			if (!is_array($value)) return '';

			$cum = '';
			$index = 0;
			foreach ($value as $key => $row) {
				$injection = [
					'value' => array_merge((array) $row, [
						'index' => $index,
						'length' => count($value),
						'key' => $key,

						// TODO: investigate the performance impact of exposing this every single iteration
						'tuple' => &$value,
					]),
				];

				$cum .= unsuspend_content($content, $injection);
				$index++;
			}

			return do_blocks($cum);
		},
	]);
});
