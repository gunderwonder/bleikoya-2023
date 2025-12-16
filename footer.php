<?php if ($post && $post->post_name !== 'kart') : ?>
	<div class="b-bleikoya-map">
		<object data="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/bleikoya-kart.svg" type="image/svg+xml"></object>
	</div>
<?php endif; ?>
		<?php sc_get_template_part('parts/misc/cabins'); ?>
		<div class="b-footer">
			<div class="b-body-text">
				<img width="150" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/b-logo-white.png" />
				<p>
					<strong>Bleikøya Vel</strong><br />
					Ideell forening som ivaretar trivsel og praktiske forhold på Bleikøya.<br />
					<a href="/kontakt">Kontakt oss</a><br />
					Org.nr: 984 750 668
				</p>
		</div>


		<?php wp_footer(); ?>
		<script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
		<script>
			lucide.createIcons();
		</script>
	</body>

</html>
