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

$agent_auth_secret = $_ENV['AGENT_AUTH_SECRET'] ?? $_SERVER['AGENT_AUTH_SECRET'] ?? '';
$agent_base_url = rtrim($_ENV['AGENT_BASE_URL'] ?? $_SERVER['AGENT_BASE_URL'] ?? '', '/');
$current_user = wp_get_current_user();

// Generate JWT (HS256) for the current user
$token = '';
if ($agent_auth_secret) {
	$header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
	$payload = base64url_encode(json_encode([
		'sub' => (string) $current_user->ID,
		'name' => $current_user->display_name,
		'iss' => 'bleikoya.net',
		'iat' => time(),
		'exp' => time() + 3600,
	]));
	$signature = base64url_encode(
		hash_hmac('sha256', "$header.$payload", $agent_auth_secret, true)
	);
	$token = "$header.$payload.$signature";
}

get_header();
?>

<style>
.chat-page { max-width: 42rem; margin: 2rem auto; padding: 0 1rem; }
.chat-page .chat { height: min(50rem, calc(100dvh - 12rem)); }
/* Hide site chrome on agent page */
.b-header, .b-navigation, .b-bleikoya-map, .b-cabin-gallery, .b-footer { display: none; }
</style>

<div class="chat-page">
	<div class="chat">
		<header class="chat__header">
			<img class="chat__logo"
				 src="<?php echo get_stylesheet_directory_uri(); ?>/assets/img/b-logo.png"
				 alt="Bleikøya Velforening">
			<div>
				<h1 class="chat__title">Bleikøya-agenten</h1>
				<p class="chat__subtitle">Søkeassistent for styret</p>
			</div>
		</header>
		<div class="chat__messages" id="messages">
			<div class="chat__message chat__message--assistant">
				<div class="chat__bubble">
					<p>Hei, <?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?>!
					   Jeg er styrets søkeassistent. Jeg kan hjelpe deg med å finne informasjon
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
				   placeholder="Spor meg om noe..." autocomplete="off">
			<button class="chat__send" type="submit">Send</button>
		</form>
	</div>
</div>

<link rel="stylesheet" href="<?php echo get_stylesheet_directory_uri(); ?>/agent/static/chat.css?v=<?php echo bleikoya_asset_version('/agent/static/chat.css'); ?>">
<script src="https://cdn.jsdelivr.net/npm/marked@15.0.7/marked.min.js"></script>
<script>
	window.AGENT_CONFIG = {
		baseUrl: <?php echo json_encode($agent_base_url); ?>,
		token: <?php echo json_encode($token); ?>,
	};
</script>
<script src="<?php echo get_stylesheet_directory_uri(); ?>/agent/static/chat.js?v=<?php echo bleikoya_asset_version('/agent/static/chat.js'); ?>"></script>

<?php get_footer(); ?>
