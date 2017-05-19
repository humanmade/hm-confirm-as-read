<?php
/**
 * Plugin Name:     HM confirm as read
 * Description:     Allow users to confirm that they have read a post
 * Author:          Human Made Limited
 * Author URI:      hmn.md
 * Text Domain:     hm-confirm-as-read
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         hm_confirm_as_read
 */

namespace HM;

require_once __DIR__ . '/inc/namespace.php';
require_once __DIR__ . '/inc/settings/namespace.php';

add_action( 'plugins_loaded', '\HM\ConfirmAsRead\init' );
