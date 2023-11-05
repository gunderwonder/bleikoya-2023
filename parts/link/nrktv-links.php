<?php if ($tv_query->have_posts()): ?>

    <?php while ($tv_query->have_posts()): ?>
        <?php $tv_query->the_post(); ?>
		<?php $url = sc_get_field('external-link') ?>
		<?php $parsed_url = sc_parse_video_url($url) ?>

		<?php $metadata = sc_get_json('https://psapi.nrk.no/playback/metadata/program/' . $parsed_url['id']) ?>

		<?php $link_image ?>
			<?php if ($metadata): ?>
				<?php $link_image = $metadata->preplay->poster->images[1]->url; ?>
			<?php else: ?>
			<?php $link_image = get_the_post_thumbnail_url(); ?>
		<?php endif ?>

		<div class="nrkmusikk-plug nrkmusikk-tvplug">
			<a href="<?php echo $url ?>">

				
				<img class="nrkmusikk-tvplug__image" src="<?php echo $link_image; ?>" />

				<div class="nrkmusikk-tvplug__content">
					<span class="nrkmusikk-nrktv-label ">
						<span class="nrk-sr">NRK TV</span>
						<svg style="width:5.8em;height:1.4em" focusable="false" aria-hidden="true"><use xlink:href="#nrk-logo-nrk-tv"></use></svg>
					</span>

					<h4 class="nrkmusikk-tvplug__title"><?php the_title(); ?></h4>
				</div>
			</a>
		</div>

	<?php endwhile ?>

<?php endif ?>
