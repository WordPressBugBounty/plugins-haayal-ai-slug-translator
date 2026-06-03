jQuery(document).ready(function($) {

    function updateCardSelection() {
        var selected = $('input[name="connection_method"]:checked').val();

        // Update card visual state and ARIA.
        $('.connection-method-card').removeClass('selected').attr('aria-checked', 'false');
        $('input[name="connection_method"]:checked').closest('.connection-method-card')
            .addClass('selected').attr('aria-checked', 'true');

        // Show API key input only when api_key method is selected.
        $('.api-key-input-wrapper').toggle(selected === 'api_key');

        // Show max tokens for api_key and connectors (hide for proxy).
        $('.max-tokens-wrapper').toggle(selected === 'api_key' || selected === 'connectors');
    }

    // Card click handler.
    $('.connection-method-card').on('click', function(e) {
        // Don't handle clicks on links or form inputs inside cards.
        if ($(e.target).closest('a, input[type="text"], label[for="api_key"]').length) {
            return;
        }

        var $card = $(this);
        if ($card.hasClass('disabled')) {
            return;
        }

        var $radio = $card.find('input[type="radio"]');
        if (!$radio.prop('disabled')) {
            $radio.prop('checked', true).trigger('change');
        }
    });

    // Keyboard support: Space/Enter to select card.
    $('.connection-method-card').on('keydown', function(e) {
        if (e.key === ' ' || e.key === 'Enter') {
            // Don't intercept if focus is on the text input.
            if ($(e.target).is('input[type="text"]')) {
                return;
            }
            e.preventDefault();
            $(this).trigger('click');
        }
    });

    // "Save & Go to Connectors" — select connectors radio, save the form,
    // then redirect on next page load via URL parameter.
    $('.save-and-redirect').on('click', function(e) {
        e.preventDefault();
        var redirectUrl = $(this).attr('href');
        var $form = $('.slug-translator-settings-form');

        // Ensure connectors method is selected before saving.
        $form.find('input[name="connection_method"][value="connectors"]').prop('checked', true);

        // Set the form action to include a redirect_to param so we can detect it on reload.
        var action = $form.attr('action') || window.location.pathname + window.location.search;
        if (action.indexOf('?') === -1) {
            action += '?redirect_to=' + encodeURIComponent(redirectUrl);
        } else {
            action += '&redirect_to=' + encodeURIComponent(redirectUrl);
        }
        $form.attr('action', action);
        $form.find('button[name="submit"]').trigger('click');
    });

    // After a "Save & Go to Connectors" round-trip, redirect to Connectors page.
    var urlParams = new URLSearchParams(window.location.search);
    var redirectTo = urlParams.get('redirect_to');
    if (redirectTo) {
        window.location.href = redirectTo;
        return;
    }

    // Radio change handler.
    $('input[name="connection_method"]').on('change', updateCardSelection);

    // Initialize on page load.
    updateCardSelection();

    // --- Tabs ---
    var $tabs = $('.slug-translator-tab');
    var $panels = $('.slug-translator-tabpanel');

    function activateTab($tab, updateUrl) {
        // Deactivate all tabs.
        $tabs.attr('aria-selected', 'false').attr('tabindex', '-1').removeClass('active');

        // Hide all panels.
        $panels.attr('hidden', true);

        // Activate the clicked tab.
        $tab.attr('aria-selected', 'true').attr('tabindex', '0').addClass('active');

        // Show the associated panel.
        var panelId = $tab.attr('aria-controls');
        $('#' + panelId).removeAttr('hidden');

        // Update URL with tab parameter.
        if (updateUrl !== false) {
            var params = new URLSearchParams(window.location.search);
            params.set('tab', panelId.replace('tab-', ''));
            history.replaceState(null, '', window.location.pathname + '?' + params.toString());
        }
    }

    // Tab click handler.
    $tabs.on('click', function() {
        activateTab($(this));
    });

    // Links that switch to a specific tab (e.g. "Where do I get an API key?").
    $('.switch-to-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');
        activateTab($('#' + tabId));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Activate tab from URL parameter on page load.
    var tabParam = new URLSearchParams(window.location.search).get('tab');
    if (tabParam) {
        var $targetTab = $tabs.filter('[aria-controls="tab-' + tabParam + '"]');
        if ($targetTab.length) {
            activateTab($targetTab, false);
        }
    }

    // Arrow key navigation between tabs.
    $tabs.on('keydown', function(e) {
        var index = $tabs.index(this);
        var newIndex = null;

        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            newIndex = (index + 1) % $tabs.length;
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            newIndex = (index - 1 + $tabs.length) % $tabs.length;
        } else if (e.key === 'Home') {
            newIndex = 0;
        } else if (e.key === 'End') {
            newIndex = $tabs.length - 1;
        }

        if (newIndex !== null) {
            e.preventDefault();
            activateTab($tabs.eq(newIndex));
            $tabs.eq(newIndex).trigger('focus');
        }
    });
});
