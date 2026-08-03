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
			<!-- Settings Info -->
			<div class="e2e-panel e2e-info-panel">
				<h2>Content Moderation Settings</h2>
				<div class="e2e-loading" id="moderate-info-loading">Loading...</div>
				<div id="moderate-settings-info" style="display:none;">
					<div class="e2e-info-grid">
						<div class="e2e-info-item"><span class="label">Spam Detection:</span><span class="value" id="mod-spam-enabled">-</span></div>
						<div class="e2e-info-item"><span class="label">Spam Quality:</span><span class="value" id="mod-spam-quality">-</span></div>
						<div class="e2e-info-item"><span class="label">Spam Threshold:</span><span class="value" id="mod-spam-threshold">-</span></div>
						<div class="e2e-info-item"><span class="label">Forum Context:</span><span class="value" id="mod-spam-context">-</span></div>
						<div class="e2e-info-item"><span class="label">Policy Check:</span><span class="value" id="mod-policy-enabled">-</span></div>
						<div class="e2e-info-item"><span class="label">Policy Quality:</span><span class="value" id="mod-policy-quality">-</span></div>
						<div class="e2e-info-item"><span class="label">Toxicity Check:</span><span class="value" id="mod-toxicity-enabled">-</span></div>
						<div class="e2e-info-item"><span class="label">Toxicity Quality:</span><span class="value" id="mod-toxicity-quality">-</span></div>
						<div class="e2e-info-item full-width"><span class="label">Policy Rules:</span><span class="value" id="mod-policy-rules">-</span></div>
					</div>
				</div>
			</div>

			<!-- Two Column Layout for Spam and Toxicity -->
			<div class="e2e-moderation-grid">
				<!-- Spam Detection Form -->
				<div class="e2e-panel">
					<h2>Spam Detection Test</h2>
					<div class="e2e-form-row">
						<label>Quality:</label>
						<select id="spam-quality">
							<option value="fast">Fast</option>
							<option value="balanced" selected>Balanced</option>
							<option value="advanced">Advanced</option>
							<option value="premium">Premium</option>
						</select>
					</div>
					<div class="e2e-form-row">
						<label>Examples:</label>
						<div class="e2e-example-buttons" id="spam-examples">
							<span class="e2e-loading-inline">Loading...</span>
						</div>
					</div>
					<div class="e2e-form-row">
						<textarea id="spam-content" placeholder="Enter content to check for spam..." style="flex:1;min-height:80px;"></textarea>
					</div>
					<div class="e2e-form-row">
						<button type="button" class="button button-primary e2e-run-test" data-test="spam">Check for Spam</button>
					</div>
					<div class="e2e-result-box" id="result-spam"></div>
				</div>

				<!-- Toxicity Detection Form -->
				<div class="e2e-panel">
					<h2>Toxicity Detection Test</h2>
					<div class="e2e-form-row">
						<label>Quality:</label>
						<select id="toxic-quality">
							<option value="fast">Fast</option>
							<option value="balanced" selected>Balanced</option>
							<option value="advanced">Advanced</option>
							<option value="premium">Premium</option>
						</select>
						<label style="margin-left:15px;">Sensitivity:</label>
						<select id="toxic-sensitivity">
							<option value="low">Low</option>
							<option value="medium" selected>Medium</option>
							<option value="high">High</option>
						</select>
					</div>
					<div class="e2e-form-row">
						<label>Examples:</label>
						<div class="e2e-example-buttons" id="toxic-examples">
							<span class="e2e-loading-inline">Loading...</span>
						</div>
					</div>
					<div class="e2e-form-row">
						<textarea id="toxic-content" placeholder="Enter content to check for toxicity..." style="flex:1;min-height:80px;"></textarea>
					</div>
					<div class="e2e-form-row">
						<button type="button" class="button button-primary e2e-run-test" data-test="toxic">Check Toxicity</button>
					</div>
					<div class="e2e-result-box" id="result-toxic"></div>
				</div>
			</div>

			<!-- Moderation Test History -->
			<div class="e2e-history-section" style="margin-top: 30px;">
				<h3>Moderation Test History
					<button type="button" class="button button-small" id="refresh-moderation-history">Refresh</button>
					<button type="button" class="button button-small e2e-clear-history" data-type="moderation" data-test-type="spam">Clear All</button>
				</h3>
				<div class="e2e-history-table-wrap">
					<table class="e2e-history-table" id="spam-history-table">
						<thead>
							<tr>
								<th>Date</th>
								<th>Type</th>
								<th>Content</th>
								<th>Quality</th>
								<th>Score</th>
								<th>Result</th>
								<th>Time</th>
								<th>Credits</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="spam-history-body">
							<tr><td colspan="9" class="e2e-loading">Loading history...</td></tr>
						</tbody>
					</table>
				</div>
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
				<h2>RAG Indexing Status</h2>
				<p style="color:#666;margin-bottom:15px;">RAG (Retrieval-Augmented Generation) indexes your forum content for AI-powered semantic search, suggestions, and spam detection context.</p>
				<div class="e2e-form-row">
					<button type="button" class="button button-primary e2e-run-test" data-test="rag_status">Refresh Status</button>
				</div>

				<!-- RAG Status Display -->
				<div id="rag-status-display" style="display:none;">
					<div class="e2e-info-grid" style="margin-top:20px;">
						<div class="e2e-info-item">
							<span class="label">Indexing Status:</span>
							<span class="value" id="rag-is-indexing">-</span>
						</div>
						<div class="e2e-info-item">
							<span class="label">Total Indexed:</span>
							<span class="value" id="rag-total-indexed">-</span>
						</div>
						<div class="e2e-info-item">
							<span class="label">Last Indexed:</span>
							<span class="value" id="rag-last-indexed">-</span>
						</div>
						<div class="e2e-info-item">
							<span class="label">Progress:</span>
							<span class="value" id="rag-progress">-</span>
						</div>
					</div>

					<!-- Queue Info -->
					<h3 style="margin-top:20px;font-size:14px;">Queue Status</h3>
					<div class="e2e-info-grid">
						<div class="e2e-info-item">
							<span class="label">Pending:</span>
							<span class="value" id="rag-queue-pending">-</span>
						</div>
						<div class="e2e-info-item">
							<span class="label">Processing:</span>
							<span class="value" id="rag-queue-processing">-</span>
						</div>
						<div class="e2e-info-item">
							<span class="label">Failed:</span>
							<span class="value" id="rag-queue-failed">-</span>
						</div>
					</div>

					<!-- Media Progress -->
					<div id="rag-media-section" style="display:none;">
						<h3 style="margin-top:20px;font-size:14px;">Media Processing (Images & Documents)</h3>
						<div class="e2e-info-grid">
							<div class="e2e-info-item">
								<span class="label">Total Media:</span>
								<span class="value" id="rag-media-total">-</span>
							</div>
							<div class="e2e-info-item">
								<span class="label">Completed:</span>
								<span class="value" id="rag-media-done">-</span>
							</div>
							<div class="e2e-info-item">
								<span class="label">Failed:</span>
								<span class="value" id="rag-media-failed">-</span>
							</div>
							<div class="e2e-info-item">
								<span class="label">Skipped:</span>
								<span class="value" id="rag-media-skipped">-</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Raw JSON (collapsible) -->
				<details style="margin-top:20px;">
					<summary style="cursor:pointer;color:#2271b1;">Show Raw JSON Response</summary>
					<div class="e2e-result-box" id="result-rag" style="margin-top:10px;"></div>
				</details>
			</div>
		</div>

		<!-- Analytics Tab -->
		<div class="e2e-tab-content" data-tab="analytics">
			<div class="e2e-panel e2e-info-panel">
				<h2>AI-Powered Analytics Insights</h2>
				<p style="color:#666;">Analyze your forum content with AI to get actionable insights. Each analysis uses forum posts/topics data.</p>
			</div>

			<!-- Sentiment Analysis -->
			<div class="e2e-analytics-section">
				<div class="e2e-analytics-form">
					<h3>Sentiment Analysis <span class="e2e-credits-badge">2 credits</span></h3>
					<p>Analyze community sentiment across 7 emotions: joy, trust, anticipation, surprise, fear, sadness, anger.</p>
					<div class="e2e-form-row">
						<label>Posts to analyze:</label>
						<select id="analytics-sentiment-limit">
							<option value="50" selected>Last 50 posts</option>
							<option value="100">Last 100 posts</option>
							<option value="200">Last 200 posts</option>
						</select>
						<button type="button" class="button button-primary e2e-run-analytics" data-type="sentiment">Analyze Sentiment</button>
					</div>
				</div>
				<div class="e2e-analytics-result">
					<div class="e2e-result-box" id="result-analytics-sentiment"></div>
				</div>
			</div>

			<!-- Trending Topics -->
			<div class="e2e-analytics-section">
				<div class="e2e-analytics-form">
					<h3>Trending Topics <span class="e2e-credits-badge">1 credit</span></h3>
					<p>Detect what topics and themes are currently trending in your community.</p>
					<div class="e2e-form-row">
						<label>Posts to analyze:</label>
						<select id="analytics-trending-limit">
							<option value="50" selected>Last 50 posts</option>
							<option value="100">Last 100 posts</option>
							<option value="200">Last 200 posts</option>
						</select>
						<button type="button" class="button button-primary e2e-run-analytics" data-type="trending">Find Trending</button>
					</div>
				</div>
				<div class="e2e-analytics-result">
					<div class="e2e-result-box" id="result-analytics-trending"></div>
				</div>
			</div>

			<!-- Growth Recommendations -->
			<div class="e2e-analytics-section">
				<div class="e2e-analytics-form">
					<h3>Growth Recommendations <span class="e2e-credits-badge">3 credits</span></h3>
					<p>Get AI-powered recommendations for growing your community engagement.</p>
					<div class="e2e-form-row">
						<label>Posts to analyze:</label>
						<select id="analytics-recommendations-limit">
							<option value="50" selected>Last 50 posts</option>
							<option value="100">Last 100 posts</option>
							<option value="200">Last 200 posts</option>
						</select>
						<button type="button" class="button button-primary e2e-run-analytics" data-type="recommendations">Get Recommendations</button>
					</div>
				</div>
				<div class="e2e-analytics-result">
					<div class="e2e-result-box" id="result-analytics-recommendations"></div>
				</div>
			</div>

			<!-- Deep Analysis -->
			<div class="e2e-analytics-section">
				<div class="e2e-analytics-form">
					<h3>Deep Analysis <span class="e2e-credits-badge">5 credits</span></h3>
					<p>Comprehensive community deep dive with detailed behavioral patterns and insights.</p>
					<div class="e2e-form-row">
						<label>Posts to analyze:</label>
						<select id="analytics-deep_analysis-limit">
							<option value="50" selected>Last 50 posts</option>
							<option value="100">Last 100 posts</option>
							<option value="200">Last 200 posts</option>
						</select>
						<button type="button" class="button button-primary e2e-run-analytics" data-type="deep_analysis">Run Deep Analysis</button>
					</div>
				</div>
				<div class="e2e-analytics-result">
					<div class="e2e-result-box" id="result-analytics-deep_analysis"></div>
				</div>
			</div>

			<!-- Sentiment Trend -->
			<div class="e2e-analytics-section">
				<div class="e2e-analytics-form">
					<h3>Sentiment Trend <span class="e2e-credits-badge">4 credits</span></h3>
					<p>Track how community sentiment has changed over time periods.</p>
					<div class="e2e-form-row">
						<label>Posts to analyze:</label>
						<select id="analytics-sentiment_trend-limit">
							<option value="50" selected>Last 50 posts</option>
							<option value="100">Last 100 posts</option>
							<option value="200">Last 200 posts</option>
						</select>
						<button type="button" class="button button-primary e2e-run-analytics" data-type="sentiment_trend">Analyze Trend</button>
					</div>
				</div>
				<div class="e2e-analytics-result">
					<div class="e2e-result-box" id="result-analytics-sentiment_trend"></div>
				</div>
			</div>
		</div>
	</div>
</div>
