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
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'instructions' ]
	);

	add_settings_field(
		'hm_confirm_as_read_button_text',
		'Button text',
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'button_text' ]
	);

	add_settings_field(
		'hm_confirm_as_read_confirmed_message_text',
		'Confirmed message text',
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'confirmed_message_text' ]
	);

	add_settings_field(
		'hm_confirm_as_read_confirmed_text',
		'Confirmed heading text',
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'confirmed_text' ]

	);

	add_settings_field(
		'hm_confirm_as_read_none_confirmed_text',
		'None confirmed text',
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'none_confirmed_text' ]
	);

	add_settings_field(
		'hm_confirm_as_read_unconfirmed_text',
		'Unconfirmed heading text',
		__NAMESPACE__ . '\\render_text_field',
		'reading',
		'hm-confirm-as-read',
		[ 'key' => 'unconfirmed_text' ]
	);

	register_setting( 'reading', 'hm_confirm_as_read_settings', __NAMESPACE__ . '\\sanitize_setting' );
}

function get_keys() {
	return [
		'instructions',
		'button_text',
		'confirmed_message_text',
		'confirmed_text',
		'none_confirmed_text',
		'unconfirmed_text',
	];
}

/**
 * Get settings.
 *
 * @param  boolean $raw Return raw settings or with default values. If true returns only data from the database.
 * @return array settings.
 */
function get_settings( $raw = false ) {
	$settings = get_option( 'hm_confirm_as_read_settings' );
	if ( $raw ) {
		$settings = wp_parse_args( $settings, array_map( '__return_empty_string', array_flip( get_keys() ) ) );
	} else {
		$settings = wp_parse_args( array_filter( $settings ), get_default_settings() );
	}
	return $settings;
}

function get_default_settings() {
	$post_type_label = \HM\ConfirmAsRead\get_post_type_label_singular( get_the_ID() );

	return [
		'instructions'     => sprintf(
			__( 'Please confirm that you have read and understood the content of this %s and any material that has been linked to from it.', 'hm-confirm-as-read' ),
			esc_html( $post_type_label )
		),
		'button_text'      => sprintf(
			__( 'I confirm that I have read this %s.', 'hm-confirm-as-read' ),
			esc_html( $post_type_label )
		),
		'confirmed_message_text' => __( 'You have confirmed you have read this.', 'hm-confirm-as-read' ),
		'confirmed_text' => __( 'The following users have confirmed.', 'hm-confirm-as-read' ),
		'none_confirmed_text' => __( 'No users have confirmed.', 'hm-confirm-as-read' ),
		'unconfirmed_text' => __( 'The following users have not confirmed.', 'hm-confirm-as-read' ),
		'none_unconfirmed_text' => __( 'Great! Everyone has confirmed.', 'hm-confirm-as-read' ),
	];
}

function sanitize_setting( $dirty_data ) {
	$clean_data = [];
	foreach ( get_keys() as $key ) {
		if ( isset( $dirty_data[ $key ] ) ) {
			$clean_data[ $key ] = sanitize_text_field( $dirty_data[ $key ] );
		}
	}
	return $clean_data;
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
 * Render text field.
 *
 * @return void
 */
function render_text_field( $args ) {
	$settings = get_settings( true );
	printf(
		'<input type="text" name="hm_confirm_as_read_settings[%s]" class="large-text" value="%s" />',
		$args['key'],
		esc_attr( $settings[ $args['key'] ] )
	);
}
