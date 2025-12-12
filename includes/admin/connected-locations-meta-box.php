<?php
/**
 * Connected Locations Meta Box
 *
 * Shows on posts, pages, events, and users to display
 * which kartpunkt (locations) are connected to them
 */

/**
 * Register meta box for connected locations
 */
function register_connected_locations_meta_box() {
	$post_types = array('post', 'page', 'tribe_events');

	foreach ($post_types as $post_type) {
		add_meta_box(
			'connected_locations',
			'Koblede steder',
			'render_connected_locations_meta_box',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action('add_meta_boxes', 'register_connected_locations_meta_box');

/**
 * Render connected locations meta box
 */
function render_connected_locations_meta_box($post) {
	$locations = get_connected_locations($post->ID, 'post');

	?>
	<div id="connected-locations-display">
		<?php if (empty($locations)) : ?>
			<p class="description">Ingen steder koblet til dette innholdet.</p>
		<?php else : ?>
			<ul class="connected-locations-list">
				<?php foreach ($locations as $location_id) : ?>
					<?php
					$location = get_post($location_id);
					if (!$location) {
						continue;
					}

					$type = get_location_type($location_id);
					$gruppe = wp_get_post_terms($location_id, 'gruppe', array('fields' => 'names'));
					?>
					<li class="connected-location-item">
						<span class="location-icon">📍</span>
						<a href="<?php echo esc_url(get_edit_post_link($location_id)); ?>" target="_blank">
							<?php echo esc_html($location->post_title); ?>
						</a>
						<span class="location-meta">
							<span class="location-type-badge"><?php echo esc_html($type); ?></span>
							<?php if (!empty($gruppe)) : ?>
								<span class="location-group-badge"><?php echo esc_html($gruppe[0]); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>

			<p class="description" style="margin-top: 10px;">
				<a href="<?php echo esc_url(home_url('/kart/')); ?>" target="_blank">
					Vis på kartet →
				</a>
			</p>
		<?php endif; ?>

		<p style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
			<button type="button" class="button" id="manage-location-connections">
				Administrer koblinger
			</button>
		</p>
	</div>

	<style>
		.connected-locations-list {
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.connected-location-item {
			padding: 8px;
			margin-bottom: 5px;
			background: #f9f9f9;
			border-left: 3px solid #2271b1;
			display: flex;
			align-items: center;
			gap: 5px;
			font-size: 12px;
		}
		.connected-location-item a {
			flex: 1;
			text-decoration: none;
		}
		.location-icon {
			font-size: 14px;
		}
		.location-meta {
			display: flex;
			gap: 5px;
		}
		.location-type-badge,
		.location-group-badge {
			display: inline-block;
			padding: 2px 6px;
			background: #eee;
			border-radius: 3px;
			font-size: 10px;
			text-transform: uppercase;
		}
		.location-group-badge {
			background: #d4edda;
			color: #155724;
		}
	</style>
	<?php
}

/**
 * Add connected locations section to user profile
 */
function render_user_connected_locations($user) {
	if (!current_user_can('edit_users')) {
		return;
	}

	$locations = get_connected_locations($user->ID, 'user');

	?>
	<h2>Koblede steder</h2>
	<table class="form-table">
		<tr>
			<th scope="row">Steder på kartet</th>
			<td>
				<?php if (empty($locations)) : ?>
					<p class="description">Ingen steder koblet til denne brukeren.</p>
				<?php else : ?>
					<ul class="connected-locations-list" style="margin-top: 0;">
						<?php foreach ($locations as $location_id) : ?>
							<?php
							$location = get_post($location_id);
							if (!$location) {
								continue;
							}

							$type = get_location_type($location_id);
							$gruppe = wp_get_post_terms($location_id, 'gruppe', array('fields' => 'names'));
							?>
							<li class="connected-location-item">
								<span class="location-icon">📍</span>
								<a href="<?php echo esc_url(get_edit_post_link($location_id)); ?>" target="_blank">
									<?php echo esc_html($location->post_title); ?>
								</a>
								<span class="location-meta">
									<span class="location-type-badge"><?php echo esc_html($type); ?></span>
									<?php if (!empty($gruppe)) : ?>
										<span class="location-group-badge"><?php echo esc_html($gruppe[0]); ?></span>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<p style="margin-top: 10px;">
					<a href="<?php echo esc_url(home_url('/kart/')); ?>" target="_blank" class="button">
						Vis på kartet
					</a>
				</p>
			</td>
		</tr>
	</table>

	<style>
		.connected-locations-list {
			margin: 0;
			padding: 0;
			list-style: none;
		}
		.connected-location-item {
			padding: 8px;
			margin-bottom: 5px;
			background: #f9f9f9;
			border-left: 3px solid #2271b1;
			display: flex;
			align-items: center;
			gap: 5px;
			font-size: 12px;
		}
		.connected-location-item a {
			flex: 1;
			text-decoration: none;
		}
		.location-icon {
			font-size: 14px;
		}
		.location-meta {
			display: flex;
			gap: 5px;
		}
		.location-type-badge,
		.location-group-badge {
			display: inline-block;
			padding: 2px 6px;
			background: #eee;
			border-radius: 3px;
			font-size: 10px;
			text-transform: uppercase;
		}
		.location-group-badge {
			background: #d4edda;
			color: #155724;
		}
	</style>
	<?php
}
add_action('show_user_profile', 'render_user_connected_locations');
add_action('edit_user_profile', 'render_user_connected_locations');
