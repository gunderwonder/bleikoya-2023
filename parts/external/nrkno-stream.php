<ul class="nrkmusikk-stream">

	<?php foreach ($feed as $item): ?>
		<li class="nrkmusikk-stream__item">
			<a href="<?php echo $item->get_permalink() ?>">
				<span class="nrkmusikk-stream__item-date">
					<?php $date = DateTime::createFromFormat(DateTime::ISO8601, $item->get_date('c')); ?>
					<?php echo $date->format('H:i'); ?>
				</span>
				<span class="nrkmusikk-stream__item-title"><?php echo $item->get_title() ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
