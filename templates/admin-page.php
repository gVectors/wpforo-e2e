<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap wpforo-e2e-wrap">
	<h1>wpForo E2E Tester</h1>
	<p class="description">Real end-to-end testing using your current tenant connection, credits, and forum data.</p>

	<!-- Tenant Info Box -->
	<div class="e2e-info-box" id="tenant-info-box">
		<h2>Tenant Connection Info</h2>
		<div class="e2e-loading" id="tenant-loading">Loading tenant info...</div>
		<div class="e2e-info-grid" id="tenant-info" style="display:none;">
			<div class="e2e-info-item">
				<span class="label">Connection Status:</span>
				<span class="value" id="info-status">-</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">Tenant ID:</span>
				<span class="value" id="info-tenant-id">-</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">API Key:</span>
				<span class="value" id="info-api-key">-</span>
				<button type="button" class="button button-small" id="toggle-api-key">Show Full</button>
			</div>
			<div class="e2e-info-item">
				<span class="label">API Base URL:</span>
				<span class="value" id="info-api-url">-</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">Board ID:</span>
				<span class="value" id="info-board-id">-</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">Storage Mode:</span>
				<span class="value">
					<select id="storage-mode-select">
						<option value="local">Local</option>
						<option value="cloud">Cloud</option>
					</select>
					<button type="button" class="button button-small" id="save-storage-mode">Save</button>
				</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">Subscription Plan:</span>
				<span class="value" id="info-plan">-</span>
			</div>
			<div class="e2e-info-item">
				<span class="label">Subscription Status:</span>
				<span class="value" id="info-sub-status">-</span>
			</div>
			<div class="e2e-info-item full-width">
				<span class="label">Credits:</span>
				<span class="value">
					<span id="info-credits-remaining">-</span> remaining /
					<span id="info-credits-used">-</span> used /
					<span id="info-credits-total">-</span> total
				</span>
			</div>
			<div class="e2e-info-item full-width">
				<span class="label">Features Enabled:</span>
				<span class="value" id="info-features">-</span>
			</div>
			<div class="e2e-info-item full-width">
				<span class="label">Indexing Stats:</span>
				<span class="value" id="info-indexing">-</span>
			</div>
		</div>
		<button type="button" class="button" id="refresh-tenant-info">Refresh Info</button>
	</div>

	<!-- Test Controls -->
	<div class="e2e-test-section">
		<h2>Endpoint Tests</h2>

		<div class="e2e-test-grid">
			<!-- Tenant Status Test -->
			<div class="e2e-test-card">
				<h3>Tenant Status</h3>
				<p>Test /tenant/status endpoint</p>
				<button type="button" class="button button-primary e2e-run-test" data-test="tenant_status">Run Test</button>
			</div>

			<!-- Semantic Search Test -->
			<div class="e2e-test-card">
				<h3>Semantic Search</h3>
				<p>Test /search endpoint</p>
				<input type="text" id="search-query" placeholder="Search query..." value="how to configure">
				<input type="number" id="search-limit" placeholder="Limit" value="5" min="1" max="20">
				<button type="button" class="button button-primary e2e-run-test" data-test="semantic_search">Run Test</button>
			</div>

			<!-- Index Topic Test -->
			<div class="e2e-test-card">
				<h3>Index Topic</h3>
				<p>Test topic indexing (local/cloud based on setting)</p>
				<select id="topic-select">
					<option value="">Select a topic...</option>
				</select>
				<label>
					<input type="checkbox" id="filter-attachments"> Only with attachments
				</label>
				<button type="button" class="button" id="load-topics">Load Topics</button>
				<button type="button" class="button button-primary e2e-run-test" data-test="index_topic">Run Test</button>
			</div>

			<!-- Translation Test -->
			<div class="e2e-test-card">
				<h3>Translation</h3>
				<p>Test /translate endpoint</p>
				<input type="number" id="translate-postid" placeholder="Post ID (or random)" value="">
				<select id="translate-language">
					<option value="Spanish">Spanish</option>
					<option value="French">French</option>
					<option value="German">German</option>
					<option value="Chinese">Chinese</option>
					<option value="Japanese">Japanese</option>
					<option value="Russian">Russian</option>
				</select>
				<button type="button" class="button button-primary e2e-run-test" data-test="translate">Run Test</button>
			</div>

			<!-- Summarization Test -->
			<div class="e2e-test-card">
				<h3>Topic Summarization</h3>
				<p>Test /summarizing/summarize endpoint</p>
				<input type="number" id="summarize-topicid" placeholder="Topic ID (or random)">
				<button type="button" class="button button-primary e2e-run-test" data-test="summarize">Run Test</button>
			</div>

			<!-- Topic Suggestions Test -->
			<div class="e2e-test-card">
				<h3>Topic Suggestions</h3>
				<p>Test /suggestions/suggest endpoint</p>
				<input type="text" id="suggestion-title" placeholder="Topic title..." value="How to configure forum settings">
				<button type="button" class="button button-primary e2e-run-test" data-test="suggestions">Run Test</button>
			</div>

			<!-- Moderation Test -->
			<div class="e2e-test-card">
				<h3>Content Moderation</h3>
				<p>Test /moderation/check endpoint</p>
				<textarea id="moderate-content" placeholder="Content to moderate...">This is a test message for moderation check.</textarea>
				<button type="button" class="button button-primary e2e-run-test" data-test="moderate">Run Test</button>
			</div>

			<!-- Chat Test -->
			<div class="e2e-test-card">
				<h3>AI Chat</h3>
				<p>Test /chat/message endpoint</p>
				<input type="text" id="chat-message" placeholder="Chat message..." value="Hello, how can I get help?">
				<button type="button" class="button button-primary e2e-run-test" data-test="chat">Run Test</button>
			</div>

			<!-- RAG Status Test -->
			<div class="e2e-test-card">
				<h3>RAG Status</h3>
				<p>Test /rag/status endpoint</p>
				<button type="button" class="button button-primary e2e-run-test" data-test="rag_status">Run Test</button>
			</div>

			<!-- Analytics Test -->
			<div class="e2e-test-card">
				<h3>Analytics</h3>
				<p>Test /analytics endpoint</p>
				<button type="button" class="button button-primary e2e-run-test" data-test="analytics">Run Test</button>
			</div>
		</div>
	</div>

	<!-- Results Section -->
	<div class="e2e-results-section">
		<h2>Test Results</h2>
		<button type="button" class="button" id="clear-results">Clear Results</button>
		<div id="test-results"></div>
	</div>
</div>
