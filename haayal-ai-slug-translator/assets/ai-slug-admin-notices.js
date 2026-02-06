jQuery(document).on('click', '.haayal-welcome-notice .notice-dismiss, .slug-translator-settings-form .save-settings-button', function () {
    jQuery.post(ajaxurl, { action: 'haayal_dismiss_notice' });
});

jQuery(document).on('click', '.haayal-review-notice .notice-dismiss', function () {
    jQuery.post(ajaxurl, { action: 'haayal_dismiss_review_notice' });
});
