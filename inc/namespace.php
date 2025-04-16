<?php

namespace HM\ConfirmAsRead;

/**
 * Setup.
 *
 * @return void
 */
function init() {
	foreach ( [ 'post', 'page' ] as $post_type ) {
		add_post_type_support( $post_type, 'hm-confirm-as-read' );
	}

	add_filter( 'the_content', __NAMESPACE__ . '\\filter_the_content' );
	add_action( 'wp_head',     __NAMESPACE__ . '\\action_wp_head' );
	add_action( 'admin_init',  __NAMESPACE__ . '\\action_add_meta_boxes' );
	add_action( 'save_post',   __NAMESPACE__ . '\\handle_save_meta_box' );
	add_action( 'admin_init', __NAMESPACE__ . '\\Settings\init' );

	if ( isset( $_GET['hm-car-action-confirm-nonce'] ) ) {
		handle_action_confirm();
	} elseif ( isset( $_GET['hm-car-action-unconfirm-nonce'] ) ) {
		handle_action_unconfirm();
	}
}

/**
 * Add the meta box.
 *
 * @return void
 */
function action_add_meta_boxes() {
	$post_types = array_filter( get_post_types(), function( $post_type ) {
		return post_type_supports( $post_type, 'hm-confirm-as-read' );
	} );

	add_meta_box( 'hm-confirm-as-read', __( 'HM Confirm as read.', 'hm-confirm-as-read' ), __NAMESPACE__ . '\\render_meta_box', $post_types, 'side' );
}

/**
 * Handle wp head actions.
 *
 * @return void
 */
function action_wp_head() {
	if ( is_singular() && is_confirmation_allowed_for_post( get_the_ID() ) ) {
		render_styles();
	}
}

/**
 * Filter the content. Maybe add the confirmation button/UI.
 *
 * @param  string $content Post content.
 * @return string $content Post content.
 */
function filter_the_content( $content ) {
	if ( is_singular() && is_confirmation_allowed_for_post( get_the_ID() ) ) {
		ob_start();
		render_front_end_ui( get_the_ID() );
		$content = $content . ob_get_clean();
	}
	return $content;
}

/**
 * Get the users that have confirmed they have read a post.
 *
 * @param  int $post_id Post ID.
 * @return array User IDs.
 */
function get_confirmed_users_for_post( $post_id ) {
	return array_map( 'absint', (array) json_decode( get_post_meta( $post_id, 'hm_car_confirmed_users', true ) ) );
}

/**
 * Get users and confirmation times for a post.
 *
 * @param int $post_id Post ID.
 * @return array Map of user ID => timestamp. (Timestamp may be null for legacy data.)
 */
function get_confirmed_users( $post_id ) {
	$ids = (array) json_decode( get_post_meta( $post_id, 'hm_car_confirmed_users', true ) );

	$users = [];
	foreach ( $ids as $id ) {
		$confirmed_at = get_post_meta( $post_id, 'hm_car_confirmed_' . (int) $id, true );
		$users[ $id ] = empty( $confirmed_at ) ? null : (int) $confirmed_at;
	}

	return $users;
}

/**
 * Confirm a user has read a post.
 *
 * @param  int $user_id User ID.
 * @param  int $post_id Post ID.
 * @return void
 */
function confirm_user_for_post( $user_id, $post_id ) {
	$confirmed = get_confirmed_users_for_post( $post_id );
	if ( ! in_array( $user_id, $confirmed, true ) ) {
		$confirmed[] = $user_id;
		$confirmed   = sanitize_confirmed_users_data( $confirmed );
		update_post_meta( $post_id, 'hm_car_confirmed_users', (string) wp_json_encode( $confirmed ) );
		update_post_meta( $post_id, 'hm_car_confirmed_' . (int) $user_id, time() );
	}
}

/**
 * Remove a user from confirmed list.
 *
 * @param  int $user_id User ID.
 * @param  int $post_id Post ID.
 * @return void
 */
function unconfirm_user_for_post( $user_id, $post_id ) {
	$confirmed = get_confirmed_users_for_post( $post_id );
	$key       = array_search( $user_id, $confirmed, true );
	if ( false !== $key ) {
		unset( $confirmed[ $key ] );
		$confirmed = sanitize_confirmed_users_data( $confirmed );
		update_post_meta( $post_id, 'hm_car_confirmed_users', wp_json_encode( $confirmed ) );
		delete_post_meta( $post_id, 'hm_car_confirmed_' . (int) $user_id );
	}
}

/**
 * Check if a user has confirmed they have read a post.
 *
 * @param  int $user_id User ID.
 * @param  int $post_id Post ID.
 * @return void
 */
function is_user_confirmed_for_post( $user_id, $post_id ) {
	$user_ids = get_confirmed_users_for_post( $post_id );
	return in_array( $user_id, $user_ids );
}

/**
 * Sanitize confirmed user data.
 *
 * @param  array $data Dirty data, expected array of user IDs.
 * @return array       Array of IDs.
 */
function sanitize_confirmed_users_data( $user_ids ) {
	return array_filter( array_map( 'absint', $user_ids ) );
}

/**
 * Is the confirm as read functionality enabled for a post.
 *
 * @param  int  $post_id Post ID.
 * @return boolean       Is enabled.
 */
function is_enabled_for_post( $post_id ) {
	return (bool) get_post_meta( $post_id, 'hm_car_enabled', true );
}

/**
 * Is the confirm as read functionality allowed for a post.
 *
 * Checks post type support, logged in state, permissions and enabled state.
 *
 * @param  int  $post_id Post ID.
 * @return boolean Is allowed.
 */
function is_confirmation_allowed_for_post( $post_id ) {
	return (
		post_type_supports( get_post_type( $post_id ), 'hm-confirm-as-read' ) &&
		is_user_logged_in() &&
		current_user_can( 'read', $post_id ) &&
		is_enabled_for_post( $post_id )
	);
}

/**
 * Handle save meta box.
 *
 * @param  int $post_id Post ID.
 * @return int Post ID.
 */
function handle_save_meta_box( $post_id ) {

	if (
		! current_user_can( 'edit_post', $post_id ) ||
		( defined('DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		! isset( $_POST[ 'hm-car-meta-box-nonce'] )
	) {
		return $post_id;
	}

	check_admin_referer( 'hm_car_metabox', 'hm-car-meta-box-nonce' );

	if ( isset( $_POST['hm-car-enabled'] ) ) {
		update_post_meta( $post_id, 'hm_car_enabled', true );
	} else {
		delete_post_meta( $post_id, 'hm_car_enabled' );
	}

	if ( isset( $_POST['hm-car-reset'] ) ) {
		delete_post_meta( $post_id, 'hm_car_confirmed_users' );

		// Clear out all timestamps.
		$meta = get_post_meta( $post_id );
		foreach ( $meta as $key => $_value ) {
			if ( strpos( $key, 'hm_car_confirmed_' ) === 0 ) {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	return $post_id;
}

/**
 * Handle confirm as read user action.
 *
 * @return void
 */
function handle_action_confirm() {
	$nonce   = sanitize_text_field( $_GET['hm-car-action-confirm-nonce'] );
	$post_id = isset( $_GET['hm-car-post-id'] ) ? absint( $_GET['hm-car-post-id'] ) : 0;
	if (
		$post_id &&
		wp_verify_nonce( $nonce, 'hm_car_action_confirm' ) &&
		is_confirmation_allowed_for_post( $post_id )
	) {
		confirm_user_for_post( get_current_user_id(), $post_id );
		wp_safe_redirect( add_query_arg( [ 'hm-car-status' => 'confirmed' ], wp_get_referer() . '#hm-confirm-as-read' ) );
		exit;
	}
}

/**
 * Handle undo confirm as read user action.
 *
 * @return void
 */
function handle_action_unconfirm() {
	$nonce   = sanitize_text_field( $_GET['hm-car-action-unconfirm-nonce'] );
	$post_id = isset( $_GET['hm-car-post-id'] ) ? absint( $_GET['hm-car-post-id'] ) : 0;
	if (
		$post_id &&
		wp_verify_nonce( $nonce, 'hm_car_action_unconfirm' ) &&
		is_confirmation_allowed_for_post( $post_id )
	) {
		unconfirm_user_for_post( get_current_user_id(), $post_id );
		wp_safe_redirect( add_query_arg( [ 'hm-car-status' => 'unconfirmed', ], wp_get_referer() ) );
		exit;
	}
}

/**
 * Helper to get the post type singular label.
 *
 * @return string
 */
function get_post_type_label_singular( $post_id ) {
	$object = get_post_type_object( get_post_type( $post_id ) );
	if ( $object ) {
		return strtolower( $object->labels->singular_name );
	}
}

/**
 * Render the confirm as read box.
 *
 * @return string
 */
 function render_front_end_ui( $post_id ) {
	$settings = Settings\get_settings();

	?>
	<div id="hm-confirm-as-read" class="hm-confirm-as-read-container" style="clear: both; width: 100%;">

		<h3><?php esc_html_e( 'Confirm as read.', 'hm-confirm-as-read' ); ?></h3>

		<p><?php echo esc_html( $settings['instructions'] ); ?></p>

		<?php if ( ! is_user_confirmed_for_post( get_current_user_id(), $post_id ) ) : ?>
			<form action="<?php echo esc_url( get_permalink() ); ?>">
				<?php wp_nonce_field( 'hm_car_action_confirm', 'hm-car-action-confirm-nonce', true, true ); ?>

				<input type="hidden" name="hm-car-post-id" value="<?php echo absint( get_the_ID() ); ?>" />

				<button class="btn button Btn" type="submit">
					<?php echo esc_html( $settings['button_text'] ); ?>
				</button>
			</form>
		<?php else : ?>
			<?php
			$unconfirm_url = add_query_arg( [
				'hm-car-post-id'                => $post_id,
				'hm-car-action-unconfirm-nonce' => wp_create_nonce( 'hm_car_action_unconfirm' ),
				'_wp_http_referer'             => esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
			], get_permalink() );
			?>
			<p class="hm-car-notice"><small>
				<?php echo esc_html( $settings['confirmed_message_text'] ); ?>
				<a href="<?php echo esc_url( $unconfirm_url ); ?>"><?php esc_html_e( 'Undo', 'hm-confirm-as-read' ); ?></a>
			</small></p>
		<?php endif; ?>

		<div class="hm-confirm-as-read-confirmed-users">
			<h4><span class="hm-car-icon-tick">✔</span> <?php echo esc_html( $settings['confirmed_text'] ); ?></h4>
			<?php render_confirmed_users( $post_id ); ?>
		</div>
		<div class="hm-confirm-as-read-unconfirmed-users">
			<h4><span class="hm-car-icon-cross">✘</span> <?php echo esc_html( $settings['unconfirmed_text'] ); ?></h4>
			<?php render_unconfirmed_users( $post_id ); ?>
		</div>
	</div>
	<?php
}

/**
 * Render list of confirmed users.
 *
 * @param  int $post_id Post ID.
 * @return void
 */
function render_confirmed_users( $post_id ) {
	if ( $users = get_confirmed_users_for_post( $post_id ) ) {
		$users = get_users( [ 'include' => $users ] );
		echo '<ul class="hm-car-users">';
		foreach ( $users as $user ) {
			render_user_list_item( $user );
		}
		echo '</ul>';
	} else {
		$settings = Settings\get_settings();
		echo '<p>';
		echo esc_html( $settings['none_confirmed_text'] );
		echo '</p>';
	}
}

/**
 * Render list of unconfirmed users.
 *
 * @param  int $post_id Post ID.
 * @return void
 */
function render_unconfirmed_users( $post_id ) {
	$users = get_users( [ 'exclude' => get_confirmed_users_for_post( $post_id ) ] );
	if ( $users ) {
		echo '<ul class="hm-car-users">';
		foreach ( $users as $user ) {
			render_user_list_item( $user );
		}
		echo '</ul>';
	} else {
		$settings = Settings\get_settings();
		echo '<p>';
		echo esc_html( $settings['none_unconfirmed_text'] );
		echo '</p>';
	}
}

/**
 * Render a single user list item.
 *
 * Used by render_confirmed_users and render_unconfirmed_users.
 *
 * @param  WP_User $user WP User object.
 * @return void
 */
function render_user_list_item( $user ) {
	?>
	<li class="hm-car-user" tabindex="0">
		<span class="hm-car-user-name"><?php echo esc_html( $user->display_name ); ?></span>
		<?php echo get_avatar( $user->ID, 40 ); ?>
	</li>
	<?php
}

/**
 * Render a style element for styling the main front end UI.
 *
 * @return void
 */
function render_styles() {
	?>
	<style>

	.hm-confirm-as-read-container,
	.hm-confirm-as-read-container * {
		box-sizing: border-box;
	}

	.hm-confirm-as-read-container {
		padding: 20px;
		background: #EFF1EF;
		border-radius: 2px;
		margin: .75em 0;
	}

	.hm-confirm-as-read-container h3 {
		margin-top: 0;
	}

	.hm-confirm-as-read-container h4,
	.hm-confirm-as-read-container p {
		margin: .75em 0;
	}

	.hm-car-users,
	.hm-car-user {
		margin: 0;
		padding: 0;
		list-style: none;
		list-style-position: inside;
	}

	.hm-car-users:before,
	.hm-car-users:after {
		content: "";
		display: table;
	}

	.hm-car-users:after {
		clear: both;
	}

	.hm-car-user {
		position: relative;
		float: left;
		z-index: 1;
		width: 30px;
		margin: 0 0 5px 5px;
	}

	.hm-car-user img {
		width: 100%;
		height: auto;
	}

	.hm-car-user-name {
		display: none;
		position: absolute;
		bottom: 100%;
		margin-bottom: 8px;
		background: rgba(0,0,0,0.75);
		border-radius: 2px;
		color: white;
		font-size: 14px;
		line-height: 18px;
		padding: 5px 10px;
		z-index: 2;
		left: 50%;
		transform: translate( -50%, 0% );
	}

	.hm-car-user-name:after {
		content: ' ';
		display: block;
		border: 5px solid transparent;
		border-top-color: rgba(0,0,0,0.75);
		position: absolute;
		top: 100%;
		left: 50%;h
		margin-left: -5px;
	}

	.hm-car-user:hover .hm-car-user-name,
	.hm-car-user:focus .hm-car-user-name {
		display: block;
	}

	.hm-car-icon-tick,
	.hm-car-icon-cross {
		color: #7DBF67;
		margin: 0 10px 0 0;
	}

	.hm-car-icon-cross {
		color: #BF5241;
	}

	.hm-car-notice {
		padding: 5px 15px;
		background: #E3EDDF;
		border-radius: 2px;
	}
	</style>
	<?php
}

/**
 * Render the admin JS.
 *
 * Should be loaded only when the meta box is shown (post edit screen).
 *
 * @return void
 */
function render_admin_script() {
	?>
	<script>
		var el = document.querySelector( 'input[name="hm-car-reset"]' );
		el.addEventListener( 'change', function(e) {
			if ( ! confirm( <?php echo wp_json_encode( __( 'Are you sure you want to reset the confirm as read state for all users?', 'hm-confirm-as-read' ) ); ?> ) ) {
				e.preventDefault();
				this.checked = false;
			}
		})
	</script>
	<?php
}

/**
 * Render the post meta box.
 *
 * @return void
 */
function render_meta_box( $post ) {
	add_action( 'admin_footer', __NAMESPACE__ . '\\render_admin_script' );
	wp_nonce_field( 'hm_car_metabox', 'hm-car-meta-box-nonce', true, true )
	?>
	<p>
	<label style="display: block; padding-left: 25px;">
		<input type="checkbox" style="margin-left: -25px;" name="hm-car-enabled" <?php checked( is_enabled_for_post( $post->ID ) ); ?>/>
		<?php esc_html_e( 'Enable confirm as read.', 'hm-confirm-as-read' ); ?>
	</label>
	</p>
	<p>
	<label style="display: block; padding-left: 25px;">
		<input type="checkbox" style="margin-left: -25px;" name="hm-car-reset"/>
		<?php esc_html_e( 'Mark as major update. Reset users read status.', 'hm-confirm-as-read' ); ?>
	</label>
	</p>
	<?php
}
