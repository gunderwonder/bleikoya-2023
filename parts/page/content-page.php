<?php

/**
 * Template part for displaying page content in page.php
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 * @version 1.0
 */

?>
<article class="b-article">
	<?php if (!is_front_page()): ?>
		<h1><?php the_title() ?></h1>
	<?php endif; ?>

	<div class="b-body-text">
		<?php the_content() ?>
	</div>

</article>
