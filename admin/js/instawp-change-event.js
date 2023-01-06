jQuery(document).ready(function ($) {
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        $('.bulk-sync-popup').show();
    });

    $(document).on('click', '.bulk-sync-popup .close', function(){
        $('.bulk-sync-popup').hide();
    });

    //Single sync
    $(document).on('click', '.two-way-sync-btn', function(){
        var sync_ids = $(this).attr('data-id');
        const sync_message = 'This is a single sync.';
        //Initiate Step 2
        $('#btn-sync-'+sync_ids).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif">');
        packThings(sync_message,'single_sync',sync_ids);
    });
    // $(document).on('click', '.two-way-sync-btn', function(){
    //     var sync_id = $(this).attr('data-id'); 
    //     $.ajax({
    //         url: ajax_obj.ajax_url, 
    //         type: 'POST',
    //         //dataType: "json",
    //         data:{ 
    //             action: 'single_sync', 
    //             sync_id: sync_id
    //         },
    //         beforeSend: function() {
    //             $('#btn-sync-'+sync_id).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif">');
    //         },
    //         success: function( response ){
    //             //console.log(response);
    //             if(response){
    //                 $('.message-change-events').text(response);
    //             }
    //         },
    //         complete: function(){
    //             $('#btn-sync-'+sync_id).parent().find('.sync-loader').html('');
    //             $('#btn-sync-'+sync_id).parent().find('.sync-success').show().delay(1000).fadeOut(300);
    //         }
    //     });
    // });

    const baseCall = async (body) => {
        return await fetch(ajax_obj.ajax_url, {
            method: "POST",
            credentials: 'same-origin',
            body
        });
    }

    const packThings = async (sync_message,sync_type,sync_ids) => {
        let formData = new FormData();
        formData.append('action', 'pack_things');
        formData.append('sync_type', sync_type);
        formData.append('sync_ids', sync_ids);
        formData.append('sync_message', sync_message);
        baseCall(formData).then((response) => response.json()).then((data) => {
            if(data.success === true){
                //Complete Step 1
                $(".sync_process .step-1").removeClass('process_inprogress').addClass('process_complete');
                //Initiate Step 2
                $(".sync_process .step-2").removeClass('process_pending').addClass('process_inprogress');
                bulkSync(sync_message,data.data,sync_type,sync_ids); 
            }
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }
    
    const bulkSync = (sync_message, data, sync_type, sync_ids) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes'); //ajax action
        formData.append('sync_message', sync_message);
        formData.append('sync_type', sync_type);
        formData.append('sync_ids', sync_ids);
        formData.append('data', data);
        baseCall(formData).then((response) => response.json()).then((data) => { 
            if(sync_type == 'single_sync'){
                // for single sync....
                if(data.success === true){
                    const synced_status =  data.data.res_data.status;
                    const synced_message =  data.data.res_data.synced_message;
                    $('#btn-sync-'+sync_ids).parent().find('.sync-loader').html('');
                    $('#btn-sync-'+sync_ids).parent().find('.column-synced_message').html(synced_message);
                    $('#btn-sync-'+sync_ids).parent().parent().find('.column-synced_message').html(synced_message);
                    $('#btn-sync-'+sync_ids).parent().parent().find('.synced_status').html(synced_status);
                    $('#btn-sync-'+sync_ids).remove();
                }else{
                    $('#btn-sync-'+sync_ids).parent().find('.sync-loader').html('');
	                $('#btn-sync-'+sync_ids).parent().find('.sync-success').html(data.message);  
                }
            }else{
                if(data.success === true){
                    //Upload Data, Completed Step 2
                    $(".sync_process .step-2")
                    .removeClass('process_inprogress')
                    .addClass('process_complete');
                    //Initiated Step3
                    $(".sync_process .step-3")
                        .removeClass('process_pending')
                        .addClass('process_inprogress'); 
                    //Set TimeOut
                    setTimeout( function() { 
                        $(".sync_process .step-3")
                        .removeClass('process_inprogress')
                        .addClass('process_complete');
                    }, 2000);
                }else{
                    $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');
                }
            }
        });
    }

    //Bulk sync
    $(document).on('click', '.sync-changes-btn', function(){
        const sync_message = $("#sync_message").val();
        //Initiate Step 2
        $(".sync_process .step-1").removeClass('process_pending').addClass('process_inprogress');
        packThings(sync_message,'bulk_sync','');
    });
});


