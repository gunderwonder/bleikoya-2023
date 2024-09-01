<!doctype html>
<html class="no-js" lang="no-nb">

<head>
	<meta charset="utf-8">
	<title><?php bloginfo('name'); ?><?php wp_title(); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<meta name="description" content="<?php if (is_single()) {
														single_post_title('', true);
													} else {
														bloginfo('name');
														echo " - ";
														bloginfo('description');
													}
													?>" />
	<meta property="og:type" content="">
	<meta property="og:url" content="">
	<meta property="og:image" content="">
	<meta name="theme-color" content="#b93e3c">

	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo ASSETS_DIR ?>/favicon/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php echo ASSETS_DIR ?>/favicon/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php echo ASSETS_DIR ?>/favicon-16x16.png">
	<link rel="manifest" href="<?php echo ASSETS_DIR ?>/site.webmanifest">

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Jost:ital,wght@0,100..900;1,100..900&family=Libre+Franklin:ital,wght@0,100;0,200;0,300;0,400;0,500;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

	<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/assets/css/normalize.css">
	<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/assets/css/tralla.css?">

	<script src="https://static.nrk.no/core-components/major/7/core-suggest/core-suggest.min.js"></script>
	<script src="<?php echo ASSETS_DIR ?>/js/tralla.js?foo=1"></script>

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
				<li class="b-menu__item"><a class="b-menu__link <?php if ($post && $post->post_name === 'info' || is_category()) : ?>b-menu__link--active<?php endif; ?>" href="/info/">Info</a></li>
			<?php endif; ?>
			<li class="b-menu__item"><a class="b-menu__link <?php if (tribe_is_event()) : ?>b-menu__link--active<?php endif; ?>" href="/kalender/">Kalender</a></li>
			<?php if (is_user_logged_in()) : ?>
				<?php $queried_object = get_queried_object() ?>
				<li class="b-menu__item"><a class="b-menu__link <?php if ($post && $post->post_name === 'galleri' || (isset($queried_object->taxonomy) && $queried_object->taxonomy === 'gallery')) : ?>b-menu__link--active<?php endif; ?>" href="/galleri/">Bilder</a></li>
			<?php endif; ?>
			<li class="b-menu__item"><a class="b-menu__link <?php if ($post && $post->post_name === 'kontakt') : ?>b-menu__link--active<?php endif; ?>" href="/kontakt/">Kontakt</a></li>
		</ul>

		<!-- <button class="b-menu-scroll-button"></button> -->

		<div class="b-login">
			<?php $loginout_link = wp_loginout('/', false); ?>
			<?php $logout_url = wp_logout_url('/') ?>

			<?php $user_admin_url = user_admin_url(); ?>
			<?php global $current_user; ?>
			<?php if (wp_get_current_user() && is_user_logged_in()) : ?>
				<?php $current_user_id = get_current_user_id(); ?>
				<?php $profile_url = get_edit_profile_url($current_user_id); ?>
				<button class="b-profile-button" type="button">
					<img class="b-profile-button__image" src="<?php echo get_avatar_url($current_user); ?>" />

					<menu class="b-profile-button__menu">
						<ul>
							<li class="b-profile-button__menu-item">
								<a href="<?php echo $profile_url; ?>" tabindex="1">
									<i data-lucide="user"></i> Profil
								</a>

							</li>
							<li class="b-profile-button__menu-item">
								<a href="<?php echo $logout_url; ?>" tabindex="2">
									<i data-lucide="log-out"></i> Logg ut
								</a>

							</li>
						</ul>
					</menu>
				</button>

			<?php else : ?>

				<span class="b-profile-button__login-link">
					<?php echo $loginout_link; ?>
				</span>
			<?php endif; ?>
			</button>
		</div>


	</nav>


	<div class="b-header">
		<a class="b-header-link" href="/">
			<img src="<?php echo ASSETS_DIR ?>/img/b-logo.png" />
			<span class="b-header-link__extra">Bleikøya Velforening · 1923 - <?php echo date('Y'); ?></span>
		</a>
	</div>

	<?php if (is_user_logged_in()) : ?>
		<?php echo get_search_form(); ?>
	<?php endif; ?>
