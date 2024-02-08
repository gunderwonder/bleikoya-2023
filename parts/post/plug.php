<article class="b-article-plug b-box b-box--green <?php if (has_post_thumbnail()): ?>b-article-plug--has-image<?php endif; ?>">
	<?php if (has_post_thumbnail()): ?>
		<a class="b-article-plug__thumbnail" href="<?php the_permalink() ?>">
			<?php the_post_thumbnail('thumbnail'); ?>
		</a>
	<?php endif; ?>

	<a class="b-article-permalink" href="<?php the_permalink() ?>">
		<?php the_date() ?>
	</a>
	<?php $categories = get_the_category() ?>
	<ul class="b-inline-list b-float-right">
		<?php foreach ($categories as $category) : ?>
			<li>
				<a class="b-subject-link b-subject-link--small" href="<?php echo get_category_link($category->term_id); ?>">
					<?php echo $category->name; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<h1 class="b-article-heading--small">
		<?php the_title() ?>
	</h1>

	<div class="b-body-text">
		<?php the_excerpt() ?>
	</div>
</article>
