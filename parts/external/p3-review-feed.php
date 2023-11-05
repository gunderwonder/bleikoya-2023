<ul class="nrkmusikk-review-list">
	<?php foreach ($feed as $item): ?>
		<li>
			<?php $dice_match = null ?>
			<?php foreach ($item->get_categories() as $category): ?>
				<?php if (preg_match('/^Terningkast (\d+)$/', $category->term, $dice_match)): ?>
					<svg class="nrkmusikk-review-list__dice" focusable="false" aria-hidden="true">
						<use xlink:href="#nrk-dice-<?php echo $dice_match[1]; ?>--active"></use>
					</svg>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php unset($dice_match) ?>
			<a href="<?php echo $item->get_permalink() ?>"><?php echo $item->get_title() ?></a>
		</li>
	<?php endforeach; ?>
</ul>
