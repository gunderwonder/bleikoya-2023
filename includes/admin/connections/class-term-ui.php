<?php
/**
 * Connection Term UI
 *
 * Adds connection management UI to taxonomy term edit screens.
 *
 * @package Bleikoya
 */

/**
 * Class Bleikoya_Connection_Term_UI
 */
class Bleikoya_Connection_Term_UI {

	/**
	 * Connection type name
	 *
	 * @var string
	 */
	private $connection_name;

	/**
	 * Connection type config
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Taxonomy name
	 *
	 * @var string
	 */
	private $taxonomy;

	/**
	 * Registered instances
	 *
	 * @var array
	 */
	private static $instances = array();

	/**
	 * Constructor
	 *
	 * @param string $connection_name Connection type name.
	 * @param string $taxonomy        Taxonomy name.
	 */
	public function __construct( $connection_name, $taxonomy ) {
		$this->connection_name = $connection_name;
		$this->taxonomy        = $taxonomy;
		$this->config          = bleikoya_connection_registry()->get( $connection_name );

		if ( ! $this->config ) {
			return;
		}

		$key                    = $connection_name . '_' . $taxonomy;
		self::$instances[ $key ] = $this;
	}

	/**
	 * Register the UI
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->config ) {
			return;
		}

		// Add fields to term edit screen.
		add_action( $this->taxonomy . '_edit_form_fields', array( $this, 'render' ), 10, 2 );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Render the connection UI on term edit screen
	 *
	 * @param WP_Term $term     Current term.
	 * @param string  $taxonomy Taxonomy name.
	 * @return void
	 */
	public function render( $term, $taxonomy ) {
		$connections = Bleikoya_Connection_Manager::get_connections_full(
			'term',
			$term->term_id,
			$this->connection_name
		);

		$searchable_types = Bleikoya_Connection_Manager::get_searchable_types( $this->connection_name );
		?>
		<tr class="form-field term-connections-wrap">
			<th scope="row">
				<label><?php echo esc_html( $this->config['labels']['title'] ); ?></label>
			</th>
			<td>
				<div class="bleikoya-connections-manager"
					 data-connection-name="<?php echo esc_attr( $this->connection_name ); ?>"
					 data-entity-type="term"
					 data-entity-id="<?php echo esc_attr( $term->term_id ); ?>">

					<!-- Search section -->
					<div class="bleikoya-connection-search-wrapper">
						<input type="text"
							   class="bleikoya-connection-search"
							   placeholder="<?php echo esc_attr( $this->config['labels']['search_placeholder'] ); ?>"
							   style="width: 100%; max-width: 300px;" />

						<?php if ( count( $searchable_types ) > 1 || count( array_merge( ...array_values( $searchable_types ) ) ) > 1 ) : ?>
							<select class="bleikoya-connection-type-filter" style="max-width: 150px;">
								<option value=""><?php esc_html_e( 'Alle typer', 'flavor' ); ?></option>
								<?php foreach ( $searchable_types as $group => $types ) : ?>
									<?php if ( ! empty( $types ) ) : ?>
										<optgroup label="<?php echo esc_attr( $this->get_group_label( $group ) ); ?>">
											<?php foreach ( $types as $type ) : ?>
												<option value="<?php echo esc_attr( $type['value'] ); ?>">
													<?php echo esc_html( $type['label'] ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</div>

					<!-- Search results -->
					<div class="bleikoya-connection-results"></div>

					<!-- Current connections -->
					<div class="bleikoya-connection-list-header" style="margin-top: 16px;">
						<span class="bleikoya-connection-list-title">
							<?php esc_html_e( 'Nåværende koblinger', 'flavor' ); ?>
						</span>
						<span class="bleikoya-connection-count"><?php echo count( $connections ); ?></span>
					</div>

					<div class="bleikoya-connection-list">
						<?php if ( empty( $connections ) ) : ?>
							<div class="bleikoya-connection-empty">
								<?php esc_html_e( 'Ingen koblinger ennå.', 'flavor' ); ?>
							</div>
						<?php else : ?>
							<?php foreach ( $connections as $conn ) : ?>
								<?php echo $this->render_connection_item( $conn ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<p class="description">
					<?php esc_html_e( 'Søk og velg elementer å koble til dette temaet.', 'flavor' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a single connection item
	 *
	 * @param array $conn Connection data.
	 * @return string HTML.
	 */
	private function render_connection_item( $conn ) {
		$type_label = Bleikoya_Connection_Manager::get_type_label( $conn['type'] );
		$thumbnail  = isset( $conn['thumbnail'] ) ? $conn['thumbnail'] : ( isset( $conn['avatar'] ) ? $conn['avatar'] : '' );

		ob_start();
		?>
		<div class="bleikoya-connection-item" data-id="<?php echo esc_attr( $conn['id'] ); ?>" data-type="<?php echo esc_attr( $conn['type'] ); ?>">
			<?php if ( $thumbnail ) : ?>
				<img src="<?php echo esc_url( $thumbnail ); ?>" alt="" class="bleikoya-connection-thumbnail" />
			<?php endif; ?>
			<div class="bleikoya-connection-item-info">
				<span class="bleikoya-connection-type-badge bleikoya-connection-type-badge--<?php echo esc_attr( $conn['type'] ); ?>">
					<?php echo esc_html( $type_label ); ?>
				</span>
				<a href="<?php echo esc_url( isset( $conn['link'] ) ? $conn['link'] : '#' ); ?>"
				   class="bleikoya-connection-item-title"
				   target="_blank">
					<?php echo esc_html( $conn['title'] ); ?>
				</a>
			</div>
			<button type="button" class="button bleikoya-connection-remove-btn" title="<?php esc_attr_e( 'Fjern', 'flavor' ); ?>">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get group label for type filter
	 *
	 * @param string $group Group key.
	 * @return string Label.
	 */
	private function get_group_label( $group ) {
		$labels = array(
			'posts'      => __( 'Innhold', 'flavor' ),
			'users'      => __( 'Brukere', 'flavor' ),
			'taxonomies' => __( 'Kategorier', 'flavor' ),
		);
		return isset( $labels[ $group ] ) ? $labels[ $group ] : $group;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'term.php' !== $hook ) {
			return;
		}

		// Check if we're on the right taxonomy.
		$screen = get_current_screen();
		if ( ! $screen || $screen->taxonomy !== $this->taxonomy ) {
			return;
		}

		// Use the shared asset enqueue method from meta box.
		Bleikoya_Connection_Meta_Box::enqueue_connection_assets();
	}

	/**
	 * Get instance for a connection type and taxonomy
	 *
	 * @param string $connection_name Connection type name.
	 * @param string $taxonomy        Taxonomy name.
	 * @return Bleikoya_Connection_Term_UI|null Instance or null.
	 */
	public static function get_instance( $connection_name, $taxonomy ) {
		$key = $connection_name . '_' . $taxonomy;
		return isset( self::$instances[ $key ] ) ? self::$instances[ $key ] : null;
	}
}
