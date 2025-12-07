<?php get_header(); ?>

<div class="b-center">
	<article class="b-article">
		<h1><?php echo single_cat_title() ?></h1>
		<?php $category = get_queried_object(); ?>

		<?php $documentation = sc_get_field('category-documentation', $category) ?>
		<?php $documentation = apply_filters('the_content', $documentation); ?>


		<div class="b-body-text">
			<?php echo $documentation; ?>
		</div>

	</article>
</div>

<?php
$posts = get_posts([
	'category' => $category->term_id,
	'posts_per_page' => -1,
	'post_status' => ['publish', 'private'],
]);
if ($posts) : ?>
	<aside class="b-center-wide">
		<h2>Relatert</h2>
		<?php foreach ($posts as $post) : ?>
			<?php setup_postdata($post); ?>
			<?php sc_get_template_part('parts/post/plug', 'post'); ?>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	</aside>
<?php endif; ?>

<?php if (is_user_logged_in()) : ?>
	<aside class="b-center-wide">
		<?php $categories = get_categories(array('hide_empty' => false)); ?>

		<?php sc_get_template_part('parts/category/category-index', null, array(
			'categories' => $categories
		)); ?>
	</aside>
<?php endif; ?>

<?php get_footer(); ?>
