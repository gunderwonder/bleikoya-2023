<?php
/**
 * Single template for meeting_poll CPT (Møtepoll)
 *
 * Renders the public poll page: table of date options + responses, with
 * a "Du"-row at top for the current voter to fill in.
 */

get_header(); ?>

<div class="b-center-wide">
	<main class="meeting-poll">
		<?php while (have_posts()) : the_post();
			$post_id   = get_the_ID();
			$options   = meeting_poll_get_options($post_id);
			$responses = meeting_poll_get_responses($post_id);
			$roster    = meeting_poll_get_styret_roster();
			$placeholders = meeting_poll_get_placeholder_members($responses, $roster);
			$yes_counts = meeting_poll_count_yes_per_option($post_id, count($options));
			$max_yes = !empty($yes_counts) ? max($yes_counts) : 0;
		?>

		<h1><?php the_title(); ?></h1>

		<?php if (get_the_content()): ?>
			<div class="meeting-poll__intro b-body-text">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php if (empty($options)): ?>
			<p class="meeting-poll__empty">Denne pollen har ingen datoalternativer enda.</p>
		<?php else: ?>

			<?php if (!is_user_logged_in() && !empty($placeholders)): ?>
				<p class="meeting-poll__hint">
					Er du i styret? Klikk navnet ditt i lista for å svare — ellers skriv navnet ditt i «Ditt navn»-raden.
				</p>
			<?php endif; ?>

			<div class="meeting-poll__table-wrap">
				<table class="meeting-poll__table">
					<thead>
						<tr>
							<th scope="col" class="meeting-poll__th-name">Navn</th>
							<?php foreach ($options as $i => $opt):
								$is_leader = ($max_yes > 0 && $yes_counts[$i] === $max_yes);
								$parts = meeting_poll_format_option_parts($opt);
							?>
								<th scope="col" class="meeting-poll__th-option<?php echo $is_leader ? ' meeting-poll__col--leader' : ''; ?>">
									<?php if ($parts['weekday'] !== ''): ?>
										<span class="meeting-poll__option-weekday"><?php echo esc_html($parts['weekday']); ?></span>
									<?php endif; ?>
									<span class="meeting-poll__option-label"><?php echo esc_html($parts['date']); ?></span>
									<?php if ($parts['time'] !== ''): ?>
										<span class="meeting-poll__option-time"><?php echo esc_html($parts['time']); ?></span>
									<?php endif; ?>
									<?php if ($parts['note'] !== ''): ?>
										<span class="meeting-poll__option-note"><?php echo esc_html($parts['note']); ?></span>
									<?php endif; ?>
									<span class="meeting-poll__yes-count"><?php echo (int) $yes_counts[$i]; ?> ja</span>
								</th>
							<?php endforeach; ?>
						</tr>
					</thead>

					<tbody>
						<!-- Eksisterende svar -->
						<?php foreach ($responses as $r): ?>
							<tr
								class="meeting-poll__row"
								data-user-id="<?php echo esc_attr($r['user_id'] ?? ''); ?>"
								data-name="<?php echo esc_attr($r['name'] ?? ''); ?>"
							>
								<th scope="row" class="meeting-poll__cell-name"><?php echo esc_html($r['name'] ?? ''); ?></th>
								<?php foreach ($options as $i => $_opt):
									$vote = $r['votes'][$i] ?? '';
								?>
									<td class="meeting-poll__cell meeting-poll__cell--<?php echo esc_attr($vote ?: 'blank'); ?>"></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>

						<!-- Styremedlemmer som ennå ikke har svart — tomme rader så hele styret vises.
						     Når man ikke er pålogget er raden "claimbar": klikk navnet ditt for å svare i den. -->
						<?php $claimable = !is_user_logged_in(); ?>
						<?php foreach ($placeholders as $m): ?>
							<tr
								class="meeting-poll__row meeting-poll__row--placeholder<?php echo $claimable ? ' meeting-poll__row--claimable' : ''; ?>"
								data-user-id="<?php echo (int) $m['user_id']; ?>"
								data-name="<?php echo esc_attr($m['name']); ?>"
								data-placeholder="1"
							>
								<th scope="row" class="meeting-poll__cell-name">
									<?php if ($claimable): ?>
										<button type="button" class="meeting-poll__claim"><?php echo esc_html($m['name']); ?></button>
									<?php else: ?>
										<?php echo esc_html($m['name']); ?>
									<?php endif; ?>
								</th>
								<?php foreach ($options as $i => $_opt): ?>
									<td class="meeting-poll__cell meeting-poll__cell--blank<?php echo $claimable ? ' meeting-poll__cell--self' : ''; ?>">
										<?php if ($claimable): ?>
											<button
												type="button"
												class="meeting-poll__vote"
												data-option="<?php echo (int) $i; ?>"
												data-vote=""
												aria-label="Endre svar for alternativ <?php echo (int) $i + 1; ?>"
											></button>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>

						<!-- "Du"-rad nederst: skjema for den som stemmer nå (nytt/eksternt navn) -->
						<tr class="meeting-poll__row-self" data-self="1">
							<th scope="row" class="meeting-poll__cell-name">
								<input
									type="text"
									name="name"
									class="meeting-poll__name-input"
									placeholder="Ditt navn"
									value="<?php echo esc_attr(meeting_poll_default_name()); ?>"
									maxlength="80"
									autocomplete="name"
									<?php echo is_user_logged_in() ? 'readonly' : ''; ?>
								>
							</th>
							<?php foreach ($options as $i => $_opt): ?>
								<td class="meeting-poll__cell meeting-poll__cell--blank meeting-poll__cell--self">
									<button
										type="button"
										class="meeting-poll__vote"
										data-option="<?php echo (int) $i; ?>"
										data-vote=""
										aria-label="Endre svar for alternativ <?php echo (int) $i + 1; ?>"
									></button>
								</td>
							<?php endforeach; ?>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="meeting-poll__actions">
				<button type="button" class="b-button b-button--green" id="meeting-poll-submit">Lagre svar</button>
				<button type="button" class="b-button" id="meeting-poll-delete" hidden>Slett mitt svar</button>
				<p class="meeting-poll__status" id="meeting-poll-status" aria-live="polite"></p>
			</div>

		<?php endif; ?>

		<?php endwhile; ?>
	</main>
</div>

<?php get_footer(); ?>
