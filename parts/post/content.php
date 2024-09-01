<?php global $post; ?>

<article class="b-article">
	<a class="b-article-permalink" href="<?php the_permalink() ?>">
		<?php the_date() ?>
	</a>
	<h1><?php the_title() ?></h1>

	<?php $categories = get_the_category() ?>
	<ul class="b-inline-list">
		<?php foreach ($categories as $category) : ?>
			<li>
				<a class="b-subject-link b-subject-link--small" href="<?php echo get_category_link($category->term_id); ?>">
					<?php echo $category->name; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>


	<?php if (has_post_thumbnail()) : ?>
		<div class="b-article-thumbnail wp-block-image">
			<?php the_post_thumbnail() ?>
		</div>
	<?php endif; ?>

	<div class="b-body-text">
		<?php the_content() ?>
	</div>

</article>
