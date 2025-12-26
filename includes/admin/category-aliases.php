<?php
/**
 * Category Aliases - Custom admin UI for managing category aliases.
 * Allows adding/removing aliases without ACF Pro.
 */

/**
 * Add alias fields to category add/edit forms.
 */
function sc_category_aliases_fields( $term = null ) {
	$aliases = array();
	if ( $term && ! is_string( $term ) ) {
		$aliases = get_term_meta( $term->term_id, 'category-aliases', true );
		if ( ! is_array( $aliases ) ) {
			$aliases = array();
		}
	}

	$is_edit = $term && ! is_string( $term );
	?>
	<?php if ( $is_edit ) : ?>
	<tr class="form-field">
		<th scope="row"><label for="category-aliases">Aliaser</label></th>
		<td>
	<?php else : ?>
	<div class="form-field">
		<label for="category-aliases">Aliaser</label>
	<?php endif; ?>

		<div id="category-aliases-wrapper">
			<?php if ( empty( $aliases ) ) : ?>
				<div class="category-alias-row">
					<input type="text" name="category-aliases[]" value="" placeholder="f.eks. Badebrygge" class="category-alias-input" />
					<button type="button" class="button category-alias-remove" style="display:none;">Fjern</button>
				</div>
			<?php else : ?>
				<?php foreach ( $aliases as $alias ) : ?>
					<div class="category-alias-row">
						<input type="text" name="category-aliases[]" value="<?php echo esc_attr( $alias ); ?>" placeholder="f.eks. Badebrygge" class="category-alias-input" />
						<button type="button" class="button category-alias-remove">Fjern</button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" class="button category-alias-add" style="margin-top: 8px;">+ Legg til alias</button>
		<p class="description">Alternative navn for dette temaet som vises i søk og kategori-indeks.</p>

		<style>
			.category-alias-row {
				display: flex;
				gap: 8px;
				margin-bottom: 8px;
				align-items: center;
			}
			.category-alias-input {
				flex: 1;
				max-width: 300px;
			}
		</style>

		<script>
		jQuery(function($) {
			var wrapper = $('#category-aliases-wrapper');

			// Add new alias row
			$('.category-alias-add').on('click', function() {
				var row = $('<div class="category-alias-row">' +
					'<input type="text" name="category-aliases[]" value="" placeholder="f.eks. Badebrygge" class="category-alias-input" />' +
					'<button type="button" class="button category-alias-remove">Fjern</button>' +
					'</div>');
				wrapper.append(row);
				row.find('input').focus();
				updateRemoveButtons();
			});

			// Remove alias row
			wrapper.on('click', '.category-alias-remove', function() {
				$(this).closest('.category-alias-row').remove();
				updateRemoveButtons();
			});

			// Show/hide remove buttons based on row count
			function updateRemoveButtons() {
				var rows = wrapper.find('.category-alias-row');
				rows.find('.category-alias-remove').toggle(rows.length > 1);
			}

			updateRemoveButtons();
		});
		</script>

	<?php if ( $is_edit ) : ?>
		</td>
	</tr>
	<?php else : ?>
	</div>
	<?php endif; ?>
	<?php
}
add_action( 'category_add_form_fields', 'sc_category_aliases_fields' );
add_action( 'category_edit_form_fields', 'sc_category_aliases_fields' );

/**
 * Save aliases when category is created or updated.
 */
function sc_save_category_aliases( $term_id ) {
	if ( ! isset( $_POST['category-aliases'] ) ) {
		return;
	}

	$aliases = array_map( 'sanitize_text_field', $_POST['category-aliases'] );
	$aliases = array_filter( array_map( 'trim', $aliases ) ); // Remove empty values
	$aliases = array_values( $aliases ); // Re-index array

	update_term_meta( $term_id, 'category-aliases', $aliases );
}
add_action( 'created_category', 'sc_save_category_aliases' );
add_action( 'edited_category', 'sc_save_category_aliases' );
