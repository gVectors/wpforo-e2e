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
		}
		// Add more feature renderers as needed
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
		$('.e2e-run-test').on('click', runTest);
		$('#refresh-search-history').on('click', loadSearchHistory);
		$('#clear-search-history').on('click', clearSearchHistory);
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
				return { postid: $('#translate-postid').val(), language: $('#translate-language').val() };
			case 'summarize':
				return { topicid: $('#summarize-topicid').val() };
			case 'suggestions':
				return { title: $('#suggestion-title').val() };
			case 'moderate':
				return { content: $('#moderate-content').val() };
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
