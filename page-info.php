<?php get_header(); ?>

<?php if (is_user_logged_in()) : ?>


	<aside>
		<?php $categories = get_categories(array('hide_empty' => false)); ?>
		<!-- <ul class="b-inline-list">
			<?php foreach ($categories as $category) : ?>
				<li>
					<a class="b-subject-link" href="<?php echo get_category_link($category->term_id); ?>">
						<?php echo $category->name; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul> -->


		<?php
		$nested_categories = array();

		foreach ($categories as $category) {
			$first_letter = strtoupper(substr($category->name, 0, 1));
			if (!array_key_exists($first_letter, $nested_categories)) {
				$nested_categories[$first_letter] = array();
			}
			array_push($nested_categories[$first_letter], $category);
		}
		?>

		<ul class="b-subject-list clearfix">
			<?php foreach ($nested_categories as $letter => $letter_categories) : ?>
				<li class="b-subject-list__item">
					<span class="b-subject-list__first-letter"><?php echo $letter; ?></span>
					<ul class=" b-inline-list">
						<?php foreach ($letter_categories as $category) : ?>
							<li>
								<a class="b-subject-link" href="<?php echo get_category_link($category->term_id); ?>">
									<?php echo $category->name; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</li>
			<?php endforeach; ?>
		</ul>


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

				<?php $posts = get_posts(array('category' => $category->term_id)); ?>

				<?php if (count($posts) > 0) : ?>
					<h3 class="b-subject-list__item-posts-heading">Relaterte oppslag</h3>
					<ul class="b-subject-list__item-posts">
						<?php global $post; ?>
						<?php foreach ($posts as $post) : ?>
							<?php setup_postdata($post); ?>
							<li class="b-subject-list__item-post">

								<a href="<?php the_permalink() ?>" class="b-subject-list__item-post-link">
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
