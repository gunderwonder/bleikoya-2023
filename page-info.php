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
			<?php foreach ($nested_categories as $letter => $categories) : ?>
				<li class="b-subject-list__item">
					<span class="b-subject-list__first-letter"><?php echo $letter; ?></span>
					<ul class=" b-inline-list">
						<?php foreach ($categories as $category) : ?>
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
	<main>



		<?php if (have_posts()) : ?>
			<?php while (have_posts()) : ?>
				<?php the_post(); ?>
				<?php sc_get_template_part('parts/page/content-page', get_post_type(), sc_get_post_fields()); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</main>
</div>

<?php get_footer();
