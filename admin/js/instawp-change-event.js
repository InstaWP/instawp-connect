jQuery(document).ready(function ($) {
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        $('.bulk-sync-popup').show();
    });

    $(document).on('click', '.bulk-sync-popup .close', function(){
        $('.bulk-sync-popup').hide();
    });

    
    
    //Single sync
    $(document).on('click', '.two-way-sync-btn', function(){
        var sync_id = $(this).attr('data-id'); 
        
        $.ajax({
            url: ajax_obj.ajax_url, 
            type: 'POST',
            //dataType: "json",
            data:{ 
                action: 'single_sync', 
                sync_id: sync_id
            },
            beforeSend: function() {
                $('#btn-sync-'+sync_id).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif">');
            },
            success: function( response ){
                //console.log(response);
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

    
    const bulkSync = (sync_message) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes');
        formData.append('sync_message', 'John123');
        fetch(ajax_obj.ajax_url, {
            method: "POST",
            credentials: 'same-origin',
            body: formData
        }).then((respponse90) => respponse90.json()).then((datag) => {
            console.log(datag);
        });
    }

    //Bulk sync
    $(document).on('click', '.sync-changes-btn', function(){
        var sync_message = $('#sync_message').val();
        console.log(sync_message);
        //bulkSync("test message");
        $.ajax({
            url: ajax_obj.ajax_url, 
            type: 'POST',
            dataType: "json",
            data:{ 
                action: 'sync_changes',
                sync_message: sync_message
            },
            beforeSend: function() {
                $(".sync_process .step-1").removeClass('process_pending').addClass('process_inprogress'); 
                $(".sync_process .step-2").removeClass('process_pending').addClass('process_inprogress'); 
                $(".sync_process .step-3").removeClass('process_pending').addClass('process_inprogress'); 
            },
            success: function(response){
                var jsonobj = JSON.parse(response);
                $(".sync_process .step-1").removeClass('process_inprogress').addClass('process_complete');
                $(".sync_process .step-2").removeClass('process_inprogress').addClass('process_complete');
                if(jsonobj.status == true){
                    $(".sync_process .step-3").removeClass('process_inprogress').addClass('process_complete');
                }
            },
            complete: function(){
                
            }
        });
    });
});


