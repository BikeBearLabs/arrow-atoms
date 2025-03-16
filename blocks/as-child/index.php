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
			if ($value === false || $value === null) return $content;

			$at = $attributes['at'];
			$op = $attributes['op'];
			$processor = new \WP_HTML_Tag_Processor($content);
			if (!$processor->next_tag()) return $content;

			if ($op === 'replace')
				$processor->set_attribute(
					$at,
					$value === true ? '' : $value
				);
			else {
				$prev_value = $processor->get_attribute($at);
				if ($op === 'append')
					$processor->set_attribute($at, $prev_value . $value);
				else if ($op === 'prepend')
					$processor->set_attribute($at, $value . $prev_value);
			}

			return $processor->get_updated_html();
		},
	]);
});
