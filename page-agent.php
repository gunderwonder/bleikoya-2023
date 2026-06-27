<?php
/**
 * Template Name: Agent
 * Description: Chat agent for board members
 *
 * CSS: agent/static/chat.css
 * JS:  agent/static/chat.js
 */

if (!is_user_logged_in()) {
	wp_redirect(wp_login_url(get_permalink()));
	exit;
}

if (!current_user_can('read_private_posts')) {
	wp_die('Du har ikke tilgang til agenten.', 403);
}

$current_user = wp_get_current_user();

get_header();
?>

<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/agent/static/chat.css?v=<?php echo bleikoya_asset_version('/agent/static/chat.css'); ?>">
<style>
/* Undo chat.css global resets that break theme layout */
body {
	display: block;
	height: auto;
	background-color: white;
}
.b-center {
	max-width: 40rem;
	margin: auto;
	padding: 1rem;
}
/* Remove standalone chat shell styling */
.chat {
	width: 100%;
	height: auto;
	background: none;
	border-radius: 0;
	box-shadow: none;
	overflow: visible;
	display: block;
}
.chat__messages {
	padding: 1.25rem 0;
	overflow: visible;
	height: auto;
}
/* Sticky input at bottom of viewport */
.chat__form {
	position: sticky;
	bottom: 0;
	background: white;
	padding: 0.75rem 0;
	border-top: 1px solid var(--b-border-color);
	z-index: 10;
}
</style>

<div class="b-center">
	<main>
		<h1>Øyarkivaren</h1>

		<div class="chat">
			<div class="chat__messages" id="messages">
				<div class="chat__message chat__message--assistant">
					<div class="chat__bubble">
						<p>Hei, <?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?>!
						   Jeg er Øyarkivaren – styrets søkeassistent. Jeg kan hjelpe deg med å finne informasjon
						   fra nettsiden og dokumentarkivet i Google Drive.</p>
						<p>Prøv for eksempel:</p>
						<ul>
							<li>Hva er gjeldende vedtekter?</li>
							<li>Oppsummer siste styremøte</li>
							<li>Finn avtalen med vaktmesteren</li>
						</ul>
					</div>
				</div>
			</div>
			<form class="chat__form" id="form">
				<input class="chat__input" id="input" type="text"
					   placeholder="Spør meg om noe..." autocomplete="off">
				<button class="chat__send" type="submit">Send</button>
			</form>
		</div>
	</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked@15.0.7/marked.min.js"></script>
<script>
	// Same-origin PHP endpoint (chat.js POSTs to baseUrl + '/chat' → /agent/chat).
	// Auth is the WordPress session — no token needed.
	window.AGENT_CONFIG = {
		baseUrl: <?php echo wp_json_encode(home_url('/agent')); ?>,
	};
</script>
<script src="<?php echo get_stylesheet_directory_uri(); ?>/agent/static/chat.js?v=<?php echo bleikoya_asset_version('/agent/static/chat.js'); ?>"></script>

<?php get_footer(); ?>
