<?php get_header(); ?>

<?php $categories = get_categories(array('hide_empty' => false)); ?>

<?php if (is_user_logged_in()) : ?>
	<aside class="b-center-wide">
		<?php sc_get_template_part('parts/category/category-index', null, array(
			'categories' => $categories
		)); ?>
	</aside>
<?php endif; ?>

<div class="b-info-layout">
	<main class="b-subject-index">
		<?php usort($categories, function ($a, $b) {
			return strcmp($a->name, $b->name);
		}); ?>

		<?php foreach ($categories as $category) : ?>

			<div class="b-subject-index__entry">
				<h2 class="b-subject-heading" id="category-<?php echo $category->term_id ?>">
					<a href="<?php echo get_category_link($category->term_id) ?>">
						<?php echo $category->name ?>
					</a>
				</h2>
				<div class="b-body-text">
					<?php $documentation = sc_get_field('category-documentation', $category) ?>
					<?php $documentation = apply_filters('the_content', $documentation) ; ?>
					<?php echo $documentation; ?>
				</div>

				<?php $posts = get_posts(array('category' => $category->term_id, 'post_status' => array('publish', 'private'))); ?>

				<?php if (count($posts) > 0) : ?>
					<h3 class="b-subject-list__item-posts-heading">Relaterte oppslag</h3>
					<ul class="b-subject-list__item-posts">
						<?php global $post; ?>
						<?php foreach ($posts as $post) : ?>
							<?php setup_postdata($post); ?>
							<li class="b-subject-list__item-post">

								<a href="<?php the_permalink() ?>" class="b-subject-list__item-post-link b-anchor--with-icon">
									<i data-lucide="newspaper" class="b-icon b-icon--small"></i>
									<?php the_title(); ?>
								</a>
							</li>

						<?php endforeach; ?>
					</ul>
					<?php wp_reset_postdata(); ?>
				<?php endif; ?>

				<?php $connected_locations = get_connected_locations($category->term_id, 'term'); ?>
				<?php if (!empty($connected_locations)) : ?>
					<h3 class="b-subject-list__item-posts-heading">På kartet</h3>
					<ul class="b-subject-list__item-posts">
						<?php foreach ($connected_locations as $location_id) : ?>
							<?php $location = get_post($location_id); ?>
							<?php if ($location && $location->post_status === 'publish') : ?>
								<?php $gruppe_terms = wp_get_post_terms($location_id, 'gruppe'); ?>
								<?php $overlay_slug = !empty($gruppe_terms) ? $gruppe_terms[0]->slug : ''; ?>
								<li class="b-subject-list__item-post">
									<a href="/kart/?poi=<?php echo $location_id; ?><?php echo $overlay_slug ? '&overlays=' . $overlay_slug : ''; ?>" class="b-subject-list__item-post-link b-anchor--with-icon">
										<i data-lucide="map-pin" class="b-icon b-icon--small"></i>
										<?php echo esc_html($location->post_title); ?>
									</a>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

		<?php endforeach; ?>
	</main>

	<!-- Desktop: Sticky sidebar TOC -->
	<aside class="b-toc">
		<?php sc_get_template_part('parts/category/category-toc', null, array(
			'categories' => $categories
		)); ?>
	</aside>
</div>

<!-- Mobil: FAB + Popup TOC -->
<div class="b-toc-mobile">
	<button class="b-toc__fab" aria-label="Innholdsfortegnelse">
		<i data-lucide="list" class="b-icon"></i>
	</button>
	<div class="b-toc__popup">
		<?php sc_get_template_part('parts/category/category-toc', null, array(
			'categories' => $categories
		)); ?>
	</div>
</div>

<?php get_footer();
