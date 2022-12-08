jQuery(document).ready(function ($) {
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        $('.bulk-sync-popup').show();
    });

    $(document).on('click', '.bulk-sync-popup .close', function(){
        $('.bulk-sync-popup').hide();
    });
    
    $(document).on('click', '.two-way-sync-btn', function(){
        console.log(ajax_obj.plugin_images_url+'/loaders/loader.gif');
        var sync_id = $(this).attr('data-id'); 
            $.ajax({
                url: ajax_obj.ajax_url, 
                type: 'POST',
                //dataType: "json",
                data:{ 
                  action: 'sync_action', 
                  sync_id: sync_id
                },
                beforeSend: function() {
                    $('#btn-sync-'+sync_id).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif">');
                },
                success: function( response ){
                    console.log(response);
                    if(response){
                        $('.message-change-events').text(response);
                    }
                },
                complete: function(){
                    $('#btn-sync-'+sync_id).parent().find('.sync-loader').html('');
                    $('#btn-sync-'+sync_id).parent().find('.sync-success').show().delay(1000).fadeOut(300);
                }
              });
        });
});
