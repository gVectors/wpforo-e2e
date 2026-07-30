(function($) {
	'use strict';

	var tenantInfo = {};
	var apiKeyFull = '';

	function init() {
		loadTenantInfo();
		bindEvents();
	}

	function bindEvents() {
		$('#refresh-tenant-info').on('click', loadTenantInfo);
		$('#toggle-api-key').on('click', toggleApiKey);
		$('#save-storage-mode').on('click', saveStorageMode);
		$('#load-topics').on('click', loadTopics);
		$('#filter-attachments').on('change', loadTopics);
		$('.e2e-run-test').on('click', runTest);
		$('#clear-results').on('click', clearResults);

		$(document).on('click', '.e2e-result-header', function() {
			$(this).next('.e2e-result-body').toggleClass('expanded');
		});
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
				' / Total Topics: ' + (stats.total_topics || 0) +
				' | Mode: ' + (stats.storage_mode || 'unknown') +
				' | Size: ' + (stats.storage_size_mb || '0') + ' MB';
			if (stats.is_indexing) {
				statsText += ' | Progress: ' + (stats.indexing_progress || 0) + '%';
			}
			$('#info-indexing').text(statsText);
		}
	}

	function toggleApiKey() {
		var $keySpan = $('#info-api-key');
		var $btn = $('#toggle-api-key');

		if ($btn.text() === 'Show Full') {
			$keySpan.html('<span class="api-key-full">' + apiKeyFull + '</span>');
			$btn.text('Hide');
		} else {
			$keySpan.text(tenantInfo.api_key_masked || '-');
			$btn.text('Show Full');
		}
	}

	function saveStorageMode() {
		var mode = $('#storage-mode-select').val();
		var $btn = $('#save-storage-mode');

		$btn.addClass('is-loading').text('Saving');

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
					alert('Storage mode saved: ' + response.data.storage_mode);
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
		var $btn = $('#load-topics');
		var filter = $('#filter-attachments').is(':checked') ? 'with_attachments' : 'all';

		$btn.addClass('is-loading').text('Loading');
		$select.html('<option value="">Loading...</option>');

		$.ajax({
			url: wpforoE2E.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpforo_e2e_get_topics',
				nonce: wpforoE2E.nonce,
				filter: filter,
				limit: 50
			},
			success: function(response) {
				$btn.removeClass('is-loading').text('Load Topics');
				$select.empty();
				$select.append('<option value="">Select a topic...</option>');

				if (response.success && response.data.length) {
					response.data.forEach(function(topic) {
						var label = '[' + topic.topicid + '] ' + topic.title.substring(0, 50);
						var attachInfo = '';

						if (topic.has_pdf) attachInfo += ' [PDF]';
						if (topic.has_image) attachInfo += ' [IMG]';
						if (topic.attachments.length) {
							attachInfo += ' (' + topic.attachments.length + ' attachments)';
						}

						var $option = $('<option></option>')
							.val(topic.topicid)
							.text(label + attachInfo)
							.data('topic', topic);

						if (topic.attachments.length) {
							$option.addClass('topic-has-attachments');
						}

						$select.append($option);
					});
				} else {
					$select.append('<option value="">No topics found</option>');
				}
			},
			error: function() {
				$btn.removeClass('is-loading').text('Load Topics');
				$select.html('<option value="">Error loading topics</option>');
			}
		});
	}

	function runTest() {
		var $btn = $(this);
		var testType = $btn.data('test');
		var params = getTestParams(testType);

		$btn.addClass('is-loading');

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
				renderTestResult(testType, response);
			},
			error: function(xhr, status, error) {
				$btn.removeClass('is-loading');
				renderTestResult(testType, {
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
					limit: $('#search-limit').val()
				};
			case 'index_topic':
				return {
					topicid: $('#topic-select').val()
				};
			case 'translate':
				return {
					postid: $('#translate-postid').val(),
					language: $('#translate-language').val()
				};
			case 'summarize':
				return {
					topicid: $('#summarize-topicid').val()
				};
			case 'suggestions':
				return {
					title: $('#suggestion-title').val()
				};
			case 'moderate':
				return {
					content: $('#moderate-content').val()
				};
			case 'chat':
				return {
					message: $('#chat-message').val()
				};
			default:
				return {};
		}
	}

	function renderTestResult(testType, response) {
		var isSuccess = response.success && response.data && response.data.result && response.data.result.success !== false;
		var statusClass = isSuccess ? 'success' : 'error';
		var statusText = isSuccess ? 'SUCCESS' : 'FAILED';

		var duration = response.data && response.data.duration_ms ? response.data.duration_ms + 'ms' : '-';
		var timestamp = response.data && response.data.timestamp ? response.data.timestamp : new Date().toISOString();

		var resultData = response.success ? response.data : response;
		var formattedJson = syntaxHighlight(JSON.stringify(resultData, null, 2));

		var html = '<div class="e2e-result-item">' +
			'<div class="e2e-result-header">' +
				'<span class="test-name">' + formatTestName(testType) + '</span>' +
				'<span class="test-meta">' + duration + ' | ' + timestamp + '</span>' +
				'<span class="test-status ' + statusClass + '">' + statusText + '</span>' +
			'</div>' +
			'<div class="e2e-result-body expanded">' +
				'<pre>' + formattedJson + '</pre>' +
			'</div>' +
		'</div>';

		$('#test-results').prepend(html);
	}

	function formatTestName(testType) {
		var names = {
			'tenant_status': 'Tenant Status',
			'semantic_search': 'Semantic Search',
			'index_topic': 'Index Topic',
			'translate': 'Translation',
			'summarize': 'Topic Summarization',
			'suggestions': 'Topic Suggestions',
			'moderate': 'Content Moderation',
			'chat': 'AI Chat',
			'rag_status': 'RAG Status',
			'analytics': 'Analytics'
		};
		return names[testType] || testType;
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

	function clearResults() {
		$('#test-results').empty();
	}

	$(document).ready(init);

})(jQuery);
