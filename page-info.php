<?php get_header(); ?>

<?php if (is_user_logged_in()) : ?>


		<aside>
			<?php $categories = get_categories(array('hide_empty' => false)); ?>
			<ul class="b-inline-list">
				<?php foreach ($categories as $category) : ?>
					<li>
						<a class="b-subject-link" href="<?php echo get_category_link($category->term_id); ?>">
							<?php echo $category->name; ?>
						</a>
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
