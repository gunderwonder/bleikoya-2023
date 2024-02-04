<article class="b-article-plug">
	<a class="b-article-permalink" href="<?php the_permalink() ?>">
		<?php the_date() ?>
	</a>
	<?php $categories = get_the_category() ?>


	<h1 class="b-article-heading--small">
		<?php the_title() ?>
	</h1>

	<ul class="b-inline-list">
		<?php foreach ($categories as $category) : ?>
			<li>
				<a class="b-subject-link b-subject-link--small" href="<?php echo get_category_link($category->term_id); ?>">
					<?php echo $category->name; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="b-body-text">
		<?php the_excerpt() ?>
	</div>
</article>
