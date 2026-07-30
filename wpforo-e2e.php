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

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'wp_ajax_wpforo_e2e_run_test', [ $this, 'ajax_run_test' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_topics', [ $this, 'ajax_get_topics' ] );
		add_action( 'wp_ajax_wpforo_e2e_get_tenant_info', [ $this, 'ajax_get_tenant_info' ] );
		add_action( 'wp_ajax_wpforo_e2e_switch_storage', [ $this, 'ajax_switch_storage' ] );
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
		if ( isset( WPF()->vector_storage ) ) {
			$storage_mode = WPF()->vector_storage->get_storage_mode( $board_id );
		}

		$info = [
			'connected'      => $ai_client->is_connected(),
			'tenant_id'      => $tenant_id,
			'api_key_masked' => $this->mask_api_key( $api_key ),
			'api_key_full'   => $api_key, // For debugging
			'storage_mode'   => $storage_mode,
			'board_id'       => $board_id,
			'api_base_url'   => defined( 'WPFORO_AI_API' ) ? WPFORO_AI_API : 'https://api.gvectors.com/v1',
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

	public function ajax_get_topics() {
		$this->verify_request();

		global $wpdb;

		$filter = sanitize_text_field( $_POST['filter'] ?? 'all' );
		$limit = intval( $_POST['limit'] ?? 20 );

		$topics = [];

		// Base query
		$sql = "SELECT t.topicid, t.title, t.posts, t.created, f.title as forum_title
		        FROM {$wpdb->prefix}wpforo_topics t
		        LEFT JOIN {$wpdb->prefix}wpforo_forums f ON t.forumid = f.forumid
		        WHERE t.status = 0 AND t.private = 0";

		// Filter for topics with attachments
		if ( $filter === 'with_attachments' ) {
			$sql .= " AND EXISTS (
				SELECT 1 FROM {$wpdb->prefix}wpforo_posts p
				WHERE p.topicid = t.topicid
				AND (p.body LIKE '%[attach]%' OR p.body LIKE '%<img%' OR p.body LIKE '%.pdf%' OR p.body LIKE '%.docx%')
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
				'attachments' => $attachments,
				'has_pdf'     => ! empty( array_filter( $attachments, fn( $a ) => $a['type'] === 'pdf' ) ),
				'has_image'   => ! empty( array_filter( $attachments, fn( $a ) => $a['type'] === 'image' ) ),
			];
		}

		wp_send_json_success( $topics );
	}

	private function get_topic_attachments( $topicid ) {
		global $wpdb;

		$attachments = [];

		// Get posts with attachments
		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT postid, body FROM {$wpdb->prefix}wpforo_posts WHERE topicid = %d",
			$topicid
		) );

		foreach ( $posts as $post ) {
			// Check for [attach] shortcodes
			if ( preg_match_all( '/\[attach\](\d+)\[\/attach\]/i', $post->body, $matches ) ) {
				foreach ( $matches[1] as $attach_id ) {
					$file = get_attached_file( $attach_id );
					if ( $file ) {
						$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
						$type = in_array( $ext, [ 'pdf', 'docx', 'doc', 'txt' ] ) ? 'pdf' :
						        ( in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ] ) ? 'image' : 'other' );
						$attachments[] = [
							'id'      => $attach_id,
							'file'    => basename( $file ),
							'type'    => $type,
							'postid'  => $post->postid,
						];
					}
				}
			}

			// Check for embedded images
			if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->body, $matches ) ) {
				foreach ( $matches[1] as $src ) {
					$attachments[] = [
						'id'      => 0,
						'file'    => basename( parse_url( $src, PHP_URL_PATH ) ),
						'type'    => 'image',
						'postid'  => $post->postid,
						'url'     => $src,
					];
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
		$query = sanitize_text_field( $params['query'] ?? 'test search query' );
		$limit = intval( $params['limit'] ?? 5 );

		$results = WPF()->ai_client->semantic_search( $query, $limit );

		if ( is_wp_error( $results ) ) {
			return [
				'success' => false,
				'error'   => $results->get_error_message(),
			];
		}

		return [
			'success'       => true,
			'query'         => $query,
			'results_count' => count( $results ),
			'results'       => $results,
		];
	}

	private function test_index_topic( $params ) {
		$topicid = intval( $params['topicid'] ?? 0 );

		if ( ! $topicid ) {
			return [
				'success' => false,
				'error'   => 'Topic ID required',
			];
		}

		$board_id = WPF()->board->get_current( 'boardid' );
		$storage_mode = get_option( 'wpforo_ai_storage_mode_' . $board_id, 'local' );

		// Queue the topic for indexing
		$result = WPF()->ai_client->queue_topic_for_indexing( $topicid, $board_id );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'error'   => $result->get_error_message(),
			];
		}

		return [
			'success'      => true,
			'topicid'      => $topicid,
			'storage_mode' => $storage_mode,
			'message'      => 'Topic queued for indexing',
			'result'       => $result,
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
