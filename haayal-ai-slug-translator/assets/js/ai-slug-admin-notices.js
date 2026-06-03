jQuery(document).on('click', '.haayal-welcome-notice .notice-dismiss, .slug-translator-settings-form .save-settings-button', function () {
    jQuery.post(ajaxurl, {
        action: 'haayal_dismiss_notice',
        nonce: haayalNotices.dismiss_nonce
    }).fail(function() {
        console.error('Failed to dismiss welcome notice.');
    });
});

jQuery(document).on('click', '.haayal-review-notice .notice-dismiss', function () {
    jQuery.post(ajaxurl, {
        action: 'haayal_dismiss_review_notice',
        nonce: haayalNotices.dismiss_review_nonce
    }).fail(function() {
        console.error('Failed to dismiss review notice.');
    });
});

jQuery(document).on('click', '.haayal-upgrade-notice .notice-dismiss', function () {
    jQuery.post(ajaxurl, {
        action: 'haayal_dismiss_v1_upgrade_notice',
        nonce: haayalNotices.dismiss_v1_upgrade_nonce
    }).fail(function() {
        console.error('Failed to dismiss upgrade notice.');
    });
});
