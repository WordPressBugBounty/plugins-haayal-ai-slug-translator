jQuery(document).ready(function($) {
    var config = haayalRedirects;
    var i18n   = config.i18n;
    var currentPage = 1;
    var currentFilter = '';
    var loaded = false;

    // Load on tab activation.
    $('#tab-btn-redirects').on('click', function() {
        if (!loaded) {
            loadRedirects();
            loaded = true;
        }
    });

    // Also load if the tab is already active on page load.
    if ($('#tab-redirects').is(':visible')) {
        loadRedirects();
        loaded = true;
    }

    // Reload when bulk save creates new redirects.
    $(document).on('haayal:redirects:reload', function() {
        if (loaded) {
            loadRedirects();
        }
    });

    // Filter change.
    $('#haayal-redirects-filter-select').on('change', function() {
        currentFilter = $(this).val();
        currentPage = 1;
        loadRedirects();
    });

    // --- Load redirects ---
    function loadRedirects() {
        showMessage(i18n.loading, 'info');
        $('#haayal-redirects-table').hide();
        $('.haayal-redirects-footer').hide();

        $.post(config.ajax_url, {
            action:      'haayal_redirects_load',
            nonce:       config.nonce,
            filter_type: currentFilter,
            page:        currentPage
        }, function(response) {
            if (!response.success) {
                showMessage(response.data.message || i18n.error, 'error');
                return;
            }

            var data = response.data;

            updateCount(data.total);

            if (!data.items.length) {
                showMessage(i18n.no_redirects, 'info');
                return;
            }

            clearMessage();
            renderTable(data.items);
            renderPagination(data.pages, data.page);
            $('.haayal-redirects-footer').show();
        }).fail(function() {
            showMessage(i18n.error, 'error');
        });
    }

    // --- Render table ---
    function renderTable(items) {
        var $tbody = $('#haayal-redirects-table tbody').empty();

        $.each(items, function(_, item) {
            var $row = $('<tr>').attr('data-id', item.id);

            var $titleCell = $('<td>').addClass('column-title');
            if (item.permalink) {
                $titleCell.append($('<a>').attr('href', item.permalink).attr('target', '_blank').text(item.object_title));
                var permalinkPath = getPath(item.permalink);
                var newUrlPath    = getPath(item.new_url);
                if (permalinkPath && newUrlPath && permalinkPath !== newUrlPath) {
                    var $icon = $('<span>').addClass('haayal-url-mismatch dashicons dashicons-warning')
                        .attr('role', 'img')
                        .attr('aria-label', i18n.url_mismatch_1);
                    var $tooltip = $('<span>').addClass('haayal-tooltip')
                        .append($('<span>').text(i18n.url_mismatch_1))
                        .append($('<span>').text(i18n.url_mismatch_2));
                    var $wrapper = $('<span>').addClass('haayal-tooltip-wrap').append($icon, $tooltip);
                    $titleCell.append(' ', $wrapper);
                }
            } else {
                $titleCell.text(item.object_title);
            }
            $row.append($titleCell);

            var decodedOld;
            try { decodedOld = decodeURIComponent(item.old_url); } catch(e) { decodedOld = item.old_url; }
            var $oldLink = $('<a>').attr('href', item.old_url).attr('target', '_blank').append(
                $('<code>').text(decodedOld).attr('title', item.old_url)
            );
            $row.append($('<td>').addClass('column-old-url').append($oldLink));

            var $newLink = $('<a>').attr('href', item.new_url).attr('target', '_blank').append(
                $('<code>').text(item.new_url)
            );
            $row.append($('<td>').addClass('column-new-url').append($newLink));

            var typeLabel = item.object_subtype || (item.object_type === 'post' ? i18n.post : i18n.term);
            $row.append($('<td>').addClass('column-type').text(typeLabel));

            var date = new Date(item.created_at);
            var dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            $row.append($('<td>').addClass('column-created').text(dateStr));

            var $deleteBtn = $('<button>').attr('type', 'button')
                .addClass('button-link haayal-redirects-delete-btn')
                .text(i18n.remove);
            $row.append($('<td>').addClass('column-actions').append($deleteBtn));

            $tbody.append($row);
        });

        $('#haayal-redirects-table').show();
    }

    // --- Delete single ---
    $(document).on('click', '.haayal-redirects-delete-btn', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var id   = parseInt($row.attr('data-id'), 10);

        Swal.fire({
            title: i18n.delete_title,
            text:  i18n.delete_text,
            icon:  'warning',
            showCancelButton:  true,
            confirmButtonText: i18n.confirm_btn,
            cancelButtonText:  i18n.cancel_btn,
            confirmButtonColor: '#d33'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true);

            $.post(config.ajax_url, {
                action:      'haayal_redirects_delete',
                nonce:       config.nonce,
                redirect_id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $row.remove();
                        // Update count.
                        var current = parseInt($('#haayal-redirects-count').data('total') || 0, 10);
                        updateCount(Math.max(0, current - 1));

                        if ($('#haayal-redirects-table tbody tr').length === 0) {
                            loadRedirects();
                        }
                    });
                } else {
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                $btn.prop('disabled', false);
            });
        });
    });

    // --- Delete All ---
    $('#haayal-redirects-delete-all').on('click', function() {
        var $btn = $(this);

        Swal.fire({
            title: i18n.delete_all_title,
            text:  i18n.delete_all_text,
            icon:  'warning',
            showCancelButton:  true,
            confirmButtonText: i18n.confirm_btn,
            cancelButtonText:  i18n.cancel_btn,
            confirmButtonColor: '#d33'
        }).then(function(result) {
            if (!result.isConfirmed) return;

            $btn.prop('disabled', true);

            $.post(config.ajax_url, {
                action:      'haayal_redirects_delete_all',
                nonce:       config.nonce,
                filter_type: currentFilter
            }, function(response) {
                if (response.success) {
                    loadRedirects();
                }
                $btn.prop('disabled', false);
            }).fail(function() {
                $btn.prop('disabled', false);
            });
        });
    });

    // --- Pagination ---
    function renderPagination(totalPages, currentPg) {
        var $pag = $('#haayal-redirects-pagination').empty();
        if (totalPages <= 1) return;

        for (var p = 1; p <= totalPages; p++) {
            var $btn = $('<button>').attr('type', 'button')
                .addClass('button haayal-redirects-page-btn')
                .text(p)
                .attr('data-page', p);
            if (p === currentPg) {
                $btn.addClass('button-primary');
            }
            $pag.append($btn);
        }
    }

    $(document).on('click', '.haayal-redirects-page-btn', function() {
        currentPage = parseInt($(this).attr('data-page'), 10);
        loadRedirects();
    });

    // --- Export ---
    function fetchAllRedirects(callback) {
        $.post(config.ajax_url, {
            action:      'haayal_redirects_load',
            nonce:       config.nonce,
            filter_type: currentFilter,
            export:      1
        }, function(response) {
            if (response.success && response.data.items.length) {
                callback(response.data.items);
            }
        });
    }

    function downloadFile(content, filename, type) {
        var blob = new Blob([content], { type: type });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function decodeUrl(url) {
        try { return decodeURIComponent(url); } catch(e) { return url; }
    }

    function getPath(url) {
        try {
            // Handle both full URLs and relative paths.
            if (url.indexOf('://') !== -1) {
                return new URL(url).pathname.replace(/\/+$/, '');
            }
            return url.replace(/\/+$/, '');
        } catch(e) { return url; }
    }

    $('#haayal-redirects-export-csv').on('click', function() {
        fetchAllRedirects(function(items) {
            var lines = ['"Old URL","New URL","Title","Type","Redirected On"'];
            $.each(items, function(_, item) {
                var title = (item.object_title || '').replace(/"/g, '""');
                var type  = item.object_subtype || item.object_type;
                lines.push('"' + decodeUrl(item.old_url) + '","' + decodeUrl(item.new_url) + '","' + title + '","' + type + '","' + item.created_at + '"');
            });
            var csv = '\uFEFF' + lines.join('\n');
            var dateStr = new Date().toISOString().slice(0, 10);
            downloadFile(csv, 'ailo-redirects-' + dateStr + '.csv', 'text/csv;charset=utf-8');
        });
    });

    $('#haayal-redirects-export-htaccess').on('click', function() {
        fetchAllRedirects(function(items) {
            var lines = ['# 301 Redirects exported from Ailo - AI Slug Translator'];
            $.each(items, function(_, item) {
                lines.push('Redirect 301 ' + decodeUrl(item.old_url) + ' ' + decodeUrl(item.new_url));
            });
            var dateStr = new Date().toISOString().slice(0, 10);
            downloadFile(lines.join('\n'), 'ailo-redirects-' + dateStr + '.htaccess.txt', 'text/plain;charset=utf-8');
        });
    });

    // --- Helpers ---
    function updateCount(total) {
        $('#haayal-redirects-count')
            .data('total', total)
            .text(total + ' ' + i18n.active_redirects);
    }

    function showMessage(text, type) {
        $('#haayal-redirects-message')
            .text(text)
            .attr('class', 'haayal-redirects-message ' + type)
            .show();
    }

    function clearMessage() {
        $('#haayal-redirects-message').hide().text('').attr('class', 'haayal-redirects-message');
    }
});
