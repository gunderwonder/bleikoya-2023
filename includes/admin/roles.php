<?php
/**
 * Custom user roles.
 *
 * "Styret" (the board): an explicit role giving editing rights to virtually all
 * content plus member-list administration, without full administrator access
 * (themes, plugins, site settings stay admin-only).
 *
 * Cabin ownership is tracked via ACF user meta (`user-cabin-number`), independent
 * of the WP role — so assigning/removing the Styret role does not touch a user's
 * cabin-owner status. Administer board membership via the Users screen
 * (Brukere → "Endre rolle til → Styret", or per-user role selector).
 */

// Bump this when the capability set below changes; the role is re-registered on
// the next admin page load after deploy (the theme is not re-activated on git pull).
define('BLEIKOYA_STYRET_ROLE_VERSION', 1);

add_action('admin_init', function () {
	if (get_option('bleikoya_styret_role_version') === BLEIKOYA_STYRET_ROLE_VERSION)
		return;

	// Clone the Editor role's capabilities as the baseline. This captures all
	// content caps (posts, pages, categories, media) AND The Events Calendar's
	// event caps (TEC grants those to Editor), without hardcoding plugin cap names.
	$editor = get_role('editor');
	$caps = $editor ? $editor->capabilities : array();

	// Explicit extras for the board — adjust freely.
	$caps += array(
		'list_users'         => true,  // see the Users list (unlocks Brukere menu)
		'edit_users'         => true,  // edit cabin-owner profiles
		'export_member_list' => true,  // custom cap gating the member-list export tools
		'read_private_posts' => true,
		'read_private_pages' => true,
	);
	// Deliberately NOT granted (cloning Editor already excludes these): manage_options,
	// promote_users, create_users, delete_users, switch_themes, activate_plugins, etc.
	// Without promote_users the role selector is hidden, so Styret cannot change roles.

	remove_role('styret');
	add_role('styret', 'Styret', $caps);

	// Administrators keep access to the member-list export tools (now gated on the
	// custom cap instead of manage_options).
	$admin = get_role('administrator');
	if ($admin)
		$admin->add_cap('export_member_list');

	update_option('bleikoya_styret_role_version', BLEIKOYA_STYRET_ROLE_VERSION);
});

/**
 * Prevent a Styret member (who has edit_users) from editing an administrator
 * account. Admins themselves are unaffected.
 */
add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
	if ($cap !== 'edit_user' || empty($args[0]))
		return $caps;

	$target_id = (int) $args[0];
	if ($target_id && $target_id !== (int) $user_id
		&& user_can($target_id, 'manage_options')
		&& !user_can($user_id, 'manage_options')) {
		$caps[] = 'do_not_allow';
	}

	return $caps;
}, 10, 4);
