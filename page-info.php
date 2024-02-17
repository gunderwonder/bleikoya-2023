<?php get_header(); ?>

<?php if (is_user_logged_in()) : ?>
	<aside class="b-center-wide">
		<?php $categories = get_categories(array('hide_empty' => false)); ?>

		<?php sc_get_template_part('parts/category/category-index', null, array(
			'categories' => $categories
		)); ?>
	</aside>
<?php endif; ?>

<div class="b-center">
	<main class="b-subject-index">
		<?php usort($categories, function ($a, $b) {
			return strcmp($a->name, $b->name);
		}); ?>

		<?php foreach ($categories as $category) : ?>

			<div class="b-subject-index__entry">
				<h2 class="b-subject-heading" id="category-<?php echo $category->term_id ?>">
					<?php echo $category->name ?>
				</h2>
				<div class="b-body-text">
					<?php echo sc_get_field('category-documentation', $category) ?>
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
			</div>

		<?php endforeach; ?>
	</main>
</div>

<?php get_footer();
