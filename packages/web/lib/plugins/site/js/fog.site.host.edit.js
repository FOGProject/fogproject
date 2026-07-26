(function($) {
    // The whole tab -- Update wiring plus the create-and-associate button and
    // modal -- is one shared helper (fog.common.js). These nine plugin tabs are
    // the same card with a different noun, and each used to carry its own copy
    // of the submit wiring.
    $.registerSelectTab({
        slug: 'host-site',
        send: 'site-send',
        node: 'site'
    });
})(jQuery);
