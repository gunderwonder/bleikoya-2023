<?php
/**
 * Connection Meta Box
 *
 * Reusable meta box class for managing connections on post edit screens.
 *
 * @package Bleikoya
 */

/**
 * Class Bleikoya_Connection_Meta_Box
 */
class Bleikoya_Connection_Meta_Box {

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
	 * Meta box ID
	 *
	 * @var string
	 */
	private $meta_box_id;

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
	 */
	public function __construct( $connection_name ) {
		$this->connection_name = $connection_name;
		$this->config          = bleikoya_connection_registry()->get( $connection_name );
		$this->meta_box_id     = 'bleikoya_connections_' . $connection_name;

		if ( ! $this->config ) {
			return;
		}

		self::$instances[ $connection_name ] = $this;
	}

	/**
	 * Register the meta box
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->config ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add meta box to edit screen
	 *
	 * @return void
	 */
	public function add_meta_box() {
		$post_types = (array) $this->config['from_object'];

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				$this->meta_box_id,
				$this->config['labels']['title'],
				array( $this, 'render' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box content
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		$connections = Bleikoya_Connection_Manager::get_connections_full(
			'post',
			$post->ID,
			$this->connection_name
		);

		$searchable_types = Bleikoya_Connection_Manager::get_searchable_types( $this->connection_name );
		?>
		<div class="bleikoya-connections-manager"
			 data-connection-name="<?php echo esc_attr( $this->connection_name ); ?>"
			 data-entity-type="post"
			 data-entity-id="<?php echo esc_attr( $post->ID ); ?>">

			<!-- Search section -->
			<div class="bleikoya-connection-search-wrapper">
				<input type="text"
					   class="bleikoya-connection-search widefat"
					   placeholder="<?php echo esc_attr( $this->config['labels']['search_placeholder'] ); ?>" />

				<?php if ( count( $searchable_types ) > 1 || count( array_merge( ...array_values( $searchable_types ) ) ) > 1 ) : ?>
					<select class="bleikoya-connection-type-filter">
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
			<div class="bleikoya-connection-list-header">
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
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, (array) $this->config['from_object'], true ) ) {
			return;
		}

		$this->enqueue_connection_assets();
	}

	/**
	 * Enqueue connection assets
	 *
	 * @return void
	 */
	public static function enqueue_connection_assets() {
		static $enqueued = false;
		if ( $enqueued ) {
			return;
		}
		$enqueued = true;

		$theme_url = get_template_directory_uri();
		$theme_dir = get_template_directory();

		wp_enqueue_style(
			'bleikoya-admin-connections',
			$theme_url . '/assets/css/admin-connections.css',
			array(),
			filemtime( $theme_dir . '/assets/css/admin-connections.css' )
		);

		wp_enqueue_script(
			'bleikoya-admin-connections',
			$theme_url . '/assets/js/admin-connections.js',
			array(),
			filemtime( $theme_dir . '/assets/js/admin-connections.js' ),
			true
		);

		// Build type labels for all registered connections.
		$type_labels = array();
		foreach ( bleikoya_connection_registry()->get_all() as $name => $config ) {
			foreach ( (array) $config['to_object'] as $object ) {
				if ( ! isset( $type_labels[ $object ] ) ) {
					$type_labels[ $object ] = Bleikoya_Connection_Manager::get_type_label( $object );
				}
			}
		}

		wp_localize_script(
			'bleikoya-admin-connections',
			'bleikoyaConnections',
			array(
				'resturl'    => rest_url( 'bleikoya/v1' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'typeLabels' => $type_labels,
				'i18n'       => array(
					'searching'     => __( 'Søker...', 'flavor' ),
					'noResults'     => __( 'Ingen resultater funnet.', 'flavor' ),
					'noConnections' => __( 'Ingen koblinger ennå.', 'flavor' ),
					'confirmRemove' => __( 'Er du sikker på at du vil fjerne denne koblingen?', 'flavor' ),
					'add'           => __( 'Legg til', 'flavor' ),
					'remove'        => __( 'Fjern', 'flavor' ),
					'searchError'   => __( 'Feil ved søk. Prøv igjen.', 'flavor' ),
					'addError'      => __( 'Kunne ikke legge til kobling.', 'flavor' ),
					'removeError'   => __( 'Kunne ikke fjerne kobling.', 'flavor' ),
				),
			)
		);
	}

	/**
	 * Get instance for a connection type
	 *
	 * @param string $connection_name Connection type name.
	 * @return Bleikoya_Connection_Meta_Box|null Instance or null.
	 */
	public static function get_instance( $connection_name ) {
		return isset( self::$instances[ $connection_name ] ) ? self::$instances[ $connection_name ] : null;
	}
}
