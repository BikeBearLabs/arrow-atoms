<?php

namespace AA;

if (!defined('ABSPATH')) die;

require_once __DIR__ . '/../../lib/of/get.php';
require_once __DIR__ . '/../../lib/suspend/suspend.php';
require_once __DIR__ . '/../../lib/of/create_scope.php';

// TODO: this is a really unoptimized approach to this problem
// currently, this block intercepts _all blocks within itself_
// after they've already rendered, then reverts them back to
// their serialized form to be re-parsed again when
// `render_callback` actually runs.
// it's tricky because i don't think there is a way to suspend
// block rendering for the blocks we don't control (i.e. using
// our own `suspend()`), as there isn't a hook that fires
// before the block is rendered.
// then, there isn't a real way to pass data up the block tree
// either, hence the serialization->re-parsing approach.

add_filter('block_type_metadata', function ($metadata) {
	$block_name = $metadata['name'] ?? null;
	if (!$block_name || $block_name === 'aa/as-block/of')
		return;

	$metadata['usesContext'] = [
		...($metadata['usesContext'] ?? []),
		'aa/as-block/of',
	];

	return $metadata;
}, 10, 1);

add_filter('render_block', function (string $block_content, array $block, \WP_Block $instance) {
	$block_name = $instance->name;
	if (
		!($instance->context['aa/as-block/of'] ?? null)
		|| $block_name === 'aa/as-block/of'
	)
		return $block_content;

	// revert to original representation to be re-parsed
	return serialize_block($block);
}, 0, 3);

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
			if (!$value) return $content;

			$blocks = parse_blocks($content);
			$content = '';
			foreach ($blocks as $block) {
				$block_name = $block['blockName'];
				if (!$block_name) continue;
				$block['attrs'] = array_merge_deep($block['attrs'], $value);
				$content .= render_block($block);
			}

			return $content;
		},
	]);
});

function array_merge_deep(array $target, array $source) {
	foreach ($source as $key => $value) {
		// if the key exists in target and both are arrays, recursively merge
		if (array_key_exists($key, $target) && is_array($target[$key]) && is_array($value)) {
			$target[$key] = array_merge_deep($target[$key], $value);
		} else {
			// otherwise, overwrite or add the value from source
			$target[$key] = $value;
		}
	}
	return $target;
}
