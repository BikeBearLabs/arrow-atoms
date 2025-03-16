<?php

namespace AA;

if (!defined('ABSPATH')) exit;

$api_v1 = 'aa/v1';

add_action('rest_api_init', function () use ($api_v1) {
	register_rest_route($api_v1, '/render/islands/post/(?P<id>\d+)', array(
		'methods' => 'GET',
		'callback' => function (\WP_REST_Request $req) {
			$post_id = (int) $req['id'];
			$query = new \WP_Query([
				'p' => $post_id,
				'post_type' =>  get_post_types(),
			]);
			if (!$query->have_posts()) {
				wp_reset_postdata();
				return new \WP_REST_Response([
					'error' => 'Invalid post ID',
				], 400);
			}
			$post_content = get_post_field('post_content', $post_id);

			// 1. resolve all the patterns (wp:block) in the post_content
			// 2. extract all top-level aa/* blocks
			// 3. render the extracted blocks
			// 4. return as JSON

			$resolved_block_content = resolve_pattern_blocks($post_content);
			// var_dump('resolved_block_content', $resolved_block_content);
			$block_infos = get_block_infos($resolved_block_content, 'aa');
			// var_dump('block_infos', $block_infos);
			$top_level_block_contents = array_map(function ($block_info) use ($resolved_block_content) {
				$block_content = substr(
					$resolved_block_content,
					$block_info['opener_offset'],
					$block_info['self_closing']
						? $block_info['opener_length']
						: ($block_info['closer_offset'] + $block_info['closer_length']) - $block_info['opener_offset']
				);
				return $block_content;
			}, $block_infos);
			// var_dump('top_level_block_contents', $top_level_block_contents);
			$query->the_post();
			$rendered_block_contents = array_map(function ($block_content) {
				// var_dump('post', get_post());
				return do_blocks($block_content);
			}, $top_level_block_contents);
			wp_reset_postdata();

			header('cache-control: no-cache, no-store, must-revalidate');
			return new \WP_REST_Response($rendered_block_contents);
		},
		'permission_callback' => function () {
			return current_user_can('edit_posts');
		},
	));
});

function get_block_infos(string $content, string $namespace) {
	$processor = new \WP_HTML_Tag_Processor($content);
	$processor_reflector = new \ReflectionObject($processor);
	$processor_token_length_prop = $processor_reflector->getProperty('token_length');
	$processor_token_length_prop->setAccessible(true);
	$processor_token_starts_at_prop = $processor_reflector->getProperty('token_starts_at');
	$processor_token_starts_at_prop->setAccessible(true);
	$get_curr_token_start = function () use ($processor, $processor_token_starts_at_prop) {
		/** @var int */
		$start = $processor_token_starts_at_prop->getValue($processor);
		return $start;
	};
	$get_curr_token_length = function () use ($processor, $processor_token_length_prop) {
		/** @var int */
		$length = $processor_token_length_prop->getValue($processor);
		return $length;
	};
	$open_stack = [];
	$open_info_stack = [];
	$infos = [];
	$commit = function (array $info) use (&$open_info_stack, &$infos) {
		$open_info_parent = &$open_info_stack[array_key_last($open_info_stack)]
			?? null;
		// var_dump('info', $info);
		// var_dump('$open_info_parent', $open_info_stack[array_key_last($open_info_stack)]);
		if ($open_info_parent)
			$open_info_parent['children'][] = $info;
		else $infos[] = $info;
		// var_dump('infos', $infos);
	};
	while ($processor->next_token()) {
		if ($processor->get_token_type() !== '#comment') continue;

		$comment = $processor->get_modifiable_text();

		if (str_starts_with(trim($comment), "wp:$namespace/")) {
			preg_match("/^wp:$namespace\/([\w\-\/]+)\s*(\{.*\})?/ms", trim($comment), $matches);
			$block_name = @$matches[1];
			if (!$block_name) continue;

			$attributes = json_decode(@$matches[2] ?? 'null', true);
			$self_closing = str_ends_with(trim($comment), '/');
			$info = [
				'opener_offset' => $get_curr_token_start(),
				'opener_length' => $get_curr_token_length(),
				'closer_offset' => 0 ?: null,
				'closer_length' => 0 ?: null,
				'self_closing' => $self_closing,
				'namespace' => $namespace,
				'name' => $block_name,
				'attributes' => $attributes,
				'content' => trim($comment),
				'children' => [],
			];

			if ($self_closing) $commit($info);
			else {
				$open_stack[] = $block_name;
				$open_info_stack[] = $info;
				// var_dump('open', $block_name, $open_info_stack);
			}
			continue;
		}

		if (str_starts_with(trim($comment), '/')) {
			$curr_open = $open_stack[count($open_stack) - 1];
			// var_dump('curr_open', trim($comment), "/wp:$namespace/$curr_open");
			if (str_starts_with(trim($comment), "/wp:$namespace/$curr_open")) {
				$info = array_pop($open_info_stack);
				$info['closer_offset'] = $get_curr_token_start();
				$info['closer_length'] = $get_curr_token_length();
				$commit($info);
				array_pop($open_stack);
				// var_dump('close', $block_name, $open_info_stack);
			}
			continue;
		}
	}

	return $infos;
}

function resolve_pattern_blocks(string $content) {
	$resolved_content = '';

	preg_match_all(
		'/<!--\s*wp:block\s*\{.*?"ref":(\d+).*?\}\s*\/-->/ms',
		$content,
		$block_ref_matches,
		PREG_OFFSET_CAPTURE
	);
	$block_tag_and_tag_offset_list = $block_ref_matches[0];
	$block_id_and_id_offset_list = $block_ref_matches[1];
	if (empty($block_id_and_id_offset_list)) return $content;
	$pattern_block_infos = array_map(function ($block_tag_and_tag_offset, $block_id_and_id_offset) {
		$tag_offset = $block_tag_and_tag_offset[1];
		$tag_length = strlen($block_tag_and_tag_offset[0]);
		$id = $block_id_and_id_offset[0];
		return [
			'id' => $id,
			'offset' => $tag_offset,
			'length' => $tag_length,
		];
	}, $block_tag_and_tag_offset_list, $block_id_and_id_offset_list);

	$prev_block_offset = 0;
	foreach ($pattern_block_infos as $pattern_block_info) {
		$block_id = $pattern_block_info['id'];
		$block_offset = $pattern_block_info['offset'];
		$block_length = $pattern_block_info['length'];
		$block_content = get_post_field('post_content', $block_id);

		$pre_block_content = substr($content, $prev_block_offset, $block_offset - $prev_block_offset);
		$resolved_content .= $pre_block_content;

		$resolved_block_content = resolve_pattern_blocks($block_content);
		$resolved_content .= $resolved_block_content;

		$prev_block_offset = $block_offset + $block_length;
	}
	$post_block_content = substr($content, $prev_block_offset);
	$resolved_content .= $post_block_content;

	return $resolved_content;
}
