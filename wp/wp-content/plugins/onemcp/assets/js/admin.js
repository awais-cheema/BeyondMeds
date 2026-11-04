(function () {
	'use strict';

	function textOf(target) {
		if (!target) {
			return '';
		}
		if (target.value !== undefined) {
			return target.value;
		}
		return target.textContent || '';
	}

	function safeBtoa(str) {
		try {
			return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function (match, p1) {
				return String.fromCharCode('0x' + p1);
			}));
		} catch (e) {
			return btoa(str);
		}
	}

	// Modern copy button handler with icon & feedback animation
	document.querySelectorAll('[data-onemcp-copy]').forEach(function (button) {
		button.addEventListener('click', function () {
			var target = document.getElementById(button.getAttribute('data-onemcp-copy'));
			var value = textOf(target);
			if (!value) {
				return;
			}
			var originalHtml = button.innerHTML;
			var done = function () {
				button.classList.add('is-copied');
				button.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:-2px;margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> ' + (button.getAttribute('data-copied') || 'Copied!');
				window.setTimeout(function () {
					button.classList.remove('is-copied');
					button.innerHTML = originalHtml;
				}, 1800);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(done);
				return;
			}
			if (target && target.select) {
				target.select();
				document.execCommand('copy');
				done();
			}
		});
	});

	// Confirmation forms (Bulk write / Clear log)
	document.querySelectorAll('[data-onemcp-confirm]').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm(form.getAttribute('data-onemcp-confirm'))) {
				event.preventDefault();
			}
		});
	});

	// Dynamic config generator
	var configTextarea  = document.getElementById('onemcp-config');
	var passwordInput   = document.getElementById('onemcp-app-password');
	var authHeaderInput = document.getElementById('onemcp-auth-header');
	var generateBtn     = document.getElementById('onemcp-btn-generate');
	var appNameInput    = document.getElementById('onemcp-app-name');
	var noticeBox       = document.getElementById('onemcp-pw-notice');
	var noticeText      = document.getElementById('onemcp-pw-notice-text');
	var ideTitle        = document.getElementById('onemcp-ide-title');
	var pathLabel       = document.getElementById('onemcp-client-path-label');
	var pathValue       = document.getElementById('onemcp-client-path');
	var stepsList       = document.getElementById('onemcp-client-steps');
	var clientTabs      = document.querySelectorAll('[data-onemcp-client]');
	var activeTab       = document.querySelector('.onemcp-client-tab.is-active');
	var activeClientId  = activeTab ? (activeTab.getAttribute('data-onemcp-client') || '') : '';
	var serverName      = 'onemcp';

	function prettyJson(value) {
		return JSON.stringify(value, null, 2);
	}

	function clientById(id) {
		var clients = (typeof onemcpData !== 'undefined' && onemcpData.clients) ? onemcpData.clients : [];
		var index;
		for (index = 0; index < clients.length; index += 1) {
			if (clients[index] && clients[index].id === id) {
				return clients[index];
			}
		}
		return clients.length ? clients[0] : null;
	}

	function snippetFor(format, endpoint, auth) {
		var header = 'Authorization: ' + auth;
		var remote = {};
		if (format === 'claude-desktop') {
			remote[serverName] = {
				command: 'npx',
				args: [
					'-y',
					'mcp-remote',
					endpoint,
					'--header',
					header
				]
			};
			return prettyJson({ mcpServers: remote });
		}
		if (format === 'grok') {
			return 'grok mcp add --transport http ' + serverName + ' "' + endpoint + '" --header "' + header + '"';
		}
		if (format === 'cursor' || format === 'antigravity') {
			remote[serverName] = {
				url: endpoint,
				headers: { Authorization: auth }
			};
			return prettyJson({ mcpServers: remote });
		}
		if (format === 'vscode') {
			remote[serverName] = {
				type: 'http',
				url: endpoint,
				headers: { Authorization: auth }
			};
			return prettyJson({ servers: remote });
		}
		if (format === 'codex') {
			return '[mcp_servers."' + serverName + '"]\nurl = "' + endpoint + '"\nhttp_headers = { Authorization = "' + auth + '" }\n';
		}
		if (format === 'opencode') {
			remote[serverName] = {
				type: 'remote',
				url: endpoint,
				enabled: true,
				headers: { Authorization: auth }
			};
			return prettyJson({
				$schema: 'https://opencode.ai/config.json',
				mcp: remote
			});
		}
		if (format === 'gemini') {
			remote[serverName] = {
				httpUrl: endpoint,
				headers: { Authorization: auth }
			};
			return prettyJson({ mcpServers: remote });
		}
		if (format === 'windsurf') {
			remote[serverName] = {
				serverUrl: endpoint,
				headers: { Authorization: auth }
			};
			return prettyJson({ mcpServers: remote });
		}
		remote[serverName] = {
			type: 'http',
			url: endpoint,
			headers: { Authorization: auth }
		};
		return prettyJson({ mcpServers: remote });
	}

	function renderClient(password) {
		if (!configTextarea || typeof onemcpData === 'undefined') {
			return;
		}
		var username = onemcpData.username || '';
		var endpoint = onemcpData.endpoint || '';
		var cleanPassword = (password || '').replace(/\s+/g, '');
		var authToken = '';

		if (cleanPassword) {
			authToken = safeBtoa(username + ':' + cleanPassword);
		}

		var auth = 'Basic ' + (authToken || 'YOUR_BASE64_USERNAME_COLON_APPLICATION_PASSWORD');
		if (authHeaderInput) {
			authHeaderInput.value = authToken ? 'Basic ' + authToken : '';
		}

		var client = clientById(activeClientId);
		var format = client && client.format ? client.format : 'other';
		configTextarea.value = snippetFor(format, endpoint, auth);
		var lines = configTextarea.value.split('\n').length;
		configTextarea.rows = lines < 8 ? 8 : (lines > 18 ? 18 : lines + 1);

		if (ideTitle) {
			ideTitle.textContent = client && client.title ? client.title : 'mcp.json';
		}
		if (pathLabel) {
			pathLabel.textContent = client && client.pathLabel ? client.pathLabel : '';
		}
		if (pathValue) {
			pathValue.textContent = client && client.path ? client.path : '';
		}
		if (stepsList) {
			while (stepsList.firstChild) {
				stepsList.removeChild(stepsList.firstChild);
			}
			var steps = client && client.steps ? client.steps : [];
			var stepIndex;
			for (stepIndex = 0; stepIndex < steps.length; stepIndex += 1) {
				var item = document.createElement('li');
				item.textContent = steps[stepIndex];
				stepsList.appendChild(item);
			}
		}
	}

	function updateConfigWithPassword(password) {
		renderClient(password);
	}

	clientTabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			activeClientId = tab.getAttribute('data-onemcp-client') || '';
			clientTabs.forEach(function (other) {
				var selected = other === tab;
				other.classList.toggle('is-active', selected);
				other.setAttribute('aria-selected', selected ? 'true' : 'false');
			});
			renderClient(passwordInput ? passwordInput.value : '');
		});
	});

	renderClient(passwordInput ? passwordInput.value : '');

	// Listen for manual typing or pasting into the password field
	if (passwordInput) {
		passwordInput.addEventListener('input', function () {
			updateConfigWithPassword(passwordInput.value);
		});
	}

	// Generate password via AJAX
	if (generateBtn && typeof onemcpData !== 'undefined') {
		generateBtn.addEventListener('click', function () {
			var originalHtml = generateBtn.innerHTML;
			generateBtn.disabled = true;
			generateBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="onemcp-spin" style="display:inline-block;vertical-align:-2px;margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg> ' + ((onemcpData.i18n && onemcpData.i18n.generating) ? onemcpData.i18n.generating : 'Generating...');

			var appName = appNameInput ? appNameInput.value.trim() : '';
			var formData = new FormData();
			formData.append('action', 'onemcp_generate_app_password');
			formData.append('nonce', onemcpData.nonce);
			if (appName) {
				formData.append('name', appName);
			}

			fetch(onemcpData.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				generateBtn.disabled = false;
				generateBtn.innerHTML = originalHtml;

				if (!data || !data.success) {
					var errMsg = (data && data.data && data.data.message) ? data.data.message : ((onemcpData.i18n && onemcpData.i18n.genericError) ? onemcpData.i18n.genericError : 'Failed to generate password.');
					alert(errMsg);
					return;
				}

				var result = data.data;
				if (passwordInput) {
					passwordInput.value = result.chunked_password || result.password;
				}

				if (noticeBox && noticeText) {
					var msg = (onemcpData.i18n && onemcpData.i18n.passwordGenerated) ? onemcpData.i18n.passwordGenerated : 'Application Password generated. Copy it now — it will not be shown again.';
					noticeText.textContent = msg;
					noticeBox.style.display = 'flex';
				}

				updateConfigWithPassword(result.password);

				// Visual feedback: highlight the configuration editor in Step 2
				if (configTextarea) {
					var ideBox = configTextarea.closest('.onemcp-ide-window') || configTextarea;
					ideBox.classList.add('onemcp-highlight');
					window.setTimeout(function () {
						ideBox.classList.remove('onemcp-highlight');
					}, 2000);
				}
			})
			.catch(function () {
				generateBtn.disabled = false;
				generateBtn.innerHTML = originalHtml;
				alert((onemcpData.i18n && onemcpData.i18n.genericError) ? onemcpData.i18n.genericError : 'Failed to generate password.');
			});
		});
	}

	// =========================================================================
	// Test Connection via AJAX without reload
	// =========================================================================
	var testBtn = document.getElementById('onemcp-btn-test-connection');
	var testResultBox = document.getElementById('onemcp-test-result');

	function escapeText(str) {
		var p = document.createElement('p');
		p.textContent = str || '';
		return p.innerHTML;
	}

	if (testBtn && typeof onemcpData !== 'undefined') {
		testBtn.addEventListener('click', function (event) {
			event.preventDefault();
			var originalHtml = testBtn.innerHTML;
			testBtn.disabled = true;
			var testingText = (onemcpData.i18n && onemcpData.i18n.testing) ? onemcpData.i18n.testing : 'Testing server...';
			testBtn.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="onemcp-spin" style="display:inline-block;vertical-align:-2px;margin-right:6px;"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg> ' + testingText;

			var formData = new FormData();
			formData.append('action', 'onemcp_test_connection');
			formData.append('nonce', onemcpData.nonce);

			fetch(onemcpData.ajaxUrl, {
				method: 'POST',
				body: formData
			})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				testBtn.disabled = false;
				testBtn.innerHTML = originalHtml;

				var isOk = false;
				var message = '';
				var when = '';

				if (data && data.success && data.data) {
					isOk = !!data.data.ok;
					message = data.data.message || '';
					when = data.data.when || '';
				} else {
					message = (data && data.data && data.data.message) ? data.data.message : ((onemcpData.i18n && onemcpData.i18n.testFailed) ? onemcpData.i18n.testFailed : 'Connection test failed.');
				}

				if (testResultBox) {
					testResultBox.className = 'onemcp-alert ' + (isOk ? 'onemcp-alert-success' : 'onemcp-alert-error');
					var checkSvg = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
					var alertSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
					var iconHtml = '<span class="onemcp-alert-icon">' + (isOk ? checkSvg : alertSvg) + '</span>';

					var textHtml = '<span id="onemcp-test-result-text">' + escapeText(message);
					if (when) {
						textHtml += ' <span style="opacity:0.8;font-size:12px;">(' + escapeText(when) + ')</span>';
					}
					textHtml += '</span>';

					testResultBox.innerHTML = iconHtml + textHtml;
					testResultBox.style.display = 'flex';
				}
			})
			.catch(function () {
				testBtn.disabled = false;
				testBtn.innerHTML = originalHtml;
				if (testResultBox) {
					testResultBox.className = 'onemcp-alert onemcp-alert-error';
					var alertSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
					testResultBox.innerHTML = '<span class="onemcp-alert-icon">' + alertSvg + '</span><span id="onemcp-test-result-text">' + escapeText((onemcpData.i18n && onemcpData.i18n.testFailed) ? onemcpData.i18n.testFailed : 'Connection test failed. Please try again.') + '</span>';
					testResultBox.style.display = 'flex';
				}
			});
		});
	}

	// =========================================================================
	// Integrations Tab: Live Search & Category Filter
	// =========================================================================
	var intSearchInput = document.getElementById('onemcp-integrations-search');
	var intClearBtn    = document.getElementById('onemcp-integrations-clear');
	var intPills       = document.querySelectorAll('#onemcp-integrations-pills .onemcp-filter-pill');
	var intCards       = document.querySelectorAll('#onemcp-integrations-container .onemcp-product-card');
	var intSections    = document.querySelectorAll('#onemcp-integrations-container .onemcp-category-section');
	var intCountText   = document.getElementById('onemcp-integrations-count-text');
	var intEmptyBox    = document.getElementById('onemcp-integrations-empty');
	var intActiveFilter = 'all';

	function filterIntegrations() {
		var query = intSearchInput ? intSearchInput.value.trim().toLowerCase() : '';
		if (intClearBtn) {
			intClearBtn.style.display = query ? 'flex' : 'none';
		}

		var visibleTotal = 0;
		intSections.forEach(function (sec) {
			var secCards = sec.querySelectorAll('.onemcp-product-card');
			var secVisible = 0;

			secCards.forEach(function (card) {
				var cardGroup = card.getAttribute('data-group');
				var isActive = card.getAttribute('data-active') === '1';
				var cardText = card.getAttribute('data-name') || '';

				var groupMatches = false;
				if (intActiveFilter === 'all') {
					groupMatches = true;
				} else if (intActiveFilter === 'active') {
					groupMatches = isActive;
				} else {
					groupMatches = (cardGroup === intActiveFilter);
				}

				var searchMatches = !query || cardText.indexOf(query) !== -1;

				if (groupMatches && searchMatches) {
					card.style.display = '';
					secVisible++;
					visibleTotal++;
				} else {
					card.style.display = 'none';
				}
			});

			sec.style.display = secVisible > 0 ? '' : 'none';
		});

		if (intCountText) {
			intCountText.textContent = 'Showing ' + visibleTotal + ' supported integrations' + (query ? ' matching "' + query + '"' : '');
		}

		if (intEmptyBox) {
			intEmptyBox.style.display = (visibleTotal === 0) ? 'block' : 'none';
		}
	}

	if (intSearchInput) {
		intSearchInput.addEventListener('input', filterIntegrations);
	}
	if (intClearBtn) {
		intClearBtn.addEventListener('click', function () {
			intSearchInput.value = '';
			filterIntegrations();
			intSearchInput.focus();
		});
	}

	intPills.forEach(function (pill) {
		pill.addEventListener('click', function () {
			intPills.forEach(function (p) { p.classList.remove('is-active'); });
			pill.classList.add('is-active');
			intActiveFilter = pill.getAttribute('data-group') || 'all';
			filterIntegrations();
		});
	});

	// =========================================================================
	// Abilities Tab: Live Search, Type Filter, Category Quick Jumper & Switches
	// =========================================================================
	var abSearchInput    = document.getElementById('onemcp-abilities-search');
	var abClearBtn       = document.getElementById('onemcp-abilities-clear');
	var abTypePills      = document.querySelectorAll('#onemcp-abilities-type-pills .onemcp-filter-pill');
	var abCatPills       = document.querySelectorAll('.onemcp-cat-pill');
	var abSections       = document.querySelectorAll('#onemcp-abilities-container .onemcp-category-section');
	var abCountText      = document.getElementById('onemcp-abilities-count-text');
	var abEmptyBox       = document.getElementById('onemcp-abilities-empty');
	var abStickyCounter  = document.getElementById('onemcp-sticky-counter');
	var abUnsavedBadge   = document.getElementById('onemcp-unsaved-indicator');
	var abActiveType     = 'all';
	var abActiveCategory = 'all';

	// Preselect category from URL if ?cat=slug is present
	var urlParams = new URLSearchParams(window.location.search);
	var initialCat = urlParams.get('cat');
	if (initialCat) {
		abActiveCategory = initialCat;
		abCatPills.forEach(function (p) {
			p.classList.toggle('is-active', p.getAttribute('data-cat') === initialCat);
		});
	}

	function filterAbilities() {
		var query = abSearchInput ? abSearchInput.value.trim().toLowerCase() : '';
		if (abClearBtn) {
			abClearBtn.style.display = query ? 'flex' : 'none';
		}

		var visibleTotal = 0;
		abSections.forEach(function (sec) {
			var catSlug = sec.getAttribute('data-cat-slug');
			var catMatches = (abActiveCategory === 'all' || abActiveCategory === catSlug);
			var secRows = sec.querySelectorAll('.onemcp-ability-row');
			var secVisible = 0;

			if (!catMatches) {
				sec.style.display = 'none';
				return;
			}

			secRows.forEach(function (row) {
				var rowType = row.getAttribute('data-type');
				var switchEl = row.querySelector('.onemcp-switch-input');
				var isRowActive = switchEl ? switchEl.checked : false;
				var rowText = row.getAttribute('data-search') || '';

				var typeMatches = false;
				if (abActiveType === 'all') {
					typeMatches = true;
				} else if (abActiveType === 'read') {
					typeMatches = (rowType === 'read');
				} else if (abActiveType === 'write') {
					typeMatches = (rowType === 'write');
				} else if (abActiveType === 'active') {
					typeMatches = isRowActive;
				} else if (abActiveType === 'inactive') {
					typeMatches = !isRowActive;
				}

				var searchMatches = !query || rowText.indexOf(query) !== -1;

				if (typeMatches && searchMatches) {
					row.style.display = '';
					secVisible++;
					visibleTotal++;
				} else {
					row.style.display = 'none';
				}
			});

			sec.style.display = secVisible > 0 ? '' : 'none';
		});

		if (abCountText) {
			abCountText.textContent = 'Showing ' + visibleTotal + ' abilities' + (query ? ' matching "' + query + '"' : '');
		}

		if (abEmptyBox) {
			abEmptyBox.style.display = (visibleTotal === 0) ? 'block' : 'none';
		}
	}

	if (abSearchInput) {
		abSearchInput.addEventListener('input', filterAbilities);
	}
	if (abClearBtn) {
		abClearBtn.addEventListener('click', function () {
			abSearchInput.value = '';
			filterAbilities();
			abSearchInput.focus();
		});
	}

	abTypePills.forEach(function (pill) {
		pill.addEventListener('click', function () {
			abTypePills.forEach(function (p) { p.classList.remove('is-active'); });
			pill.classList.add('is-active');
			abActiveType = pill.getAttribute('data-type') || 'all';
			filterAbilities();
		});
	});

	abCatPills.forEach(function (pill) {
		pill.addEventListener('click', function () {
			abCatPills.forEach(function (p) { p.classList.remove('is-active'); });
			pill.classList.add('is-active');
			abActiveCategory = pill.getAttribute('data-cat') || 'all';
			filterAbilities();
		});
	});

	// Accordion toggle on category header
	document.querySelectorAll('.onemcp-category-toggle-header').forEach(function (header) {
		header.addEventListener('click', function (e) {
			if (e.target.closest('.onemcp-cat-actions') || e.target.closest('.onemcp-cat-quick-btn')) {
				return;
			}
			var section = header.closest('.onemcp-category-section');
			if (section) {
				section.classList.toggle('is-collapsed');
			}
		});
	});

	// Per-category quick enable / disable buttons
	document.querySelectorAll('.onemcp-cat-quick-btn').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var action = btn.getAttribute('data-cat-action');
			var catSlug = btn.getAttribute('data-cat');
			var section = document.querySelector('.onemcp-category-section[data-cat-slug="' + catSlug + '"]');
			if (!section) return;

			var switches = section.querySelectorAll('.onemcp-switch-input:not(:disabled)');
			switches.forEach(function (sw) {
				sw.checked = (action === 'enable');
			});

			updateAbilitiesStats();
			markUnsaved();
		});
	});

	// Switch toggle change listeners
	function updateAbilitiesStats() {
		var total = document.querySelectorAll('.onemcp-switch-input').length;
		var enabled = document.querySelectorAll('.onemcp-switch-input:checked').length;
		if (abStickyCounter) {
			abStickyCounter.textContent = enabled + ' of ' + total + ' tools enabled';
		}

		// Update per-category pills
		document.querySelectorAll('.onemcp-category-section').forEach(function (sec) {
			var slug = sec.getAttribute('data-cat-slug');
			var pill = document.getElementById('cat-count-' + slug);
			if (pill) {
				var catTotal = sec.querySelectorAll('.onemcp-switch-input').length;
				var catActive = sec.querySelectorAll('.onemcp-switch-input:checked').length;
				pill.innerHTML = catTotal + ' tools • <strong style="color:var(--onemcp-success-dark);">' + catActive + ' active</strong>';
			}
		});
	}

	function markUnsaved() {
		if (abUnsavedBadge) {
			abUnsavedBadge.style.display = 'inline-flex';
		}
	}

	document.querySelectorAll('.onemcp-switch-input').forEach(function (input) {
		input.addEventListener('change', function () {
			updateAbilitiesStats();
			markUnsaved();
		});
	});

	// Click to copy tool name
	document.querySelectorAll('[data-onemcp-copy-text]').forEach(function (badge) {
		badge.addEventListener('click', function () {
			var text = badge.getAttribute('data-onemcp-copy-text');
			if (!text) return;
			var originalHtml = badge.innerHTML;

			var done = function () {
				badge.classList.add('is-copied');
				badge.innerHTML = text + ' ✓ Copied!';
				window.setTimeout(function () {
					badge.classList.remove('is-copied');
					badge.innerHTML = originalHtml;
				}, 1500);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(done);
			} else {
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				done();
			}
		});
	});

	// Initial filter run if needed
	if (initialCat) {
		filterAbilities();
	}

	/**
	 * OneMCP Admin SPA Tab Handling (matches FormLayer)
	 */
	function handle_tabs() {
		var hash_full = window.location.hash.trim().replace(/^#/, '');
		var hash_parts = hash_full.split(/[?&]/);
		var hash = hash_parts[0].split('/')[0];
		var nav = document.getElementById('onemcp-main-nav');
		if (!nav) {
			return;
		}

		// Handle hash or query param
		if (!hash) {
			var url_params = new URLSearchParams(window.location.search);
			hash = url_params.get('tab') || 'connection';
		}

		var target_tab = document.getElementById('onemcp-tab-' + hash);
		var nav_item = nav.querySelector('a[data-tab="' + hash + '"]');

		if (target_tab) {
			// Hide all tabs
			var all_tabs = document.querySelectorAll('.onemcp-tab-content');
			all_tabs.forEach(function (t) {
				t.style.display = 'none';
				t.classList.remove('is-active');
			});

			// Show target tab
			target_tab.style.display = 'block';
			target_tab.classList.add('is-active');

			// Update nav state
			var nav_items = nav.querySelectorAll('.onemcp-nav-item');
			nav_items.forEach(function (item) {
				item.classList.remove('is-active');
			});
			if (nav_item) {
				nav_item.classList.add('is-active');
			}

			// Handle category parameter when switching to abilities
			if (hash === 'abilities') {
				var catSlug = 'all';
				if (hash_parts.length > 1) {
					for (var i = 1; i < hash_parts.length; i++) {
						var pair = hash_parts[i].split('=');
						if (pair[0] === 'cat') {
							catSlug = decodeURIComponent(pair[1] || '');
						}
					}
				} else {
					var url_params = new URLSearchParams(window.location.search);
					if (url_params.get('cat')) {
						catSlug = url_params.get('cat');
					}
				}
				if (typeof abCatPills !== 'undefined' && abCatPills && abCatPills.length) {
					abActiveCategory = catSlug;
					abCatPills.forEach(function (p) {
						p.classList.toggle('is-active', p.getAttribute('data-cat') === catSlug);
					});
					if (typeof filterAbilities === 'function') {
						filterAbilities();
					}
				}
			}
		} else {
			// Fallback for default 'connection'
			var default_tab = document.getElementById('onemcp-tab-connection');
			if (default_tab) {
				var all_tabs = document.querySelectorAll('.onemcp-tab-content');
				all_tabs.forEach(function (t) {
					t.style.display = 'none';
					t.classList.remove('is-active');
				});
				default_tab.style.display = 'block';
				default_tab.classList.add('is-active');

				var nav_items = nav.querySelectorAll('.onemcp-nav-item');
				nav_items.forEach(function (item) {
					item.classList.remove('is-active');
				});
				var default_nav = nav.querySelector('a[data-tab="connection"]');
				if (default_nav) {
					default_nav.classList.add('is-active');
				}
			}
		}
	}

	// Tab change listener
	window.addEventListener('hashchange', function () {
		handle_tabs();
	});

	// Initial tab load
	handle_tabs();
}());
