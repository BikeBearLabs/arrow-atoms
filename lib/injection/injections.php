<?php

namespace AA;

if (!defined('ABSPATH')) exit;

$injections = [];

add_action('shutdown', function () use (&$injections) {
	array_splice($injections, 0);
});
