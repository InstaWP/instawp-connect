(function ($) {
    'use strict';

    // Will use in future
    $(document).on('click', '#wp-admin-bar-instawp-go-live > a', function (e) {

        e.preventDefault();

        let el_go_live_btn = $(this),
            el_go_live_btn_wrap = el_go_live_btn.parent();

        el_go_live_btn_wrap.addClass('loading');

        $.ajax({
            type: 'POST',
            url: ajax_obj.ajax_url,
            context: this,
            data: {
                'action': 'instawp_go_live',
            },
            success: function (response) {
                if (response.success) {
                    window.open(response.data.redirect_url, '_blank');
                }
                el_go_live_btn_wrap.removeClass('loading');
            }
        });

        return false;
    });
})(jQuery);