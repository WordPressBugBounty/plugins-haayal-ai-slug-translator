jQuery(document).ready(function($) {
    var config   = haayalBulk;
    var i18n     = config.i18n;
    var items    = [];

    // --- Regenerate button icon + helpers ---
    var REGEN_ICON = '<svg class="haayal-regen-icon" width="13" height="13" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M50 85.625c-9.822 0-18.728-3.996-25.179-10.447l9.434-9.433c1.428-1.428.417-3.87-1.602-3.87H6.573A4.073 4.073 0 0 0 2.5 65.948v26.08c0 2.02 2.442 3.03 3.87 1.602l10.058-10.058C25.03 92.172 36.904 97.5 50 97.5c24.609 0 44.907-18.812 47.27-42.81.248-2.52-1.778-4.69-4.31-4.69h-3.25c-2.219 0-4.055 1.682-4.296 3.888C83.471 71.709 68.33 85.625 50 85.625M50 2.5C25.39 2.5 5.093 21.311 2.73 45.31 2.482 47.83 4.508 50 7.04 50h3.25c2.219 0 4.055-1.682 4.296-3.888C16.529 28.291 31.67 14.375 50 14.375c9.822 0 18.728 3.996 25.179 10.446l-9.434 9.434c-1.428 1.428-.417 3.87 1.602 3.87h26.08a4.073 4.073 0 0 0 4.073-4.073V7.972c0-2.02-2.442-3.03-3.87-1.602L83.572 16.428C74.97 7.828 63.096 2.5 50 2.5"/></svg>';

    function iconHtml(spinning) {
        return REGEN_ICON.replace('class="haayal-regen-icon"', 'class="haayal-regen-icon' + (spinning ? ' haayal-icon-spin' : '') + '"');
    }

    function setRegenContent(btn, spinning) {
        btn.innerHTML = iconHtml(spinning);
        btn.appendChild(document.createTextNode(spinning ? i18n.translating : i18n.regenerate));
    }

    function showBulkSameSlugTooltip(el, message) {
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

        setTimeout(function() {
            tip.style.opacity = '0';
            setTimeout(function() { if (tip.parentNode) { tip.parentNode.removeChild(tip); } }, 300);
        }, 3000);
    }

    var stopped  = false;
    var currentSourceType = '';
    var currentSourceName = '';
    var currentPage       = 1;
    var bulkTotal         = 0;
    var bulkPages         = 0;

    // --- Populate source selector ---
    var $select = $('#haayal-bulk-source-select');

    function populateSelect(postTypes, taxonomies) {
        var currentVal = $select.val();
        $select.find('optgroup').remove();

        if (postTypes.length) {
            var $ptGroup = $('<optgroup>').attr('label', 'Post Types');
            $.each(postTypes, function(_, pt) {
                $ptGroup.append($('<option>').val('post_type:' + pt.name).text(pt.label));
            });
            $select.append($ptGroup);
        }

        if (taxonomies.length) {
            var $taxGroup = $('<optgroup>').attr('label', 'Taxonomies');
            $.each(taxonomies, function(_, tax) {
                $taxGroup.append($('<option>').val('taxonomy:' + tax.name).text(tax.label));
            });
            $select.append($taxGroup);
        }

        // Restore previous selection if still available.
        if (currentVal && $select.find('option[value="' + currentVal + '"]').length) {
            $select.val(currentVal);
        } else {
            $select.val('');
        }
    }

    // Initial populate from localized data.
    populateSelect(config.enabled_post_types, config.enabled_taxonomies);

    // --- Dynamic redirect note ---
    var $redirectNote = $('.haayal-bulk-redirect-note');
    var $redirectCb   = $('#haayal-bulk-redirects');
    function updateRedirectNote() {
        var isOn = $redirectCb.is(':checked');
        $redirectNote.text( isOn ? $redirectNote.data('note-on') : $redirectNote.data('note-off') );
        $redirectNote.toggleClass('is-off', !isOn);
    }
    updateRedirectNote();
    $redirectCb.on('change', updateRedirectNote);

    // Refresh sources when Bulk tab is activated.
    $('#tab-btn-bulk').on('click', function() {
        $.post(config.ajax_url, {
            action: 'haayal_bulk_get_sources',
            nonce:  config.nonce
        }, function(response) {
            if (response.success) {
                config.enabled_post_types = response.data.enabled_post_types;
                config.enabled_taxonomies = response.data.enabled_taxonomies;
                config.connection_method  = response.data.connection_method;
                config.proxy_quota        = response.data.proxy_quota;
                populateSelect(config.enabled_post_types, config.enabled_taxonomies);
                refreshProxyWarning();
            }
        });
    });

    // Show proxy warning if applicable.
    refreshProxyWarning();

    function refreshProxyWarning() {
        if (config.connection_method === 'proxy') {
            updateProxyWarning(config.proxy_quota);
        } else {
            $('#haayal-bulk-proxy-warning').hide();
        }
    }

    // --- Source select change ---
    $select.on('change', function() {
        var val = $(this).val();
        if (!val) {
            resetUI();
            return;
        }

        var parts = val.split(':');
        currentSourceType = parts[0];
        currentSourceName = parts[1];
        currentPage = 1;
        loadItems();
    });

    // --- Load items ---
    function loadItems() {
        showMessage(i18n.loading, 'info', true);
        $('#haayal-bulk-table').hide();
        $('#haayal-bulk-count').hide();
        $('#haayal-bulk-pagination').hide();
        $('#haayal-bulk-progress').hide();
        $('#haayal-bulk-summary').hide();
        hideAllActions();

        $.post(config.ajax_url, {
            action:      'haayal_bulk_load_items',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName,
            page:        currentPage
        }, function(response) {
            if (!response.success) {
                showMessage(response.data.message || i18n.error, 'error');
                return;
            }

            var data = response.data;
            items = data.items;

            if (!items.length) {
                showMessage(i18n.no_items, 'info');
                checkExcludedSection();
                return;
            }

            clearMessage();
            renderTable();
            updateBulkCount(data.total, data.pages, data.page);
            renderPagination(data.pages, data.page);
            showTranslateBtn();
            checkExcludedSection();
        }).fail(function() {
            showMessage(i18n.error, 'error');
        });
    }

    // --- Render table ---
    function renderTable() {
        var $tbody = $('#haayal-bulk-table tbody').empty();

        $.each(items, function(_, item) {
            var $row = $('<tr>').attr('data-id', item.id);

            $row.append($('<td>').addClass('column-title').append(
                $('<a>').attr('href', item.edit_url).attr('target', '_blank').text(item.title)
            ));

            $row.append($('<td>').addClass('column-current-slug').text(item.current_slug));

            $row.append($('<td>').addClass('column-new-slug').append($('<div>').addClass('haayal-bulk-slug-placeholder')));

            var $excludeBtn = $('<button>').attr('type', 'button')
                .addClass('button-link haayal-bulk-keep-btn')
                .text(i18n.exclude);
            $row.append($('<td>').addClass('column-actions').append($excludeBtn));

            $tbody.append($row);
        });

        $('#haayal-bulk-table').show();
    }

    // --- Keep Original: animate row toward excluded section, then remove ---
    $(document).on('click', '.haayal-bulk-keep-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var id   = parseInt($row.attr('data-id'), 10);

        // Animate row sliding down toward excluded list.
        $row.addClass('haayal-excluding');

        // Remove from items array and update count.
        items = items.filter(function(item) { return item.id !== id; });
        adjustBulkCount(-1);

        // After animation ends, remove the row.
        setTimeout(function() {
            $row.remove();

            // If table is now empty, show message and hide actions.
            if ($('#haayal-bulk-table tbody tr').length === 0) {
                showMessage(i18n.no_items, 'info');
                $('#haayal-bulk-table').hide();
                hideAllActions();
            }
        }, 650);

        // Persist dismissal via AJAX.
        $.post(config.ajax_url, {
            action:      'haayal_bulk_dismiss',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName,
            item_id:     id
        }, function() {
            checkExcludedSection(true);
        });
    });

    // --- Pagination ---
    function renderPagination(totalPages, currentPg) {
        var $pag = $('#haayal-bulk-pagination').empty();
        if (totalPages <= 1) {
            $pag.hide();
            return;
        }

        for (var p = 1; p <= totalPages; p++) {
            var $btn = $('<button>').attr('type', 'button')
                .addClass('button haayal-bulk-page-btn')
                .text(p)
                .attr('data-page', p);
            if (p === currentPg) {
                $btn.addClass('button-primary');
            }
            $pag.append($btn);
        }
        $pag.show();
    }

    $(document).on('click', '.haayal-bulk-page-btn', function() {
        currentPage = parseInt($(this).attr('data-page'), 10);
        loadItems();
    });

    // --- Translate All ---
    $('#haayal-bulk-translate-btn').on('click', function() {
        var toTranslate = [];
        $('#haayal-bulk-table tbody tr').each(function() {
            var $row = $(this);
            var id = parseInt($row.attr('data-id'), 10);
            var item = findItem(id);
            if (item) {
                toTranslate.push({ id: item.id, title: item.title, $row: $row });
            }
        });

        if (!toTranslate.length) return;

        stopped = false;
        startProgress(i18n.translating, toTranslate.length);
        hideAllActions();
        showStop();
        disablePagination();
        $('#haayal-bulk-table tbody .haayal-bulk-keep-btn').prop('disabled', true);

        var batches = chunkArray(toTranslate, parseInt(config.translate_batch, 10) || 1);
        processBatches(batches, 0, 'translate', toTranslate.length);
    });

    // --- Save All ---
    $('#haayal-bulk-save-btn').on('click', function() {
        var toSave = [];
        $('#haayal-bulk-table tbody tr').each(function() {
            var $row = $(this);
            var slugVal = $row.find('.haayal-bulk-slug-input').val();
            var slug = slugVal ? slugVal.trim() : '';
            if (!slug) return;

            toSave.push({
                id:          parseInt($row.attr('data-id'), 10),
                new_slug:    slug,
                source_type: currentSourceType,
                source_name: currentSourceName,
                $row:        $row
            });
        });

        if (!toSave.length) return;

        stopped = false;
        lockTable();
        startSaveProgress();
        hideAllActions();
        $('.haayal-bulk-footer').show();
        $('#haayal-bulk-save-btn').show().prop('disabled', true);

        var batches = chunkArray(toSave, parseInt(config.save_batch, 10) || 5);
        processBatches(batches, 0, 'save', toSave.length);
    });

    // --- Stop ---
    $('#haayal-bulk-stop-btn').on('click', function() {
        stopped = 'user';
    });

    // --- Batch processor ---
    var totalProcessed = 0;
    var totalFailed    = 0;
    var totalRedirects = 0;

    function processBatches(batches, index, mode, totalItems) {
        if (index === 0) {
            totalProcessed = 0;
            totalFailed    = 0;
            totalRedirects = 0;
        }

        if (stopped) {
            finishProgress(stopped === 'quota' ? i18n.quota_exhausted : i18n.stopped);
            hideStop();
            // Show the appropriate button after stop.
            if (mode === 'translate') {
                if (totalProcessed > 0) {
                    var cta = totalProcessed + ' ' + i18n.translated + '. ' + i18n.translate_cta;
                    showSummary(cta);
                    showSaveBtn();
                    updateGlobalCounter(totalProcessed);
                } else {
                    showTranslateBtn();
                }
            } else {
                unlockTable();
                showSaveBtn();
                if (totalRedirects > 0) {
                    $(document).trigger('haayal:redirects:reload');
                }
            }
            return;
        }

        if (index >= batches.length) {
            var summary = totalProcessed + ' ' + (mode === 'translate' ? i18n.translated : i18n.saved);
            if (totalFailed > 0) {
                summary += ', ' + totalFailed + ' ' + i18n.failed;
            }
            if (mode === 'save' && totalRedirects > 0) {
                summary += ', ' + totalRedirects + ' ' + i18n.redirects_created;
            }
            finishProgress(i18n.complete + ' ' + summary);
            hideStop();

            if (mode === 'translate') {
                if (totalProcessed === 0) {
                    showSummary(i18n.translate_none, 'error');
                    showTranslateBtn();
                } else {
                    var cta = totalProcessed + ' ' + i18n.translated + '. ' + i18n.translate_cta;
                    showSummary(cta);
                    showSaveBtn();
                    updateGlobalCounter(totalProcessed);
                }
            } else {
                unlockTable();
                showSummary(summary);
                // Reload to refresh pagination — saved items no longer have non-Latin slugs.
                reloadAfterSave();
                // Refresh redirects tab if redirects were created.
                if (totalRedirects > 0) {
                    $(document).trigger('haayal:redirects:reload');
                }
            }
            return;
        }

        var batch = batches[index];
        var ajaxData = { nonce: config.nonce };

        if (mode === 'translate') {
            ajaxData.action = 'haayal_bulk_translate';
            ajaxData.source_type = currentSourceType;
            ajaxData.source_name = currentSourceName;
            ajaxData.items = batch.map(function(b) {
                return { id: b.id, title: b.title };
            });
        } else {
            ajaxData.action = 'haayal_bulk_save';
            ajaxData.enable_redirects = $('#haayal-bulk-redirects').is(':checked') ? 'true' : 'false';
            ajaxData.items = batch.map(function(b) {
                return { id: b.id, new_slug: b.new_slug, source_type: b.source_type, source_name: b.source_name };
            });
        }

        // Show per-item spinner for current batch.
        if (mode === 'translate') {
            $.each(batch, function(_, batchItem) {
                batchItem.$row.find('.column-new-slug').empty().append(
                    $('<span>').addClass('haayal-bulk-spinner').html('<span class="spinner is-active"></span>')
                );
            });
        }

        $.ajax({
            url: config.ajax_url,
            type: 'POST',
            data: ajaxData,
            timeout: 60000
        }).done(function(response) {
            // Remove spinners.
            if (mode === 'translate') {
                $.each(batch, function(_, batchItem) {
                    batchItem.$row.find('.haayal-bulk-spinner').remove();
                });
            }

            if (!response.success) {
                totalFailed += batch.length;
            } else {
                var results = response.data.results;
                $.each(results, function(i, res) {
                    var batchItem = batch[i];
                    if (!batchItem) return;

                    if (mode === 'translate') {
                        var $newSlugTd = batchItem.$row.find('.column-new-slug');
                        var $actionsTd = batchItem.$row.find('.column-actions');

                        if (res.slug) {
                            var $input = $('<input>').attr('type', 'text')
                                .addClass('haayal-bulk-slug-input')
                                .val(res.slug)
                                .attr('data-original', batchItem.$row.find('.column-current-slug').text());
                            $newSlugTd.empty().append($input);

                            // Update button text from "Exclude" to "Keep Original" and re-enable.
                            $actionsTd.find('.haayal-bulk-keep-btn').text(i18n.keep_original).prop('disabled', false);

                            // Add Regenerate button if not already present.
                            if (!$actionsTd.find('.haayal-bulk-regen-btn').length) {
                                var $regenBtn = $('<button>').attr('type', 'button')
                                    .addClass('haayal-pill haayal-pill-regen haayal-bulk-regen-btn');
                                setRegenContent($regenBtn[0], false);
                                $actionsTd.append($regenBtn);
                            }

                            totalProcessed++;
                        } else {
                            $actionsTd.empty().append(
                                $('<span>').addClass('haayal-bulk-error').text(res.error || i18n.failed)
                            );
                            totalFailed++;
                        }
                    } else {
                        if (res.success) {
                            batchItem.$row.addClass('saved');
                            batchItem.$row.find('.haayal-bulk-keep-btn, .haayal-bulk-regen-btn').remove();
                            batchItem.$row.find('.haayal-bulk-slug-input').prop('disabled', true);
                            if (res.new_slug) {
                                batchItem.$row.find('.haayal-bulk-slug-input').val(res.new_slug);
                            }
                            totalProcessed++;
                        } else {
                            batchItem.$row.find('.column-actions').append(
                                $('<span>').addClass('haayal-bulk-error').text(res.error || i18n.failed)
                            );
                            totalFailed++;
                        }
                    }
                });

                if (mode === 'save' && response.data.redirects_created) {
                    totalRedirects += response.data.redirects_created;
                }

                // Update proxy quota.
                if (mode === 'translate' && typeof response.data.quota_remaining !== 'undefined') {
                    config.proxy_quota = response.data.quota_remaining;
                    refreshProxyWarning();

                    if (config.connection_method === 'proxy' && config.proxy_quota <= 0) {
                        stopped = 'quota';
                    }
                }
            }

            updateProgress(totalProcessed + totalFailed, batch.length * (batches.length), totalItems);

            processBatches(batches, index + 1, mode, totalItems);
        }).fail(function(jqXHR, textStatus) {
            // Remove spinners on failure too.
            if (mode === 'translate') {
                $.each(batch, function(_, batchItem) {
                    var $newSlugTd = batchItem.$row.find('.column-new-slug');
                    $newSlugTd.find('.haayal-bulk-spinner').remove();
                    if (textStatus === 'timeout') {
                        $newSlugTd.append(
                            $('<span>').addClass('haayal-bulk-error').text(i18n.timeout || 'Request timed out')
                        );
                    }
                });
            }
            totalFailed += batch.length;
            updateProgress(totalProcessed + totalFailed, 0, totalItems);
            processBatches(batches, index + 1, mode, totalItems);
        });
    }

    // --- Progress bar ---
    function startProgress(label, total) {
        var $bar = $('#haayal-bulk-progress');
        var $fill = $bar.find('.haayal-bulk-progress-fill');
        $bar.show();
        $fill.removeClass('is-saving');
        $fill.css('width', '0%');
        $bar.find('.haayal-bulk-progress-text').text(label + ' 0 ' + i18n.of + ' ' + total);
        $('#haayal-bulk-summary').hide();
    }

    function startSaveProgress() {
        var $bar = $('#haayal-bulk-progress');
        $bar.show();
        $bar.find('.haayal-bulk-progress-fill').addClass('is-saving');
        $bar.find('.haayal-bulk-progress-text').text('');
        $('#haayal-bulk-summary').hide();
    }

    function updateProgress(done, ignored, total) {
        var $fill = $('#haayal-bulk-progress .haayal-bulk-progress-fill');
        if ($fill.hasClass('is-saving')) { return; }
        var pct = Math.round((done / total) * 100);
        $fill.css('width', pct + '%');
        $('#haayal-bulk-progress .haayal-bulk-progress-text').text(
            done + ' ' + i18n.of + ' ' + total
        );
    }

    function finishProgress(text) {
        var $fill = $('#haayal-bulk-progress .haayal-bulk-progress-fill');
        $fill.removeClass('is-saving');
        $fill.css('width', '100%');
        $('#haayal-bulk-progress .haayal-bulk-progress-text').text(text);
    }

    // --- Button visibility ---
    function showTranslateBtn() {
        $('.haayal-bulk-footer').show();
        $('#haayal-bulk-translate-btn').show().prop('disabled', false);
        $('#haayal-bulk-save-btn').hide();
        $('#haayal-bulk-stop-btn').hide();
    }

    function showSaveBtn() {
        $('.haayal-bulk-footer').show();
        $('#haayal-bulk-save-btn').show().prop('disabled', false);
        $('#haayal-bulk-translate-btn').hide();
        $('#haayal-bulk-stop-btn').hide();
        disablePagination();
    }

    function hideAllActions() {
        $('.haayal-bulk-footer').hide();
        $('#haayal-bulk-translate-btn, #haayal-bulk-save-btn, #haayal-bulk-stop-btn').hide();
    }

    function showStop() {
        $('.haayal-bulk-footer').show();
        $('#haayal-bulk-stop-btn').show();
    }

    function hideStop() {
        $('#haayal-bulk-stop-btn').hide();
    }

    function lockTable() {
        $('#haayal-bulk-table').addClass('haayal-bulk-locked');
        $('#haayal-bulk-table input, #haayal-bulk-table button').prop('disabled', true);
    }

    function unlockTable() {
        $('#haayal-bulk-table').removeClass('haayal-bulk-locked');
        $('#haayal-bulk-table input, #haayal-bulk-table button').prop('disabled', false);
    }

    function disablePagination() {
        $('#haayal-bulk-pagination').addClass('disabled');
    }

    function enablePagination() {
        $('#haayal-bulk-pagination').removeClass('disabled');
    }

    // --- Reload after save ---
    function reloadAfterSave() {
        var saveSummary = $('#haayal-bulk-summary').text();

        $.post(config.ajax_url, {
            action:      'haayal_bulk_load_items',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName,
            page:        currentPage
        }, function(response) {
            if (!response.success || !response.data.items.length) {
                // If current page is now empty, try page 1.
                if (currentPage > 1) {
                    currentPage = 1;
                    reloadAfterSave();
                    return;
                }
                // No items left at all.
                items = [];
                $('#haayal-bulk-table').hide().find('tbody').empty();
                $('#haayal-bulk-count').hide();
                $('.haayal-bulk-footer').hide();
                showSummary(saveSummary);
                return;
            }

            var data = response.data;
            items = data.items;
            renderTable();
            updateBulkCount(data.total, data.pages, data.page);
            renderPagination(data.pages, data.page);
            enablePagination();
            showTranslateBtn();
            // Preserve the save summary.
            showSummary(saveSummary);
        });
    }

    // --- Helpers ---
    function updateBulkCount(total, pages, page) {
        bulkTotal = total;
        bulkPages = pages;
        var $count = $('#haayal-bulk-count');
        var text = total + ' ' + i18n.items_count;
        if (pages > 1) {
            text += ' · ' + i18n.page_of.replace('%1$s', page).replace('%2$s', pages);
        }
        $count.text(text).show();
    }

    function adjustBulkCount(delta) {
        bulkTotal = Math.max(0, bulkTotal + delta);
        var $count = $('#haayal-bulk-count');
        if (bulkTotal === 0) {
            $count.hide();
            return;
        }
        var text = bulkTotal + ' ' + i18n.items_count;
        if (bulkPages > 1) {
            text += ' · ' + i18n.page_of.replace('%1$s', currentPage).replace('%2$s', bulkPages);
        }
        $count.text(text);
    }

    function updateExcludedCount(count) {
        var $count = $('#haayal-bulk-excluded-count');
        if (count > 0) {
            $count.text(count + ' ' + i18n.items_count).show();
        } else {
            $count.hide();
        }
    }

    function updateProxyWarning(quota) {
        var $warning = $('#haayal-bulk-proxy-warning');
        $('#haayal-bulk-quota-count').text(quota);
        if (quota <= 0) {
            $warning.removeClass('haayal-bulk-warning').addClass('haayal-bulk-message error')
                .html(i18n.quota_zero);
        } else {
            $warning.removeClass('haayal-bulk-message error').addClass('haayal-bulk-warning');
        }
        $warning.show();
    }

    function updateGlobalCounter(added) {
        var $counter = $('#haayal-slugs-counter');
        var $wrap    = $('#haayal-slugs-counter-wrap');
        var current  = parseInt($counter.text(), 10) || 0;
        var newVal   = current + added;
        $counter.text(newVal);
        $wrap.show();
    }

    function findItem(id) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].id === id) return items[i];
        }
        return null;
    }

    function chunkArray(arr, size) {
        var chunks = [];
        for (var i = 0; i < arr.length; i += size) {
            chunks.push(arr.slice(i, i + size));
        }
        return chunks;
    }

    function resetUI() {
        items = [];
        $('#haayal-bulk-table').hide().find('tbody').empty();
        $('#haayal-bulk-count').hide();
        $('#haayal-bulk-pagination').empty();
        enablePagination();
        $('#haayal-bulk-progress').hide();
        $('#haayal-bulk-summary').hide();
        $('.haayal-bulk-footer').hide();
        $('#haayal-bulk-excluded-section').hide();
        clearMessage();
        hideAllActions();
    }

    var dotsInterval = null;

    function showMessage(text, type, loading) {
        var $msg = $('#haayal-bulk-message');
        stopDots();

        if (loading) {
            // Strip trailing dots/ellipsis from the base text.
            var base = text.replace(/\.+$/, '');
            $msg.attr('class', 'haayal-bulk-message ' + type + ' haayal-loading')
                .html(base + '<span class="haayal-dots"></span>')
                .show();

            var dots = 0;
            var $dots = $msg.find('.haayal-dots');
            dotsInterval = setInterval(function() {
                dots = (dots + 1) % 4;
                $dots.text('.'.repeat(dots));
            }, 400);
        } else {
            $msg.text(text).attr('class', 'haayal-bulk-message ' + type).show();
        }
    }

    function stopDots() {
        if (dotsInterval) {
            clearInterval(dotsInterval);
            dotsInterval = null;
        }
    }

    function clearMessage() {
        stopDots();
        $('#haayal-bulk-message').hide().text('').attr('class', 'haayal-bulk-message');
    }

    function showSummary(text, type) {
        var $summary = $('#haayal-bulk-summary');
        $summary.attr('class', 'haayal-bulk-summary');
        if (type) {
            $summary.addClass(type);
        }
        $summary.text(text).show();
    }

    // --- Excluded items section ---
    function checkExcludedSection(bump) {
        if (!currentSourceType || !currentSourceName) {
            $('#haayal-bulk-excluded-section').hide();
            return;
        }

        // Quick check: load excluded to see if any exist.
        $.post(config.ajax_url, {
            action:      'haayal_bulk_load_excluded',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName
        }, function(response) {
            if (response.success && response.data.items.length > 0) {
                var count = response.data.items.length;
                var $toggle = $('#haayal-bulk-show-excluded');
                var isOpen = $toggle.attr('aria-expanded') === 'true';

                $('#haayal-bulk-excluded-section').show();

                // Update badge count, preserve open/closed state.
                var label = isOpen ? i18n.hide_excluded : i18n.show_excluded;
                $toggle.html(label + ' <span class="haayal-excluded-badge">' + count + '</span>');

                // Bump the badge after the number is visible.
                if (bump) {
                    setTimeout(function() {
                        var $badge = $toggle.find('.haayal-excluded-badge');
                        $badge.addClass('haayal-badge-bump');
                        setTimeout(function() { $badge.removeClass('haayal-badge-bump'); }, 300);
                    }, 50);
                }

                if (isOpen) {
                    // Refresh the excluded table rows in-place.
                    var $tbody = $('#haayal-bulk-excluded-table tbody').empty();
                    $.each(response.data.items, function(_, item) {
                        var $row = $('<tr>').attr('data-id', item.id);
                        $row.append($('<td>').addClass('column-title').text(item.title));
                        $row.append($('<td>').addClass('column-current-slug').text(item.current_slug));
                        var $restoreBtn = $('<button>').attr('type', 'button')
                            .addClass('button-link haayal-bulk-restore-btn')
                            .attr('title', i18n.restore_hint)
                            .text(i18n.restore_item);
                        $row.append($('<td>').addClass('column-actions').append($restoreBtn));
                        $tbody.append($row);
                    });
                    updateExcludedCount(count);
                }
            } else {
                $('#haayal-bulk-excluded-section').hide();
                updateExcludedCount(0);
            }
        });
    }

    // Toggle excluded list.
    $('#haayal-bulk-show-excluded').on('click', function() {
        var $list = $('#haayal-bulk-excluded-list');

        if ($list.is(':visible')) {
            $list.hide();
            $(this).attr('aria-expanded', 'false');
            var count = $('#haayal-bulk-excluded-table tbody tr').length;
            $(this).html(i18n.show_excluded + ' <span class="haayal-excluded-badge">' + count + '</span>');
            return;
        }

        // Load and render excluded items.
        $.post(config.ajax_url, {
            action:      'haayal_bulk_load_excluded',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName
        }, function(response) {
            if (!response.success || !response.data.items.length) {
                $('#haayal-bulk-excluded-section').hide();
                return;
            }

            var $tbody = $('#haayal-bulk-excluded-table tbody').empty();
            $.each(response.data.items, function(_, item) {
                var $row = $('<tr>').attr('data-id', item.id);
                $row.append($('<td>').addClass('column-title').text(item.title));
                $row.append($('<td>').addClass('column-current-slug').text(item.current_slug));

                var $restoreBtn = $('<button>').attr('type', 'button')
                    .addClass('button-link haayal-bulk-restore-btn')
                    .attr('title', i18n.restore_hint)
                    .text(i18n.restore_item);
                $row.append($('<td>').addClass('column-actions').append($restoreBtn));

                $tbody.append($row);
            });

            $list.show();
            var count = response.data.items.length;
            updateExcludedCount(count);
            $('#haayal-bulk-show-excluded')
                .attr('aria-expanded', 'true')
                .html(i18n.hide_excluded + ' <span class="haayal-excluded-badge">' + count + '</span>');
        });
    });

    // --- Regenerate single slug ---
    $(document).on('click', '.haayal-bulk-regen-btn', function() {
        // Block only during save (table locked), not during translate — regen runs in parallel fine.
        if ($('#haayal-bulk-table').hasClass('haayal-bulk-locked')) return;

        var $btn    = $(this);
        if ($btn.prop('disabled')) return;

        var $row    = $btn.closest('tr');
        var id      = parseInt($row.attr('data-id'), 10);
        var item    = findItem(id);
        if (!item) return;

        var $newSlugTd  = $row.find('.column-new-slug');
        var $actionsTd  = $row.find('.column-actions');
        var $slugInput  = $newSlugTd.find('.haayal-bulk-slug-input');

        var oldSlug = $slugInput.val().trim();

        $btn.prop('disabled', true);
        setRegenContent($btn[0], true);
        $slugInput.prop('disabled', true);

        $.ajax({
            url:     config.ajax_url,
            type:    'POST',
            timeout: 60000,
            data: {
                action:      'haayal_bulk_translate',
                nonce:       config.nonce,
                source_type: currentSourceType,
                source_name: currentSourceName,
                items:       [{ id: item.id, title: item.title }]
            }
        }).done(function(response) {
            $slugInput.prop('disabled', false);

            if (response.success && response.data.results && response.data.results[0] && response.data.results[0].slug) {
                var newSlug = response.data.results[0].slug;
                if (newSlug.trim() === oldSlug) {
                    showBulkSameSlugTooltip($btn[0], i18n.already_best);
                } else {
                    $slugInput.val(newSlug);
                    updateGlobalCounter(1);
                }
            } else {
                var errMsg = (response.data && response.data.results && response.data.results[0] && response.data.results[0].error) || i18n.failed;
                var $err = $('<span>').addClass('haayal-bulk-error').text(errMsg);
                $newSlugTd.append($err);
                setTimeout(function() { $err.fadeOut(300, function() { $(this).remove(); }); }, 3000);
            }

            if (typeof response.data !== 'undefined' && typeof response.data.quota_remaining !== 'undefined') {
                config.proxy_quota = response.data.quota_remaining;
                refreshProxyWarning();
            }

            $btn.prop('disabled', false);
            setRegenContent($btn[0], false);
        }).fail(function() {
            $slugInput.prop('disabled', false);
            $btn.prop('disabled', false);
            setRegenContent($btn[0], false);
        });
    });

    // Restore excluded item.
    $(document).on('click', '.haayal-bulk-restore-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var id   = parseInt($row.attr('data-id'), 10);
        var title = $row.find('.column-title').text();
        var slug  = $row.find('.column-current-slug').text();

        $btn.prop('disabled', true);

        $.post(config.ajax_url, {
            action:      'haayal_bulk_restore',
            nonce:       config.nonce,
            source_type: currentSourceType,
            source_name: currentSourceName,
            item_id:     id
        }, function(response) {
            if (!response.success) {
                $btn.prop('disabled', false);
                return;
            }

            // Start animation only after successful response.
            $row.addClass('haayal-restoring');

            // After animation completes, clean up and add to main table.
            setTimeout(function() {
                $row.remove();

                // Update badge count; hide section only if empty.
                var remaining = $('#haayal-bulk-excluded-table tbody tr').length;
                updateExcludedCount(remaining);
                if (remaining === 0) {
                    $('#haayal-bulk-excluded-section').hide();
                } else {
                    var $toggle = $('#haayal-bulk-show-excluded');
                    var label = $toggle.attr('aria-expanded') === 'true' ? i18n.hide_excluded : i18n.show_excluded;
                    $toggle.html(label + ' <span class="haayal-excluded-badge">' + remaining + '</span>');
                }

                var $newRow = $('<tr>').attr('data-id', id).addClass('haayal-restored-row');
                $newRow.append($('<td>').addClass('column-title').append(
                    $('<a>').attr('href', '#').text(title)
                ));
                $newRow.append($('<td>').addClass('column-current-slug').text(slug));
                $newRow.append($('<td>').addClass('column-new-slug').append($('<div>').addClass('haayal-bulk-slug-placeholder')));
                var $excludeBtn = $('<button>').attr('type', 'button')
                    .addClass('button-link haayal-bulk-keep-btn')
                    .text(i18n.exclude);
                $newRow.append($('<td>').addClass('column-actions').append($excludeBtn));
                $('#haayal-bulk-table tbody').append($newRow);
                $('#haayal-bulk-table').show();

                // Add to items array so Translate All includes this row.
                items.push({ id: id, title: title, current_slug: slug, edit_url: '#' });
                adjustBulkCount(1);

                clearMessage();
                if (!$('#haayal-bulk-save-btn').is(':visible')) {
                    showTranslateBtn();
                }
            }, 750);
        });
    });
});
