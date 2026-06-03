(function () {
    'use strict';

    // --- Shared icon SVGs ---
    var AI_CHECK_ICON = '<svg class="haayal-badge-icon" width="13" height="13" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m-2 15-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8z"/></svg>';
    var EDIT_ICON = '<svg class="haayal-badge-icon" width="13" height="13" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75z"/></svg>';
    var REGEN_ICON = '<svg class="haayal-regen-icon" width="14" height="14" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 85.625c-9.822 0-18.728-3.996-25.179-10.447l9.434-9.433c1.428-1.428.417-3.87-1.602-3.87H6.573A4.073 4.073 0 0 0 2.5 65.948v26.08c0 2.02 2.442 3.03 3.87 1.602l10.058-10.058C25.03 92.172 36.904 97.5 50 97.5c24.609 0 44.907-18.812 47.27-42.81.248-2.52-1.778-4.69-4.31-4.69h-3.25c-2.219 0-4.055 1.682-4.296 3.888C83.471 71.709 68.33 85.625 50 85.625M50 2.5C25.39 2.5 5.093 21.311 2.73 45.31 2.482 47.83 4.508 50 7.04 50h3.25c2.219 0 4.055-1.682 4.296-3.888C16.529 28.291 31.67 14.375 50 14.375c9.822 0 18.728 3.996 25.179 10.446l-9.434 9.434c-1.428 1.428-.417 3.87 1.602 3.87h26.08a4.073 4.073 0 0 0 4.073-4.073V7.972c0-2.02-2.442-3.03-3.87-1.602L83.572 16.428C74.97 7.828 63.096 2.5 50 2.5"/></svg>';

    // --- Shared state ---
    var isTranslating = false;
    var slugIsAi = false;
    var slugIsUserEdited = false;
    var titleBlurAttached = false;
    var maxRetries = 20;
    var retryCount = 0;

    // --- Helpers ---

    function hasNonAscii(str) {
        return /[^\x00-\x7F]/.test(str);
    }

    function isDefaultNonLatinSlug(slug) {
        if (!slug) {
            return true;
        }
        if (/%[0-9a-fA-F]{2}/.test(slug)) {
            return true;
        }
        if (hasNonAscii(slug)) {
            return true;
        }
        return false;
    }

    function iconHtml(spinning) {
        return REGEN_ICON.replace('class="haayal-regen-icon"', 'class="haayal-regen-icon' + (spinning ? ' haayal-icon-spin' : '') + '"');
    }

    function getEditorDocument() {
        var iframe = document.querySelector('iframe[name="editor-canvas"]');
        if (iframe && iframe.contentDocument) {
            return iframe.contentDocument;
        }
        return document;
    }

    // --- Core translation function ---

    function doTranslate(title, postId, callback) {
        wp.apiFetch({
            path: '/haayal-ai-slug/v1/translate',
            method: 'POST',
            data: {
                title: title,
                post_id: postId || 0
            }
        }).then(function (response) {
            if (response.slug) {
                wp.data.dispatch('core/editor').editPost({ slug: response.slug });
            }
            if (callback) { callback(response.slug || null); }
        }).catch(function () {
            if (callback) { callback(null); }
        });
    }

    // --- Title blur: auto-translate ---

    function translateSlug(title) {
        if (isTranslating) {
            return;
        }

        if (!hasNonAscii(title)) {
            return;
        }

        if (haayalGutenberg.slugSource === 'user-edited') {
            return;
        }

        var editedSlug = wp.data.select('core/editor').getEditedPostAttribute('slug');

        if (!isDefaultNonLatinSlug(editedSlug)) {
            return;
        }

        isTranslating = true;
        var post = wp.data.select('core/editor').getCurrentPost();

        doTranslate(title, post.id, function (slug) {
            isTranslating = false;
            if (slug) {
                slugIsAi = true;
                updateInlineUI();
                updatePopoverUI();
            }
        });
    }

    function onTitleBlur() {
        var title = wp.data.select('core/editor').getEditedPostAttribute('title');
        if (title && title.trim() !== '') {
            translateSlug(title.trim());
        }
    }

    // --- Blur listener attachment ---

    function attachTitleBlurListener() {
        if (titleBlurAttached) {
            return;
        }

        var editorDoc = getEditorDocument();
        var titleField = editorDoc.querySelector('.editor-post-title__input');

        if (!titleField) {
            retryCount++;
            if (retryCount < maxRetries) {
                setTimeout(attachTitleBlurListener, 500);
            }
            return;
        }

        titleField.addEventListener('blur', onTitleBlur);
        titleBlurAttached = true;
    }

    function watchForIframe() {
        var iframe = document.querySelector('iframe[name="editor-canvas"]');
        if (iframe) {
            if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                attachTitleBlurListener();
            } else {
                iframe.addEventListener('load', function () {
                    retryCount = 0;
                    attachTitleBlurListener();
                });
            }
        } else {
            attachTitleBlurListener();
        }
    }

    // =========================================================
    // DOM injection: inline indicator (next to slug toggle row)
    // =========================================================

    function createIndicatorElement() {
        var wrap = document.createElement('div');
        wrap.className = 'haayal-ai-slug-badge haayal-pill haayal-pill--ai';
        wrap.innerHTML = AI_CHECK_ICON + escapeHtml(haayalGutenberg.i18n.slugGeneratedByAi);
        return wrap;
    }

    function createUserEditedElement() {
        var wrap = document.createElement('div');
        wrap.className = 'haayal-ai-slug-badge haayal-pill haayal-pill--edited';
        wrap.innerHTML = EDIT_ICON + escapeHtml(haayalGutenberg.i18n.slugManuallyEdited);
        return wrap;
    }

    function createInlineRegenerateButton() {
        var wrap = document.createElement('div');
        wrap.className = 'haayal-inline-regenerate';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'haayal-pill haayal-pill-regen haayal-regenerate-btn';
        btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);

        btn.addEventListener('click', function (e) {
            e.stopPropagation(); // Don't open the slug popover.
            if (btn.disabled) { return; }
            btn.disabled = true;
            btn.innerHTML = iconHtml(true) + escapeHtml(haayalGutenberg.i18n.translating);

            var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            if (!currentTitle.trim() || !hasNonAscii(currentTitle)) {
                btn.disabled = false;
                btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);
                return;
            }

            var slugBeforeRegen = (wp.data.select('core/editor').getEditedPostAttribute('slug') || '').trim();
            var postId = wp.data.select('core/editor').getCurrentPostId();
            isTranslating = true;
            doTranslate(currentTitle.trim(), postId, function (slug) {
                isTranslating = false;
                btn.disabled = false;
                btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);
                if (slug) {
                    if (slug.trim() === slugBeforeRegen) {
                        showSameSlugTooltip(btn, haayalGutenberg.i18n.alreadyBestOption);
                    } else {
                        slugIsAi = true;
                        slugIsUserEdited = false;
                        updateInlineUI();
                        updatePopoverUI();
                    }
                }
            });
        });

        wrap.appendChild(btn);
        return wrap;
    }

    var EXTERNAL_LINK_ICON = '<svg class="haayal-badge-icon" width="12" height="12" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 19H5V5h7V3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7h-2zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3z"/></svg>';

    function createBulkTranslateLink() {
        var link = document.createElement('a');
        link.href = haayalGutenberg.bulkUrl;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.className = 'haayal-ai-slug-badge haayal-pill haayal-pill--bulk';
        link.innerHTML = escapeHtml(haayalGutenberg.i18n.bulkTranslate) + ' ' + EXTERNAL_LINK_ICON;
        return link;
    }

    /**
     * Place / update the ✓ badge and regenerate button below the slug toggle in the Summary panel.
     */
    function updateInlineUI() {
        // Remove existing wrapper.
        var oldWrap = document.querySelector('.haayal-ai-slug-inline-wrap');
        if (oldWrap) { oldWrap.remove(); }

        // Find the slug toggle row.
        var toggle = document.querySelector('.editor-post-url__panel-toggle');
        if (!toggle) {
            return;
        }

        var row = toggle.closest('.editor-post-panel__row') || toggle.parentNode;
        if (!row) {
            return;
        }

        var hasAiBadge = slugIsAi;
        var hasUserEditedBadge = slugIsUserEdited;
        var post = wp.data.select('core/editor').getCurrentPost();
        var title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        var editedSlug = wp.data.select('core/editor').getEditedPostAttribute('slug') || post.slug || '';
        var hasRegenerate = (slugIsAi || slugIsUserEdited) && post.status !== 'publish' && post.status !== 'private' && hasNonAscii(title);
        var hasBulkLink = !!haayalGutenberg.bulkUrl && !slugIsAi && !slugIsUserEdited && haayalGutenberg.slugSource !== 'user-edited' && post.status !== 'auto-draft' && isDefaultNonLatinSlug(editedSlug);

        if (!hasAiBadge && !hasUserEditedBadge && !hasRegenerate && !hasBulkLink) {
            return;
        }

        // Create a wrapper that sits below the row.
        var wrap = document.createElement('div');
        wrap.className = 'haayal-ai-slug-inline-wrap';

        if (hasAiBadge) {
            wrap.appendChild(createIndicatorElement());
        }

        if (hasUserEditedBadge) {
            wrap.appendChild(createUserEditedElement());
        }

        if (hasRegenerate) {
            wrap.appendChild(createInlineRegenerateButton());
        }

        if (hasBulkLink) {
            wrap.appendChild(createBulkTranslateLink());
        }

        // Insert after the row, not inside it.
        row.parentNode.insertBefore(wrap, row.nextSibling);
    }

    // =========================================================
    // DOM injection: popover (regenerate button + indicator)
    // =========================================================

    function createPopoverUI() {
        var container = document.createElement('div');
        container.className = 'haayal-ai-slug-popover-ui';

        // Indicator.
        if (slugIsAi) {
            var badge = document.createElement('div');
            badge.className = 'haayal-popover-badge haayal-pill haayal-pill--ai';
            badge.innerHTML = AI_CHECK_ICON + escapeHtml(haayalGutenberg.i18n.slugGeneratedByAi);
            container.appendChild(badge);
        } else if (slugIsUserEdited) {
            var editedBadge = document.createElement('div');
            editedBadge.className = 'haayal-popover-badge haayal-pill haayal-pill--edited';
            editedBadge.innerHTML = EDIT_ICON + escapeHtml(haayalGutenberg.i18n.slugManuallyEdited);
            container.appendChild(editedBadge);
        }

        // Regenerate button — for AI or user-edited slugs, on unpublished posts with non-Latin title.
        var post = wp.data.select('core/editor').getCurrentPost();
        var title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
        var showRegenerate = (slugIsAi || slugIsUserEdited) && post.status !== 'publish' && post.status !== 'private' && hasNonAscii(title);

        if (showRegenerate) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'haayal-pill haayal-pill-regen haayal-regenerate-btn';
            btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);

            btn.addEventListener('mousedown', function (e) {
                e.preventDefault(); // Prevent focus shift so popover stays open.
            });
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (btn.disabled) { return; }
                btn.disabled = true;
                btn.innerHTML = iconHtml(true) + escapeHtml(haayalGutenberg.i18n.translating);

                var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
                if (!currentTitle.trim() || !hasNonAscii(currentTitle)) {
                    btn.disabled = false;
                    btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);
                    return;
                }

                var slugBeforeRegen = (wp.data.select('core/editor').getEditedPostAttribute('slug') || '').trim();
                var postId = wp.data.select('core/editor').getCurrentPostId();
                isTranslating = true;
                doTranslate(currentTitle.trim(), postId, function (slug) {
                    isTranslating = false;
                    btn.disabled = false;
                    btn.innerHTML = iconHtml(false) + escapeHtml(haayalGutenberg.i18n.regenerateSlug);
                    if (slug) {
                        if (slug.trim() === slugBeforeRegen) {
                            showSameSlugTooltip(btn, haayalGutenberg.i18n.alreadyBestOption);
                        } else {
                            slugIsAi = true;
                            slugIsUserEdited = false;
                            updateInlineUI();
                            updatePopoverUI();
                        }
                    }
                });
            });

            container.appendChild(btn);
        }

        return container;
    }

    /**
     * Inject or refresh UI inside the open slug popover.
     * The popover is rendered at body-level with class .editor-post-url__panel-dialog.
     */
    function updatePopoverUI() {
        var popover = document.querySelector('.editor-post-url__panel-dialog');
        if (!popover) {
            return;
        }

        // Find the inner .editor-post-url container.
        var inner = popover.querySelector('.editor-post-url');
        if (!inner) {
            return;
        }

        // Remove old injected UI.
        var oldUI = inner.querySelector('.haayal-ai-slug-popover-ui');
        if (oldUI) {
            oldUI.remove();
        }

        var ui = createPopoverUI();
        // Only inject if there's something to show.
        if (ui.children.length > 0) {
            inner.appendChild(ui);
        }
    }

    // =========================================================
    // Observers
    // =========================================================

    /**
     * Watch for the slug popover to appear (it's rendered at body-level via React portal).
     */
    function startPopoverObserver() {
        var injecting = false;

        var popoverObserver = new MutationObserver(function () {
            if (injecting) { return; }
            var popover = document.querySelector('.editor-post-url__panel-dialog');
            if (popover && !popover.querySelector('.haayal-ai-slug-popover-ui')) {
                injecting = true;
                updatePopoverUI();
                injecting = false;
            }
        });

        popoverObserver.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Watch the sidebar for the slug toggle row to appear, then inject the inline indicator.
     */
    function startSidebarObserver() {
        var injecting = false;

        // Initial attempt.
        updateInlineUI();

        var sidebarObserver = new MutationObserver(function () {
            if (injecting) { return; }

            var toggle = document.querySelector('.editor-post-url__panel-toggle');
            if (!toggle) { return; }

            // Re-inject if our wrapper is missing.
            if (!document.querySelector('.haayal-ai-slug-inline-wrap')) {
                injecting = true;
                updateInlineUI();
                injecting = false;
            }
        });

        sidebarObserver.observe(document.body, { childList: true, subtree: true });
    }

    // --- Utility ---

    function showSameSlugTooltip(el, message) {
        var tip = document.createElement('div');
        tip.className = 'haayal-slug-tooltip';
        tip.textContent = message;
        document.body.appendChild(tip);

        var rect = el.getBoundingClientRect();
        var top  = rect.top - tip.offsetHeight - 6;
        if (top < 4) { top = rect.bottom + 6; }
        var left = rect.left + rect.width / 2 - tip.offsetWidth / 2;
        if (left < 4) { left = 4; }

        tip.style.top  = top + 'px';
        tip.style.left = left + 'px';

        setTimeout(function () {
            tip.style.opacity = '0';
            setTimeout(function () { if (tip.parentNode) { tip.parentNode.removeChild(tip); } }, 300);
        }, 3000);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // --- Init ---

    wp.domReady(function () {
        if (typeof haayalGutenberg === 'undefined') {
            return;
        }

        // Set initial state from PHP.
        slugIsAi = haayalGutenberg.slugSource === 'ai';
        slugIsUserEdited = haayalGutenberg.slugSource === 'user-edited';

        // Watch for user-initiated slug changes (e.g. editing in the popover).
        // Use a grace period to ignore slug changes from Gutenberg's own
        // post-data hydration that fire right after domReady.
        var ignoreSlugChanges = true;
        var lastKnownSlug = wp.data.select('core/editor').getEditedPostAttribute('slug');
        setTimeout(function () {
            // Re-read the slug after Gutenberg settles, so the baseline is accurate.
            lastKnownSlug = wp.data.select('core/editor').getEditedPostAttribute('slug');
            ignoreSlugChanges = false;
        }, 1500);
        wp.data.subscribe(function () {
            var currentSlug = wp.data.select('core/editor').getEditedPostAttribute('slug');
            if (currentSlug === lastKnownSlug) { return; }
            lastKnownSlug = currentSlug;
            if (ignoreSlugChanges) { return; }
            if (!isTranslating && slugIsAi) {
                slugIsAi = false;
                slugIsUserEdited = true;
                updateInlineUI();
                updatePopoverUI();
            }
        });

        // Attach title blur listener (handles iframe).
        watchForIframe();

        var iframeObserver = new MutationObserver(function () {
            if (titleBlurAttached) {
                iframeObserver.disconnect();
                return;
            }
            watchForIframe();
        });

        iframeObserver.observe(document.body, { childList: true, subtree: true });

        setTimeout(function () {
            iframeObserver.disconnect();
        }, 15000);

        // Start DOM observers for UI injection.
        startSidebarObserver();
        startPopoverObserver();
    });
})();
