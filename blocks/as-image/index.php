<?php

namespace AA;

if (!defined('ABSPATH')) die;

require_once __DIR__ . '/../../lib/of/get.php';
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
			if (
				($block->context['aa/foreach/of'] ?? null)
				|| ($block->context['aa/if/of'] ?? null)
			)
				return suspend($attributes, $content, $block);

			$value = null;
			if (is_string($of = $attributes['of'] ?? null))
				$value = get($of, create_scope($attributes, $block));

			if (is_string($value))
				return <<<HTML
					<img src="{$value}" />
				HTML;

			if (is_array($value))
				return <<<HTML
					<img src="{$value['url']}"
						alt="{$value['alt']}"
						class="wp-image-{$value['id']}"
					/>
				HTML;

			if ($value instanceof \WP_Post) {
				$url = wp_get_attachment_image_url($value->ID, 'full');
				return <<<HTML
					<img src="{$url}"
						alt="{$value->post_title}"
						class="wp-image-{$value->ID}"
					/>
				HTML;
			}

			return $content;
		},
	]);
});
