<?php

namespace HM\ConfirmAsRead\Settings;

/**
 * Setup admin.
 *
 * @return void
 */
function init() {

	add_settings_section(
		'hm-confirm-as-read',
		__( 'Confirm as read settings.', 'hm-confirm-as-read' ),
		__NAMESPACE__ . '\\render_settings_intro',
		'reading'
	);

	add_settings_field(
		'hm_confirm_as_read_instructions',
		'Instruction text',
		__NAMESPACE__ . '\\render_instructions_field',
		'reading',
		'hm-confirm-as-read'
	);
}

/**
 * Render settings section intro.
 *
 * @return void
 */
function render_settings_intro() {
	echo '<p>' . __( 'Allow users to confirm whether they have read posts or pages.', 'hm-confirm-as-read' ) . '</p>';
}

/**
 * Render instructions field.
 *
 * @return void
 */
function render_instructions_field( $args ) {
	$value = get_option( 'eg_setting_name' ), false ) . ' /> Explanation text';
	printf( '<textarea>%s</textarea>' );
}
