<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap wpforo-e2e-wrap">
	<h1>wpForo E2E Tester</h1>

	<div class="e2e-tabs">
		<div class="e2e-tab-nav">
			<button class="e2e-tab-btn active" data-tab="info">📊 Info</button>
			<button class="e2e-tab-btn" data-tab="search">🔍 Search</button>
			<button class="e2e-tab-btn" data-tab="index">📥 Index</button>
			<button class="e2e-tab-btn" data-tab="translate">🌐 Translate</button>
			<button class="e2e-tab-btn" data-tab="summarize">📝 Summary</button>
			<button class="e2e-tab-btn" data-tab="suggestions">💡 Suggest</button>
			<button class="e2e-tab-btn" data-tab="moderate">🛡️ Moderate</button>
			<button class="e2e-tab-btn" data-tab="chat">💬 Chat</button>
			<button class="e2e-tab-btn" data-tab="rag">📦 RAG</button>
			<button class="e2e-tab-btn" data-tab="analytics">📈 Analytics</button>
		</div>

		<!-- Info Tab -->
		<div class="e2e-tab-content active" data-tab="info">
			<div class="e2e-panel">
				<h2>Tenant Connection</h2>
				<div class="e2e-loading" id="tenant-loading">Loading...</div>
				<div class="e2e-info-grid" id="tenant-info" style="display:none;">
					<div class="e2e-info-item">
						<span class="label">Status:</span>
						<span class="value" id="info-status">-</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">Tenant ID:</span>
						<span class="value" id="info-tenant-id">-</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">API Key:</span>
						<span class="value">
							<span id="info-api-key">-</span>
							<button type="button" class="button button-small" id="toggle-api-key">Show</button>
						</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">API URL:</span>
						<span class="value" id="info-api-url">-</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">Board ID:</span>
						<span class="value" id="info-board-id">-</span>
					</div>
					<div class="e2e-info-item" id="storage-mode-item">
						<span class="label">Storage:</span>
						<span class="value">
							<select id="storage-mode-select">
								<option value="local">Local</option>
								<option value="cloud">Cloud</option>
							</select>
							<button type="button" class="button button-small" id="save-storage-mode">Save</button>
						</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">Plan:</span>
						<span class="value" id="info-plan">-</span>
					</div>
					<div class="e2e-info-item">
						<span class="label">Sub Status:</span>
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
						<span class="label">Features:</span>
						<span class="value" id="info-features">-</span>
					</div>
					<div class="e2e-info-item full-width">
						<span class="label">Indexing:</span>
						<span class="value" id="info-indexing">-</span>
					</div>
					<div class="e2e-info-item e2e-warning-item full-width" id="storage-mode-warning" style="display:none;">
						<span class="label">Warning:</span>
						<span class="value" id="storage-mode-warning-text"></span>
					</div>
				</div>
				<button type="button" class="button" id="refresh-tenant-info">Refresh Info</button>
			</div>
		</div>

		<!-- Search Tab -->
		<div class="e2e-tab-content" data-tab="search">
			<div class="e2e-panel">
				<h2>Semantic Search</h2>

				<!-- Feature Info -->
				<div class="e2e-feature-info" id="search-info">
					<div class="e2e-info-loading">Loading search info...</div>
				</div>

				<!-- Test Form -->
				<div class="e2e-test-form">
					<h3>Run Test</h3>
					<div class="e2e-form-row">
						<input type="text" id="search-query" placeholder="Search query..." value="how to configure" style="flex:1;">
						<select id="search-storage-mode" title="Storage mode to search">
							<option value="current">Current Mode</option>
							<option value="local">Local</option>
							<option value="cloud">Cloud</option>
						</select>
						<input type="number" id="search-limit" placeholder="Limit" value="5" min="1" max="20" style="width:70px;">
						<button type="button" class="button button-primary e2e-run-test" data-test="semantic_search">Search</button>
					</div>
					<div class="e2e-suggestions" id="search-suggestions"></div>
				</div>

				<div class="e2e-result-box" id="result-search"></div>

				<!-- Search History -->
				<div class="e2e-history-section">
					<h3>
						Test History
						<button type="button" class="button button-small" id="refresh-search-history">Refresh</button>
						<button type="button" class="button button-small" id="clear-search-history">Clear All</button>
					</h3>
					<div class="e2e-history-table-wrap">
						<table class="e2e-history-table" id="search-history-table">
							<thead>
								<tr>
									<th>Date</th>
									<th>Query</th>
									<th>Mode</th>
									<th>Results</th>
									<th>Time</th>
									<th>Avg</th>
									<th>Top</th>
									<th>Enhanced</th>
									<th>Changes</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody id="search-history-body">
								<tr><td colspan="10" class="e2e-loading">Loading history...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Index Tab -->
		<div class="e2e-tab-content" data-tab="index">
			<div class="e2e-panel">
				<h2>Content Indexing</h2>

				<!-- Indexing Info Section -->
				<div class="e2e-feature-info" id="index-info">
					<div class="e2e-info-loading">Loading indexing info...</div>
				</div>

				<!-- Indexing Flow Diagram -->
				<div class="e2e-flow-diagram" id="index-diagram">
					<h4>How Indexing Works</h4>
					<div class="e2e-diagram-loading">Loading...</div>
				</div>

				<!-- Test Form -->
				<div class="e2e-test-form">
					<h3>Test Topic Indexing</h3>
					<div class="e2e-index-form">
						<div class="e2e-form-row full-width">
							<label class="e2e-form-label">Topic:</label>
							<select id="topic-select">
								<option value="">Loading topics...</option>
							</select>
							<button type="button" class="button" id="refresh-topics" title="Refresh topic list">Refresh</button>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Filter:</label>
							<label class="e2e-checkbox"><input type="checkbox" id="filter-with-images"> <span>Has images</span></label>
							<label class="e2e-checkbox"><input type="checkbox" id="filter-with-docs"> <span>Has documents</span></label>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Process:</label>
							<label class="e2e-checkbox"><input type="checkbox" id="index-include-images" checked> <span>Images</span></label>
							<label class="e2e-checkbox"><input type="checkbox" id="index-include-docs" checked> <span>Documents</span></label>
						</div>
						<div class="e2e-form-row actions">
							<button type="button" class="button button-primary button-hero e2e-run-test" data-test="index_topic">Run Indexing Test</button>
						</div>
					</div>
					<div class="e2e-result-box" id="result-index"></div>
				</div>
			</div>
		</div>

		<!-- Translate Tab -->
		<div class="e2e-tab-content" data-tab="translate">
			<div class="e2e-panel">
				<h2>Translation</h2>

				<!-- Translation Info Section -->
				<div class="e2e-feature-info" id="translate-info">
					<div class="e2e-info-loading">Loading translation info...</div>
				</div>

				<!-- Test Form -->
				<div class="e2e-test-form">
					<h3>Test Translation</h3>
					<div class="e2e-translate-form">
						<div class="e2e-form-row full-width">
							<label class="e2e-form-label">Post:</label>
							<select id="translate-post-select">
								<option value="">Loading posts...</option>
							</select>
							<button type="button" class="button" id="refresh-translate-posts">Refresh</button>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Target:</label>
							<select id="translate-language">
								<option value="Spanish">Spanish</option>
								<option value="French">French</option>
								<option value="German">German</option>
								<option value="Italian">Italian</option>
								<option value="Portuguese">Portuguese</option>
								<option value="Chinese">Chinese</option>
								<option value="Japanese">Japanese</option>
								<option value="Korean">Korean</option>
								<option value="Russian">Russian</option>
								<option value="Arabic">Arabic</option>
								<option value="Hindi">Hindi</option>
								<option value="Turkish">Turkish</option>
							</select>
						</div>
						<div class="e2e-form-row actions">
							<button type="button" class="button button-primary button-hero e2e-run-test" data-test="translate">Translate</button>
						</div>
					</div>

					<!-- Translation Result -->
					<div class="e2e-translate-result" id="translate-result-display" style="display:none;">
						<div class="e2e-translate-comparison">
							<div class="e2e-translate-column">
								<h4>Original</h4>
								<div class="e2e-translate-content" id="translate-original"></div>
							</div>
							<div class="e2e-translate-column">
								<h4>Translated</h4>
								<div class="e2e-translate-content" id="translate-translated"></div>
							</div>
						</div>
					</div>

					<div class="e2e-result-box" id="result-translate"></div>
				</div>
			</div>
		</div>

		<!-- Summarize Tab -->
		<div class="e2e-tab-content" data-tab="summarize">
			<div class="e2e-panel">
				<h2>Topic Summarization</h2>

				<!-- Summarize Info Section -->
				<div class="e2e-feature-info" id="summarize-info">
					<div class="e2e-info-loading">Loading summarize info...</div>
				</div>

				<!-- Test Form -->
				<div class="e2e-test-form">
					<h3>Test Summarization</h3>
					<div class="e2e-summarize-form">
						<div class="e2e-form-row full-width">
							<label class="e2e-form-label">Topic:</label>
							<select id="summarize-topic-select">
								<option value="">Loading topics...</option>
							</select>
							<button type="button" class="button" id="refresh-summarize-topics">Refresh</button>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Quality:</label>
							<select id="summarize-quality">
								<option value="fast">Fast (1 credit)</option>
								<option value="balanced">Balanced (2 credits)</option>
								<option value="advanced">Advanced (3 credits)</option>
								<option value="premium">Premium (4 credits)</option>
							</select>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Style:</label>
							<select id="summarize-style">
								<option value="compact">Compact with Key Points</option>
								<option value="structured">Structured with Sections</option>
								<option value="conversational">Conversational Flow</option>
								<option value="detailed">Short Summary + Details</option>
								<option value="minimal">Minimal and Clean</option>
							</select>
						</div>
						<div class="e2e-form-row actions">
							<button type="button" class="button button-primary button-hero e2e-run-test" data-test="summarize">Summarize Topic</button>
						</div>
					</div>

					<!-- Summarize Result -->
					<div class="e2e-summarize-result" id="summarize-result-display" style="display:none;">
						<div class="e2e-summarize-topic-info">
							<h4>Topic Info</h4>
							<div class="e2e-summarize-topic-details" id="summarize-topic-details"></div>
						</div>
						<div class="e2e-summarize-output">
							<h4>Generated Summary</h4>
							<div class="e2e-summarize-content" id="summarize-content"></div>
						</div>
					</div>

					<div class="e2e-result-box" id="result-summarize"></div>
				</div>
			</div>
		</div>

		<!-- Suggestions Tab -->
		<div class="e2e-tab-content" data-tab="suggestions">
			<div class="e2e-panel">
				<h2>Topic Suggestions</h2>

				<!-- Suggestions Info Section -->
				<div class="e2e-feature-info" id="suggestions-info">
					<div class="e2e-info-loading">Loading suggestions info...</div>
				</div>

				<!-- Test Form -->
				<div class="e2e-test-form">
					<h3>Test Suggestions</h3>
					<div class="e2e-suggestions-form">
						<div class="e2e-form-row full-width">
							<label class="e2e-form-label">Title:</label>
							<input type="text" id="suggestion-title" placeholder="Enter topic title to get suggestions..." value="">
						</div>
						<div class="e2e-form-row full-width">
							<label class="e2e-form-label">Examples:</label>
							<div class="e2e-suggestion-examples" id="suggestion-examples">
								<span class="e2e-example-loading">Loading...</span>
							</div>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Quality:</label>
							<select id="suggestion-quality">
								<option value="fast">Fast (1 credit)</option>
								<option value="balanced">Balanced (2 credits)</option>
								<option value="advanced">Advanced (3 credits)</option>
								<option value="premium">Premium (4 credits)</option>
							</select>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Similarity:</label>
							<select id="suggestion-similarity">
								<option value="0.40">40% (Very Loose)</option>
								<option value="0.50">50% (Loose)</option>
								<option value="0.55">55% (Default)</option>
								<option value="0.60">60% (Moderate)</option>
								<option value="0.70">70% (Strict)</option>
								<option value="0.80">80% (Very Strict)</option>
							</select>
						</div>
						<div class="e2e-form-row">
							<label class="e2e-form-label">Options:</label>
							<label class="e2e-checkbox"><input type="checkbox" id="suggestion-include-answer" checked> <span>Include AI Answer</span></label>
						</div>
						<div class="e2e-form-row actions">
							<button type="button" class="button button-primary button-hero e2e-run-test" data-test="suggestions">Get Suggestions</button>
						</div>
					</div>

					<!-- Suggestions Result Display -->
					<div class="e2e-suggestions-result" id="suggestions-result-display" style="display:none;">
						<div class="e2e-suggestions-similar" id="suggestions-similar-section" style="display:none;">
							<h4>Similar Topics <span class="e2e-badge" id="suggestions-similar-count">0</span></h4>
							<div class="e2e-suggestions-list" id="suggestions-similar-list"></div>
						</div>
						<div class="e2e-suggestions-related" id="suggestions-related-section" style="display:none;">
							<h4>Related Topics <span class="e2e-badge" id="suggestions-related-count">0</span></h4>
							<div class="e2e-suggestions-list" id="suggestions-related-list"></div>
						</div>
						<div class="e2e-suggestions-answer" id="suggestions-answer-section" style="display:none;">
							<h4>AI Quick Answer</h4>
							<div class="e2e-suggestions-answer-content" id="suggestions-answer-content"></div>
						</div>
					</div>

					<div class="e2e-result-box" id="result-suggestions"></div>
				</div>

				<!-- Suggestion History -->
				<div class="e2e-history-section">
					<h3>
						Test History
						<button type="button" class="button button-small" id="refresh-suggestion-history">Refresh</button>
						<button type="button" class="button button-small" id="clear-suggestion-history">Clear All</button>
					</h3>
					<div class="e2e-history-table-wrap">
						<table class="e2e-history-table" id="suggestion-history-table">
							<thead>
								<tr>
									<th>Date</th>
									<th>Query</th>
									<th>Quality</th>
									<th>Threshold</th>
									<th>Similar</th>
									<th>Related</th>
									<th>Answer</th>
									<th>Top Score</th>
									<th>Time</th>
									<th>Credits</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody id="suggestion-history-body">
								<tr><td colspan="11" class="e2e-loading">Loading history...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- Moderate Tab -->
		<div class="e2e-tab-content" data-tab="moderate">
			<div class="e2e-panel">
				<h2>Content Moderation</h2>
				<div class="e2e-form-row">
					<textarea id="moderate-content" placeholder="Content to moderate..." style="flex:1;min-height:60px;">This is a test message for moderation check.</textarea>
				</div>
				<div class="e2e-form-row">
					<button type="button" class="button button-primary e2e-run-test" data-test="moderate">Check Moderation</button>
				</div>
				<div class="e2e-result-box" id="result-moderate"></div>
			</div>
		</div>

		<!-- Chat Tab -->
		<div class="e2e-tab-content" data-tab="chat">
			<div class="e2e-panel">
				<h2>AI Chat</h2>
				<div class="e2e-form-row">
					<input type="text" id="chat-message" placeholder="Chat message..." value="Hello, how can I get help?" style="flex:1;">
					<button type="button" class="button button-primary e2e-run-test" data-test="chat">Send</button>
				</div>
				<div class="e2e-result-box" id="result-chat"></div>
			</div>
		</div>

		<!-- RAG Tab -->
		<div class="e2e-tab-content" data-tab="rag">
			<div class="e2e-panel">
				<h2>RAG Status</h2>
				<div class="e2e-form-row">
					<button type="button" class="button button-primary e2e-run-test" data-test="rag_status">Get RAG Status</button>
				</div>
				<div class="e2e-result-box" id="result-rag"></div>
			</div>
		</div>

		<!-- Analytics Tab -->
		<div class="e2e-tab-content" data-tab="analytics">
			<div class="e2e-panel">
				<h2>Analytics</h2>
				<div class="e2e-form-row">
					<button type="button" class="button button-primary e2e-run-test" data-test="analytics">Get Analytics</button>
				</div>
				<div class="e2e-result-box" id="result-analytics"></div>
			</div>
		</div>
	</div>
</div>
