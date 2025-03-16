<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/../tokenize/tok_eat.php';
require_once __DIR__ . '/../tokenize/tok_can_eat.php';
require_once __DIR__ . '/../tokenize/tok_eat_whole.php';
require_once __DIR__ . '/../tokenize/tok_eat_identifier.php';
require_once __DIR__ . '/../tokenize/tok_eat_whitespace.php';

/**
 * SECURITY RESEARCHERS LOOK AWAY
 * 
 * This function is a recursive descent parser for a simple
 * expression language that allows for accessing fields
 * and calling functions with arguments.
 * 
 * It's used to evaluate the `of` attribute of the Arrow Atoms
 * blocks, & CAN DO THINGS LIKE ACCESS PROPERTIES ON OBJECTS 
 * & RETURNING THEM.
 * 
 * However, do not fret as it's *probably* secure, due to the 
 * fact that the client can't invoke `do_block` without being 
 * a privileged user, but it's still a recursive descent parser,
 * so it's worth (not?) looking at.
 */
// TODO: optimise to use lazy evaluation. instead of interpreting
// starting from the inner args, we create functions that will
// interpret the args when called, return those as the args.
// so, when we wanna use the args, we'll just do `$args[0]()` etc.
// helps to not run stuff in if(), or(), etc. that doesn't need to be run.
function evaluate(string $of, &$i = 0, $scope = [], $depth = 0) {
	if ($depth > 1024)
		throw new \Exception('Maximum `evaluate` recursion depth exceeded');
	tok_eat_whitespace($of, $i);
	if (!tok_can_eat($of, '->', $i)) return null;

	static $debug = false;

	if ($debug) var_dump($of);

	$value = null;
	/** @var IterContext[] */
	$iter_stack = [];
	for (
		$chained = false;
		$i < strlen($of);
		$chained = true
	) {
		$iter_context = $iter_stack[count($iter_stack) - 1] ?? null;
		$ate = parse_function_call($of, $i, function ($field, &$i) use ($scope, $depth) {
			return evaluate($field, $i, $scope, $depth + 1);
		}, $function_name, $user_args, $intrinsic_namespace);
		if ($ate) {
			$args = $chained ? [$value, ...$user_args] : $user_args;
			if ($debug) var_dump('fn: call', $function_name, $ate);

			if ($intrinsic_namespace !== null) {
				switch ($intrinsic_namespace) {
					case 'iter':
						if ($function_name === '') {
							$iter_stack[] = new IterContext($args[0] ?: []);
							break;
						}
						if (!$iter_context) throw new \BadMethodCallException(
							"Cannot call `iter::$function_name` outside of an `iter::` block"
						);
						switch ($function_name) {
							case 'find':
							case 'find-index':
								$finding_index = $function_name === 'find-index';
								$found = null;
								$tuple = $iter_context->tuple;
								switch ($args[1] ?? null) {
									case 'first':
										break;
									case 'last':
										$tuple = array_reverse($tuple, true);
										break;
									default:
								}
								foreach ($tuple as $tuple_key => $tuple_value) {
									$returned = array_reduce($iter_context->instructions, function ($v, $instruction) {
										return interpret_function_call($instruction[0], [$v, ...$instruction[1]]);
									}, $tuple_value);
									if ($returned) {
										$found = $finding_index ? $tuple_key : $tuple_value;
										break;
									}
								}
								$value = $found;
								array_pop($iter_stack);
								break;
							case 'filter':
								switch ($args[1] ?? null) {
									case 'unique':
										$seen = [];
										$uniques = [];
										foreach ($iter_context->tuple as $tuple_key => $tuple_value) {
											$returned = array_reduce($iter_context->instructions, function ($v, $instruction) {
												return interpret_function_call($instruction[0], [$v, ...$instruction[1]]);
											}, $tuple_value);
											if ($seen[$returned]) continue;
											$uniques[$tuple_key] = $tuple_value;
											$seen[$returned] = true;
										}
										$value = $uniques;
										break;
									case '':
									default:
										$value = array_filter($iter_context->tuple, function ($tuple_value) use ($iter_context) {
											return array_reduce($iter_context->instructions, function ($v, $instruction) {
												return interpret_function_call($instruction[0], [$v, ...$instruction[1]]);
											}, $tuple_value);
										});
										break;
								}
								array_pop($iter_stack);
								break;
							case 'collect':
								$value = array_map(function ($tuple_value) use ($iter_context) {
									return array_reduce($iter_context->instructions, function ($v, $instruction) {
										return interpret_function_call($instruction[0], [$v, ...$instruction[1]]);
									}, $tuple_value);
								}, $iter_context->tuple);
								array_pop($iter_stack);
								// TODO: actually run these operations in the iter
								// instead of looping over again, for performance
								switch ($args[1] ?? null) {
									case 'reverse':
										$value = array_reverse($value);
										break;
									case 'unique':
										$value = array_unique($value, SORT_REGULAR);
										break;
									default:
								}
								break;
							default:
								throw new \InvalidArgumentException(
									'Invalid iter function: ' . $function_name
								);
						}
						break;
					default:
						throw new \InvalidArgumentException(
							'Invalid intrinsic namespace: ' . $intrinsic_namespace
						);
				}
				if ($debug) var_dump('fn: ret (iter)', $value);
				continue;
			}

			if ($iter_context) {
				$iter_context->instructions[] = [$function_name, $user_args];
				continue;
			}
			$value = interpret_function_call(
				$function_name,
				$args
			);
			if ($debug) var_dump('fn: ret', $value);
			continue;
		}

		$ate = parse_property_access($of, $i, $property);
		if ($ate) {
			if ($debug) var_dump('prop: pick', $property);
			if ($iter_context) {
				$iter_context->instructions[] = ['pick', [$property]];
				continue;
			}
			if ($chained) $value = interpret_chained_property_access(
				$property,
				$value
			);
			else $value = interpret_global_property_access(
				$property,
				$scope
			);
			// if ($debug) var_dump('prop: ret', $value);

			continue;
		}

		break;
	}

	return $value;
}

class IterContext {
	public array $tuple;
	public array $instructions = [];

	public function __construct(array $tuple) {
		$this->tuple = $tuple;
	}
}

function interpret_function_call(string $function_name, array $args) {
	$value = null;

	switch ($function_name) {
		case 'tuple-reverse':
			$value = array_reverse($args[0], $args[1] ?? null);
			break;
		case 'record-merge':
		case 'tuple-merge':
			$value = array_merge(...$args);
			break;
		case 'tuple-join':
			$value = implode($args[1] ?? '', $args[0] ?? []);
			break;
		case 'tuple-unique':
			$value = array_unique($args[0], SORT_REGULAR);
			break;
		case 'tuple-intersect':
			$value = array_intersect(...$args);
			break;
		case 'tuple-dehole':
			$index = 0;
			$value = [];
			foreach ($args[0] as $k => $v) {
				if (is_int($k)) {
					$value[$index++] = $v;
				} else {
					$value[$k] = $v;
				}
			}
			break;
		case 'tuple-order-by':
			$tuple = $args[0];
			if (!is_array($tuple)) {
				$value = $tuple;
				break;
			}
			$key = $args[1];
			if (!is_string($key)) {
				$value = $tuple;
				break;
			}
			$orders = [];
			$order = strtolower($args[2] ?? null ?: 'asc');
			foreach ($tuple as $i => $row)
				$orders[$i] = $row[$key];

			array_multisort(
				$orders,
				$order === 'asc' ? SORT_ASC : (
					$order === 'desc' ? SORT_DESC :
					SORT_ASC
				),
				$tuple
			);

			$value = $tuple;
			break;
		case '-':
		case 'tuple':
			$value = $args;
			break;
		case '--':
		case 'record':
			$value = array_reduce(
				$args,
				function ($acc, $pair) {
					$acc[$pair[0]] = $pair[1];
					return $acc;
				},
				[]
			);
			break;
		case 'record-entries':
			$value = array_map(
				function ($key, $value) {
					return [$key, $value];
				},
				array_keys($args[0]),
				array_values($args[0])
			);
			break;
		case 'string':
			$value = implode('', $args);
			break;
		case 'string-split':
			$value = explode($args[1] ?? '', $args[0] ?? []);
			break;
		case 'string-pad-left':
			$value = str_pad($args[0], $args[1], $args[2] ?? ' ', STR_PAD_LEFT);
			break;
		case 'string-pad-right':
			$value = str_pad($args[0], $args[1], $args[2] ?? ' ', STR_PAD_RIGHT);
			break;
		case 'string-trim':
			$value = trim($args[0], $args[1] ?? " \t\n\r\0\x0B");
			break;
		case 'string-trim-words':
			$value = wp_trim_words($args[0], $args[1] ?? 55, $args[2] ?? '…');
			break;
		case 'string-starts-with':
			$value = strpos($args[0], $args[1]) === 0;
			break;
		case 'string-ends-with':
			$value = substr($args[0], -strlen($args[1])) === $args[1];
			break;
		case 'string-focus':
			$haystack = preg_replace('/\s+/', ' ', $args[0]);
			$needle = $args[1];

			$words = preg_split('/ /', $haystack);
			$needle_lower = strtolower($needle);
			$result = [];
			$prev_index = null;
			foreach ($words as $index => $word) {
				// Check if the current word matches the needle (case-insensitive)
				if (strtolower($word) === $needle_lower) {
					// Calculate start and end indexes for surrounding context
					$start = max(0, $index - 3);
					$end = min(count($words) - 1, $index + 3);

					// Add ellipsis if there's a gap from the previous match
					if ($prev_index !== null && $start > $prev_index + 1) {
						$result[] = '...';
					}

					// Add words surrounding the needle
					for ($i = $start; $i <= $end; $i++) {
						// Highlight the needle with asterisks
						$result[] = $i === $index ? "<b>{$words[$i]}</b>" : $words[$i];
					}

					// Update the last index of needle match
					$prev_index = $end;
				}
			}

			// Add final ellipsis if needed
			if (count($result) > 0 && $prev_index < count($words) - 1) {
				$result[] = '...';
			}

			// Join the result array into a single string
			$value = implode(' ', $result);
			break;
		case 'slice':
			if (is_array($args[0])) {
				if ($args[2] ?? null)
					$value = array_slice($args[0], $args[1], $args[2] - $args[1]);
				else $value = array_slice($args[0], $args[1]);
				break;
			}
			if (is_string($args[0])) {
				if ($args[2] ?? null)
					$value = substr($args[0], $args[1], $args[2] - $args[1]);
				else $value = substr($args[0], $args[1]);
				break;
			}
			break;
		case 'length':
			$value = 0;
			if (($args[0] ?? null) === null)
				$value = 0;
			if (is_string($args[0]))
				$value = strlen($args[0]);
			else if (is_array($args[0]))
				$value = count($args[0]);
			break;
		case 'at':
			if (($args[0] ?? null) === null || ($args[1] ?? null) === null)
				break;
			$index = $args[1];
			if ($index < 0)
				$index = count($args[0]) + $index;
			$value = $args[0][$index] ?? null;
			break;
		case 'int':
			$value = is_numeric($args[0]) ? (int) ($args[0] ?? null) : 0;
			break;
		case 'float':
			$value = is_numeric($args[0]) ? (float) ($args[0] ?? null) : 0.0;
			break;
		case 'boolean':
			$value = !!($args[0] ?? null);
			break;
		case 'true':
			$value = true;
			break;
		case 'false':
			$value = false;
			break;
		case 'field':
			if (count($args) === 0)
				$value = 0;
			else if (count($args) === 1)
				$value = get_field($args[0]);
			else if (count($args) === 2)
				$value = get_field($args[1], $args[0]);
			else if (count($args) === 3)
				$value = get_field($args[1], $args[0], $args[2]);
			else
				$value = get_field($args[1], $args[0], $args[2], $args[3]);
			break;
		case 'option':
			$value = get_field($args[0], 'option');
			break;
		case 'post':
			$value = get_post($args[0] ?? null, $args[1] ?? null, $args[2] ?? null);
			break;
		case 'post-title':
			$value = get_the_title($args[0] ?? null);
			break;
		case 'post-featured-image':
			$thumbnail_id = get_post_thumbnail_id($args[0] ?? null);
			if ($thumbnail_id)
				$value = get_post($thumbnail_id);
			break;
		case 'post-date':
			if (count($args) === 0)
				$value = get_the_date();
			else if (count($args) === 1)
				$value = get_the_date($args[0]);
			else
				$value = get_the_date($args[1], $args[0]);
			break;
		case 'post-type':
			$value = get_post_type($args[0] ?? null);
			break;
		case 'post-type-label':
			$get_post_type_label_prop = fn($arg) => (
				$arg === 'plural' ?
				'name'
				: ($arg === 'singular' ?
					'singular_name'
					: null)
			);
			if (count($args) === 0)
				$value = get_post_type_object(get_post_type())->label;
			else if (count($args) === 1) {
				if (is_numeric($args[0]))
					$value = get_post_type_object(get_post_type((int) $args[0]))->label;
				else if ($args[0] instanceof \WP_Post)
					$value = get_post_type_object($args[0]->post_type)->label;
				else if (is_string($args[0])) {
					$prop = $get_post_type_label_prop($args[0]);
					if ($prop)
						$value = get_post_type_object(get_post_type())->labels->$prop;
				}
			} else {
				$prop = $get_post_type_label_prop($args[1]);
				if ($prop)
					$value = get_post_type_object($args[0])->$prop;
			}
			break;
		case 'post-type-object':
			$value = get_post_type_object($args[0] ?? get_post_type());
			break;
		case 'post-content':
			$value = get_post_field('post_content', $args[0] ?? null);
			break;
		case 'do-blocks':
			$value = do_blocks(($args[0] ?? null));
			break;
		case 'all-posts':
			$value = (new \WP_Query(array_merge([
				'posts_per_page' => -1,
			], $args[0])))->posts;
			break;
		case 'permalink':
			$value = get_permalink(($args[0] ?? null));
			break;
		case 'permalink-archive':
			if (count($args) === 0) {
				$value = get_post_type_archive_link(get_post_type());
			} else {
				if (is_numeric($args[0]))
					$value = get_post_type_archive_link(get_post_type((int) $args[0]));
				else if ($args[0] instanceof \WP_Post)
					$value = get_post_type_archive_link($args[0]->post_type);
				else if (is_string($args[0]))
					$value = get_post_type_archive_link($args[0]);
			}
			break;
		case 'ancestors':
			$value = array_map(
				function ($id) {
					return get_post($id);
				},
				get_post_ancestors(($args[0] ?? null) ?: get_the_ID())
			);
			break;
		case 'parent':
			$parent_id = get_post_ancestors(($args[0] ?? null) ?: get_the_ID())[0];
			if ($parent_id)
				$value = get_post($parent_id);
			break;
		case 'terms':
			if (is_string($args[0]))
				$value = get_the_terms(($args[1] ?? null) ?: get_the_ID(), $args[0]);
			else if (is_numeric($args[0]))
				$value = get_the_terms((int) $args[0], $args[1]);
			else if (is_object($args[0]))
				$value = get_the_terms($args[0], $args[1]);
			else
				$value = null;
			if (!$value || is_wp_error($value))
				$value = null;
			break;
		case 'all-terms':
			$value = get_terms(array_merge([
				'taxonomy' => $args[0],
				'hide_empty' => false,
				'fields' => 'all_with_object_id',
			], ($args[1] ?? [])));
			if (!$value || is_wp_error($value))
				$value = null;
			break;
		case 'contains':
			$value = is_string($args[0]) ?
				strpos($args[0], $args[1]) !== false
				: (is_array($args[0]) ?
					in_array($args[1], $args[0], true)
					: false);
			break;
		case 'extract':
			$value = [];
			for ($i = 1; $i < count($args); $i++) {
				$key = $args[$i];
				$value[$key] = $args[0][$key] ?? null;
			}
			break;
		case 'pick':
			$value = pick($args[0], $args[1]);
			break;
		case 'gt':
			$value = ($args[0] ?? null) > ($args[1] ?? null);
			break;
		case 'gte':
			$value = ($args[0] ?? null) >= ($args[1] ?? null);
			break;
		case 'lt':
			$value = ($args[0] ?? null) < ($args[1] ?? null);
			break;
		case 'lte':
			$value = ($args[0] ?? null) <= ($args[1] ?? null);
			break;
		case 'eq':
			$value = ($args[0] ?? null) == ($args[1] ?? null);
			break;
		case 'neq':
			$value = ($args[0] ?? null) != ($args[1] ?? null);
			break;
		case 'and':
		case 'both':
			$value = null;
			foreach ($args as $arg) {
				$value = $arg;
				if (!$value) break;
			}
			break;
		case 'or':
		case 'either':
			$value = null;
			foreach ($args as $arg) {
				$value = $arg;
				if ($value) break;
			}
			break;
		case 'not':
			$value = !($args[0] ?? false);
			break;
		case 'if':
			$value = $args[0] ? ($args[1] ?? null) : ($args[2] ?? null);
			break;
		case 'sum':
			$value = array_sum($args);
			break;
		case 'sub':
			$first = $args[0] ?? null;
			if ($first === null) {
				$value = 0;
				break;
			}
			$rest = array_slice($args, 1);
			$value = array_reduce($rest, function ($acc, $v) {
				return $acc - $v;
			}, $first);
			break;
		case 'mul':
			$first = $args[0] ?? null;
			if ($first === null) {
				$value = 0;
				break;
			}
			$rest = array_slice($args, 1);
			$value = array_reduce($rest, function ($acc, $v) {
				return $acc * $v;
			}, $first);
			break;
		case 'div':
			$first = $args[0] ?? null;
			if ($first === null) {
				$value = 0;
				break;
			}
			$rest = array_slice($args, 1);
			$value = array_reduce($rest, function ($acc, $v) {
				return $acc / $v;
			}, $first);
			break;
		case 'mod':
			$first = $args[0] ?? null;
			if ($first === null) {
				$value = 0;
				break;
			}
			$rest = array_slice($args, 1);
			$value = array_reduce($rest, function ($acc, $v) {
				return $acc % $v;
			}, $first);
			break;
		case 'url-param':
			$value = sanitize_text_field(
				$_GET[$args[0]] ?? '',
			);
			break;
		case 'url-encode':
			$value = urlencode($args[0]);
			break;
		case 'url-decode':
			$value = urldecode($args[0]);
			break;
		case 'box':
			$value = [$args[1] ?? 'value' => $args[0]];
			break;
		case 'date-format':
			if (count($args) === 1)
				$value = date_create_immutable()->format($args[0]);
			else
				$value = date_create_immutable($args[0])->format($args[1]);
			break;
		case 'number':
			$value = is_numeric($args[0]) ? $args[0] + 0 : NAN;
			break;
		case 'number-format':
			$value = number_format($args[0], $args[1] ?? 0, $args[2] ?? '.', $args[3] ?? ',');
			break;
		case 'number-format-ordinal':
			$value = $args[0] . (
				$args[0] % 100 === 11 || $args[0] % 100 === 12 || $args[0] % 100 === 13
				? 'th'
				: ($args[0] % 10 === 1
					? 'st'
					: ($args[0] % 10 === 2
						? 'nd'
						: ($args[0] % 10 === 3
							? 'rd'
							: 'th'
						)
					)
				)
			);
			break;
		case 'strip-tags':
			$value = wp_strip_all_tags($args[0]);
			break;
		default:
	}

	return $value;
}

function interpret_chained_property_access(string $property, mixed $scope) {
	$value = pick($scope, $property);
	if ($value === null)
		$value = get_matching_field_value($property, $scope);
	return $value;
}

function interpret_global_property_access(string $property, array $scope) {
	$value = get_global_value($property);
	if ($value === null)
		$value = interpret_chained_property_access($property, $scope);
	return $value;
}

function pick($from, $key) {
	if ($from === null) return null;
	// special case WP_Post as it implements a magic __get
	// that does some magic & also gets from post meta.
	// we don't want to use the builtin post meta logic
	// if we're using acf, so we just short circuit to
	// our get field value implementation if it's not a
	// recognised property.
	if ($from instanceof \WP_Post)
		if (property_exists($from, $key) || in_array($key, [
			'page_template',
			'post_category',
			'tags_input',
			'ancestors',
		]))
			return $from->$key;
		else return get_field_value($key, $from->ID);
	if (is_array($from)) return $from[is_numeric($key) ? (int) $key : $key] ?? null;
	if (is_object($from)) return $from->$key ?? null;
	return null;
}

function get_global_value(string $property) {
	$value = null;

	switch ($property) {
		case 'false':
			$value = false;
			break;
		case 'true':
			$value = true;
			break;
		case 'null':
			$value = null;
			break;
		case 'url':
			$value = home_url() . $_SERVER['REQUEST_URI'];
			break;
		default:
	}

	return $value;
}

function get_matching_field_value(string $property, mixed $scope) {
	$id = null;
	$object = null;
	if (is_array($scope)) {
		$id = $scope['ID'] ?? null;
		if (!is_numeric($id))
			$id = $scope['id'] ?? null;
		if (!is_numeric($id))
			$id = $scope['post_id'] ?? null;
	} else if (is_object($scope)) {
		if (
			$scope instanceof \WP_Post
			|| $scope instanceof \WP_Term
		) {
			$object = $scope;
		} else {
			$id = $scope->ID ?? null;
			if (!is_numeric($id))
				$id = $scope->id ?? null;
			if (!is_numeric($id))
				$id = $scope->post_id ?? null;
		}
	}
	if (is_numeric($id))
		$id = (int) $id;

	$value = get_field_value($property, $object ?? $id ?? get_the_ID());

	return $value;
}

function get_field_value(string $property, mixed $post_or_id) {
	$value = null;

	if (function_exists('get_field'))
		$value = get_field($property, $post_or_id);
	else $value = get_post_meta($post_or_id, $property, true);

	return $value;
}

function parse_function_call(
	string $field,
	int &$i,
	callable $recurse,
	string|null &$function_name,
	array|null &$args,
	string|null &$intrinsic_namespace,
) {
	$prev_i = $i;
	// consume the first valid chain of `->name(args)`
	// and return the function name & the arguments

	$function_name = null;
	$args = null;
	$intrinsic_namespace = null;

	tok_eat_whitespace($field, $i);
	if (!tok_eat($field, '->', $i)) goto fail;
	tok_eat_whitespace($field, $i);
	$function_name = tok_eat_identifier($field, $i);
	if (!$function_name) goto fail;
	if (tok_eat($field, '::', $i)) {
		$intrinsic_namespace = $function_name;
		$function_name = tok_eat_identifier($field, $i);
		// function names can be empty if paired with
		// an intrinsic namespace
		if (!$function_name) $function_name = '';
	}
	tok_eat_whitespace($field, $i);
	if (!tok_eat($field, '(', $i)) goto fail;

	$args = [];
	while ($i < strlen($field)) {
		tok_eat_whitespace($field, $i);
		if (tok_can_eat($field, '->', $i)) {
			$pre_recurse_i = $i;
			$arg = $recurse($field, $i);
			if ($i === $pre_recurse_i) goto fail;
			$args[] = $arg;
			tok_eat_whitespace($field, $i);
		} else if (tok_can_eat($field, "'", $i)) {
			tok_eat($field, "'", $i);
			$arg = tok_eat_whole($field, '/^.*?(?=(?<!\\\)[\'])/ms', $i);
			if (!tok_eat($field, "'", $i)) goto fail;
			$args[] = $arg;
			tok_eat_whitespace($field, $i);
		} else {
			$arg = tok_eat_whole($field, '/^.*?(?=(?<!\\\)[,)])/ms', $i);
			if (strlen($arg) === 0 && !tok_can_eat($field, ',', $i)) break;
			if ($arg === null) break;
			$arg = trim($arg);
			if (is_string($arg) && is_numeric($arg))
				if (strpos($arg, '.') !== false)
					$arg = (float) $arg;
				else $arg = (int) $arg;
			$args[] = $arg;
		}
		if (!tok_eat($field, ',', $i)) break;
	}

	if (!tok_eat($field, ')', $i)) goto fail;

	return substr($field, $prev_i, $i);

	fail:
	$i = $prev_i;
	$function_name = null;
	$args = null;
	$intrinsic_namespace = null;
	return null;
}

function parse_property_access($field, &$i, &$property) {
	$prev_i = $i;
	// consume the first valid access of `->name`
	// and return the property name

	$property = null;

	tok_eat_whitespace($field, $i);
	if (!tok_eat($field, '->', $i)) goto fail;
	tok_eat_whitespace($field, $i);
	$ate = parse_identifier($field, $i, $property);
	if (!$ate) goto fail;

	return substr($field, $prev_i, $i);

	fail:
	$i = $prev_i;
	$property = null;
	return null;
}

function parse_identifier($field, &$i, &$identifier) {
	$prev_i = $i;
	// consume the first valid identifier
	// and return the identifier

	$identifier = null;

	parse_string($field, $i, $identifier);
	if ($identifier !== null)
		return substr($field, $prev_i, $i);

	$identifier = tok_eat_identifier($field, $i);
	if (!strlen($identifier)) goto fail;
	return substr($field, $prev_i, $i);

	fail:
	$i = $prev_i;
	$identifier = null;
	return null;
}

function parse_string($field, &$i, &$string) {
	$prev_i = $i;
	// consume the first valid string
	// and return the string

	$string = null;

	if (!tok_eat($field, "'", $i)) goto fail;
	$string = tok_eat_whole($field, '/^.*?(?=(?<!\\\)[\'])/ms', $i);
	if (!tok_eat($field, "'", $i)) goto fail;
	return substr($field, $prev_i, $i);

	fail:
	$i = $prev_i;
	$string = null;
	return null;
}
