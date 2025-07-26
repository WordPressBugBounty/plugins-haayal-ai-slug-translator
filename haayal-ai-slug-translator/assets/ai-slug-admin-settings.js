jQuery(document).ready(function($) {
    // Show or hide the .max-tokens-wrapper based on the #api_key value
    function toggleMaxTokensWrapper() {
        const hasApiKey = $('#api_key').val().trim() !== '';
        $('.max-tokens-wrapper').toggle(hasApiKey);
    }

    // Check on page load
    toggleMaxTokensWrapper();

    // Check on every input change
    $('#api_key').on('input', toggleMaxTokensWrapper);
});