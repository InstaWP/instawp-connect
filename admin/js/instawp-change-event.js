jQuery(document).ready(function ($) {
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        $('.bulk-sync-popup').show();
    });

    $(document).on('click', '.bulk-sync-popup .close', function(){
        $('.bulk-sync-popup').hide();
    });
    
    // bulk sync
    $(document).on('click', '.sync-changes-btn', function(){
        $.ajax({
            url: ajax_obj.ajax_url, 
            type: 'POST',
            //dataType: "json",
            data:{ 
                action: 'sync_changes'
            },
            beforeSend: function() {
                
            },
            success: function( response ){
                $("#json_data").val(response);
            },
            complete: function(){
                
            }
        });
    });
});
