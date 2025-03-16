<?php

namespace AA;

if (!defined('ABSPATH')) die;

add_action('init', function () {
	/** 
	 * @param mixed[] $attributes
	 * @param string $content
	 * @param WP_Block $block
	 */
	register_block_type_from_metadata(__DIR__, [
		'render_callback' => function ($attributes, $content, $block) {
			$target = $attributes['target'];
			$post = get_post();
			$permalink = get_permalink($post);
			return <<<HTML
				<a href="$permalink" target="$target">$content</a>
			HTML;
		},
	]);
});
