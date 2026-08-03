(function($) {
	'use strict';

	var tenantInfo = {};
	var apiKeyFull = '';

	function init() {
		initTabs();
		loadTenantInfo();
		bindEvents();
	}

	function initTabs() {
		// Restore tab from URL hash
		var hash = window.location.hash.replace('#', '');
		if (hash && $('.e2e-tab-btn[data-tab="' + hash + '"]').length) {
			switchTab(hash);
		} else {
			// Load info tab by default
			loadFeatureInfo('info');
		}

		// Tab click handler
		$('.e2e-tab-btn').on('click', function() {
			var tab = $(this).data('tab');
			switchTab(tab);
			window.location.hash = tab;
		});
	}

	function switchTab(tab) {
		$('.e2e-tab-btn').removeClass('active');
		$('.e2e-tab-btn[data-tab="' + tab + '"]').addClass('active');
		$('.e2e-tab-content').removeClass('active');
		$('.e2e-tab-content[data-tab="' + tab + '"]').addClass('active');

		// Load feature info for this tab
		loadFeatureInfo(tab);

		// Load search history when search tab is shown
		if (tab === 'search') {
			loadSearchHistory();
		}

		// Load topics when index tab is shown
		if (tab === 'index' && !$('#topic-select').data('loaded')) {
			loadTopics();
		}

		// Load posts when translate tab is shown
		if (tab === 'translate' && !$('#translate-post-select').data('loaded')) {
			loadTranslatePosts();
		}

		// Load topics when summarize tab is shown
		if (tab === 'summarize' && !$('#summarize-topic-select').data('loaded')) {
			loadSummarizeTopics();
		}

		// Load suggestion history when suggestions tab is shown
		if (tab === 'suggestions') {
			loadSuggestionHistory();
		}

		// Load moderate tab content
		if (tab === 'moderate') {
			loadModerateInfo();
			loadSpamHistory();
		}
	}

	function loadFeatureInfo(tab) {
		var featureMap = {
			'search': 'search',
			'index': 'index',
			'translate': 'translate',
			'summarize': 'summarize',
			'suggestions': 'suggestions',
			'moderate': 'moderate',
			'chat': 'chat'
		};

		var feature = featureMap[tab];
		if (!feature) return;

		var $infoBox = $('#' + tab + '-info');
		if (!$infoBox.length || $infoBox.data('loaded')) return;

		$infoBox.html('<div class="e2e-info-loading">Loading...</div>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_feature_info',
				nonce: wpforoE2E.nonce,
				feature: feature
			},
			success: function(response) {
				if (response.success) {
					renderFeatureInfo(tab, response.data);
					$infoBox.data('loaded', true);
				} else {
					$infoBox.html('<div class="e2e-info-loading">Error: ' + (response.data.message || 'Failed') + '</div>');
				}
			},
			error: function() {
				$infoBox.html('<div class="e2e-info-loading">Connection error</div>');
			}
		});
	}

	function renderFeatureInfo(tab, data) {
		if (tab === 'search') {
			renderSearchInfo(data);
		} else if (tab === 'index') {
			renderIndexInfo(data);
		} else if (tab === 'translate') {
			renderTranslateInfo(data);
		} else if (tab === 'summarize') {
			renderSummarizeInfo(data);
		} else if (tab === 'suggestions') {
			renderSuggestionsInfo(data);
		}
	}

	function renderTranslateInfo(data) {
		var settings = data.settings || {};
		var stats = data.stats || {};
		var connection = data.connection || {};
		var api = data.api || {};

		var html = '<h4>Translation Settings</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Translation Enabled', settings.translation_enabled ? 'Yes' : 'No', settings.translation_enabled ? 'success' : 'error');
		html += infoItem('Default Language', settings.default_language || 'Auto');
		html += infoItem('Quality', settings.translation_quality || 'standard');
		html += infoItem('Auto-detect', settings.auto_detect_language ? 'Yes' : 'No');
		html += '</div>';

		html += '<h4>Status</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('API Connected', connection.connected ? 'Yes' : 'No', connection.connected ? 'success' : 'error');
		if (connection.credits) {
			html += infoItem('Credits Remaining', connection.credits.remaining || 0);
		}
		html += infoItem('Total Posts', stats.total_posts || 0);
		html += infoItem('Available Languages', stats.available_languages || 0);
		html += '</div>';

		html += '<h4>API Info</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Endpoint', api.endpoint || '/translate');
		html += infoItem('Method', api.method || 'POST');
		html += infoItem('Cost', api.cost || '1 credit');
		html += '</div>';

		$('#translate-info').html(html);
	}

	function renderSummarizeInfo(data) {
		var settings = data.settings || {};
		var stats = data.stats || {};
		var connection = data.connection || {};
		var api = data.api || {};
		var options = data.options || {};

		var html = '<h4>Summarization Settings</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Summary Enabled', settings.summary_enabled ? 'Yes' : 'No', settings.summary_enabled ? 'success' : 'error');
		html += infoItem('Quality', settings.summary_quality || 'balanced');
		html += infoItem('Style', settings.summary_style || 'detailed');
		html += infoItem('Min Replies', settings.min_replies || 1);
		html += infoItem('Language', settings.summary_language || 'Auto');
		html += '</div>';

		html += '<h4>Status</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('API Connected', connection.connected ? 'Yes' : 'No', connection.connected ? 'success' : 'error');
		if (connection.credits) {
			html += infoItem('Credits Remaining', connection.credits.remaining || 0);
		}
		html += infoItem('Total Topics', stats.total_topics || 0);
		html += infoItem('With Min Replies', stats.topics_with_replies || 0);
		html += '</div>';

		html += '<h4>API Info</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Endpoint', api.endpoint || '/summarize');
		html += infoItem('Method', api.method || 'POST');
		html += infoItem('Cost', api.cost || '1-4 credits');
		html += '</div>';

		$('#summarize-info').html(html);

		// Set default values in form from settings
		if (settings.summary_quality) {
			$('#summarize-quality').val(settings.summary_quality);
		}
		if (settings.summary_style) {
			$('#summarize-style').val(settings.summary_style);
		}
	}

	function loadSummarizeTopics() {
		var $select = $('#summarize-topic-select');
		var $btn = $('#refresh-summarize-topics');

		$btn.addClass('is-loading');
		$select.html('<option value="">Loading topics...</option>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_topics',
				nonce: wpforoE2E.nonce,
				limit: 50,
				min_replies: 1
			},
			success: function(response) {
				$btn.removeClass('is-loading');
				$select.data('loaded', true);
				$select.empty().append('<option value="">Select a topic...</option>');

				if (response.success && response.data.length) {
					response.data.forEach(function(topic) {
						var label = '#' + topic.topicid + ' - ' + topic.title + ' (' + topic.posts + ' posts)';
						$('<option></option>')
							.val(topic.topicid)
							.text(label)
							.data('topic', topic)
							.appendTo($select);
					});
					$select.append('<option disabled>───────────────</option>');
					$select.append('<option disabled>' + response.data.length + ' topics loaded</option>');
				} else {
					$select.append('<option disabled>No topics with replies found</option>');
				}
			},
			error: function() {
				$btn.removeClass('is-loading');
				$select.html('<option value="">Error loading topics</option>');
			}
		});
	}

	function renderSuggestionsInfo(data) {
		var settings = data.settings || {};
		var stats = data.stats || {};
		var connection = data.connection || {};
		var api = data.api || {};
		var examples = data.examples || [];

		var html = '<h4>Suggestions Settings</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Suggestions Enabled', settings.suggestions_enabled ? 'Yes' : 'No', settings.suggestions_enabled ? 'success' : 'error');
		html += infoItem('Quality', settings.quality || 'balanced');
		html += infoItem('Min Words', settings.min_words || 3);
		html += infoItem('Similarity', (settings.similarity || 55) + '%');
		html += infoItem('Max Similar', settings.max_similar || 3);
		html += infoItem('Max Related', settings.max_related || 3);
		html += infoItem('Show Related', settings.show_related ? 'Yes' : 'No');
		html += infoItem('Show AI Answer', settings.show_answer ? 'Yes' : 'No');
		html += '</div>';

		html += '<h4>Status</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('API Connected', connection.connected ? 'Yes' : 'No', connection.connected ? 'success' : 'error');
		if (connection.credits) {
			html += infoItem('Credits Remaining', connection.credits.remaining || 0);
		}
		html += infoItem('Total Topics', stats.total_topics || 0);
		html += infoItem('Storage Mode', stats.storage_mode || 'local');
		html += '</div>';

		html += '<h4>API Info</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Endpoint', api.endpoint || '/suggestions/suggest');
		html += infoItem('Method', api.method || 'POST');
		html += infoItem('Cost', api.cost || '1-4 credits');
		html += '</div>';

		$('#suggestions-info').html(html);

		// Set default values in form from settings
		if (settings.quality) {
			$('#suggestion-quality').val(settings.quality);
		}
		if (settings.similarity) {
			var simVal = (settings.similarity / 100).toFixed(2);
			$('#suggestion-similarity').val(simVal);
		}
		$('#suggestion-include-answer').prop('checked', settings.show_answer !== false);

		// Render example buttons
		if (examples.length > 0) {
			var examplesHtml = '';
			examples.forEach(function(ex) {
				examplesHtml += '<button type="button" class="e2e-example-btn" data-title="' + escapeHtml(ex) + '">' + escapeHtml(ex.substring(0, 40)) + (ex.length > 40 ? '...' : '') + '</button>';
			});
			$('#suggestion-examples').html(examplesHtml);
		} else {
			$('#suggestion-examples').html('<span class="e2e-example-loading">No examples available</span>');
		}
	}

	function loadTranslatePosts() {
		var $select = $('#translate-post-select');
		var $btn = $('#refresh-translate-posts');

		$btn.addClass('is-loading');
		$select.html('<option value="">Loading posts...</option>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_posts',
				nonce: wpforoE2E.nonce,
				limit: 50
			},
			success: function(response) {
				$btn.removeClass('is-loading');
				$select.data('loaded', true);
				$select.empty().append('<option value="">Select a post...</option>');

				if (response.success && response.data.length) {
					response.data.forEach(function(post) {
						var label = '#' + post.postid + ' - ' + post.preview;
						$('<option></option>')
							.val(post.postid)
							.text(label)
							.data('post', post)
							.appendTo($select);
					});
					$select.append('<option disabled>───────────────</option>');
					$select.append('<option disabled>' + response.data.length + ' posts loaded</option>');
				} else {
					$select.append('<option disabled>No posts found</option>');
				}
			},
			error: function() {
				$btn.removeClass('is-loading');
				$select.html('<option value="">Error loading posts</option>');
			}
		});
	}

	function renderIndexInfo(data) {
		var mode = data.storage_mode || 'local';
		var stats = data.indexing_stats || {};
		var localStats = data.local_stats || {};
		var cloudStats = data.cloud_stats || {};

		// Info section
		var html = '<h4>Current Storage: ' + mode.toUpperCase() + '</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Storage Mode', mode, mode === 'local' ? 'success' : 'warning');
		html += infoItem('Embedding Model', data.embedding_model || 'Titan v2');
		html += infoItem('Vector Dimensions', data.embedding_dims || 1024);
		html += '</div>';

		html += '<h4>Indexing Status</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Total Forum Topics', data.total_topics || 0);
		html += infoItem('Topics Indexed', stats.total_indexed || 0, 'success');
		html += infoItem('Queue Size', data.queue_size || 0, data.queue_size > 0 ? 'warning' : '');
		html += infoItem('Is Indexing', data.is_indexing ? 'Yes' : 'No', data.is_indexing ? 'warning' : '');
		if (data.is_indexing && data.indexing_progress > 0) {
			html += infoItem('Progress', data.indexing_progress + '%');
		}
		html += infoItem('Last Indexed', data.last_indexed || 'Never');
		html += '</div>';

		if (mode === 'local' && localStats.total_embeddings) {
			html += '<h4>Local Storage Details</h4>';
			html += '<div class="e2e-info-grid-sm">';
			html += infoItem('Total Embeddings', localStats.total_embeddings);
			html += infoItem('Topics with Embeddings', localStats.topics_indexed || 0);
			html += infoItem('Posts with Embeddings', localStats.posts_indexed || 0);
			html += infoItem('Storage Size', (localStats.storage_size_mb || 0) + ' MB');
			html += '</div>';
		}

		if (mode === 'cloud' && cloudStats.topics_indexed !== undefined) {
			html += '<h4>Cloud Storage Details</h4>';
			html += '<div class="e2e-info-grid-sm">';
			html += infoItem('Topics in S3 Vectors', cloudStats.topics_indexed);
			html += infoItem('Storage Location', 'AWS S3 Vectors');
			html += infoItem('Region', 'us-east-1');
			html += '</div>';
		}

		$('#index-info').html(html);

		// Render diagram
		renderIndexDiagram(mode);
	}

	function renderIndexDiagram(mode) {
		var html = '<h4>How ' + mode.charAt(0).toUpperCase() + mode.slice(1) + ' Indexing Works</h4>';

		// Text-only posts diagram
		html += '<div class="e2e-diagram">';
		html += '<div class="e2e-diagram-label">Text Posts</div>';
		html += '<div class="e2e-diagram-row">';
		html += '<div class="e2e-diagram-box">Topic/Post<br><small>text content</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box">Text Extraction<br><small>clean HTML</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box highlight">Bedrock API<br><small>Titan Embed v2</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		if (mode === 'local') {
			html += '<div class="e2e-diagram-box success">WordPress DB<br><small>ai_embeddings</small></div>';
		} else {
			html += '<div class="e2e-diagram-box success">S3 Vectors<br><small>AWS Cloud</small></div>';
		}
		html += '</div>';
		html += '</div>';

		// Posts with images diagram
		html += '<div class="e2e-diagram">';
		html += '<div class="e2e-diagram-label">Posts with Images</div>';
		html += '<div class="e2e-diagram-row">';
		html += '<div class="e2e-diagram-box">Post + Images<br><small>jpg, png, gif</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box highlight">Claude Vision<br><small>image analysis</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box">Text + Captions<br><small>combined</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box highlight">Titan Embed<br><small>1024 dims</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		if (mode === 'local') {
			html += '<div class="e2e-diagram-box success">WP DB</div>';
		} else {
			html += '<div class="e2e-diagram-box success">S3 Vectors</div>';
		}
		html += '</div>';
		html += '</div>';

		// Posts with documents diagram
		html += '<div class="e2e-diagram">';
		html += '<div class="e2e-diagram-label">Posts with Documents</div>';
		html += '<div class="e2e-diagram-row">';
		html += '<div class="e2e-diagram-box">Post + Docs<br><small>pdf, docx, txt</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box highlight">Text Extraction<br><small>Textract/parser</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box">Post + Doc Text<br><small>combined</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		html += '<div class="e2e-diagram-box highlight">Titan Embed<br><small>1024 dims</small></div>';
		html += '<div class="e2e-diagram-arrow">→</div>';
		if (mode === 'local') {
			html += '<div class="e2e-diagram-box success">WP DB</div>';
		} else {
			html += '<div class="e2e-diagram-box success">S3 Vectors</div>';
		}
		html += '</div>';
		html += '</div>';

		// Description
		html += '<div class="e2e-diagram-desc">';
		if (mode === 'local') {
			html += '<strong>Local Mode:</strong> All embeddings stored in WordPress database (wp_wpforo_ai_embeddings). ';
			html += 'Images are analyzed by Claude Vision to generate searchable captions. ';
			html += 'Documents (PDF, DOCX) are parsed to extract text content. ';
			html += 'Search uses cosine similarity against local vectors.';
		} else {
			html += '<strong>Cloud Mode:</strong> Content sent to gVectors API, processed by AWS Lambda. ';
			html += 'Images analyzed asynchronously by image_worker Lambda. ';
			html += 'Documents processed by Textract for text extraction. ';
			html += 'Vectors stored in S3 Vectors for fast k-NN search at scale.';
		}
		html += '</div>';

		$('#index-diagram').html(html);
	}

	function renderSearchInfo(data) {
		var db = data.database || {};
		var settings = data.settings || {};
		var suggestions = data.suggestions || [];

		var html = '<h4>Embeddings Table: wp_wpforo_ai_embeddings</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Total Embedding Rows', db.total_rows || 0);
		html += infoItem('Table Size', (db.table_size_mb || 0) + ' MB');
		html += infoItem('Last Updated', db.last_indexed || 'Never');
		html += '</div>';

		html += '<h4>Topics Indexing Status</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Forum Topics (public)', db.total_forum_topics || 0);
		html += infoItem('Topics Marked Indexed', db.topics_marked_indexed || 0, 'success');
		html += infoItem('Topics with Embeddings', db.topics_with_embeddings || 0);
		html += infoItem('Posts with Embeddings', db.posts_with_embeddings || 0);
		html += infoItem('Orphaned Embeddings', db.orphaned_topic_embeddings || 0, db.orphaned_topic_embeddings > 0 ? 'warning' : '');
		html += infoItem('Storage Mode', data.storage_mode || 'local', data.storage_mode === 'local' ? 'success' : '');
		html += '</div>';

		// Content types breakdown
		if (db.by_type && db.by_type.length) {
			html += '<h4>Indexed Content by Type</h4>';
			html += '<div class="e2e-info-grid-sm">';
			db.by_type.forEach(function(t) {
				var label = t.content_type === 'forum' ? 'Forum Posts' : t.content_type;
				html += infoItem(label, t.count + ' embeddings');
			});
			html += '</div>';
		}

		html += '<h4>Settings</h4>';
		html += '<div class="e2e-info-grid-sm">';
		html += infoItem('Re-ranking Model', settings.search_quality);
		html += infoItem('Min Score Threshold', settings.search_min_score + '%');
		html += infoItem('AI Summary', settings.search_enhance ? 'On' : 'Off', settings.search_enhance ? 'success' : '');
		html += infoItem('Summary Model', settings.search_enhance_quality);
		html += infoItem('Response Language', settings.search_language || 'Auto');
		html += infoItem('Max Results', settings.search_max_results);
		html += '</div>';

		$('#search-info').html(html);

		// Render suggestions from indexed content
		if (suggestions.length) {
			var sugHtml = '<div class="suggestion-label">Try searching (from indexed topics):</div><div>';
			suggestions.forEach(function(s) {
				if (s && s.phrase) {
					sugHtml += '<span class="e2e-suggestion-btn" data-query="' + escapeHtml(s.phrase) + '">' + escapeHtml(s.phrase) + '</span>';
				}
			});
			sugHtml += '</div>';
			$('#search-suggestions').html(sugHtml);

			// Bind click
			$('.e2e-suggestion-btn').off('click').on('click', function() {
				$('#search-query').val($(this).data('query'));
			});
		} else {
			$('#search-suggestions').html('<div class="suggestion-label" style="color:#d63638;">No indexed content found. Index some topics first.</div>');
		}
	}

	function infoItem(label, value, valueClass) {
		var cls = valueClass ? ' ' + valueClass : '';
		return '<div class="e2e-info-sm"><span class="label">' + label + '</span><span class="value' + cls + '">' + value + '</span></div>';
	}

	function escapeHtml(str) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function bindEvents() {
		$('#refresh-tenant-info').on('click', loadTenantInfo);
		$('#toggle-api-key').on('click', toggleApiKey);
		$('#save-storage-mode').on('click', saveStorageMode);
		$('#refresh-topics').on('click', loadTopics);
		$('#filter-with-images, #filter-with-docs').on('change', loadTopics);
		$('#refresh-translate-posts').on('click', function() {
			$('#translate-post-select').data('loaded', false);
			loadTranslatePosts();
		});
		$('#refresh-summarize-topics').on('click', function() {
			$('#summarize-topic-select').data('loaded', false);
			loadSummarizeTopics();
		});
		$('.e2e-run-test').on('click', runTest);
		$('#refresh-search-history').on('click', loadSearchHistory);
		$('#clear-search-history').on('click', clearSearchHistory);

		// Suggestion events
		$('#refresh-suggestion-history').on('click', loadSuggestionHistory);
		$('#clear-suggestion-history').on('click', clearSuggestionHistory);

		// Example suggestion buttons
		$(document).on('click', '.e2e-example-btn', function() {
			$('#suggestion-title').val($(this).data('title'));
		});

		// Delete suggestion test
		$(document).on('click', '.e2e-delete-suggestion', function() {
			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.ajax({
				url: wpforoE2E.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_e2e_delete_suggestion_test',
					nonce: wpforoE2E.nonce,
					id: id
				},
				success: function() {
					$row.fadeOut(300, function() { $(this).remove(); });
				}
			});
		});

		// View JSON popup
		$(document).on('click', '.e2e-view-json', function() {
			var json = $(this).data('json');
			showJsonModal(json);
		});

		// Moderation events - spam example buttons
		$(document).on('click', '.e2e-spam-example', function() {
			var content = $(this).data('content');
			$('#spam-content').val(content);
		});

		// Toxicity example buttons
		$(document).on('click', '.e2e-toxic-example', function() {
			var content = $(this).data('content');
			$('#toxic-content').val(content);
		});

		// Moderation history refresh
		$('#refresh-moderation-history').on('click', loadSpamHistory);

		// Delete moderation test
		$(document).on('click', '.e2e-delete-moderation', function() {
			var id = $(this).data('id');
			deleteModerationTest(id);
		});

		// Clear moderation history
		$(document).on('click', '.e2e-clear-history[data-type="moderation"]', function() {
			clearModerationHistory();
		});

		// Moderation history details
		$('#spam-history-body').on('click', '.e2e-expand-btn', function() {
			var json = $(this).data('json');
			showJsonModal(json);
		});

		// Analytics buttons
		$('.e2e-run-analytics').on('click', function() {
			var $btn = $(this);
			var insightType = $btn.data('type');
			var limit = $('#analytics-' + insightType + '-limit').val();
			var resultBoxId = 'result-analytics-' + insightType;

			$btn.addClass('is-loading');
			$('#' + resultBoxId).html('<div class="e2e-loading">Running analysis...</div>');

			$.ajax({
				url: wpforoE2E.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpforo_e2e_run_test',
					nonce: wpforoE2E.nonce,
					test_type: 'analytics_insight',
					params: {
						insight_type: insightType,
						limit: limit
					}
				},
				success: function(response) {
					$btn.removeClass('is-loading');
					renderAnalyticsResult(resultBoxId, insightType, response);
				},
				error: function(xhr, status, error) {
					$btn.removeClass('is-loading');
					$('#' + resultBoxId).html('<div class="e2e-result-meta error">Connection error: ' + error + '</div>');
				}
			});
		});
	}

	function renderAnalyticsResult(boxId, insightType, response) {
		var isSuccess = response.success && response.data && response.data.result;
		var statusClass = isSuccess ? 'success' : 'error';
		var statusText = isSuccess ? 'SUCCESS' : 'FAILED';

		var html = '<div class="e2e-result-meta">';
		html += '<span class="' + statusClass + '">' + statusText + '</span>';

		if (response.data && response.data.duration_ms) {
			html += ' | ' + response.data.duration_ms + 'ms';
		}
		if (response.data && response.data.result && response.data.result.credits_used) {
			html += ' | Credits: ' + response.data.result.credits_used;
		}
		html += '</div>';

		if (isSuccess) {
			var result = response.data.result;
			var data = result.data || {};

			// Human-readable summary based on insight type
			html += '<div style="padding:10px 15px;background:#2d3338;color:#fff;">';

			if (insightType === 'sentiment' && data.emotions) {
				html += '<strong>Dominant Emotion:</strong> ' + (data.dominant_emotion || '-') + '<br>';
				html += '<strong>Overall:</strong> ' + (data.overall_sentiment || '-') + '<br>';
				html += '<div style="margin-top:8px;"><strong>Emotions:</strong></div>';
				html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:5px;">';
				for (var emotion in data.emotions) {
					var val = data.emotions[emotion];
					html += '<span style="background:#374151;padding:3px 8px;border-radius:3px;">' + emotion + ': ' + val + '%</span>';
				}
				html += '</div>';
			} else if (insightType === 'trending' && data.topics) {
				html += '<strong>Trending Topics:</strong><br>';
				html += '<ul style="margin:5px 0 0 20px;padding:0;">';
				data.topics.forEach(function(topic) {
					html += '<li>' + escapeHtml(topic.name || topic) + (topic.score ? ' (' + topic.score + ')' : '') + '</li>';
				});
				html += '</ul>';
			} else if (insightType === 'recommendations' && data.recommendations) {
				html += '<strong>Recommendations:</strong><br>';
				html += '<ul style="margin:5px 0 0 20px;padding:0;">';
				data.recommendations.forEach(function(rec) {
					html += '<li>' + escapeHtml(typeof rec === 'string' ? rec : rec.title || rec.recommendation || JSON.stringify(rec)) + '</li>';
				});
				html += '</ul>';
			} else if (insightType === 'deep_analysis') {
				html += '<strong>Summary:</strong> ' + escapeHtml(data.summary || data.overview || '-') + '<br>';
				if (data.key_findings) {
					html += '<strong>Key Findings:</strong><br><ul style="margin:5px 0 0 20px;padding:0;">';
					data.key_findings.forEach(function(f) {
						html += '<li>' + escapeHtml(typeof f === 'string' ? f : f.finding || JSON.stringify(f)) + '</li>';
					});
					html += '</ul>';
				}
			} else if (insightType === 'sentiment_trend' && data.trend) {
				html += '<strong>Trend Direction:</strong> ' + (data.trend.direction || '-') + '<br>';
				html += '<strong>Change:</strong> ' + (data.trend.change || '-') + '<br>';
				if (data.periods) {
					html += '<strong>Periods:</strong><br><ul style="margin:5px 0 0 20px;padding:0;">';
					data.periods.forEach(function(p) {
						html += '<li>' + escapeHtml(p.period || p.label || '-') + ': ' + (p.sentiment || p.score || '-') + '</li>';
					});
					html += '</ul>';
				}
			} else {
				html += '<em>See JSON below for details</em>';
			}

			html += '</div>';
		}

		// JSON details
		html += '<details style="margin-top:0;"><summary style="padding:8px 15px;background:#1d2327;color:#3b82f6;cursor:pointer;">Show JSON</summary>';
		html += '<pre style="margin:0;padding:15px;">' + syntaxHighlight(JSON.stringify(response.data, null, 2)) + '</pre>';
		html += '</details>';

		$('#' + boxId).html(html);
	}

	function showJsonModal(json) {
		var formatted = syntaxHighlight(JSON.stringify(json, null, 2));
		var $modal = $('#e2e-json-modal');

		if (!$modal.length) {
			$('body').append(
				'<div id="e2e-json-modal" class="e2e-modal-overlay">' +
					'<div class="e2e-modal">' +
						'<div class="e2e-modal-header">' +
							'<span>JSON Response</span>' +
							'<button type="button" class="e2e-modal-close">&times;</button>' +
						'</div>' +
						'<div class="e2e-modal-body"><pre></pre></div>' +
					'</div>' +
				'</div>'
			);
			$modal = $('#e2e-json-modal');

			$modal.on('click', '.e2e-modal-close, .e2e-modal-overlay', function(e) {
				if (e.target === this) $modal.hide();
			});
		}

		$modal.find('pre').html(formatted);
		$modal.show();
	}

	function loadTenantInfo() {
		$('#tenant-loading').show();
		$('#tenant-info').hide();

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_tenant_info',
				nonce: wpforoE2E.nonce
			},
			success: function(response) {
				$('#tenant-loading').hide();
				$('#tenant-info').show();

				if (response.success) {
					tenantInfo = response.data;
					apiKeyFull = response.data.api_key_full || '';
					renderTenantInfo(response.data);
				} else {
					$('#tenant-info').html('<div class="e2e-status-error">Error: ' + (response.data.message || 'Failed to load') + '</div>');
				}
			},
			error: function() {
				$('#tenant-loading').hide();
				$('#tenant-info').show().html('<div class="e2e-status-error">Connection error</div>');
			}
		});
	}

	function renderTenantInfo(data) {
		var statusClass = data.connected ? 'e2e-status-active' : 'e2e-status-error';
		var statusText = data.connected ? 'Connected' : 'Disconnected';

		if (data.status === 'error') {
			statusClass = 'e2e-status-error';
			statusText = 'Error: ' + (data.error || 'Unknown');
		}

		$('#info-status').html('<span class="' + statusClass + '">' + statusText + '</span>');
		$('#info-tenant-id').text(data.tenant_id || '-');
		$('#info-api-key').text(data.api_key_masked || '-');
		$('#info-api-url').text(data.api_base_url || '-');
		$('#info-board-id').text(data.board_id);
		$('#storage-mode-select').val(data.storage_mode || 'local');

		if (data.subscription) {
			$('#info-plan').text(data.subscription.plan || 'free_trial');
			var subStatus = data.subscription.status || '-';
			var subStatusClass = subStatus === 'active' ? 'e2e-status-active' :
			                     subStatus === 'trial' ? 'e2e-status-pending' : 'e2e-status-error';
			$('#info-sub-status').html('<span class="' + subStatusClass + '">' + subStatus + '</span>');
		}

		if (data.credits) {
			$('#info-credits-remaining').text(data.credits.remaining);
			$('#info-credits-used').text(data.credits.used);
			$('#info-credits-total').text(data.credits.total);
		}

		if (data.features_enabled && data.features_enabled.length) {
			$('#info-features').text(data.features_enabled.join(', '));
		} else {
			$('#info-features').text('None');
		}

		if (data.indexing_stats) {
			var stats = data.indexing_stats;
			var statsText = 'Indexed: ' + (stats.total_indexed || 0) +
				' / Total: ' + (stats.total_topics || 0) +
				' | Mode: ' + (stats.storage_mode || 'unknown') +
				' | Size: ' + (stats.storage_size_mb || '0') + ' MB';
			if (stats.is_indexing) {
				statsText += ' | Progress: ' + (stats.indexing_progress || 0) + '%';
			}
			$('#info-indexing').text(statsText);
		}

		// Show warning if needed
		if (data.mode_warning) {
			$('#storage-mode-warning-text').text(data.mode_warning);
			$('#storage-mode-warning').show();
		} else {
			$('#storage-mode-warning').hide();
		}

		// Disable cloud option if not available
		if (!data.cloud_available) {
			$('#storage-mode-select option[value="cloud"]').prop('disabled', true).text('Cloud (Business+)');
		}
	}

	function toggleApiKey() {
		var $keySpan = $('#info-api-key');
		var $btn = $('#toggle-api-key');

		if ($btn.text() === 'Show') {
			$keySpan.html('<span class="api-key-full">' + apiKeyFull + '</span>');
			$btn.text('Hide');
		} else {
			$keySpan.text(tenantInfo.api_key_masked || '-');
			$btn.text('Show');
		}
	}

	function saveStorageMode() {
		var mode = $('#storage-mode-select').val();
		var $btn = $('#save-storage-mode');

		$btn.addClass('is-loading').text('...');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_switch_storage',
				nonce: wpforoE2E.nonce,
				storage_mode: mode
			},
			success: function(response) {
				$btn.removeClass('is-loading').text('Save');
				if (response.success) {
					loadTenantInfo(); // Refresh to show updated info
				} else {
					alert('Error: ' + (response.data.message || 'Failed'));
				}
			},
			error: function() {
				$btn.removeClass('is-loading').text('Save');
				alert('Connection error');
			}
		});
	}

	function loadTopics() {
		var $select = $('#topic-select');
		var $btn = $('#refresh-topics');
		var filterImages = $('#filter-with-images').is(':checked');
		var filterDocs = $('#filter-with-docs').is(':checked');
		var filter = 'all';
		if (filterImages && filterDocs) filter = 'with_attachments';
		else if (filterImages) filter = 'with_images';
		else if (filterDocs) filter = 'with_documents';

		$btn.addClass('is-loading');
		$select.html('<option value="">Loading topics...</option>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_topics',
				nonce: wpforoE2E.nonce,
				filter: filter,
				limit: 100
			},
			success: function(response) {
				$btn.removeClass('is-loading');
				$select.data('loaded', true);
				$select.empty().append('<option value="">Select a topic...</option>');

				if (response.success && response.data.length) {
					response.data.forEach(function(topic) {
						var title = topic.title.length > 50 ? topic.title.substring(0, 50) + '...' : topic.title;
						var badges = [];
						if (topic.has_image) badges.push('IMG');
						if (topic.has_pdf) badges.push('PDF');
						if (topic.has_doc) badges.push('DOC');
						var badgeStr = badges.length ? ' [' + badges.join(', ') + ']' : '';

						$('<option></option>')
							.val(topic.topicid)
							.text('#' + topic.topicid + ' - ' + title + badgeStr)
							.data('topic', topic)
							.appendTo($select);
					});
					$select.append('<option disabled>───────────────</option>');
					$select.append('<option disabled>' + response.data.length + ' topics loaded</option>');
				} else {
					$select.append('<option disabled>No topics found</option>');
				}
			},
			error: function() {
				$btn.removeClass('is-loading');
				$select.html('<option value="">Error loading topics</option>');
			}
		});
	}

	function runTest() {
		var $btn = $(this);
		var testType = $btn.data('test');
		var params = getTestParams(testType);
		var resultBoxId = getResultBoxId(testType);

		$btn.addClass('is-loading');
		$('#' + resultBoxId).html('<div class="e2e-loading">Running test...</div>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_run_test',
				nonce: wpforoE2E.nonce,
				test_type: testType,
				params: params
			},
			success: function(response) {
				$btn.removeClass('is-loading');
				renderResult(resultBoxId, response);
			},
			error: function(xhr, status, error) {
				$btn.removeClass('is-loading');
				renderResult(resultBoxId, {
					success: false,
					data: { message: 'Connection error: ' + error }
				});
			}
		});
	}

	function getTestParams(testType) {
		switch (testType) {
			case 'semantic_search':
				return {
					query: $('#search-query').val(),
					limit: $('#search-limit').val(),
					storage_mode: $('#search-storage-mode').val()
				};
			case 'index_topic':
				return {
					topicid: $('#topic-select').val(),
					include_images: $('#index-include-images').is(':checked') ? 1 : 0,
					include_docs: $('#index-include-docs').is(':checked') ? 1 : 0
				};
			case 'translate':
				return { postid: $('#translate-post-select').val(), language: $('#translate-language').val() };
			case 'summarize':
				return {
					topicid: $('#summarize-topic-select').val(),
					quality: $('#summarize-quality').val(),
					style: $('#summarize-style').val()
				};
			case 'suggestions':
				return {
					title: $('#suggestion-title').val(),
					quality: $('#suggestion-quality').val(),
					similarity: $('#suggestion-similarity').val(),
					include_answer: $('#suggestion-include-answer').is(':checked') ? 1 : 0
				};
			case 'moderate':
				return { content: $('#moderate-content').val() };
			case 'spam':
				return {
					test_type: 'spam',
					content: $('#spam-content').val(),
					quality: $('#spam-quality').val()
				};
			case 'toxic':
				return {
					test_type: 'toxic',
					content: $('#toxic-content').val(),
					quality: $('#toxic-quality').val(),
					sensitivity: $('#toxic-sensitivity').val()
				};
			case 'chat':
				return { message: $('#chat-message').val() };
			default:
				return {};
		}
	}

	function getResultBoxId(testType) {
		var map = {
			'semantic_search': 'result-search',
			'index_topic': 'result-index',
			'translate': 'result-translate',
			'summarize': 'result-summarize',
			'suggestions': 'result-suggestions',
			'moderate': 'result-moderate',
			'spam': 'result-spam',
			'toxic': 'result-toxic',
			'chat': 'result-chat',
			'rag_status': 'result-rag',
			'analytics': 'result-analytics'
		};
		return map[testType] || 'result-search';
	}

	function renderResult(boxId, response) {
		var isSuccess = response.success && response.data && response.data.result && response.data.result.success !== false;
		var statusClass = isSuccess ? 'success' : 'error';
		var statusText = isSuccess ? 'SUCCESS' : 'FAILED';
		var duration = response.data && response.data.duration_ms ? response.data.duration_ms + 'ms' : '-';
		var timestamp = response.data && response.data.timestamp ? response.data.timestamp : new Date().toISOString();

		var resultData = response.success ? response.data : response;

		// Special rendering for index results with steps
		if (boxId === 'result-index' && resultData.result && resultData.result.steps) {
			renderIndexResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			return;
		}

		// Special rendering for translate results
		if (boxId === 'result-translate' && resultData.result) {
			renderTranslateResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			return;
		}

		// Special rendering for summarize results
		if (boxId === 'result-summarize' && resultData.result) {
			renderSummarizeResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			return;
		}

		// Special rendering for suggestions results
		if (boxId === 'result-suggestions' && resultData.result) {
			renderSuggestionsResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			return;
		}

		// Special rendering for spam results
		if (boxId === 'result-spam' && resultData.result) {
			renderSpamResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			loadSpamHistory();
			return;
		}

		// Special rendering for toxic results
		if (boxId === 'result-toxic' && resultData.result) {
			renderToxicResult(boxId, resultData, statusClass, statusText, duration, timestamp);
			loadSpamHistory();
			return;
		}

		// Special rendering for RAG status
		if (boxId === 'result-rag' && resultData.result) {
			renderRagStatus(boxId, resultData, statusClass, statusText, duration, timestamp);
			return;
		}

		var formattedJson = syntaxHighlight(JSON.stringify(resultData, null, 2));

		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp +
		'</div>' +
		'<pre>' + formattedJson + '</pre>';

		$('#' + boxId).html(html);
	}

	function renderIndexResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;
		var steps = result.steps || [];

		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp +
		'</div>';

		// Summary header
		html += '<div class="e2e-index-summary">';
		html += '<div class="e2e-index-title">' + escapeHtml(result.topic_title || 'Topic #' + result.topicid) + '</div>';
		html += '<div class="e2e-index-meta">';
		html += '<span>Mode: <strong>' + (result.storage_mode || 'local') + '</strong></span>';
		html += '<span>Time: <strong>' + (result.total_time_ms || 0) + 'ms</strong></span>';
		if (result.summary) {
			html += '<span>Posts: <strong>' + result.summary.posts_indexed + '</strong></span>';
			html += '<span>Images: <strong>' + (result.summary.images_processed || 0) + '</strong></span>';
			html += '<span>Docs: <strong>' + (result.summary.documents_processed || 0) + '</strong></span>';
		}
		html += '</div>';
		if (result.options_used) {
			html += '<div class="e2e-index-options">';
			html += 'Options: Images=' + result.options_used.include_images + ', Docs=' + result.options_used.include_docs;
			html += '</div>';
		}
		html += '</div>';

		// Step-by-step flow
		html += '<div class="e2e-index-flow">';
		steps.forEach(function(step, idx) {
			var stepIcon = getStepIcon(step.step);
			var stepClass = step.success ? 'success' : 'error';

			html += '<div class="e2e-index-step ' + stepClass + '">';
			html += '<div class="e2e-step-icon">' + stepIcon + '</div>';
			html += '<div class="e2e-step-content">';
			html += '<div class="e2e-step-name">' + formatStepName(step.step) + '</div>';
			html += '<div class="e2e-step-message">' + escapeHtml(step.message) + '</div>';

			// Step data details
			if (step.data && Object.keys(step.data).length > 0) {
				html += '<div class="e2e-step-data">';
				for (var key in step.data) {
					var val = step.data[key];
					if (Array.isArray(val) && val.length > 0) {
						html += '<div class="e2e-step-detail"><span class="key">' + key + ':</span></div>';
						val.forEach(function(item) {
							if (typeof item === 'object') {
								var itemStr = item.filename || item.url || JSON.stringify(item);
								html += '<div class="e2e-step-subitem">' + escapeHtml(itemStr) + '</div>';
							}
						});
					} else if (typeof val !== 'object') {
						html += '<div class="e2e-step-detail"><span class="key">' + key + ':</span> ' + val + '</div>';
					}
				}
				html += '</div>';
			}

			html += '</div>'; // step-content
			html += '</div>'; // step

			// Arrow between steps
			if (idx < steps.length - 1) {
				html += '<div class="e2e-step-arrow">↓</div>';
			}
		});
		html += '</div>';

		// Raw JSON toggle
		html += '<div class="e2e-json-toggle">';
		html += '<button type="button" class="button button-small" onclick="jQuery(this).next().toggle(); jQuery(this).text(jQuery(this).next().is(\':visible\') ? \'Hide JSON\' : \'Show JSON\')">Show JSON</button>';
		html += '<pre style="display:none;">' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';
		html += '</div>';

		$('#' + boxId).html(html);
	}

	function getStepIcon(step) {
		var icons = {
			'topic_load': '📄',
			'posts_load': '📝',
			'content_analysis': '🔍',
			'indexing': '⚡',
			'image_processing': '🖼️',
			'document_processing': '📎',
			'storage': '💾'
		};
		return icons[step] || '▶';
	}

	function formatStepName(step) {
		var names = {
			'topic_load': 'Load Topic',
			'posts_load': 'Load Posts',
			'content_analysis': 'Analyze Content',
			'indexing': 'Generate Embeddings',
			'image_processing': 'Process Images',
			'document_processing': 'Process Documents',
			'storage': 'Store Vectors'
		};
		return names[step] || step;
	}

	function renderTranslateResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;

		// Show comparison view
		$('#translate-result-display').show();

		if (result.success && result.original && result.translated) {
			$('#translate-original').html(
				'<div class="e2e-translate-meta">' +
				'Language: ' + (result.language.source || 'auto') +
				' | Words: ' + result.original.words +
				' | Length: ' + result.original.length + ' chars' +
				'</div>' +
				'<div style="white-space: pre-wrap;">' + escapeHtml(result.original.text) + '</div>'
			);

			$('#translate-translated').html(
				'<div class="e2e-translate-meta">' +
				'Language: ' + result.language.target +
				' | Words: ' + result.translated.words +
				' | Length: ' + result.translated.length + ' chars' +
				'</div>' +
				'<div style="white-space: pre-wrap;">' + escapeHtml(result.translated.text) + '</div>'
			);
		} else {
			$('#translate-result-display').hide();
		}

		// Show JSON result
		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp;

		if (result.metrics) {
			html += ' | API: ' + result.metrics.request_time_ms + 'ms';
			html += ' | Credits: ' + result.metrics.credits_used;
		}
		html += '</div>';

		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';

		$('#' + boxId).html(html);
	}

	function renderSummarizeResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;

		// Show result display
		$('#summarize-result-display').show();

		if (result.success && result.response) {
			// Topic info
			var topicHtml = '';
			topicHtml += '<div class="detail-item"><span class="detail-label">Topic ID</span><span class="detail-value">#' + result.topicid + '</span></div>';
			topicHtml += '<div class="detail-item"><span class="detail-label">Title</span><span class="detail-value">' + escapeHtml(result.title || '') + '</span></div>';
			topicHtml += '<div class="detail-item"><span class="detail-label">Posts</span><span class="detail-value">' + (result.posts || 0) + '</span></div>';
			topicHtml += '<div class="detail-item"><span class="detail-label">Quality</span><span class="detail-value">' + (result.quality || 'balanced') + '</span></div>';
			topicHtml += '<div class="detail-item"><span class="detail-label">Style</span><span class="detail-value">' + (result.style || 'detailed') + '</span></div>';
			$('#summarize-topic-details').html(topicHtml);

			// Summary content - check various response structures
			var summaryText = '';
			if (result.response.summary) {
				summaryText = result.response.summary;
			} else if (result.response.content) {
				summaryText = result.response.content;
			} else if (typeof result.response === 'string') {
				summaryText = result.response;
			}

			// Convert markdown-like formatting to HTML
			var summaryHtml = formatSummaryContent(summaryText);
			$('#summarize-content').html(summaryHtml);
		} else {
			$('#summarize-result-display').hide();
		}

		// Show JSON result
		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp;

		if (result.response && result.response.credits_used) {
			html += ' | Credits: ' + result.response.credits_used;
		}
		html += '</div>';

		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';

		$('#' + boxId).html(html);
	}

	function renderSuggestionsResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;
		var response = result.response || {};

		// Show result display
		$('#suggestions-result-display').show();

		// Similar topics
		var similar = response.similar_topics || [];
		if (similar.length > 0) {
			$('#suggestions-similar-section').show();
			$('#suggestions-similar-count').text(similar.length);
			var similarHtml = '';
			similar.forEach(function(topic) {
				var score = topic.score || 0;
				var scoreClass = score >= 0.7 ? 'high' : (score >= 0.5 ? 'medium' : '');
				similarHtml += '<div class="e2e-suggestion-item">';
				similarHtml += '<div class="e2e-suggestion-score ' + scoreClass + '">' + (score * 100).toFixed(0) + '%</div>';
				similarHtml += '<div class="e2e-suggestion-content">';
				similarHtml += '<div class="e2e-suggestion-title">' + escapeHtml(topic.title || '') + '</div>';
				similarHtml += '<div class="e2e-suggestion-meta">ID: ' + topic.topic_id + ' | Posts: ' + (topic.posts || 0) + '</div>';
				similarHtml += '</div></div>';
			});
			$('#suggestions-similar-list').html(similarHtml);
		} else {
			$('#suggestions-similar-section').hide();
		}

		// Related topics (AI-generated, have reason instead of score)
		var related = response.related_topics || [];
		if (related.length > 0) {
			$('#suggestions-related-section').show();
			$('#suggestions-related-count').text(related.length);
			var relatedHtml = '';
			related.forEach(function(topic) {
				relatedHtml += '<div class="e2e-suggestion-item">';
				relatedHtml += '<div class="e2e-suggestion-content" style="flex:1;">';
				relatedHtml += '<div class="e2e-suggestion-title">' + escapeHtml(topic.title || '') + '</div>';
				relatedHtml += '<div class="e2e-suggestion-meta">' + escapeHtml(topic.reason || '') + '</div>';
				relatedHtml += '</div></div>';
			});
			$('#suggestions-related-list').html(relatedHtml);
		} else {
			$('#suggestions-related-section').hide();
		}

		// AI Answer
		var aiAnswer = response.ai_insight || response.quick_answer || '';
		if (aiAnswer) {
			$('#suggestions-answer-section').show();
			$('#suggestions-answer-content').html(aiAnswer);
		} else {
			$('#suggestions-answer-section').hide();
		}

		// Show JSON result
		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp;

		if (result.credits_used) {
			html += ' | Credits: ' + result.credits_used;
		}
		html += '</div>';

		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';

		$('#' + boxId).html(html);

		// Reload history after test
		loadSuggestionHistory();
	}

	function renderSpamResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;

		var isSpam = result.is_spam;
		var spamScore = result.spam_score || 0;
		var confidence = result.confidence || 0;
		var indicators = result.indicators || [];
		var analysis = result.analysis_summary || '';

		// Verdict badge
		var verdictClass = isSpam ? 'error' : 'success';
		var verdictText = isSpam ? 'SPAM DETECTED' : 'NOT SPAM';

		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp;

		if (result.credits_used) {
			html += ' | Credits: ' + result.credits_used;
		}
		html += '</div>';

		// Spam result summary
		html += '<div class="e2e-spam-result">';
		html += '<div class="e2e-spam-verdict ' + verdictClass + '">' + verdictText + '</div>';
		html += '<div class="e2e-spam-scores">';
		html += '<div class="e2e-spam-score-item"><span class="label">Spam Score:</span> <span class="value">' + spamScore + '</span></div>';
		html += '<div class="e2e-spam-score-item"><span class="label">Confidence:</span> <span class="value">' + (confidence * 100).toFixed(0) + '%</span></div>';
		html += '<div class="e2e-spam-score-item"><span class="label">Quality:</span> <span class="value">' + (result.quality || '-') + '</span></div>';
		html += '</div>';

		// Indicators
		if (indicators.length > 0) {
			html += '<div class="e2e-spam-indicators"><strong>Indicators:</strong> ' + escapeHtml(indicators.join(', ')) + '</div>';
		}

		// Analysis
		if (analysis) {
			html += '<div class="e2e-spam-analysis"><strong>Analysis:</strong> ' + escapeHtml(analysis) + '</div>';
		}

		html += '</div>';

		// Full JSON
		html += '<details><summary>Full Response JSON</summary>';
		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';
		html += '</details>';

		$('#' + boxId).html(html);
	}

	function renderRagStatus(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;

		// Show the status display
		$('#rag-status-display').show();

		// Indexing status
		var isIndexing = result.is_indexing;
		var statusHtml = isIndexing
			? '<span class="e2e-badge" style="background:#fef3c7;color:#92400e;">Indexing in Progress</span>'
			: '<span class="e2e-badge success">Idle</span>';
		$('#rag-is-indexing').html(statusHtml);

		// Total indexed
		$('#rag-total-indexed').text((result.total_indexed || 0) + ' topics');

		// Last indexed
		var lastIndexed = result.last_indexed_at;
		if (lastIndexed) {
			var d = new Date(lastIndexed);
			$('#rag-last-indexed').text(d.toLocaleString());
		} else {
			$('#rag-last-indexed').text('Never');
		}

		// Progress
		var progress = result.indexing_progress || 0;
		if (isIndexing) {
			$('#rag-progress').html('<strong>' + progress + '%</strong>');
		} else {
			$('#rag-progress').text('-');
		}

		// Queue info
		var queue = result.queue_info || {};
		$('#rag-queue-pending').text(queue.pending || 0);
		$('#rag-queue-processing').text(queue.processing || 0);
		var failedCount = queue.failed || 0;
		$('#rag-queue-failed').html(failedCount > 0
			? '<span style="color:#dc2626;">' + failedCount + '</span>'
			: '0');

		// Media progress
		var media = result.media_progress;
		if (media && media.total > 0) {
			$('#rag-media-section').show();
			$('#rag-media-total').text(media.total);
			$('#rag-media-done').text(media.done + ' (' + media.progress_percent + '%)');
			$('#rag-media-failed').html(media.failed > 0
				? '<span style="color:#dc2626;">' + media.failed + '</span>'
				: '0');
			$('#rag-media-skipped').text(media.skipped_cancelled || 0);
		} else {
			$('#rag-media-section').hide();
		}

		// Show raw JSON
		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp +
		'</div>';
		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';
		$('#' + boxId).html(html);
	}

	function renderToxicResult(boxId, data, statusClass, statusText, duration, timestamp) {
		var result = data.result;

		var isToxic = result.is_toxic;
		var toxicityScore = result.toxicity_score || 0;
		var confidence = result.confidence || 0;
		var categories = result.categories || [];
		var analysis = result.analysis_summary || '';

		// Verdict badge
		var verdictClass = isToxic ? 'error' : 'success';
		var verdictText = isToxic ? 'TOXIC CONTENT' : 'CLEAN';

		var html = '<div class="e2e-result-meta">' +
			'<span class="' + statusClass + '">' + statusText + '</span> | ' +
			duration + ' | ' + timestamp;

		if (result.credits_used) {
			html += ' | Credits: ' + result.credits_used;
		}
		html += '</div>';

		// Toxic result summary
		html += '<div class="e2e-spam-result">';
		html += '<div class="e2e-spam-verdict ' + verdictClass + '">' + verdictText + '</div>';
		html += '<div class="e2e-spam-scores">';
		html += '<div class="e2e-spam-score-item"><span class="label">Toxicity Score:</span> <span class="value">' + toxicityScore + '</span></div>';
		html += '<div class="e2e-spam-score-item"><span class="label">Confidence:</span> <span class="value">' + (confidence * 100).toFixed(0) + '%</span></div>';
		html += '<div class="e2e-spam-score-item"><span class="label">Sensitivity:</span> <span class="value">' + (result.sensitivity || '-') + '</span></div>';
		html += '</div>';

		// Categories
		if (categories.length > 0) {
			html += '<div class="e2e-spam-indicators"><strong>Categories:</strong> ' + escapeHtml(categories.join(', ')) + '</div>';
		}

		// Analysis
		if (analysis) {
			html += '<div class="e2e-spam-analysis"><strong>Analysis:</strong> ' + escapeHtml(analysis) + '</div>';
		}

		html += '</div>';

		// Full JSON
		html += '<details><summary>Full Response JSON</summary>';
		html += '<pre>' + syntaxHighlight(JSON.stringify(data, null, 2)) + '</pre>';
		html += '</details>';

		$('#' + boxId).html(html);
	}

	function loadSuggestionHistory() {
		var $tbody = $('#suggestion-history-body');
		$tbody.html('<tr><td colspan="11" class="e2e-loading">Loading...</td></tr>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_suggestion_history',
				nonce: wpforoE2E.nonce,
				limit: 50
			},
			success: function(response) {
				if (response.success && response.data.length > 0) {
					var html = '';
					response.data.forEach(function(row) {
						var date = new Date(row.created_at).toLocaleString();
						var hasAnswer = row.has_answer ? '✓' : '-';
						var topScore = row.top_score ? (row.top_score * 100).toFixed(0) + '%' : '-';

						html += '<tr data-id="' + row.id + '">';
						html += '<td>' + date + '</td>';
						html += '<td class="e2e-query-cell" title="' + escapeHtml(row.query) + '">' + escapeHtml(row.query.substring(0, 30)) + (row.query.length > 30 ? '...' : '') + '</td>';
						html += '<td>' + row.quality + '</td>';
						html += '<td>' + (row.similarity * 100).toFixed(0) + '%</td>';
						html += '<td>' + row.total_similar + '</td>';
						html += '<td>' + row.total_related + '</td>';
						html += '<td>' + hasAnswer + '</td>';
						html += '<td>' + topScore + '</td>';
						html += '<td>' + row.query_time_ms + 'ms</td>';
						html += '<td>' + row.credits_used + '</td>';
						html += '<td>';
						html += '<button class="button button-small e2e-view-json" data-json="' + escapeHtml(JSON.stringify(row.results)) + '">JSON</button> ';
						html += '<button class="button button-small e2e-delete-suggestion" data-id="' + row.id + '">Delete</button>';
						html += '</td>';
						html += '</tr>';
					});
					$tbody.html(html);
				} else {
					$tbody.html('<tr><td colspan="11" class="e2e-empty">No history yet</td></tr>');
				}
			},
			error: function() {
				$tbody.html('<tr><td colspan="11" class="e2e-error">Error loading history</td></tr>');
			}
		});
	}

	function clearSuggestionHistory() {
		if (!confirm('Clear all suggestion test history?')) return;

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_clear_suggestion_history',
				nonce: wpforoE2E.nonce
			},
			success: function() {
				loadSuggestionHistory();
			}
		});
	}

	// =========================
	// MODERATION FUNCTIONS
	// =========================

	function loadModerateInfo() {
		var $loading = $('#moderate-info-loading');
		var $infoBox = $('#moderate-settings-info');

		if ($infoBox.data('loaded')) return;

		$loading.show();
		$infoBox.hide();

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_feature_info',
				nonce: wpforoE2E.nonce,
				feature: 'moderate'
			},
			success: function(response) {
				$loading.hide();
				if (response.success) {
					var data = response.data;
					var spam = data.settings && data.settings.spam ? data.settings.spam : {};
					var policy = data.settings && data.settings.policy ? data.settings.policy : {};
					var toxicity = data.settings && data.settings.toxicity ? data.settings.toxicity : {};
					var spamExamples = data.examples && data.examples.spam ? data.examples.spam : [];
					var toxicExamples = data.examples && data.examples.toxic ? data.examples.toxic : [];

					// Spam settings
					$('#mod-spam-enabled').html(spam.enabled ? '<span class="e2e-badge success">Enabled</span>' : '<span class="e2e-badge error">Disabled</span>');
					$('#mod-spam-quality').text(spam.quality || 'balanced');
					$('#mod-spam-threshold').text(spam.min_indexed || '-');
					$('#mod-spam-context').text(spam.use_context ? 'Yes' : 'No');

					// Policy settings
					$('#mod-policy-enabled').html(policy.enabled ? '<span class="e2e-badge success">Enabled</span>' : '<span class="e2e-badge error">Disabled</span>');
					$('#mod-policy-quality').text(policy.quality || 'balanced');

					// Toxicity settings
					$('#mod-toxicity-enabled').html(toxicity.enabled ? '<span class="e2e-badge success">Enabled</span>' : '<span class="e2e-badge error">Disabled</span>');
					$('#mod-toxicity-quality').text(toxicity.sensitivity || 'medium');

					// Policy rules - show placeholder for now
					$('#mod-policy-rules').text(policy.enabled ? 'Enabled' : 'No rules configured');

					// Set spam quality selector to match settings
					if (spam.quality) {
						$('#spam-quality').val(spam.quality);
					}

					// Set toxicity sensitivity selector to match settings
					if (toxicity.sensitivity) {
						$('#toxic-sensitivity').val(toxicity.sensitivity);
					}

					// Load examples
					loadSpamExamples(spamExamples);
					loadToxicExamples(toxicExamples);

					$infoBox.show().data('loaded', true);
				} else {
					$loading.text('Error loading settings');
				}
			},
			error: function() {
				$loading.text('Connection error');
			}
		});
	}

	function loadSpamExamples(examples) {
		var $container = $('#spam-examples');

		if (examples.length === 0) {
			$container.html('<span class="e2e-no-examples">No example content available</span>');
			return;
		}

		var html = '';
		examples.forEach(function(ex, idx) {
			var label = ex.label || 'Example ' + (idx + 1);
			var shortLabel = label.length > 20 ? label.substring(0, 20) + '...' : label;
			html += '<button type="button" class="button button-small e2e-spam-example" data-content="' + escapeHtml(ex.content || '') + '" title="' + escapeHtml(label) + '">' + escapeHtml(shortLabel) + '</button>';
		});
		$container.html(html);
	}

	function loadToxicExamples(examples) {
		var $container = $('#toxic-examples');

		if (examples.length === 0) {
			$container.html('<span class="e2e-no-examples">No example content available</span>');
			return;
		}

		var html = '';
		examples.forEach(function(ex, idx) {
			var label = ex.label || 'Example ' + (idx + 1);
			var shortLabel = label.length > 20 ? label.substring(0, 20) + '...' : label;
			html += '<button type="button" class="button button-small e2e-toxic-example" data-content="' + escapeHtml(ex.content || '') + '" title="' + escapeHtml(label) + '">' + escapeHtml(shortLabel) + '</button>';
		});
		$container.html(html);
	}

	function loadSpamHistory() {
		var $tbody = $('#spam-history-body');
		$tbody.html('<tr><td colspan="9" class="e2e-loading">Loading...</td></tr>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_moderation_history',
				nonce: wpforoE2E.nonce,
				limit: 50
			},
			success: function(response) {
				if (response.success && response.data.length > 0) {
					var html = '';
					response.data.forEach(function(row) {
						var date = formatDate(row.created_at);
						var testType = row.test_type || 'spam';
						var result = '-';
						if (testType === 'spam') {
							result = row.is_spam ? '<span class="e2e-badge error">Spam</span>' : '<span class="e2e-badge success">Clean</span>';
						} else if (testType === 'toxic') {
							result = row.is_spam ? '<span class="e2e-badge error">Toxic</span>' : '<span class="e2e-badge success">Clean</span>';
						}
						var score = row.spam_score !== null ? row.spam_score : '-';

						html += '<tr data-id="' + row.id + '">';
						html += '<td>' + date + '</td>';
						html += '<td>' + testType + '</td>';
						html += '<td class="query-cell" title="' + escapeHtml(row.content) + '">' + escapeHtml((row.content || '').substring(0, 40)) + '</td>';
						html += '<td>' + (row.quality || '-') + '</td>';
						html += '<td>' + score + '</td>';
						html += '<td>' + result + '</td>';
						html += '<td>' + (row.query_time_ms || '-') + 'ms</td>';
						html += '<td>' + (row.credits_used || '-') + '</td>';
						html += '<td>';
						html += '<span class="e2e-expand-btn" data-json="' + escapeHtml(JSON.stringify(row.results || {})) + '">Details</span>';
						html += '<span class="e2e-delete-btn e2e-delete-moderation" data-id="' + row.id + '">Delete</span>';
						html += '</td>';
						html += '</tr>';
					});
					$tbody.html(html);
				} else {
					$tbody.html('<tr><td colspan="9" class="e2e-empty">No moderation tests yet</td></tr>');
				}
			},
			error: function() {
				$tbody.html('<tr><td colspan="9" class="e2e-error">Error loading history</td></tr>');
			}
		});
	}

	function clearModerationHistory() {
		if (!confirm('Clear all moderation test history?')) return;

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_clear_moderation_history',
				nonce: wpforoE2E.nonce
			},
			success: function() {
				loadSpamHistory();
			}
		});
	}

	function deleteModerationTest(id) {
		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_delete_moderation_test',
				nonce: wpforoE2E.nonce,
				id: id
			},
			success: function() {
				loadSpamHistory();
			}
		});
	}

	function formatSummaryContent(text) {
		if (!text) return '';

		// Convert markdown headers
		text = text.replace(/^### (.+)$/gm, '<h4>$1</h4>');
		text = text.replace(/^## (.+)$/gm, '<h3>$1</h3>');
		text = text.replace(/^# (.+)$/gm, '<h2>$1</h2>');

		// Convert bold and italic
		text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
		text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');

		// Convert bullet points
		text = text.replace(/^- (.+)$/gm, '<li>$1</li>');
		text = text.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>');

		// Convert newlines to paragraphs for non-list content
		var parts = text.split(/\n\n+/);
		text = parts.map(function(p) {
			p = p.trim();
			if (!p) return '';
			if (p.startsWith('<h') || p.startsWith('<ul')) return p;
			return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
		}).join('');

		return text;
	}

	function syntaxHighlight(json) {
		json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
			var cls = 'json-number';
			if (/^"/.test(match)) {
				if (/:$/.test(match)) {
					cls = 'json-key';
				} else {
					cls = 'json-string';
				}
			} else if (/true|false/.test(match)) {
				cls = 'json-boolean';
			} else if (/null/.test(match)) {
				cls = 'json-null';
			}
			return '<span class="' + cls + '">' + match + '</span>';
		});
	}

	function loadSearchHistory() {
		var $tbody = $('#search-history-body');
		$tbody.html('<tr><td colspan="10" class="e2e-loading">Loading...</td></tr>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_search_history',
				nonce: wpforoE2E.nonce,
				limit: 50
			},
			success: function(response) {
				if (response.success && response.data.length) {
					renderSearchHistory(response.data);
				} else {
					$tbody.html('<tr><td colspan="10" style="text-align:center;color:#666;">No test history yet. Run a search test to start tracking.</td></tr>');
				}
			},
			error: function() {
				$tbody.html('<tr><td colspan="10" class="e2e-loading">Error loading history</td></tr>');
			}
		});
	}

	function renderSearchHistory(data) {
		var $tbody = $('#search-history-body');
		var html = '';

		data.forEach(function(row, idx) {
			var changesHtml = '-';
			if (row.changes) {
				var parts = [];
				if (row.changes.results_diff !== 0) {
					parts.push(changeTag(row.changes.results_diff, 'res'));
				}
				if (row.changes.avg_score_diff !== 0) {
					parts.push(changeTag(row.changes.avg_score_diff, 'avg'));
				}
				if (row.changes.time_diff !== 0) {
					parts.push(changeTag(-row.changes.time_diff, 'ms', true));
				}
				changesHtml = parts.length ? parts.join(' ') : '<span class="e2e-change neutral">same</span>';
			}

			var enhancedHtml = row.has_enhancement == 1
				? '<span class="e2e-enhanced-yes">Yes</span>'
				: '<span class="e2e-enhanced-no">No</span>';

			var storageMode = row.storage_mode || 'local';
			var modeClass = storageMode === 'cloud' ? 'e2e-mode-cloud' : 'e2e-mode-local';

			html += '<tr data-row-id="' + row.id + '">';
			html += '<td>' + formatDate(row.created_at) + '</td>';
			html += '<td class="query-cell" title="' + escapeHtml(row.query) + '">' + escapeHtml(row.query) + '</td>';
			html += '<td><span class="' + modeClass + '">' + storageMode + '</span></td>';
			html += '<td>' + row.total_results + '</td>';
			html += '<td>' + row.query_time_ms + 'ms</td>';
			html += '<td>' + row.avg_score + '%</td>';
			html += '<td>' + row.top_score + '%</td>';
			html += '<td>' + enhancedHtml + '</td>';
			html += '<td>' + changesHtml + '</td>';
			html += '<td>';
			html += '<span class="e2e-expand-btn" data-idx="' + idx + '">Details</span>';
			html += '<span class="e2e-delete-btn" data-id="' + row.id + '">Delete</span>';
			html += '</td>';
			html += '</tr>';

			html += '<tr class="e2e-history-row-details" id="details-row-' + idx + '" style="display:none;">';
			html += '<td colspan="10"><strong>Results & Enhancement:</strong><pre>' + syntaxHighlight(JSON.stringify(row.results_json, null, 2)) + '</pre></td>';
			html += '</tr>';
		});

		$tbody.html(html);

		$tbody.find('.e2e-expand-btn').on('click', function() {
			var idx = $(this).data('idx');
			var $row = $('#details-row-' + idx);
			$row.toggle();
			$(this).text($row.is(':visible') ? 'Hide' : 'Details');
		});

		$tbody.find('.e2e-delete-btn').on('click', function() {
			var id = $(this).data('id');
			deleteSearchTest(id);
		});
	}

	function changeTag(diff, label, invert) {
		var cls = diff > 0 ? (invert ? 'negative' : 'positive') : (diff < 0 ? (invert ? 'positive' : 'negative') : 'neutral');
		var prefix = diff > 0 ? '+' : '';
		return '<span class="e2e-change ' + cls + '">' + prefix + diff + ' ' + label + '</span>';
	}

	function formatDate(str) {
		var d = new Date(str);
		return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
	}

	function clearSearchHistory() {
		if (!confirm('Clear all search test history?')) return;

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_clear_search_history',
				nonce: wpforoE2E.nonce
			},
			success: function() {
				loadSearchHistory();
			}
		});
	}

	function deleteSearchTest(id) {
		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_delete_search_test',
				nonce: wpforoE2E.nonce,
				id: id
			},
			success: function() {
				loadSearchHistory();
			}
		});
	}

	$(document).ready(init);

})(jQuery);
