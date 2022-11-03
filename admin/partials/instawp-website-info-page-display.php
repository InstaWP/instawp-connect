<script>
    function instawp_get_ini_memory_limit() {
        var ajax_data = {
            'action': 'instawp_get_ini_memory_limit'
        };
        instawp_post_request(ajax_data, function (data) {
            try {
                jQuery('#instawp_websiteinfo_list tr').each(function (i) {
                    jQuery(this).children('td').each(function (j) {
                        if (j == 0) {
                            if (jQuery(this).html().indexOf('memory_limit') >= 0) {
                                jQuery(this).next().html(data);
                            }
                        }
                    });
                });
            }
            catch (err) {
                setTimeout(function ()
                {
                    instawp_get_ini_memory_limit();
                }, 3000);
            }
        }, function (XMLHttpRequest, textStatus, errorThrown) {
            setTimeout(function ()
            {
                instawp_get_ini_memory_limit();
            }, 3000);
        });
    }
    jQuery(document).ready(function ()
    {
        instawp_get_ini_memory_limit();
    });
</script>
