<?php

namespace AA;

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/evaluate.php';
require_once __DIR__ . '/../tokenize/tok_can_eat.php';

function get(
	string $of,
	array|null $scope = [],
) {
	$field = trim($of);

	if (!tok_can_eat(trim($field), '->', 0))
		$field = '->' . $field;

	return evaluate($field, scope: $scope);
}
