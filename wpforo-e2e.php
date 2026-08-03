<?php
/**
 * Plugin Name: wpForo E2E Tester
 * Description: End-to-end testing plugin for wpForo AI features using real tenant connection
 * Version: 1.0.0
 * Author: gVectors
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFORO_E2E_VERSION', '1.0.0' );
define( 'WPFORO_E2E_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPFORO_E2E_URL', plugin_dir_url( __FILE__ ) );

class WPForo_E2E_Tester {

	private static $instance = null;
	private $table_name;
	private $suggestion_table;
	private $moderation_table;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wpforo_ai_e2e_search_tests';
		$this->suggestion_table = $wpdb->prefix . 'wpforo_ai_e2e_suggestion_tests';
		$this->moderation_table = $wpdb->prefix . 'wpforo_ai_e2e_moderation_tests';

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_wpforo_e2e_run_test', [ $this, 'ajax_run_test' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_topics', [ $this, 'ajax_get_topics' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_posts', [ $this, 'ajax_get_posts' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_tenant_info', [ $this, 'ajax_get_tenant_info' ] );
		add_action( 'wp_ajax_wpforo_e2e_switch_storage', [ $this, 'ajax_switch_storage' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_feature_info', [ $this, 'ajax_get_feature_info' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_search_history', [ $this, 'ajax_get_search_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_clear_search_history', [ $this, 'ajax_clear_search_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_delete_search_test', [ $this, 'ajax_delete_search_test' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_suggestion_history', [ $this, 'ajax_get_suggestion_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_clear_suggestion_history', [ $this, 'ajax_clear_suggestion_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_delete_suggestion_test', [ $this, 'ajax_delete_suggestion_test' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_moderation_history', [ $this, 'ajax_get_moderation_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_clear_moderation_history', [ $this, 'ajax_clear_moderation_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_delete_moderation_test', [ $this, 'ajax_delete_moderation_test' ] );

		// Create tables on init if not exists
		$this->maybe_create_table();
		$this->maybe_create_suggestion_table();
		$this->maybe_create_moderation_table();
	}

	private function maybe_create_table() {
		global $wpdb;

		$table = $this->table_name;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			$this->create_table();
		}
	}

	public function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table = $this->table_name;

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			query VARCHAR(255) NOT NULL,
			storage_mode VARCHAR(20) NOT NULL,
			total_results INT NOT NULL DEFAULT 0,
			query_time_ms INT NOT NULL DEFAULT 0,
			credits_used INT NOT NULL DEFAULT 0,
			avg_score DECIMAL(5,2) DEFAULT NULL,
			top_score DECIMAL(5,2) DEFAULT NULL,
			has_enhancement TINYINT(1) NOT NULL DEFAULT 0,
			results_json LONGTEXT,
			settings_json TEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY query_idx (query),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private function maybe_create_suggestion_table() {
		global $wpdb;

		$table = $this->suggestion_table;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			$this->create_suggestion_table();
		}
	}

	public function create_suggestion_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table = $this->suggestion_table;

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			query VARCHAR(500) NOT NULL,
			quality VARCHAR(20) NOT NULL DEFAULT 'balanced',
			similarity_threshold DECIMAL(3,2) NOT NULL DEFAULT 0.55,
			total_similar INT NOT NULL DEFAULT 0,
			total_related INT NOT NULL DEFAULT 0,
			has_answer TINYINT(1) NOT NULL DEFAULT 0,
			top_score DECIMAL(5,2) DEFAULT NULL,
			query_time_ms INT NOT NULL DEFAULT 0,
			credits_used INT NOT NULL DEFAULT 0,
			settings_json TEXT,
			results_json LONGTEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY query_idx (query(191)),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private function maybe_create_moderation_table() {
		global $wpdb;

		$table = $this->moderation_table;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
			$this->create_moderation_table();
		}
	}

	public function create_moderation_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table = $this->moderation_table;

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			test_type VARCHAR(20) NOT NULL DEFAULT 'spam',
			content TEXT NOT NULL,
			quality VARCHAR(20) NOT NULL DEFAULT 'balanced',
			spam_score INT DEFAULT NULL,
			is_spam TINYINT(1) DEFAULT NULL,
			confidence DECIMAL(5,4) DEFAULT NULL,
			indicators TEXT,
			analysis_summary TEXT,
			query_time_ms INT NOT NULL DEFAULT 0,
			credits_used INT NOT NULL DEFAULT 0,
			settings_json TEXT,
			results_json LONGTEXT,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY test_type_idx (test_type),
			KEY created_at_idx (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function add_admin_menu() {
		add_menu_page(
			'wpForo E2E Tester',
			'wpForo E2E',
			'manage_options',
			'wpforo-e2e',
			[ $this, 'render_admin_page' ],
			'dashicons-superhero',
			100
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'toplevel_page_wpforo-e2e' ) {
			return;
		}

		wp_enqueue_style(
			'wpforo-e2e-admin',
			WPFORO_E2E_URL . 'assets/css/admin.css',
			[],
			WPFORO_E2E_VERSION
		);

		wp_enqueue_script(
			'wpforo-e2e-admin',
			WPFORO_E2E_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WPFORO_E2E_VERSION,
			true
		);

		wp_localize_script( 'wpforo-e2e-admin', 'wpforoE2E', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wpforo_e2e_nonce' ),
		] );
	}

	private function verify_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied' ], 403 );
		}
		if ( ! check_ajax_referer( 'wpforo_e2e_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed' ], 403 );
		}
	}

	public function ajax_get_tenant_info() {
		$this->verify_request();

		// Debug: check what's available
		if ( ! function_exists( 'WPF' ) ) {
			wp_send_json_error( [ 'message' => 'WPF() function not defined - is wpForo active?' ] );
		}

		$wpf = WPF();
		if ( ! is_object( $wpf ) ) {
			wp_send_json_error( [ 'message' => 'WPF() returned: ' . gettype( $wpf ) ] );
		}

		if ( ! isset( $wpf->ai_client ) || ! $wpf->ai_client ) {
			$props = array_keys( get_object_vars( $wpf ) );
			wp_send_json_error( [
				'message'    => 'ai_client property not set on WPF()',
				'wpf_class'  => get_class( $wpf ),
				'properties' => implode( ', ', array_slice( $props, 0, 20 ) ),
			] );
		}

		$ai_client = $wpf->ai_client;
		$board_id = WPF()->board->get_current( 'boardid' );

		// Get tenant status (force fresh)
		$status = $ai_client->get_tenant_status( true );

		// Get stored values
		$tenant_id = $ai_client->get_tenant_id();
		$api_key = $ai_client->get_api_key();

		// Get storage mode from VectorStorageManager (the authoritative source)
		$storage_mode = 'local';
		$storage_mode_source = 'default';
		if ( isset( WPF()->vector_storage ) ) {
			$storage_mode = WPF()->vector_storage->get_storage_mode( $board_id );
			$storage_mode_source = 'vector_storage';
		}
		// Also check raw option for debugging
		$raw_option = get_option( 'wpforo_ai_storage_mode_' . $board_id, 'NOT_SET' );

		// Check if cloud storage is available for this plan
		$cloud_available = $ai_client->is_feature_available( 'vector_db_cloud_storage' );
		$effective_mode = ( $storage_mode === 'cloud' && ! $cloud_available ) ? 'local' : $storage_mode;
		$mode_warning = ( $storage_mode === 'cloud' && ! $cloud_available )
			? 'Setting is "cloud" but your plan does not support cloud storage. Effective mode: local'
			: null;

		$info = [
			'connected'        => $ai_client->is_connected(),
			'tenant_id'        => $tenant_id,
			'api_key_masked'   => $this->mask_api_key( $api_key ),
			'api_key_full'     => $api_key, // For debugging
			'storage_mode'     => $effective_mode, // Use effective mode for dropdown
			'storage_mode_setting' => $storage_mode, // Raw setting
			'cloud_available'  => $cloud_available,
			'mode_warning'     => $mode_warning,
			'storage_mode_debug' => [
				'source'       => $storage_mode_source,
				'raw_option'   => $raw_option,
				'board_id'     => $board_id,
				'cloud_available' => $cloud_available,
				'effective'    => $effective_mode,
			],
			'board_id'         => $board_id,
			'api_base_url'     => defined( 'WPFORO_AI_API' ) ? WPFORO_AI_API : 'https://api.gvectors.com/v1',
		];

		if ( is_array( $status ) && ! is_wp_error( $status ) ) {
			$subscription = $status['subscription'] ?? [];
			$info['status'] = 'active';
			$info['subscription'] = $subscription;
			$info['credits'] = [
				'remaining' => $subscription['credits_remaining'] ?? 0,
				'used'      => $subscription['credits_used'] ?? 0,
				'total'     => $subscription['credits_total'] ?? 0,
			];
			$info['features_enabled'] = $status['features_enabled'] ?? [];

			// Get indexing stats from VectorStorageManager (not API)
			if ( isset( WPF()->vector_storage ) ) {
				$info['indexing_stats'] = WPF()->vector_storage->get_indexing_stats();
			} else {
				$info['indexing_stats'] = [];
			}

			// Include raw status for debugging
			$info['raw_status'] = $status;
		} else {
			$info['status'] = 'error';
			$info['error'] = is_wp_error( $status ) ? $status->get_error_message() : 'Unknown error';
		}

		wp_send_json_success( $info );
	}

	private function mask_api_key( $key ) {
		if ( strlen( $key ) < 10 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return substr( $key, 0, 8 ) . '...' . substr( $key, -4 );
	}

	public function ajax_switch_storage() {
		$this->verify_request();

		$mode = sanitize_text_field( $_POST['storage_mode'] ?? 'local' );
		if ( ! in_array( $mode, [ 'local', 'cloud' ], true ) ) {
			$mode = 'local';
		}

		$board_id = WPF()->board->get_current( 'boardid' );
		update_option( 'wpforo_ai_storage_mode_' . $board_id, $mode );

		wp_send_json_success( [ 'storage_mode' => $mode ] );
	}

	public function ajax_get_feature_info() {
		$this->verify_request();

		$feature = sanitize_text_field( $_POST['feature'] ?? '' );

		if ( ! function_exists( 'WPF' ) ) {
			wp_send_json_error( [ 'message' => 'wpForo not available' ] );
		}

		$info = [];

		switch ( $feature ) {
			case 'search':
				$info = $this->get_search_info();
				break;
			case 'index':
				$info = $this->get_index_info();
				break;
			case 'translate':
				$info = $this->get_translate_info();
				break;
			case 'summarize':
				$info = $this->get_summarize_info();
				break;
			case 'suggestions':
				$info = $this->get_suggestions_info();
				break;
			case 'moderate':
				$info = $this->get_moderate_info();
				break;
			case 'chat':
				$info = $this->get_chat_info();
				break;
			default:
				wp_send_json_error( [ 'message' => 'Unknown feature: ' . $feature ] );
		}

		wp_send_json_success( $info );
	}

	private function get_search_info() {
		global $wpdb;

		$board_id = WPF()->board->get_current( 'boardid' );
		$ai_settings = WPF()->settings->ai ?? [];
		$topics_table = $wpdb->prefix . 'wpforo_topics';

		// Get database stats
		$db_stats = [];
		if ( isset( WPF()->tables->ai_embeddings ) ) {
			$table = WPF()->tables->ai_embeddings;

			// Total embedding rows
			$db_stats['total_rows'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

			// By content type
			$db_stats['by_type'] = $wpdb->get_results(
				"SELECT content_type, COUNT(*) as count FROM {$table} GROUP BY content_type",
				ARRAY_A
			);

			// Topics with embeddings (that still exist in forum for current board)
			$db_stats['topics_with_embeddings'] = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(DISTINCT e.topicid)
				 FROM {$table} e
				 INNER JOIN {$topics_table} t ON e.topicid = t.topicid
				 WHERE e.topicid > 0 AND t.status = 0 AND t.private = 0",
				$board_id
			) );

			// Total valid topics in forum (current board)
			$db_stats['total_forum_topics'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$topics_table} WHERE status = 0 AND private = 0"
			);

			// Orphaned embeddings (topicid doesn't exist in forum anymore)
			$db_stats['orphaned_topic_embeddings'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT e.topicid)
				 FROM {$table} e
				 LEFT JOIN {$topics_table} t ON e.topicid = t.topicid
				 WHERE e.topicid > 0 AND t.topicid IS NULL"
			);

			// All unique topicids in embeddings (for debugging)
			$db_stats['all_topicids_in_embeddings'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT topicid) FROM {$table} WHERE topicid > 0"
			);

			// Unique posts with embeddings
			$db_stats['posts_with_embeddings'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT postid) FROM {$table} WHERE postid > 0"
			);

			// Table size
			$table_status = $wpdb->get_row( $wpdb->prepare( "SHOW TABLE STATUS WHERE Name = %s", $table ) );
			if ( $table_status ) {
				$db_stats['table_size_mb'] = round( ( $table_status->Data_length + $table_status->Index_length ) / 1024 / 1024, 2 );
			}

			// Last indexed
			$db_stats['last_indexed'] = $wpdb->get_var( "SELECT MAX(updated_at) FROM {$table}" );

			// Check wpforo_topics.local column (indexed flag)
			$db_stats['topics_marked_indexed'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$topics_table} WHERE `local` = 1"
			);
		}

		// Get search settings
		$settings = [
			'search_quality'         => $ai_settings['search_quality'] ?? 'fast',
			'search_min_score'       => $ai_settings['search_min_score'] ?? 30,
			'search_enhance'         => $ai_settings['search_enhance'] ?? false,
			'search_enhance_quality' => $ai_settings['search_enhance_quality'] ?? 'balanced',
			'search_language'        => $ai_settings['search_language'] ?? 'auto',
			'search_max_results'     => $ai_settings['search_max_results'] ?? 5,
		];

		// Get storage mode
		$storage_mode = 'local';
		if ( isset( WPF()->vector_storage ) ) {
			$storage_mode = WPF()->vector_storage->get_storage_mode( $board_id );
		}

		// Get sample indexed content for suggestions - 2-4 word phrases from indexed topics
		$suggestions = [];
		if ( isset( WPF()->tables->ai_embeddings ) ) {
			$emb_table = WPF()->tables->ai_embeddings;

			// Get indexed topics with their titles
			$indexed_topics = $wpdb->get_results(
				"SELECT DISTINCT t.topicid, t.title
				 FROM {$topics_table} t
				 INNER JOIN {$emb_table} e ON t.topicid = e.topicid
				 WHERE e.topicid > 0 AND t.status = 0 AND t.private = 0
				 ORDER BY RAND()
				 LIMIT 50",
				ARRAY_A
			);

			// Collect all 2, 3, 4 word phrases separately
			$two_word = [];
			$three_word = [];
			$four_word = [];

			foreach ( $indexed_topics as $topic ) {
				$phrases = $this->extract_search_phrases( $topic['title'] );
				foreach ( $phrases as $phrase ) {
					$word_count = count( explode( ' ', $phrase ) );
					$item = [ 'phrase' => $phrase, 'topicid' => $topic['topicid'] ];
					if ( $word_count == 2 ) {
						$two_word[] = $item;
					} elseif ( $word_count == 3 ) {
						$three_word[] = $item;
					} elseif ( $word_count == 4 ) {
						$four_word[] = $item;
					}
				}
			}

			// Shuffle each group
			shuffle( $two_word );
			shuffle( $three_word );
			shuffle( $four_word );

			// Pick unique phrases: 2 two-word, 2 three-word, 1 four-word
			$seen = [];
			$pick = function( &$arr, $count ) use ( &$suggestions, &$seen ) {
				$picked = 0;
				foreach ( $arr as $item ) {
					$key = strtolower( $item['phrase'] );
					if ( ! isset( $seen[ $key ] ) ) {
						$suggestions[] = $item;
						$seen[ $key ] = true;
						$picked++;
						if ( $picked >= $count ) break;
					}
				}
			};

			$pick( $two_word, 2 );
			$pick( $three_word, 2 );
			$pick( $four_word, 1 );

			// If still less than 5, fill from any remaining
			if ( count( $suggestions ) < 5 ) {
				$all_remaining = array_merge( $two_word, $three_word, $four_word );
				shuffle( $all_remaining );
				$pick( $all_remaining, 5 - count( $suggestions ) );
			}
		}

		return [
			'database'    => $db_stats,
			'settings'    => $settings,
			'storage_mode' => $storage_mode,
			'suggestions' => $suggestions,
		];
	}

	private function extract_search_phrases( $title ) {
		// Clean title - remove [tags] and punctuation
		$title = preg_replace( '/\[.*?\]/', '', $title );
		$title = strtolower( trim( $title ) );
		$title = preg_replace( '/[^\w\s]/', ' ', $title );
		$title = preg_replace( '/\s+/', ' ', $title );

		$stop_words = [ 'how', 'to', 'the', 'a', 'an', 'is', 'are', 'was', 'were', 'what', 'why', 'when', 'where', 'which', 'who', 'in', 'on', 'at', 'for', 'with', 'and', 'or', 'but', 'not', 'this', 'that', 'these', 'those', 'i', 'you', 'we', 'they', 'it', 'can', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'my', 'your', 'our', 'their', 'have', 'has', 'had', 'been', 'being', 'get', 'got', 'any', 'some', 'migrated', 'test', 'topic', 're' ];

		$words = array_filter( preg_split( '/\s+/', $title ), function( $w ) use ( $stop_words ) {
			return strlen( $w ) > 2 && ! in_array( $w, $stop_words );
		} );
		$words = array_values( $words );

		if ( count( $words ) < 2 ) {
			return [];
		}

		$phrases = [];

		// 2-word phrase
		if ( count( $words ) >= 2 ) {
			$phrases[] = $words[0] . ' ' . $words[1];
		}

		// 3-word phrase
		if ( count( $words ) >= 3 ) {
			$phrases[] = $words[0] . ' ' . $words[1] . ' ' . $words[2];
		}

		// 4-word phrase
		if ( count( $words ) >= 4 ) {
			$phrases[] = $words[0] . ' ' . $words[1] . ' ' . $words[2] . ' ' . $words[3];
		}

		return $phrases;
	}

	private function get_index_info() {
		global $wpdb;
		$board_id = WPF()->board->get_current( 'boardid' );

		// Storage mode
		$storage_mode = 'local';
		if ( isset( WPF()->vector_storage ) ) {
			$storage_mode = WPF()->vector_storage->get_storage_mode( $board_id );
		}

		// Get indexing stats from VectorStorageManager
		$indexing_stats = [];
		if ( isset( WPF()->vector_storage ) ) {
			$indexing_stats = WPF()->vector_storage->get_indexing_stats();
		}

		// Queue status
		$queue_key = 'wpforo_ai_indexing_queue_' . $board_id;
		$queue = get_option( $queue_key, [] );

		$mode_queue_key = 'wpforo_ai_indexing_queue_' . $storage_mode . '_' . $board_id;
		$mode_queue = get_option( $mode_queue_key, [] );

		// Total topics in forum
		$total_topics = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_topics WHERE status = 0 AND private = 0" );

		// Local embeddings stats
		$local_stats = [];
		if ( isset( WPF()->tables->ai_embeddings ) ) {
			$local_stats = [
				'total_embeddings' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . WPF()->tables->ai_embeddings ),
				'topics_indexed'   => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT topicid) FROM " . WPF()->tables->ai_embeddings . " WHERE topicid > 0" ),
				'posts_indexed'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT postid) FROM " . WPF()->tables->ai_embeddings . " WHERE postid > 0" ),
			];

			// Get storage size
			$table_status = $wpdb->get_row(
				$wpdb->prepare( "SHOW TABLE STATUS WHERE Name = %s", WPF()->tables->ai_embeddings )
			);
			if ( $table_status ) {
				$local_stats['storage_size_mb'] = round( ( $table_status->Data_length + $table_status->Index_length ) / 1024 / 1024, 2 );
			}

			// Last indexed
			$local_stats['last_indexed'] = $wpdb->get_var( "SELECT MAX(updated_at) FROM " . WPF()->tables->ai_embeddings );
		}

		// Cloud stats (if cloud mode)
		$cloud_stats = [];
		if ( $storage_mode === 'cloud' ) {
			$cloud_indexed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_topics WHERE cloud = 1" );
			$cloud_stats = [
				'topics_indexed' => $cloud_indexed,
			];
		}

		// Embedding model info
		$embedding_model = 'Amazon Titan v2';
		$embedding_dimensions = 1024;

		return [
			'storage_mode'    => $storage_mode,
			'total_topics'    => $total_topics,
			'indexing_stats'  => $indexing_stats,
			'local_stats'     => $local_stats,
			'cloud_stats'     => $cloud_stats,
			'queue_size'      => count( $queue ) + count( $mode_queue ),
			'is_indexing'     => $indexing_stats['is_indexing'] ?? false,
			'indexing_progress' => $indexing_stats['indexing_progress'] ?? 0,
			'last_indexed'    => $indexing_stats['last_indexed_at'] ?? null,
			'embedding_model' => $embedding_model,
			'embedding_dims'  => $embedding_dimensions,
		];
	}

	private function get_translate_info() {
		global $wpdb;

		$ai_settings = WPF()->settings->ai ?? [];
		$board_id = WPF()->board->get_current( 'boardid' );

		// Get translation stats
		$total_posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_posts" );

		// Get available languages
		$available_languages = [
			'Spanish', 'French', 'German', 'Italian', 'Portuguese',
			'Chinese', 'Japanese', 'Korean', 'Russian', 'Arabic',
			'Hindi', 'Turkish', 'Dutch', 'Polish', 'Vietnamese',
		];

		// Check if AI client is connected
		$ai_client = WPF()->ai_client;
		$is_connected = $ai_client && $ai_client->is_connected();

		// Get subscription info for credits
		$credits_info = [];
		if ( $is_connected ) {
			$status = $ai_client->get_tenant_status();
			if ( ! is_wp_error( $status ) && isset( $status['subscription'] ) ) {
				$credits_info = [
					'remaining' => $status['subscription']['credits_remaining'] ?? 0,
					'used'      => $status['subscription']['credits_used'] ?? 0,
				];
			}
		}

		return [
			'settings' => [
				'translation_enabled'  => $ai_settings['translation'] ?? false,
				'default_language'     => $ai_settings['translation_language'] ?? 'auto',
				'translation_quality'  => $ai_settings['translation_quality'] ?? 'standard',
				'auto_detect_language' => $ai_settings['translation_auto_detect'] ?? true,
			],
			'stats' => [
				'total_posts'         => $total_posts,
				'available_languages' => count( $available_languages ),
			],
			'connection' => [
				'connected' => $is_connected,
				'credits'   => $credits_info,
			],
			'api' => [
				'endpoint'    => '/v1/translation/translate',
				'method'      => 'POST',
				'cost'        => '1 credit per translation',
			],
		];
	}

	private function get_summarize_info() {
		global $wpdb;

		$ai_settings = WPF()->settings->ai ?? [];

		// Get topic stats
		$total_topics = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_topics" );
		$topics_with_replies = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_topics WHERE posts >= %d",
				( $ai_settings['topic_summary_min_replies'] ?? 1 ) + 1
			)
		);

		// Check if AI client is connected
		$ai_client = WPF()->ai_client;
		$is_connected = $ai_client && $ai_client->is_connected();

		// Get subscription info for credits
		$credits_info = [];
		if ( $is_connected ) {
			$status = $ai_client->get_tenant_status();
			if ( ! is_wp_error( $status ) && isset( $status['subscription'] ) ) {
				$credits_info = [
					'remaining' => $status['subscription']['credits_remaining'] ?? 0,
					'used'      => $status['subscription']['credits_used'] ?? 0,
				];
			}
		}

		// Quality options with credits
		$quality_options = [
			'fast'     => [ 'label' => 'Fast', 'credits' => 1 ],
			'balanced' => [ 'label' => 'Balanced', 'credits' => 2 ],
			'advanced' => [ 'label' => 'Advanced', 'credits' => 3 ],
			'premium'  => [ 'label' => 'Premium', 'credits' => 4 ],
		];

		// Style options
		$style_options = [
			'compact'        => 'Compact with Key Points',
			'structured'     => 'Structured with Sections',
			'conversational' => 'Conversational Flow',
			'detailed'       => 'Short Summary + Details',
			'minimal'        => 'Minimal and Clean',
		];

		return [
			'settings' => [
				'summary_enabled'     => $ai_settings['topic_summary'] ?? false,
				'summary_quality'     => $ai_settings['topic_summary_quality'] ?? 'balanced',
				'summary_style'       => $ai_settings['topic_summary_style'] ?? 'detailed',
				'min_replies'         => $ai_settings['topic_summary_min_replies'] ?? 1,
				'summary_language'    => $ai_settings['topic_summary_language'] ?? 'auto',
			],
			'options' => [
				'quality' => $quality_options,
				'style'   => $style_options,
			],
			'stats' => [
				'total_topics'        => $total_topics,
				'topics_with_replies' => $topics_with_replies,
			],
			'connection' => [
				'connected' => $is_connected,
				'credits'   => $credits_info,
			],
			'api' => [
				'endpoint' => '/v1/summarizing/summarize',
				'method'   => 'POST',
				'cost'     => '1-4 credits based on quality',
			],
		];
	}

	private function get_suggestions_info() {
		global $wpdb;

		$ai_settings = WPF()->settings->ai ?? [];

		// Get indexed topic stats
		$board_id = WPF()->board->get_current( 'boardid' );
		$storage_mode = WPF()->vector_storage ? WPF()->vector_storage->get_storage_mode( $board_id ) : 'local';
		$is_local_mode = $storage_mode === 'local';
		$total_topics = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpforo_topics" );

		// Build indexed filter based on storage mode
		// Local mode: topics with entries in ai_embeddings table
		// Cloud mode: topics with cloud = 1 in wpforo_topics
		if ( $is_local_mode ) {
			$indexed_join = "INNER JOIN (SELECT DISTINCT topicid FROM {$wpdb->prefix}wpforo_ai_embeddings) e ON t.topicid = e.topicid";
			$indexed_filter = "";
		} else {
			$indexed_join = "";
			$indexed_filter = "AND t.cloud = 1";
		}

		// Get example queries from INDEXED topic titles (1 word, 2 words, 3 words)
		$examples = [];

		// 1 word example
		$one_word = $wpdb->get_var(
			"SELECT t.title FROM {$wpdb->prefix}wpforo_topics t
			 {$indexed_join}
			 WHERE LENGTH(t.title) - LENGTH(REPLACE(t.title, ' ', '')) = 0
			 AND LENGTH(t.title) > 3 {$indexed_filter}
			 ORDER BY RAND() LIMIT 1"
		);
		if ( $one_word ) $examples[] = $one_word;

		// 2 words example
		$two_words = $wpdb->get_var(
			"SELECT t.title FROM {$wpdb->prefix}wpforo_topics t
			 {$indexed_join}
			 WHERE LENGTH(t.title) - LENGTH(REPLACE(t.title, ' ', '')) = 1 {$indexed_filter}
			 ORDER BY RAND() LIMIT 1"
		);
		if ( $two_words ) $examples[] = $two_words;

		// 3 words example
		$three_words = $wpdb->get_var(
			"SELECT t.title FROM {$wpdb->prefix}wpforo_topics t
			 {$indexed_join}
			 WHERE LENGTH(t.title) - LENGTH(REPLACE(t.title, ' ', '')) = 2 {$indexed_filter}
			 ORDER BY RAND() LIMIT 1"
		);
		if ( $three_words ) $examples[] = $three_words;

		// Fill remaining with random indexed titles
		$attempts = 0;
		while ( count( $examples ) < 5 && $attempts < 10 ) {
			$attempts++;
			$random = $wpdb->get_var(
				"SELECT t.title FROM {$wpdb->prefix}wpforo_topics t
				 {$indexed_join}
				 WHERE 1=1 {$indexed_filter}
				 ORDER BY RAND() LIMIT 1"
			);
			if ( $random && ! in_array( $random, $examples ) ) {
				$examples[] = $random;
			}
		}

		// Check if AI client is connected
		$ai_client = WPF()->ai_client;
		$is_connected = $ai_client && $ai_client->is_connected();

		// Get subscription info for credits
		$credits_info = [];
		if ( $is_connected ) {
			$status = $ai_client->get_tenant_status();
			if ( ! is_wp_error( $status ) && isset( $status['subscription'] ) ) {
				$credits_info = [
					'remaining' => $status['subscription']['credits_remaining'] ?? 0,
					'used'      => $status['subscription']['credits_used'] ?? 0,
				];
			}
		}

		// Quality options
		$quality_options = [
			'fast'     => [ 'label' => 'Fast', 'credits' => 1 ],
			'balanced' => [ 'label' => 'Balanced', 'credits' => 2 ],
			'advanced' => [ 'label' => 'Advanced', 'credits' => 3 ],
			'premium'  => [ 'label' => 'Premium', 'credits' => 4 ],
		];

		return [
			'settings' => [
				'suggestions_enabled' => $ai_settings['topic_suggestions'] ?? false,
				'quality'             => $ai_settings['topic_suggestions_quality'] ?? 'balanced',
				'min_words'           => $ai_settings['topic_suggestions_min_words'] ?? 3,
				'max_similar'         => $ai_settings['topic_suggestions_max_similar'] ?? 3,
				'max_related'         => $ai_settings['topic_suggestions_max_related'] ?? 3,
				'similarity'          => $ai_settings['topic_suggestions_similarity'] ?? 55,
				'show_related'        => $ai_settings['topic_suggestions_show_related'] ?? false,
				'show_answer'         => $ai_settings['topic_suggestions_show_answer'] ?? false,
				'language'            => $ai_settings['topic_suggestions_language'] ?? 'auto',
			],
			'options' => [
				'quality' => $quality_options,
			],
			'stats' => [
				'total_topics'  => $total_topics,
				'storage_mode'  => $storage_mode,
			],
			'examples' => $examples,
			'connection' => [
				'connected' => $is_connected,
				'credits'   => $credits_info,
			],
			'api' => [
				'endpoint' => '/v1/suggestions/suggest',
				'method'   => 'POST',
				'cost'     => '1-4 credits based on quality',
			],
		];
	}

	private function get_moderate_info() {
		$ai_settings = WPF()->settings->ai ?? [];

		// Check if AI client is connected
		$ai_client = WPF()->ai_client;
		$is_connected = $ai_client && $ai_client->is_connected();

		// Get subscription info for credits
		$credits_info = [];
		if ( $is_connected ) {
			$status = $ai_client->get_tenant_status();
			if ( ! is_wp_error( $status ) && isset( $status['subscription'] ) ) {
				$credits_info = [
					'remaining' => $status['subscription']['credits_remaining'] ?? 0,
					'used'      => $status['subscription']['credits_used'] ?? 0,
				];
			}
		}

		// Get forum context for spam detection
		$board_id = WPF()->board->get_current( 'boardid' );
		$board = WPF()->board->get_board( $board_id );
		$forum_context = '';
		if ( $board ) {
			$forum_context = 'Forum: ' . ( $board['title'] ?? 'Community' );
			if ( ! empty( $board['description'] ) ) {
				$forum_context .= ' - ' . $board['description'];
			}
		}

		// Quality options
		$quality_options = [
			'fast'     => [ 'label' => 'Fast', 'credits' => 1 ],
			'balanced' => [ 'label' => 'Balanced', 'credits' => 2 ],
			'advanced' => [ 'label' => 'Advanced', 'credits' => 3 ],
			'premium'  => [ 'label' => 'Premium', 'credits' => 4 ],
		];

		// Spam example content for testing
		$spam_examples = [
			[
				'label' => 'Promotional Spam',
				'content' => "AMAZING DEAL!!! 🔥🔥🔥 Visit www.best-cheap-pills.xyz for 90% OFF all products! FREE shipping worldwide! Limited time offer - BUY NOW before it's gone! Click here >>> bit.ly/spam123 <<< Don't miss out! 💰💰💰 Best prices guaranteed! Contact us at spam@fake-email.ru for wholesale discounts!",
			],
			[
				'label' => 'Crypto Scam',
				'content' => "Hey everyone! I just made \$50,000 in ONE WEEK using this secret crypto trading bot! 🚀 My friend shared this exclusive link with me and now I'm sharing with you! Join now at telegram.me/crypto_scam_bot - they guarantee 500% returns! Send 0.1 BTC to bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh and get 1 BTC back! This is NOT a scam, my cousin verified it!",
			],
			[
				'label' => 'SEO Link Spam',
				'content' => "Great article! Very helpful information. By the way, if anyone needs help with similar topics, check out my website [url=https://spammy-seo-site.com]best forum software[/url] and [url=https://another-spam.net]cheap web hosting[/url]. We also offer [url=https://link-farm.biz]SEO services[/url] and [url=https://fake-reviews.com]reputation management[/url]. Visit us today! Keywords: forum, community, discussion, board, PHP, WordPress, plugin",
			],
		];

		// Toxicity example content for testing (different levels)
		$toxic_examples = [
			[
				'label' => 'Mild Frustration',
				'content' => "This is so annoying! I've been waiting for hours and nobody is helping me. The support here is absolutely useless. I'm starting to regret buying this product. Can someone please just answer my simple question?",
			],
			[
				'label' => 'Aggressive Insults',
				'content' => "Are you people complete idiots? This is the dumbest thing I've ever seen! The developers must be brain-dead morons to release such garbage. Anyone who defends this trash is a clueless fool. What a joke!",
			],
			[
				'label' => 'Hate Speech',
				'content' => "All [group] are disgusting subhumans who should be exterminated. They're ruining everything and don't deserve to exist. If you're one of them, get out of here before we make you. Death to all [group]!",
			],
		];

		return [
			'settings' => [
				'spam' => [
					'enabled'            => $ai_settings['moderation_spam'] ?? false,
					'quality'            => $ai_settings['moderation_spam_quality'] ?? 'balanced',
					'use_context'        => $ai_settings['moderation_spam_use_context'] ?? true,
					'min_indexed'        => $ai_settings['moderation_spam_min_indexed'] ?? 100,
					'action_detected'    => $ai_settings['moderation_spam_action_detected'] ?? 'unapprove_ban',
					'action_suspected'   => $ai_settings['moderation_spam_action_suspected'] ?? 'unapprove',
					'exempt_minposts'    => $ai_settings['moderation_spam_exempt_minposts'] ?? 10,
				],
				'toxicity' => [
					'enabled'     => $ai_settings['moderation_toxicity'] ?? false,
					'sensitivity' => $ai_settings['moderation_toxicity_sensitivity'] ?? 'medium',
					'action'      => $ai_settings['moderation_toxicity_action'] ?? 'unapprove',
				],
				'policy' => [
					'enabled' => $ai_settings['moderation_policy'] ?? false,
				],
			],
			'options' => [
				'quality' => $quality_options,
			],
			'examples' => [
				'spam'  => $spam_examples,
				'toxic' => $toxic_examples,
			],
			'context' => [
				'forum_description' => $forum_context,
				'board_id'          => $board_id,
			],
			'connection' => [
				'connected' => $is_connected,
				'credits'   => $credits_info,
			],
			'api' => [
				'spam_endpoint'    => '/v1/moderation/spam/detect',
				'unified_endpoint' => '/v1/moderation/analyze',
				'method'           => 'POST',
				'cost'             => '1-4 credits based on quality',
			],
		];
	}

	private function get_chat_info() {
		$ai_settings = WPF()->settings->ai ?? [];
		return [
			'settings' => [
				'chatbot_enabled'  => $ai_settings['chatbot'] ?? false,
				'chatbot_position' => $ai_settings['chatbot_position'] ?? 'bottom-right',
			],
		];
	}

	public function ajax_get_topics() {
		$this->verify_request();

		global $wpdb;

		$filter = sanitize_text_field( $_POST['filter'] ?? 'all' );
		$limit = intval( $_POST['limit'] ?? 100 );
		$min_replies = intval( $_POST['min_replies'] ?? 0 );

		$topics = [];

		// Base query
		$sql = "SELECT t.topicid, t.title, t.posts, t.created, f.title as forum_title
		        FROM {$wpdb->prefix}wpforo_topics t
		        LEFT JOIN {$wpdb->prefix}wpforo_forums f ON t.forumid = f.forumid
		        WHERE t.status = 0 AND t.private = 0";

		// Filter by minimum replies (posts > min_replies since posts count includes first post)
		if ( $min_replies > 0 ) {
			$sql .= $wpdb->prepare( " AND t.posts > %d", $min_replies );
		}

		// Filter for topics with attachments
		// Images: <img> tags, [attach] shortcode, image URLs (.jpg, .png, .gif, .webp)
		// Documents: .pdf, .doc, .docx URLs
		if ( $filter === 'with_attachments' ) {
			$sql .= " AND EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpforo_posts p
				WHERE p.topicid = t.topicid
				AND (
					p.body LIKE '%<img%'
					OR p.body LIKE '%[attach]%'
					OR p.body LIKE '%.jpg%'
					OR p.body LIKE '%.jpeg%'
					OR p.body LIKE '%.png%'
					OR p.body LIKE '%.gif%'
					OR p.body LIKE '%.webp%'
					OR p.body LIKE '%.pdf%'
					OR p.body LIKE '%.docx%'
					OR p.body LIKE '%.doc%'
				)
			)";
		} elseif ( $filter === 'with_images' ) {
			$sql .= " AND EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpforo_posts p
				WHERE p.topicid = t.topicid
				AND (
					p.body LIKE '%<img%'
					OR p.body LIKE '%[attach]%'
					OR p.body LIKE '%.jpg%'
					OR p.body LIKE '%.jpeg%'
					OR p.body LIKE '%.png%'
					OR p.body LIKE '%.gif%'
					OR p.body LIKE '%.webp%'
				)
			)";
		} elseif ( $filter === 'with_documents' ) {
			$sql .= " AND EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpforo_posts p
				WHERE p.topicid = t.topicid
				AND (p.body LIKE '%.pdf%' OR p.body LIKE '%.docx%' OR p.body LIKE '%.doc%')
			)";
		}

		$sql .= " ORDER BY t.topicid DESC LIMIT %d";
		$sql = $wpdb->prepare( $sql, $limit );

		$results = $wpdb->get_results( $sql );

		foreach ( $results as $row ) {
			// Check for attachments
			$attachments = $this->get_topic_attachments( $row->topicid );

			$topics[] = [
				'topicid'     => $row->topicid,
				'title'       => $row->title,
				'posts'       => $row->posts,
				'forum'       => $row->forum_title,
				'created'     => $row->created,
				'has_image'   => ! empty( array_filter( $attachments, fn( $a ) => $a['type'] === 'image' ) ),
				'has_pdf'     => ! empty( array_filter( $attachments, fn( $a ) => $a['type'] === 'pdf' ) ),
				'has_doc'     => ! empty( array_filter( $attachments, fn( $a ) => in_array( $a['type'], [ 'doc', 'docx' ], true ) ) ),
			];
		}

		wp_send_json_success( $topics );
	}

	public function ajax_get_posts() {
		$this->verify_request();

		global $wpdb;

		$limit = intval( $_POST['limit'] ?? 50 );

		// Get random posts with decent content length
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.postid, p.topicid, p.body, p.created, t.title as topic_title, u.display_name as author
			 FROM {$wpdb->prefix}wpforo_posts p
			 LEFT JOIN {$wpdb->prefix}wpforo_topics t ON p.topicid = t.topicid
			 LEFT JOIN {$wpdb->prefix}users u ON p.userid = u.ID
			 WHERE LENGTH(p.body) > 100
			 ORDER BY RAND()
			 LIMIT %d",
			$limit
		) );

		$result = [];
		foreach ( $posts as $post ) {
			$body_plain = wp_strip_all_tags( $post->body );
			$preview = mb_substr( $body_plain, 0, 80 );
			if ( mb_strlen( $body_plain ) > 80 ) {
				$preview .= '...';
			}

			$result[] = [
				'postid'      => $post->postid,
				'topicid'     => $post->topicid,
				'topic_title' => $post->topic_title,
				'author'      => $post->author,
				'preview'     => $preview,
				'length'      => mb_strlen( $body_plain ),
				'created'     => $post->created,
			];
		}

		wp_send_json_success( $result );
	}

	private function get_topic_attachments( $topicid ) {
		global $wpdb;

		$attachments = [];
		$image_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
		$doc_exts = [ 'pdf', 'doc', 'docx', 'txt' ];

		// Get posts
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT postid, body FROM {$wpdb->prefix}wpforo_posts WHERE topicid = %d",
			$topicid
		) );

		foreach ( $posts as $post ) {
			$body = $post->body;

			// 1. Check for [attach] shortcodes (Advanced Attachments addon)
			if ( preg_match_all( '/\[attach\](\d+)\[\/attach\]/i', $body, $matches ) ) {
				foreach ( $matches[1] as $attach_id ) {
					$file = get_attached_file( $attach_id );
					if ( $file ) {
						$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
						$type = in_array( $ext, $doc_exts, true ) ? $ext :
						        ( in_array( $ext, $image_exts, true ) ? 'image' : 'other' );
						$attachments[] = [
							'id'     => $attach_id,
							'file'   => basename( $file ),
							'type'   => $type,
							'postid' => $post->postid,
						];
					}
				}
			}

			// 2. Check for <img> tags
			if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
				foreach ( $matches[1] as $src ) {
					// Only local images (same site)
					if ( strpos( $src, get_site_url() ) !== false || strpos( $src, '/wp-content/' ) === 0 ) {
						$attachments[] = [
							'id'     => 0,
							'file'   => basename( parse_url( $src, PHP_URL_PATH ) ),
							'type'   => 'image',
							'postid' => $post->postid,
						];
					}
				}
			}

			// 3. Check for plain URLs with image/doc extensions
			if ( preg_match_all( '/https?:\/\/[^\s<>"\']+\.(' . implode( '|', array_merge( $image_exts, $doc_exts ) ) . ')/i', $body, $matches ) ) {
				foreach ( $matches[0] as $idx => $url ) {
					$ext = strtolower( $matches[1][ $idx ] );
					// Only local URLs
					if ( strpos( $url, get_site_url() ) !== false ) {
						$type = in_array( $ext, $doc_exts, true ) ? $ext : 'image';
						$attachments[] = [
							'id'     => 0,
							'file'   => basename( parse_url( $url, PHP_URL_PATH ) ),
							'type'   => $type,
							'postid' => $post->postid,
						];
					}
				}
			}
		}

		return $attachments;
	}

	public function ajax_run_test() {
		$this->verify_request();

		$test_type = sanitize_text_field( $_POST['test_type'] ?? '' );
		$params = isset( $_POST['params'] ) ? $_POST['params'] : [];

		if ( ! function_exists( 'WPF' ) || ! WPF()->ai_client ) {
			wp_send_json_error( [ 'message' => 'wpForo or AI Client not available' ] );
		}

		$result = [];
		$start_time = microtime( true );

		try {
			switch ( $test_type ) {
				case 'tenant_status':
					$result = $this->test_tenant_status();
					break;

				case 'semantic_search':
					$result = $this->test_semantic_search( $params );
					break;

				case 'index_topic':
					$result = $this->test_index_topic( $params );
					break;

				case 'translate':
					$result = $this->test_translate( $params );
					break;

				case 'summarize':
					$result = $this->test_summarize( $params );
					break;

				case 'suggestions':
					$result = $this->test_suggestions( $params );
					break;

				case 'moderate':
				case 'spam':
				case 'toxic':
					$result = $this->test_moderate( $params );
					break;

				case 'chat':
					$result = $this->test_chat( $params );
					break;

				case 'rag_status':
					$result = $this->test_rag_status();
					break;

				case 'analytics':
					$result = $this->test_analytics();
					break;

				case 'analytics_insight':
					$result = $this->test_analytics_insight( $params );
					break;

				default:
					wp_send_json_error( [ 'message' => 'Unknown test type: ' . $test_type ] );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message'   => 'Test failed with exception: ' . $e->getMessage(),
				'trace'     => $e->getTraceAsString(),
				'test_type' => $test_type,
			] );
		}

		$duration = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		wp_send_json_success( [
			'test_type'   => $test_type,
			'result'      => $result,
			'duration_ms' => $duration,
			'timestamp'   => current_time( 'mysql' ),
		] );
	}

	private function test_tenant_status() {
		$status = WPF()->ai_client->get_tenant_status( true );

		if ( is_wp_error( $status ) ) {
			return [
				'success' => false,
				'error'   => $status->get_error_message(),
			];
		}

		return [
			'success' => true,
			'data'    => $status,
		];
	}

	private function test_semantic_search( $params ) {
		global $wpdb;

		$query = sanitize_text_field( $params['query'] ?? 'test search query' );
		$limit = intval( $params['limit'] ?? 5 );
		$requested_mode = sanitize_text_field( $params['storage_mode'] ?? 'current' );
		$board_id = WPF()->board->get_current( 'boardid' );

		// Use the REAL search path: VectorStorageManager (routes to local or cloud)
		if ( ! isset( WPF()->vector_storage ) ) {
			return [
				'success' => false,
				'error'   => 'VectorStorageManager not available',
			];
		}

		// Get current storage mode
		$current_mode = WPF()->vector_storage->get_storage_mode( $board_id );
		$original_mode = $current_mode;

		// Temporarily switch storage mode if requested
		if ( $requested_mode !== 'current' && in_array( $requested_mode, [ 'local', 'cloud' ], true ) ) {
			update_option( 'wpforo_ai_storage_mode_' . $board_id, $requested_mode );
			WPF()->vector_storage->reset_storage_mode_cache();
			$storage_mode = $requested_mode;
		} else {
			$storage_mode = $current_mode;
		}

		// Check if we have embeddings at all
		$embedding_count = 0;
		if ( isset( WPF()->tables->ai_embeddings ) ) {
			$embedding_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . WPF()->tables->ai_embeddings );
		}

		$raw_results = WPF()->vector_storage->semantic_search( $query, $limit );

		// Restore original storage mode if we changed it
		if ( $requested_mode !== 'current' && $requested_mode !== $original_mode ) {
			update_option( 'wpforo_ai_storage_mode_' . $board_id, $original_mode );
			WPF()->vector_storage->reset_storage_mode_cache();
		}

		if ( is_wp_error( $raw_results ) ) {
			return [
				'success' => false,
				'error'   => $raw_results->get_error_message(),
				'diagnostics' => [
					'storage_mode'    => $storage_mode,
					'embedding_count' => $embedding_count,
				],
			];
		}

		// Parse the response (same structure as real wpForo search)
		$search_results = $raw_results['results'] ?? [];
		$total = $raw_results['total'] ?? count( $search_results );
		$query_time = $raw_results['query_time_ms'] ?? 0;
		$credits_used = $raw_results['credits_used'] ?? 0;

		// Enrich results with forum data (like the real search does)
		$enriched_results = [];
		$min_score_setting = (int) wpfval( WPF()->settings->ai, 'search_min_score' );
		$is_local = $storage_mode === 'local';
		$min_score_percent = $is_local ? max( 15, round( $min_score_setting / 3 ) ) : $min_score_setting;

		foreach ( $search_results as $result ) {
			$topic_id = $result['topic_id'] ?? $result['topicid'] ?? 0;
			$post_id = $result['post_id'] ?? $result['postid'] ?? 0;
			$score = $result['score'] ?? 0;
			$score_percent = round( $score * 100 );

			// Skip low scores
			if ( $min_score_percent > 0 && $score_percent < $min_score_percent ) {
				continue;
			}

			$topic = $topic_id ? wpforo_topic( $topic_id ) : null;
			$content = $result['content'] ?? $result['excerpt'] ?? '';

			// Relevance label
			if ( $is_local ) {
				$relevance = $score_percent >= 25 ? 'Excellent' : ( $score_percent >= 20 ? 'Good' : ( $score_percent >= 15 ? 'Relevant' : 'Low' ) );
			} else {
				$relevance = $score_percent >= 80 ? 'Excellent' : ( $score_percent >= 60 ? 'Good' : ( $score_percent >= 40 ? 'Relevant' : 'Low' ) );
			}

			$enriched_results[] = [
				'topic_id'   => $topic_id,
				'post_id'    => $post_id,
				'title'      => $result['title'] ?? ( $topic ? $topic['title'] : '' ),
				'excerpt'    => wp_trim_words( wp_strip_all_tags( $content ), 30 ),
				'score'      => $score_percent . '%',
				'relevance'  => $relevance,
				'forum'      => $topic ? wpforo_forum( $topic['forumid'], 'title' ) : '',
				'url'        => $result['url'] ?? ( $topic ? wpforo_topic( $topic_id, 'url' ) : '' ),
			];
		}

		// Check for AI enhancement setting
		$enhance_setting = wpfval( WPF()->settings->ai, 'search_enhance' );
		$enhance_enabled = ! empty( $enhance_setting ) && $enhance_setting !== '0' && $enhance_setting !== 0;

		// Get AI enhancement (summary + recommendations) if enabled and we have 3+ results
		$enhancement = null;
		$enhance_credits = 0;
		if ( $enhance_enabled && count( $enriched_results ) >= 3 ) {
			$user_language = WPF()->ai_client->get_user_language();

			// Format results for enhance API (needs content, not excerpt)
			$results_for_enhance = [];
			foreach ( $enriched_results as $r ) {
				$results_for_enhance[] = [
					'title'   => $r['title'],
					'content' => $r['excerpt'],
					'url'     => $r['url'],
					'score'   => intval( $r['score'] ),
				];
			}

			$enhance_response = WPF()->ai_client->enhance_search_results( $query, $results_for_enhance, $user_language );

			if ( ! is_wp_error( $enhance_response ) && ! empty( $enhance_response['success'] ) ) {
				$enhancement = [
					'summary'         => $enhance_response['summary'] ?? '',
					'quick_answer'    => $enhance_response['quick_answer'] ?? '',
					'recommendations' => $enhance_response['recommendations'] ?? [],
				];
				$enhance_credits = $enhance_response['credits_used'] ?? 0;
			} elseif ( is_wp_error( $enhance_response ) ) {
				$enhancement = [
					'error' => $enhance_response->get_error_message(),
				];
			} elseif ( ! empty( $enhance_response['disabled'] ) ) {
				$enhancement = [
					'disabled' => true,
					'message'  => 'AI Enhancement is disabled in settings',
				];
			}
		} elseif ( $enhance_enabled && count( $enriched_results ) < 3 ) {
			$enhancement = [
				'skipped' => true,
				'message' => 'Need at least 3 results to generate AI enhancement',
			];
		}

		// Calculate scores for history
		$scores = array_map( fn($r) => intval( $r['score'] ), $enriched_results );
		$avg_score = count( $scores ) > 0 ? round( array_sum( $scores ) / count( $scores ), 2 ) : 0;
		$top_score = count( $scores ) > 0 ? max( $scores ) : 0;

		// Save to history table
		$has_valid_enhancement = $enhancement && ! isset( $enhancement['error'] ) && ! isset( $enhancement['skipped'] ) && ! isset( $enhancement['disabled'] );
		$this->save_search_test( [
			'query'           => $query,
			'storage_mode'    => $storage_mode,
			'total_results'   => count( $enriched_results ),
			'query_time_ms'   => $query_time,
			'credits_used'    => $credits_used + $enhance_credits,
			'avg_score'       => $avg_score,
			'top_score'       => $top_score,
			'has_enhancement' => $has_valid_enhancement ? 1 : 0,
			'results'         => $enriched_results,
			'enhancement'     => $has_valid_enhancement ? $enhancement : null,
			'settings'        => [
				'storage_mode'    => $storage_mode,
				'min_score'       => $min_score_percent,
				'enhance_enabled' => $enhance_enabled,
			],
		] );

		return [
			'success'       => count( $enriched_results ) > 0,
			'query'         => $query,
			'total_found'   => count( $enriched_results ),
			'total_raw'     => $total,
			'query_time_ms' => $query_time,
			'credits_used'  => $credits_used + $enhance_credits,
			'results'       => $enriched_results,
			'enhancement'   => $enhancement,
			'message'       => count( $enriched_results ) > 0
				? sprintf( 'Found %d results in %dms', count( $enriched_results ), $query_time )
				: 'No results found (or all below min_score threshold)',
			'settings_used' => [
				'storage_mode'     => $storage_mode,
				'min_score'        => $min_score_percent . '%',
				'enhance_enabled'  => $enhance_enabled ? 'Yes' : 'No',
				'embedding_count'  => $embedding_count,
			],
		];
	}

	private function save_search_test( $data ) {
		global $wpdb;

		$results_data = [
			'results'     => $data['results'],
			'enhancement' => $data['enhancement'] ?? null,
		];

		$wpdb->insert(
			$this->table_name,
			[
				'query'           => $data['query'],
				'storage_mode'    => $data['storage_mode'],
				'total_results'   => $data['total_results'],
				'query_time_ms'   => $data['query_time_ms'],
				'credits_used'    => $data['credits_used'],
				'avg_score'       => $data['avg_score'],
				'top_score'       => $data['top_score'],
				'has_enhancement' => $data['has_enhancement'],
				'results_json'    => json_encode( $results_data ),
				'settings_json'   => json_encode( $data['settings'] ),
				'created_at'      => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s' ]
		);
	}

	public function ajax_get_search_history() {
		$this->verify_request();

		global $wpdb;

		$query_filter = sanitize_text_field( $_POST['query'] ?? '' );
		$limit = intval( $_POST['limit'] ?? 50 );

		$sql = "SELECT * FROM {$this->table_name}";
		if ( $query_filter ) {
			$sql .= $wpdb->prepare( " WHERE query = %s", $query_filter );
		}
		$sql .= " ORDER BY created_at DESC LIMIT %d";
		$sql = $wpdb->prepare( $sql, $limit );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Calculate changes compared to previous test of same query
		$processed = [];
		$prev_by_query = [];

		foreach ( array_reverse( $results ) as $row ) {
			$q = $row['query'];
			$row['results_json'] = json_decode( $row['results_json'], true );
			$row['settings_json'] = json_decode( $row['settings_json'], true );

			// Calculate changes
			if ( isset( $prev_by_query[ $q ] ) ) {
				$prev = $prev_by_query[ $q ];
				$row['changes'] = [
					'results_diff'    => $row['total_results'] - $prev['total_results'],
					'time_diff'       => $row['query_time_ms'] - $prev['query_time_ms'],
					'avg_score_diff'  => round( $row['avg_score'] - $prev['avg_score'], 2 ),
					'top_score_diff'  => round( $row['top_score'] - $prev['top_score'], 2 ),
				];
			} else {
				$row['changes'] = null;
			}

			$prev_by_query[ $q ] = $row;
			$processed[] = $row;
		}

		wp_send_json_success( array_reverse( $processed ) );
	}

	public function ajax_clear_search_history() {
		$this->verify_request();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

		wp_send_json_success( [ 'message' => 'History cleared' ] );
	}

	public function ajax_delete_search_test() {
		$this->verify_request();

		global $wpdb;
		$id = intval( $_POST['id'] ?? 0 );

		if ( $id > 0 ) {
			$wpdb->delete( $this->table_name, [ 'id' => $id ], [ '%d' ] );
		}

		wp_send_json_success( [ 'message' => 'Deleted' ] );
	}

	public function ajax_get_suggestion_history() {
		$this->verify_request();

		global $wpdb;
		$limit = intval( $_POST['limit'] ?? 50 );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->suggestion_table} ORDER BY created_at DESC LIMIT %d",
			$limit
		) );

		$history = [];
		foreach ( $results as $row ) {
			$history[] = [
				'id'           => $row->id,
				'query'        => $row->query,
				'quality'      => $row->quality,
				'similarity'   => $row->similarity_threshold,
				'total_similar' => $row->total_similar,
				'total_related' => $row->total_related,
				'has_answer'   => (bool) $row->has_answer,
				'top_score'    => $row->top_score,
				'query_time_ms' => $row->query_time_ms,
				'credits_used' => $row->credits_used,
				'settings'     => json_decode( $row->settings_json, true ),
				'results'      => json_decode( $row->results_json, true ),
				'created_at'   => $row->created_at,
			];
		}

		wp_send_json_success( $history );
	}

	public function ajax_clear_suggestion_history() {
		$this->verify_request();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->suggestion_table}" );

		wp_send_json_success( [ 'message' => 'Suggestion history cleared' ] );
	}

	public function ajax_delete_suggestion_test() {
		$this->verify_request();

		global $wpdb;
		$id = intval( $_POST['id'] ?? 0 );

		if ( $id > 0 ) {
			$wpdb->delete( $this->suggestion_table, [ 'id' => $id ], [ '%d' ] );
		}

		wp_send_json_success( [ 'message' => 'Deleted' ] );
	}

	public function ajax_get_moderation_history() {
		$this->verify_request();

		global $wpdb;
		$limit = intval( $_POST['limit'] ?? 50 );
		$test_type = sanitize_text_field( $_POST['test_type'] ?? '' );

		$where = '';
		if ( $test_type ) {
			$where = $wpdb->prepare( " WHERE test_type = %s", $test_type );
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->moderation_table} {$where} ORDER BY created_at DESC LIMIT %d",
			$limit
		) );

		$history = [];
		foreach ( $results as $row ) {
			$history[] = [
				'id'               => $row->id,
				'test_type'        => $row->test_type,
				'content'          => stripslashes( $row->content ),
				'quality'          => $row->quality,
				'spam_score'       => $row->spam_score,
				'is_spam'          => (bool) $row->is_spam,
				'confidence'       => $row->confidence,
				'indicators'       => json_decode( $row->indicators, true ),
				'analysis_summary' => stripslashes( $row->analysis_summary ),
				'query_time_ms'    => $row->query_time_ms,
				'credits_used'     => $row->credits_used,
				'settings'         => json_decode( $row->settings_json, true ),
				'results'          => json_decode( $row->results_json, true ),
				'created_at'       => $row->created_at,
			];
		}

		wp_send_json_success( $history );
	}

	public function ajax_clear_moderation_history() {
		$this->verify_request();

		global $wpdb;
		$test_type = sanitize_text_field( $_POST['test_type'] ?? '' );

		if ( $test_type ) {
			$wpdb->delete( $this->moderation_table, [ 'test_type' => $test_type ], [ '%s' ] );
		} else {
			$wpdb->query( "TRUNCATE TABLE {$this->moderation_table}" );
		}

		wp_send_json_success( [ 'message' => 'Moderation history cleared' ] );
	}

	public function ajax_delete_moderation_test() {
		$this->verify_request();

		global $wpdb;
		$id = intval( $_POST['id'] ?? 0 );

		if ( $id > 0 ) {
			$wpdb->delete( $this->moderation_table, [ 'id' => $id ], [ '%d' ] );
		}

		wp_send_json_success( [ 'message' => 'Deleted' ] );
	}

	private function test_index_topic( $params ) {
		$topicid = intval( $params['topicid'] ?? 0 );
		$include_images = intval( $params['include_images'] ?? 1 );
		$include_docs = intval( $params['include_docs'] ?? 1 );

		if ( ! $topicid ) {
			return [
				'success' => false,
				'error'   => 'Please select a topic',
			];
		}

		$board_id = WPF()->board->get_current( 'boardid' );
		$storage_mode = WPF()->vector_storage->get_storage_mode( $board_id );
		$ai_client = WPF()->ai_client;

		// Step 1: Get topic info
		$topic = wpforo_topic( $topicid );
		if ( ! $topic ) {
			return [
				'success' => false,
				'error'   => 'Topic not found',
				'steps'   => [ $this->step_result( 'topic_load', false, 'Topic not found' ) ],
			];
		}

		$steps = [];
		$steps[] = $this->step_result( 'topic_load', true, 'Topic loaded', [
			'title'    => $topic['title'],
			'posts'    => $topic['posts'],
			'forumid'  => $topic['forumid'],
		] );

		// Step 2: Get posts
		$posts = WPF()->post->get_posts( [ 'topicid' => $topicid, 'orderby' => 'created', 'order' => 'ASC' ] );
		if ( empty( $posts ) ) {
			$steps[] = $this->step_result( 'posts_load', false, 'No posts found' );
			return [ 'success' => false, 'steps' => $steps ];
		}
		$steps[] = $this->step_result( 'posts_load', true, 'Posts loaded', [ 'count' => count( $posts ) ] );

		// Step 3: Analyze content (images, documents)
		$total_images = 0;
		$total_documents = 0;
		$posts_with_images = 0;
		$posts_with_docs = 0;
		$image_details = [];
		$doc_details = [];

		foreach ( $posts as $post ) {
			$body = $post['body'] ?? '';

			// Only extract if including
			$images = $include_images ? $ai_client->extract_post_images( $body ) : [];
			$documents = $include_docs ? $ai_client->extract_post_documents( $body ) : [];

			if ( ! empty( $images ) ) {
				$total_images += count( $images );
				$posts_with_images++;
				foreach ( $images as $img ) {
					$image_details[] = [
						'postid' => $post['postid'],
						'url'    => $img['url'] ?? $img,
						'type'   => pathinfo( $img['url'] ?? $img, PATHINFO_EXTENSION ),
					];
				}
			}

			if ( ! empty( $documents ) ) {
				$total_documents += count( $documents );
				$posts_with_docs++;
				foreach ( $documents as $doc ) {
					$doc_details[] = [
						'postid'   => $post['postid'],
						'url'      => $doc['url'] ?? $doc,
						'filename' => basename( $doc['url'] ?? $doc ),
						'type'     => pathinfo( $doc['url'] ?? $doc, PATHINFO_EXTENSION ),
					];
				}
			}
		}

		// Check plan capabilities
		$can_process_images = $ai_client->is_image_indexing_enabled();
		$can_process_docs = $ai_client->is_document_indexing_enabled();

		$analysis_message = 'Content analyzed';
		if ( ! $include_images && ! $include_docs ) {
			$analysis_message = 'Text-only indexing (images/docs disabled)';
		}

		$steps[] = $this->step_result( 'content_analysis', true, $analysis_message, [
			'images_found'       => $total_images,
			'documents_found'    => $total_documents,
			'include_images'     => $include_images ? 'Yes' : 'No',
			'include_docs'       => $include_docs ? 'Yes' : 'No',
			'plan_allows_images' => $can_process_images ? 'Yes' : 'No (requires Pro+)',
			'plan_allows_docs'   => $can_process_docs ? 'Yes' : 'No (requires Pro+)',
			'images'             => $include_images ? array_slice( $image_details, 0, 5 ) : [],
			'documents'          => $include_docs ? array_slice( $doc_details, 0, 5 ) : [],
		] );

		// Step 4: Run actual indexing
		$start_time = microtime( true );

		// For cloud mode, use ingest_topics() which calls /rag/ingest (same as wpForo admin)
		// Note: index_topic() calls /rag/queue which doesn't exist in the backend
		if ( $storage_mode === 'cloud' ) {
			// Use ingest_topics which is what wpForo admin uses
			$index_result = $ai_client->ingest_topics( [ $topicid ] );
			$index_time = round( ( microtime( true ) - $start_time ) * 1000 );

			if ( is_wp_error( $index_result ) ) {
				$steps[] = $this->step_result( 'indexing', false, $index_result->get_error_message(), [
					'mode'     => 'cloud',
					'endpoint' => '/rag/ingest',
				] );
				return [
					'success'      => false,
					'topicid'      => $topicid,
					'storage_mode' => $storage_mode,
					'steps'        => $steps,
				];
			}

			$indexed_count = $index_result['topics_processed'] ?? 1;
			$steps[] = $this->step_result( 'indexing', true, 'Topic sent to cloud for indexing', [
				'mode'            => 'cloud',
				'endpoint'        => '/rag/ingest',
				'topics_processed' => $indexed_count,
				'time_ms'         => $index_time,
			] );

			// Image/document processing
			if ( $total_images > 0 && $include_images && $can_process_images ) {
				$images_processed = $index_result['images_processed'] ?? 0;
				$steps[] = $this->step_result( 'image_processing', true, 'Images queued for async processing', [
					'found'  => $total_images,
					'status' => 'Processing happens asynchronously via image_worker Lambda',
				] );
			}

			if ( $total_documents > 0 && $include_docs && $can_process_docs ) {
				$docs_processed = $index_result['documents_processed'] ?? 0;
				$steps[] = $this->step_result( 'document_processing', true, 'Documents queued for processing', [
					'found'  => $total_documents,
					'status' => 'Processing happens asynchronously',
				] );
			}

			$steps[] = $this->step_result( 'storage', true, 'Vectors stored in S3', [
				'mode'     => $storage_mode,
				'location' => 'S3 Vectors (AWS)',
			] );

			return [
				'success'       => true,
				'topicid'       => $topicid,
				'topic_title'   => $topic['title'],
				'storage_mode'  => $storage_mode,
				'total_time_ms' => $index_time,
				'options_used'  => [
					'include_images' => $include_images ? 'Yes' : 'No',
					'include_docs'   => $include_docs ? 'Yes' : 'No',
				],
				'summary'       => [
					'topics_processed'   => $indexed_count,
					'images_found'       => $total_images,
					'documents_found'    => $total_documents,
				],
				'steps'         => $steps,
			];
		}

		// Local mode - immediate indexing
		$index_options = [ 'force' => true ];
		$index_result = WPF()->vector_storage->index_topic( $topicid, $index_options );
		$index_time = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $index_result ) ) {
			$steps[] = $this->step_result( 'indexing', false, $index_result->get_error_message() );
			return [
				'success'      => false,
				'topicid'      => $topicid,
				'storage_mode' => $storage_mode,
				'steps'        => $steps,
			];
		}

		$steps[] = $this->step_result( 'indexing', true, 'Indexing complete', [
			'indexed_count'   => $index_result['indexed_count'] ?? 0,
			'noise_filtered'  => $index_result['noise_filtered_count'] ?? 0,
			'total_posts'     => $index_result['total_posts'] ?? count( $posts ),
			'time_ms'         => $index_time,
		] );

		// Step 5: Image processing
		if ( $total_images > 0 || $include_images ) {
			$images_processed = $index_result['images_processed'] ?? 0;
			$img_message = 'No images found';
			$img_success = true;

			if ( $total_images > 0 ) {
				if ( ! $include_images ) {
					$img_message = 'Skipped (disabled in test options)';
					$img_success = false;
				} elseif ( ! $can_process_images ) {
					$img_message = 'Skipped (requires Professional+ plan)';
					$img_success = false;
				} elseif ( $images_processed > 0 ) {
					$img_message = 'Processed via Claude Vision';
					$img_success = true;
				} else {
					$img_message = 'Processing failed or async';
					$img_success = false;
				}
			}

			$steps[] = $this->step_result( 'image_processing', $img_success, $img_message, [
				'found'     => $total_images,
				'processed' => $images_processed,
				'enabled'   => $include_images ? 'Yes' : 'No',
			] );
		}

		// Step 6: Document processing
		if ( $total_documents > 0 || $include_docs ) {
			$docs_processed = $index_result['documents_processed'] ?? 0;
			$doc_message = 'No documents found';
			$doc_success = true;

			if ( $total_documents > 0 ) {
				if ( ! $include_docs ) {
					$doc_message = 'Skipped (disabled in test options)';
					$doc_success = false;
				} elseif ( ! $can_process_docs ) {
					$doc_message = 'Skipped (requires Professional+ plan)';
					$doc_success = false;
				} elseif ( $docs_processed > 0 ) {
					$doc_message = 'Processed via text extraction';
					$doc_success = true;
				} else {
					$doc_message = 'Processing failed or async';
					$doc_success = false;
				}
			}

			$steps[] = $this->step_result( 'document_processing', $doc_success, $doc_message, [
				'found'     => $total_documents,
				'processed' => $docs_processed,
				'enabled'   => $include_docs ? 'Yes' : 'No',
			] );
		}

		// Step 7: Storage
		$steps[] = $this->step_result( 'storage', true, 'Embeddings stored', [
			'mode'     => $storage_mode,
			'location' => $storage_mode === 'local' ? 'wp_wpforo_ai_embeddings' : 'S3 Vectors (AWS)',
		] );

		return [
			'success'       => true,
			'topicid'       => $topicid,
			'topic_title'   => $topic['title'],
			'storage_mode'  => $storage_mode,
			'total_time_ms' => $index_time,
			'options_used'  => [
				'include_images' => $include_images ? 'Yes' : 'No',
				'include_docs'   => $include_docs ? 'Yes' : 'No',
			],
			'summary'       => [
				'posts_indexed'       => $index_result['indexed_count'] ?? 0,
				'noise_filtered'      => $index_result['noise_filtered_count'] ?? 0,
				'images_processed'    => $index_result['images_processed'] ?? 0,
				'documents_processed' => $index_result['documents_processed'] ?? 0,
			],
			'steps'        => $steps,
		];
	}

	private function step_result( $step, $success, $message, $data = [] ) {
		return [
			'step'    => $step,
			'success' => $success,
			'message' => $message,
			'data'    => $data,
		];
	}

	private function test_translate( $params ) {
		$postid = intval( $params['postid'] ?? 0 );
		$language = sanitize_text_field( $params['language'] ?? 'Spanish' );

		if ( ! $postid ) {
			return [
				'success' => false,
				'error'   => 'Please select a post to translate',
			];
		}

		// Get post data
		$post = wpforo_post( $postid );
		if ( ! $post ) {
			return [
				'success' => false,
				'error'   => 'Post not found',
			];
		}

		// Get topic info
		$topic = wpforo_topic( $post['topicid'] );

		// Get original content
		$original_html = $post['body'];
		$original_text = wp_strip_all_tags( $original_html );
		$original_length = mb_strlen( $original_text );

		// Make API call
		$ai_client = WPF()->ai_client;
		$start_time = microtime( true );

		$response = $this->call_ai_endpoint( '/translate', [
			'content'         => $original_text,
			'target_language' => $language,
			'content_type'    => 'post',
		] );

		$request_time = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return [
				'success'  => false,
				'error'    => $response->get_error_message(),
				'postid'   => $postid,
				'language' => $language,
			];
		}

		// Extract translated content
		$translated_text = $response['translated_content'] ?? $response['translation'] ?? '';
		$detected_language = $response['detected_language'] ?? $response['source_language'] ?? 'unknown';
		$credits_used = $response['credits_used'] ?? 1;

		return [
			'success'     => true,
			'postid'      => $postid,
			'topicid'     => $post['topicid'],
			'topic_title' => $topic['title'] ?? '',
			'language'    => [
				'source' => $detected_language,
				'target' => $language,
			],
			'original'    => [
				'text'   => $original_text,
				'length' => $original_length,
				'words'  => str_word_count( $original_text ),
			],
			'translated'  => [
				'text'   => $translated_text,
				'length' => mb_strlen( $translated_text ),
				'words'  => str_word_count( $translated_text ),
			],
			'metrics'     => [
				'request_time_ms' => $request_time,
				'credits_used'    => $credits_used,
			],
			'api_response' => $response,
		];
	}

	private function test_summarize( $params ) {
		$topicid = intval( $params['topicid'] ?? 0 );
		$quality = sanitize_text_field( $params['quality'] ?? '' );
		$style = sanitize_text_field( $params['style'] ?? '' );

		// Use settings defaults if not provided
		$ai_settings = WPF()->settings->ai ?? [];
		if ( empty( $quality ) ) {
			$quality = $ai_settings['topic_summary_quality'] ?? 'balanced';
		}
		if ( empty( $style ) ) {
			$style = $ai_settings['topic_summary_style'] ?? 'detailed';
		}

		if ( ! $topicid ) {
			global $wpdb;
			$topicid = $wpdb->get_var( "SELECT topicid FROM {$wpdb->prefix}wpforo_topics WHERE posts > 3 ORDER BY RAND() LIMIT 1" );
		}

		$topic = wpforo_topic( $topicid );
		if ( ! $topic ) {
			return [
				'success' => false,
				'error'   => 'Topic not found',
			];
		}

		// Get posts for the topic
		$posts = WPF()->post->get_posts( [ 'topicid' => $topicid, 'row_count' => 50 ] );

		// Build posts array for API (matching TopicPost model)
		$posts_data = [];
		$is_first = true;
		foreach ( $posts as $post ) {
			$posts_data[] = [
				'post_id'       => (int) $post['postid'],
				'author'        => $post['name'] ?? 'Anonymous',
				'content'       => wp_strip_all_tags( $post['body'] ),
				'created_at'    => $post['created'] ?? '',
				'is_first_post' => $is_first,
			];
			$is_first = false;
		}

		$request_data = [
			'topic_id'    => $topicid,
			'topic_title' => $topic['title'],
			'posts'       => $posts_data,
			'quality'     => $quality,
			'style'       => $style,
		];

		$response = $this->call_ai_endpoint( '/summarize', $request_data );

		return [
			'success'  => ! is_wp_error( $response ),
			'topicid'  => $topicid,
			'title'    => $topic['title'],
			'posts'    => count( $posts ),
			'quality'  => $quality,
			'style'    => $style,
			'request'  => $request_data,
			'response' => is_wp_error( $response ) ? [ 'error' => $response->get_error_message() ] : $response,
		];
	}

	private function test_suggestions( $params ) {
		global $wpdb;

		$title = sanitize_text_field( $params['title'] ?? 'How to configure forum settings' );
		$quality = sanitize_text_field( $params['quality'] ?? '' );
		$similarity = floatval( $params['similarity'] ?? 0 );
		$include_answer = ! empty( $params['include_answer'] );

		// Use settings defaults if not provided
		$ai_settings = WPF()->settings->ai ?? [];
		if ( empty( $quality ) ) {
			$quality = $ai_settings['topic_suggestions_quality'] ?? 'balanced';
		}
		if ( $similarity <= 0 ) {
			$similarity = ( $ai_settings['topic_suggestions_similarity'] ?? 55 ) / 100;
		}

		$max_similar = $ai_settings['topic_suggestions_max_similar'] ?? 3;
		$max_related = $ai_settings['topic_suggestions_max_related'] ?? 3;

		// Check storage mode
		$board_id = WPF()->board->get_current( 'boardid' );
		$is_local_mode = WPF()->vector_storage && WPF()->vector_storage->is_local_mode( $board_id );

		$request_data = [
			'title'                => $title,
			'quality'              => $quality,
			'include_similar'      => true,
			'include_related'      => true,
			'include_answer'       => $include_answer,
			'max_similar'          => $max_similar,
			'max_related'          => $max_related,
			'similarity_threshold' => $similarity,
		];

		$start_time = microtime( true );
		$response = $this->call_ai_endpoint( '/suggestions/suggest', $request_data );
		$query_time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		$is_success = ! is_wp_error( $response );

		// Extract result data
		$total_similar = 0;
		$total_related = 0;
		$has_answer = false;
		$top_score = null;
		$credits_used = 0;

		if ( $is_success && is_array( $response ) ) {
			$similar = $response['similar_topics'] ?? [];
			$related = $response['related_topics'] ?? [];
			$total_similar = count( $similar );
			$total_related = count( $related );
			$has_answer = ! empty( $response['ai_insight'] ) || ! empty( $response['quick_answer'] );
			$credits_used = $response['credits_used'] ?? 0;

			if ( ! empty( $similar ) && isset( $similar[0]['score'] ) ) {
				$top_score = $similar[0]['score'];
			}
		}

		// Save to history table
		$settings = [
			'quality'    => $quality,
			'similarity' => $similarity,
			'include_answer' => $include_answer,
		];

		$wpdb->insert(
			$this->suggestion_table,
			[
				'query'                => $title,
				'quality'              => $quality,
				'similarity_threshold' => $similarity,
				'total_similar'        => $total_similar,
				'total_related'        => $total_related,
				'has_answer'           => $has_answer ? 1 : 0,
				'top_score'            => $top_score,
				'query_time_ms'        => $query_time_ms,
				'credits_used'         => $credits_used,
				'settings_json'        => json_encode( $settings ),
				'results_json'         => json_encode( $is_success ? $response : [ 'error' => $response->get_error_message() ] ),
				'created_at'           => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%f', '%d', '%d', '%d', '%f', '%d', '%d', '%s', '%s', '%s' ]
		);

		return [
			'success'       => $is_success,
			'title'         => $title,
			'quality'       => $quality,
			'similarity'    => $similarity,
			'storage_mode'  => $is_local_mode ? 'local' : 'cloud',
			'total_similar' => $total_similar,
			'total_related' => $total_related,
			'has_answer'    => $has_answer,
			'top_score'     => $top_score,
			'query_time_ms' => $query_time_ms,
			'credits_used'  => $credits_used,
			'request'       => $request_data,
			'response'      => $is_success ? $response : [ 'error' => $response->get_error_message() ],
		];
	}

	private function test_moderate( $params ) {
		global $wpdb;

		$test_type = sanitize_text_field( $params['test_type'] ?? 'spam' );
		$content = wp_kses_post( $params['content'] ?? '' );
		$quality = sanitize_text_field( $params['quality'] ?? '' );
		$sensitivity = sanitize_text_field( $params['sensitivity'] ?? 'medium' );

		if ( empty( $content ) ) {
			return [
				'success' => false,
				'error'   => 'Content is required',
			];
		}

		// Use settings defaults if not provided
		$ai_settings = WPF()->settings->ai ?? [];
		if ( empty( $quality ) ) {
			$quality = $ai_settings['moderation_spam_quality'] ?? 'balanced';
		}

		// Build forum context like wpForo does
		$board_id = WPF()->board->get_current( 'boardid' );
		$board = WPF()->board->get_board( $board_id );
		$site_name = get_bloginfo( 'name' );
		$site_description = get_bloginfo( 'description' );

		$forum_description = '';
		$context_parts = [];

		if ( $site_name || $site_description ) {
			$site_context = 'Website: ' . ( $site_name ?: 'Unknown' );
			if ( $site_description ) {
				$site_context .= ' - ' . $site_description;
			}
			$context_parts[] = $site_context;
		}

		if ( $board ) {
			$board_context = 'Forum: ' . ( $board['title'] ?? 'Community' );
			if ( ! empty( $board['description'] ) ) {
				$board_context .= ' - ' . $board['description'];
			}
			$context_parts[] = $board_context;
		}

		$forum_description = implode( '. ', $context_parts );

		// Build base request data (same structure for all moderation types)
		$request_data = [
			'content_type' => 'post',
			'title'        => 'E2E Test Post',
			'body'         => $content,
			'quality'      => $quality,
			'forum'        => [
				'id'          => $board_id,
				'title'       => $board['title'] ?? 'General',
				'description' => $forum_description,
				'slug'        => $board['slug'] ?? 'general',
			],
			'user'         => [
				'userid'            => get_current_user_id(),
				'display_name'      => wp_get_current_user()->display_name ?? 'Test User',
				'post_count'        => 0,
				'registration_days' => 0,
				'is_banned'         => false,
				'usergroup_id'      => 4,
			],
		];

		// Determine endpoint based on test type
		if ( $test_type === 'toxic' ) {
			// Use unified /moderation/analyze endpoint with toxicity enabled
			$request_data['spam'] = [ 'enabled' => false ];
			$request_data['toxicity'] = [
				'enabled'     => true,
				'sensitivity' => $sensitivity,
			];
			$request_data['compliance'] = [ 'enabled' => false ];
			$endpoint = '/moderation/analyze';
		} else {
			// Spam detection - use spam-only endpoint
			$request_data['use_forum_context']  = $ai_settings['moderation_spam_use_context'] ?? true;
			$request_data['min_indexed_topics'] = $ai_settings['moderation_spam_min_indexed'] ?? 100;
			$request_data['board_id']           = $board_id;
			$endpoint = '/moderation/spam/detect';
		}

		$start_time = microtime( true );
		$response = $this->call_ai_endpoint( $endpoint, $request_data );
		$query_time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		$is_success = ! is_wp_error( $response );

		// Extract result data based on test type
		$spam_score = null;
		$is_spam = null;
		$confidence = null;
		$indicators = [];
		$analysis_summary = '';
		$credits_used = 0;

		if ( $is_success && is_array( $response ) ) {
			if ( $test_type === 'toxic' ) {
				// Unified endpoint returns nested toxicity object
				$toxicity = $response['toxicity'] ?? [];
				$spam_score = $toxicity['score'] ?? null;
				$is_spam = $toxicity['is_toxic'] ?? null;
				$indicators = $toxicity['categories'] ?? [];
				$confidence = $toxicity['confidence'] ?? null;
				$analysis_summary = $toxicity['summary'] ?? '';
			} else {
				$spam_score = $response['spam_score'] ?? null;
				$is_spam = $response['is_spam'] ?? null;
				$indicators = $response['indicators'] ?? [];
				$confidence = $response['confidence'] ?? null;
				$analysis_summary = $response['analysis_summary'] ?? '';
			}
			$credits_used = $response['credits_used'] ?? 0;
		}

		// Save to history table
		$settings = [
			'quality'     => $quality,
			'test_type'   => $test_type,
			'sensitivity' => $sensitivity,
		];

		$wpdb->insert(
			$this->moderation_table,
			[
				'test_type'        => $test_type,
				'content'          => wp_trim_words( $content, 50 ),
				'quality'          => $quality,
				'spam_score'       => $spam_score,
				'is_spam'          => $is_spam ? 1 : 0,
				'confidence'       => $confidence,
				'indicators'       => json_encode( $indicators ),
				'analysis_summary' => $analysis_summary,
				'query_time_ms'    => $query_time_ms,
				'credits_used'     => $credits_used,
				'settings_json'    => json_encode( $settings ),
				'results_json'     => json_encode( $is_success ? $response : [ 'error' => $response->get_error_message() ] ),
				'created_at'       => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
		);

		$result = [
			'success'          => $is_success,
			'test_type'        => $test_type,
			'content_preview'  => wp_trim_words( $content, 20 ),
			'quality'          => $quality,
			'confidence'       => $confidence,
			'analysis_summary' => $analysis_summary,
			'query_time_ms'    => $query_time_ms,
			'credits_used'     => $credits_used,
			'request'          => $request_data,
			'response'         => $is_success ? $response : [ 'error' => $response->get_error_message() ],
		];

		if ( $test_type === 'toxic' ) {
			$result['toxicity_score'] = $spam_score;
			$result['is_toxic'] = $is_spam;
			$result['categories'] = $indicators;
			$result['sensitivity'] = $sensitivity;
		} else {
			$result['spam_score'] = $spam_score;
			$result['is_spam'] = $is_spam;
			$result['indicators'] = $indicators;
		}

		return $result;
	}

	private function test_chat( $params ) {
		$message = sanitize_text_field( $params['message'] ?? 'Hello, how can I get help with the forum?' );

		$response = $this->call_ai_endpoint( '/chat/message', [
			'message' => $message,
		] );

		return [
			'success' => ! is_wp_error( $response ),
			'message' => $message,
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_rag_status() {
		$response = $this->call_ai_endpoint( '/rag/status', [], 'GET' );

		return [
			'success' => ! is_wp_error( $response ),
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_analytics() {
		$response = $this->call_ai_endpoint( '/analytics', [
			'period' => 'month',
		], 'GET' );

		return [
			'success' => ! is_wp_error( $response ),
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_analytics_insight( $params ) {
		global $wpdb;

		$insight_type = sanitize_text_field( $params['insight_type'] ?? 'sentiment' );
		$limit = intval( $params['limit'] ?? 50 );

		$posts_table = $wpdb->prefix . 'wpforo_posts';
		$topics_table = $wpdb->prefix . 'wpforo_topics';
		$members_table = $wpdb->prefix . 'wpforo_profiles';

		// Build content based on insight type - each type expects different format
		$content = [];

		if ( $insight_type === 'sentiment' ) {
			// sentiment: List of posts with 'text' field
			$posts = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.body FROM {$posts_table} p WHERE p.status = 0 ORDER BY p.created DESC LIMIT %d",
				$limit
			) );
			foreach ( $posts as $post ) {
				$content[] = [ 'text' => wp_strip_all_tags( $post->body ) ];
			}

		} elseif ( $insight_type === 'trending' ) {
			// trending: List of topics with title, posts, views
			$topics = $wpdb->get_results( $wpdb->prepare(
				"SELECT t.title, t.posts, t.views FROM {$topics_table} t WHERE t.status = 0 ORDER BY t.created DESC LIMIT %d",
				$limit
			) );
			foreach ( $topics as $topic ) {
				$content[] = [
					'title' => $topic->title,
					'posts' => (int) $topic->posts,
					'views' => (int) $topic->views,
				];
			}

		} elseif ( $insight_type === 'recommendations' ) {
			// recommendations: Dict with 'stats' and 'recent_topics'
			$stats = [
				'total_topics'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$topics_table} WHERE status = 0" ),
				'total_posts'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table} WHERE status = 0" ),
				'unanswered_topics' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$topics_table} WHERE status = 0 AND posts = 1" ),
				'active_users_week' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT userid) FROM {$posts_table} WHERE created > DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
				'avg_response_hours'=> 'unknown',
			];
			$recent_topics = $wpdb->get_col( $wpdb->prepare(
				"SELECT title FROM {$topics_table} WHERE status = 0 ORDER BY created DESC LIMIT %d",
				$limit
			) );
			$content = [
				'stats'         => $stats,
				'recent_topics' => $recent_topics,
			];

		} elseif ( $insight_type === 'deep_analysis' ) {
			// deep_analysis: Dict with posts, user_stats, content_metrics, reply_stats
			$posts = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.body, p.created as date, p.userid, t.title as topic
				 FROM {$posts_table} p
				 LEFT JOIN {$topics_table} t ON p.topicid = t.topicid
				 WHERE p.status = 0 ORDER BY p.created DESC LIMIT %d",
				$limit
			) );
			$posts_formatted = [];
			foreach ( $posts as $post ) {
				$posts_formatted[] = [
					'text'     => wp_strip_all_tags( $post->body ),
					'topic'    => $post->topic ?? '',
					'username' => 'User ' . $post->userid,
					'date'     => $post->date,
				];
			}
			$user_stats = $wpdb->get_results(
				"SELECT userid, posts as post_count FROM {$members_table} ORDER BY posts DESC LIMIT 20"
			);
			$content_metrics = [
				'avg_post_length'  => (int) $wpdb->get_var( "SELECT AVG(LENGTH(body)) FROM {$posts_table} WHERE status = 0" ),
				'avg_topic_length' => (int) $wpdb->get_var( "SELECT AVG(LENGTH(body)) FROM {$posts_table} WHERE status = 0 AND is_first_post = 1" ),
			];
			$reply_stats = [
				'total_posts'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table} WHERE status = 0" ),
				'total_replies' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$posts_table} WHERE status = 0 AND is_first_post = 0" ),
				'unique_users'  => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT userid) FROM {$posts_table} WHERE status = 0" ),
			];
			$content = [
				'posts'           => $posts_formatted,
				'user_stats'      => $user_stats,
				'content_metrics' => $content_metrics,
				'reply_stats'     => $reply_stats,
			];

		} elseif ( $insight_type === 'sentiment_trend' ) {
			// sentiment_trend: List with 'text', 'timestamp', 'author'
			$posts = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.body, p.created, p.userid
				 FROM {$posts_table} p
				 WHERE p.status = 0 ORDER BY p.created DESC LIMIT %d",
				$limit
			) );
			foreach ( $posts as $post ) {
				$content[] = [
					'text'      => wp_strip_all_tags( $post->body ),
					'timestamp' => $post->created,
					'author'    => 'User ' . $post->userid,
				];
			}
		}

		if ( empty( $content ) ) {
			return [
				'success' => false,
				'error'   => 'No content found to analyze',
			];
		}

		$request_data = [
			'insight_type' => $insight_type,
			'content'      => $content,
		];

		$start_time = microtime( true );
		$response = $this->call_ai_endpoint( '/analytics/insights', $request_data );
		$query_time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		$is_success = ! is_wp_error( $response );

		$items_count = is_array( $content ) ? ( isset( $content[0] ) ? count( $content ) : 1 ) : 0;

		return [
			'success'       => $is_success,
			'insight_type'  => $insight_type,
			'items_count'   => $items_count,
			'query_time_ms' => $query_time_ms,
			'credits_used'  => $is_success ? ( $response['credits_used'] ?? 0 ) : 0,
			'result'        => $is_success ? $response : [ 'error' => $response->get_error_message() ],
		];
	}

	private function call_ai_endpoint( $endpoint, $data = [], $method = 'POST' ) {
		$api_key = WPF()->ai_client->get_stored_api_key();
		$base_url = defined( 'WPFORO_AI_API' ) ? WPFORO_AI_API : 'https://api.gvectors.com/v1';

		$url = rtrim( $base_url, '/' ) . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 60,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
		];

		if ( $method === 'POST' && ! empty( $data ) ) {
			$args['body'] = json_encode( $data );
		} elseif ( $method === 'GET' && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( $code >= 400 ) {
			$error_detail = $decoded['detail'] ?? $body;
			if ( is_array( $error_detail ) ) {
				$error_detail = json_encode( $error_detail, JSON_PRETTY_PRINT );
			}
			return new \WP_Error(
				'api_error',
				sprintf( 'API returned %d: %s', $code, $error_detail )
			);
		}

		return $decoded;
	}

	public function render_admin_page() {
		include WPFORO_E2E_PATH . 'templates/admin-page.php';
	}
}

// Initialize
add_action( 'plugins_loaded', function() {
	if ( function_exists( 'WPF' ) ) {
		WPForo_E2E_Tester::instance();
	}
} );
