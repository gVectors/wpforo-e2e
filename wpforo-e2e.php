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

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'wpforo_ai_e2e_search_tests';

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_wpforo_e2e_run_test', [ $this, 'ajax_run_test' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_topics', [ $this, 'ajax_get_topics' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_tenant_info', [ $this, 'ajax_get_tenant_info' ] );
		add_action( 'wp_ajax_wpforo_e2e_switch_storage', [ $this, 'ajax_switch_storage' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_feature_info', [ $this, 'ajax_get_feature_info' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_search_history', [ $this, 'ajax_get_search_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_clear_search_history', [ $this, 'ajax_clear_search_history' ] );
		add_action( 'wp_ajax_wpforo_e2e_delete_search_test', [ $this, 'ajax_delete_search_test' ] );

		// Create table on init if not exists
		$this->maybe_create_table();
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
		$ai_settings = WPF()->settings->ai ?? [];
		return [
			'settings' => [
				'translation_enabled' => $ai_settings['translation'] ?? false,
				'default_language'    => $ai_settings['translation_language'] ?? 'auto',
			],
		];
	}

	private function get_summarize_info() {
		$ai_settings = WPF()->settings->ai ?? [];
		return [
			'settings' => [
				'summary_enabled' => $ai_settings['topic_summary'] ?? false,
				'summary_quality' => $ai_settings['summary_quality'] ?? 'advanced',
				'summary_style'   => $ai_settings['summary_style'] ?? 'detailed',
			],
		];
	}

	private function get_suggestions_info() {
		$ai_settings = WPF()->settings->ai ?? [];
		return [
			'settings' => [
				'suggestions_enabled' => $ai_settings['topic_suggestions'] ?? false,
				'suggestions_count'   => $ai_settings['topic_suggestions_count'] ?? 3,
			],
		];
	}

	private function get_moderate_info() {
		$ai_settings = WPF()->settings->ai ?? [];
		return [
			'settings' => [
				'moderation_enabled' => $ai_settings['content_moderation'] ?? false,
				'auto_moderate'      => $ai_settings['auto_moderate'] ?? false,
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

		$topics = [];

		// Base query
		$sql = "SELECT t.topicid, t.title, t.posts, t.created, f.title as forum_title
		        FROM {$wpdb->prefix}wpforo_topics t
		        LEFT JOIN {$wpdb->prefix}wpforo_forums f ON t.forumid = f.forumid
		        WHERE t.status = 0 AND t.private = 0";

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

		// For cloud mode, wpForo queues topics for background cron processing
		// For local mode, we can do immediate indexing
		if ( $storage_mode === 'cloud' ) {
			// Debug: Check API key format
			$api_key = WPF()->ai_client->get_stored_api_key();
			$api_key_info = [
				'length'     => strlen( $api_key ),
				'prefix'     => substr( $api_key, 0, 6 ),
				'has_spaces' => strpos( $api_key, ' ' ) !== false,
				'has_newlines' => strpos( $api_key, "\n" ) !== false || strpos( $api_key, "\r" ) !== false,
			];

			// Use the same approach as wpForo admin - queue for cron
			$index_result = WPF()->vector_storage->index_topic( $topicid, [ 'force' => true ] );
			$index_time = round( ( microtime( true ) - $start_time ) * 1000 );

			if ( is_wp_error( $index_result ) ) {
				$steps[] = $this->step_result( 'indexing', false, $index_result->get_error_message(), [
					'mode'        => 'cloud',
					'note'        => 'Cloud indexing queues topics for background processing',
					'api_key_debug' => $api_key_info,
				] );
				return [
					'success'      => false,
					'topicid'      => $topicid,
					'storage_mode' => $storage_mode,
					'steps'        => $steps,
				];
			}

			$steps[] = $this->step_result( 'indexing', true, 'Topic queued for cloud indexing', [
				'mode'     => 'cloud',
				'note'     => 'Actual indexing happens via WP-Cron background process',
				'time_ms'  => $index_time,
			] );

			// For cloud mode, skip image/doc processing steps since they happen in background
			$steps[] = $this->step_result( 'storage', true, 'Queued for S3 Vectors', [
				'mode'     => $storage_mode,
				'location' => 'S3 Vectors (AWS) - via background cron',
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
					'queued'         => true,
					'posts_to_index' => count( $posts ),
					'images_found'   => $total_images,
					'documents_found' => $total_documents,
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
			// Get a random post
			global $wpdb;
			$postid = $wpdb->get_var( "SELECT postid FROM {$wpdb->prefix}wpforo_posts ORDER BY RAND() LIMIT 1" );
		}

		// Use reflection to access private translate method or call via AJAX simulation
		$post = wpforo_post( $postid );
		if ( ! $post ) {
			return [
				'success' => false,
				'error'   => 'Post not found',
			];
		}

		// Make API call directly
		$ai_client = WPF()->ai_client;
		$response = $this->call_ai_endpoint( '/translate', [
			'content'         => wp_strip_all_tags( $post['body'] ),
			'target_language' => $language,
			'content_type'    => 'post',
		] );

		return [
			'success'  => ! is_wp_error( $response ),
			'postid'   => $postid,
			'language' => $language,
			'original' => wp_trim_words( $post['body'], 30 ),
			'result'   => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_summarize( $params ) {
		$topicid = intval( $params['topicid'] ?? 0 );

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
		$posts = WPF()->post->get_posts( [ 'topicid' => $topicid, 'row_count' => 20 ] );

		$content = '';
		foreach ( $posts as $post ) {
			$content .= wp_strip_all_tags( $post['body'] ) . "\n\n";
		}

		$response = $this->call_ai_endpoint( '/summarizing/summarize', [
			'content'      => $content,
			'topic_title'  => $topic['title'],
			'reply_count'  => count( $posts ) - 1,
			'quality'      => 'advanced',
			'style'        => 'detailed',
		] );

		return [
			'success' => ! is_wp_error( $response ),
			'topicid' => $topicid,
			'title'   => $topic['title'],
			'posts'   => count( $posts ),
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_suggestions( $params ) {
		$title = sanitize_text_field( $params['title'] ?? 'How to configure forum settings' );

		$response = $this->call_ai_endpoint( '/suggestions/suggest', [
			'title'   => $title,
			'forumid' => 1,
		] );

		return [
			'success' => ! is_wp_error( $response ),
			'title'   => $title,
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
	}

	private function test_moderate( $params ) {
		$content = sanitize_textarea_field( $params['content'] ?? 'This is a test message for moderation check.' );

		$response = $this->call_ai_endpoint( '/moderation/check', [
			'content' => $content,
			'type'    => 'post',
		] );

		return [
			'success' => ! is_wp_error( $response ),
			'content' => wp_trim_words( $content, 20 ),
			'result'  => is_wp_error( $response ) ? $response->get_error_message() : $response,
		];
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

	private function call_ai_endpoint( $endpoint, $data = [], $method = 'POST' ) {
		$api_key = WPF()->ai_client->get_api_key();
		$base_url = defined( 'WPFORO_AI_API' ) ? WPFORO_AI_API : 'https://api.gvectors.com/v1';

		$url = rtrim( $base_url, '/' ) . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => 30,
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
			return new \WP_Error(
				'api_error',
				sprintf( 'API returned %d: %s', $code, $decoded['detail'] ?? $body )
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
