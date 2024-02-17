<!doctype html>
<html class="no-js" lang="no-nb">
<head>
	<meta charset="utf-8">
	<title>Bleikøya</title>
	<meta name="description" content="">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<meta property="og:title" content="Bleikøya Vel">
	<meta property="og:type" content="">
	<meta property="og:url" content="">
	<meta property="og:image" content="">
	<meta name="theme-color" content="#b93e3c">

	<!-- <link rel="apple-touch-icon" href="icon.png"> -->
	<!-- Place favicon.ico in the root directory -->

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Didact+Gothic&family=Libre+Franklin:ital,wght@0,100;0,200;0,300;0,400;0,500;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
	<link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/assets/css/normalize.css">
	<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/assets/css/tralla.css">

	<script src="https://static.nrk.no/core-components/major/7/core-suggest/core-suggest.min.js"></script>
	<script src="<?php echo get_stylesheet_directory_uri(); ?>/assets/js/tralla.js"></script>
	<!-- <script src="<?php echo get_stylesheet_directory_uri(); ?>/assets/vendor/core-scroll.js"></script> -->

	<meta name="theme-color" content="#fafafa">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

	<div style="height: 1rem; background-color: var(--b-red-color)"></div>

	<nav class="b-navigation">
		<ul class="b-menu">
			<li class="b-menu__item">
				<a href="/" class="b-menu__link <?php if (is_front_page()) : ?>b-menu__link--active<?php endif; ?>">
					<?php if (is_user_logged_in()) : ?>
						<?php echo "Forside" ?>
					<?php else : ?>
						<?php echo "Om øya" ?>
					<?php endif; ?>
				</a>
			</li>
			<?php global $post; ?>

			<?php if (is_user_logged_in()) : ?>
				<li class="b-menu__item"><a class="b-menu__link <?php if (is_home() || is_single()) : ?>b-menu__link--active<?php endif; ?>" href="/oppslag">Oppslag</a></li>
				<li class="b-menu__item"><a class="b-menu__link <?php if ($post->post_name === 'info' || is_category()) : ?>b-menu__link--active<?php endif; ?>" href="/info/">Info</a></li>
			<?php endif; ?>
			<li class="b-menu__item"><a class="b-menu__link <?php if (tribe_is_event()) : ?>b-menu__link--active<?php endif; ?>" href="/kalender/">Kalender</a></li>
			<?php if (is_user_logged_in()) : ?>
				<?php $queried_object = get_queried_object() ?>
				<li class="b-menu__item"><a class="b-menu__link <?php if ($post->post_name === 'galleri' || (isset($queried_object->taxonomy) && $queried_object->taxonomy === 'gallery')) : ?>b-menu__link--active<?php endif; ?>" href="/galleri/">Bilder</a></li>
			<?php endif; ?>
			<li class="b-menu__item"><a class="b-menu__link <?php if ($post->post_name === 'kontakt') : ?>b-menu__link--active<?php endif; ?>" href="/kontakt/">Kontakt</a></li>
		</ul>

		<!-- <button class="b-menu-scroll-button"></button> -->

		<div class="b-login">

			<?php global $current_user; ?>
			<?php if (wp_get_current_user() && is_user_logged_in()) : ?>
					<button class="b-profile-button" type="button">
						<img class="b-profile-button__image" src="<?php echo get_avatar_url($current_user); ?>" />
					</button>
				<?php else : ?>
					<?php $loginout_link = wp_loginout('/', false); ?>
					<span class="b-profile-button__login-link">
						<?php echo $loginout_link; ?>
					</span>
				<?php endif; ?>
				</button>
		</div>
	</nav>


	<div class="b-header">
		<a class="b-header-link" href="/">
			<?php if (is_front_page()) : ?>
				<!-- <img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/b-logo-2.webp" style="margin-bottom: 2rem;" /> -->
			<?php endif; ?>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/b-logo.png" />
			<span class="b-header-link__extra">Bleikøya Velforening · 1923 - <?php echo date('Y'); ?></span>
		</a>
	</div>

	<?php if (is_user_logged_in()) : ?>
		<?php echo get_search_form(); ?>
	<?php endif; ?>
