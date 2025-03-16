<?php

/**
 * Plugin Name:       <code>-></code> Arrow Atoms
 * Description:       Experimental, functional-expression-based blocks to fetch dynamic content without breaking into PHP.
 * Requires Plugins:  advanced-custom-fields-pro
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Version:           0.0.0
 * Author:            jiaSheng
 * Text Domain:       aa
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/api_editor.php';
require_once __DIR__ . '/api_rest.php';
require_once __DIR__ . '/blocks/as-text/index.php';
require_once __DIR__ . '/blocks/as-image/index.php';
require_once __DIR__ . '/blocks/as-child/index.php';
require_once __DIR__ . '/blocks/as-block/index.php';
require_once __DIR__ . '/blocks/foreach/index.php';
require_once __DIR__ . '/blocks/if/index.php';
require_once __DIR__ . '/blocks/if-then/index.php';
require_once __DIR__ . '/blocks/if-else/index.php';
require_once __DIR__ . '/blocks/permalink/index.php';
