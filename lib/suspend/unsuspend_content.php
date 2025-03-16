<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/../injection/add_injection.php';

function unsuspend_content(string $string, array|null $injection = null) {
	$processor = new \WP_HTML_Tag_Processor($string);
	$processor_reflector = new \ReflectionObject($processor);
	$processor_token_length_prop = $processor_reflector->getProperty('token_length');
	$processor_token_length_prop->setAccessible(true);
	$processor_token_starts_at_prop = $processor_reflector->getProperty('token_starts_at');
	$processor_token_starts_at_prop->setAccessible(true);
	$get_curr_token_start = function () use ($processor, $processor_token_starts_at_prop) {
		return $processor_token_starts_at_prop->getValue($processor);
	};
	$get_curr_token_length = function () use ($processor, $processor_token_length_prop) {
		return $processor_token_length_prop->getValue($processor);
	};
	$open_stack = [];
	$replacements = [];
	while ($processor->next_token()) {
		if ($processor->get_token_type() !== '#comment') continue;

		$comment = $processor->get_modifiable_text();

		if (str_starts_with(trim($comment), 'aa:intermediate/')) {
			preg_match('/^aa:intermediate\/([\w\-\/]+)\s*(\{.*\})?/ms', trim($comment), $matches);
			$block_name = @$matches[1];
			if (!$block_name) continue;

			$attributes = json_decode(@$matches[2] ?? 'null', true);
			$attributes['injection'] = [
				'extends' => array_merge(
					$attributes['injection']['extends'] ?? [],
					$injection ? [add_injection($injection)] : [],
				),
				'value' => array_merge(
					$attributes['injection']['value'] ?? []
				)
			];

			$self_closing = str_ends_with(trim($comment), '/');

			$replacements[] = [
				'offset' => $get_curr_token_start(),
				'length' => $get_curr_token_length(),
				'content' => '<!--' .
					' wp:aa/' . $block_name . ' '
					. serialize_block_attributes($attributes) . ' '
					. ($self_closing ? '/' : '')
					. '-->',
			];

			if (!$self_closing) $open_stack[] = $block_name;

			continue;
		}

		if (str_starts_with(trim($comment), '/')) {
			$curr_open = $open_stack[count($open_stack) - 1];

			if (str_starts_with(trim($comment), "/aa:intermediate/$curr_open")) {
				$replacements[] = [
					'offset' => $get_curr_token_start(),
					'length' => $get_curr_token_length(),
					'content' => '<!-- /' . 'wp:aa/' . $curr_open . ' -->',
				];
				array_pop($open_stack);
			}

			continue;
		}
	}

	$cum = '';
	$curr = 0;
	foreach ($replacements as $replacement) {
		$cum .= substr($string, $curr, $replacement['offset'] - $curr);
		$cum .= $replacement['content'];
		$curr = $replacement['offset'] + $replacement['length'];
	}
	$cum .= substr($string, $curr);

	return $cum;
}
