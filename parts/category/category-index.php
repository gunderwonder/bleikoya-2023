<?php
// Build list of all entries (categories + aliases)
$all_entries = array();

foreach ($categories as $category) {
	if ($category->term_id === 1)
		continue;

	// Add the category itself
	$all_entries[] = array(
		'name' => $category->name,
		'term_id' => $category->term_id,
		'link' => get_category_link($category->term_id),
		'is_alias' => false,
	);

	// Add aliases as separate entries pointing to same category
	$aliases = sc_get_category_aliases($category);
	foreach ($aliases as $alias) {
		$all_entries[] = array(
			'name' => $alias,
			'term_id' => $category->term_id,
			'link' => get_category_link($category->term_id),
			'is_alias' => true,
		);
	}
}

// Sort alphabetically by name
usort($all_entries, function($a, $b) {
	return strcasecmp($a['name'], $b['name']);
});

// Group by first letter
$nested_entries = array();
foreach ($all_entries as $entry) {
	$first_letter = strtoupper(substr($entry['name'], 0, 1));
	if (!array_key_exists($first_letter, $nested_entries)) {
		$nested_entries[$first_letter] = array();
	}
	$nested_entries[$first_letter][] = $entry;
}
?>

<ul class="b-subject-list clearfix">
	<?php foreach ($nested_entries as $letter => $letter_entries) : ?>
		<li class="b-subject-list__item">
			<span class="b-subject-list__first-letter"><?php echo $letter; ?></span>
			<ul class=" b-inline-list">
				<?php foreach ($letter_entries as $entry) : ?>
					<li>
						<a class="b-subject-link<?php echo $entry['is_alias'] ? ' b-subject-link--alias' : ''; ?>" data-alternate-href="/info#category-<?php echo $entry['term_id'] ?>" href="<?php echo $entry['link']; ?>">
							<?php echo esc_html($entry['name']); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</li>
	<?php endforeach; ?>
</ul>
