<?php
/**
 * Compact Table of Contents for /info page
 * Displays categories as small badges without alphabetical grouping
 *
 * @param array $categories Array of category objects
 */

// Sort categories alphabetically
usort($categories, function ($a, $b) {
	return strcmp($a->name, $b->name);
});
?>

<nav class="b-toc__nav">
	<?php foreach ($categories as $category) : ?>
		<?php if ($category->term_id === 1) continue; // Skip "Uncategorized" ?>
		<a class="b-subject-link b-subject-link--small"
		   data-alternate-href="/info#category-<?php echo $category->term_id; ?>"
		   href="<?php echo get_category_link($category->term_id); ?>">
			<?php echo esc_html($category->name); ?>
		</a>
	<?php endforeach; ?>
</nav>
