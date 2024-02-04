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
