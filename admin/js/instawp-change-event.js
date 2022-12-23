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

    const baseCall = async (body) => {
        return await fetch(ajax_obj.ajax_url, {
            method: "POST",
            credentials: 'same-origin',
            body
        });
    }

    var intervalId;
    const packThings = async (sync_message) => {
        let formData = new FormData();
        formData.append('action', 'pack_things');
        baseCall(formData).then((response) => response.json()).then((data) => {
            if(data.success === true){
                //Complete Step 1
                $(".sync_process .step-1")
                .removeClass('process_inprogress')
                .addClass('process_complete');
                //Initiate Step 2
                $(".sync_process .step-2")
                    .removeClass('process_pending')
                    .addClass('process_inprogress');
                bulkSync(sync_message, data.data); 
            }
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }
    clearInterval(intervalId);

    const bulkSync = (sync_message, data) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes');
        formData.append('sync_message', sync_message);
        console.log("Before Send: ", data);
        console.log("Before Send Type: ", typeof data);
        formData.append('data', data);
        baseCall(formData).then((response) => response.json()).then((data) => {
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
            }
        });
    }

    //Bulk sync
    $(document).on('click', '.sync-changes-btn', function(){
        const sync_message = $("#sync_message").val();
        //Initiate Step 2
        $(".sync_process .step-1")
            .removeClass('process_pending')
            .addClass('process_inprogress');
            packThings(sync_message);
    });
});


