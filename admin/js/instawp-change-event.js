jQuery(document).ready(function ($) {
    //selected sync btn...

    //bulk sync btn...
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        $('.bulk-sync-popup').show();
        $('.bulk-sync-popup').attr("data-sync-type", "bulk_sync");
        $('.bulk-events-info').show();
        $('.selected-events-info').hide();
    });

    $(document).on('click', '.bulk-sync-popup .close', function(){
        $('.bulk-sync-popup').hide();
    });
    
    //Get Selected sync...
    jQuery('.sync_events_form thead').on('click','input[type=checkbox]',function(){
        console.log(jQuery(this).val());
    });

    jQuery('.sync_events_form tbody').on('click','input[type=checkbox]',function(){
        getEventsID();
        // var event_slug = jQuery(this).parents('tr').find('.event_slug').text();
	    // slug_arr.push(event_slug);
    });


    // slug_counts = {};
    // jQuery.each(slug_arr, function(key,value) {
    //     if (!slug_counts.hasOwnProperty(value)) {
    //         slug_counts[value] = 1;
    //     } else {
    //         slug_counts[value]++;
    //     }
    // });

    $(document).on('click', '.selected-sync-popup-btn', function(){
        //console.log(slug_counts);
        $('.bulk-sync-popup').show();
        $('.bulk-sync-popup').attr("data-sync-type", "selected_sync");
        $('.bulk-events-info').hide();
        $('.selected-events-info').show();
    });

    //Bulk&Selected sync process...
    $(document).on('click', '.sync-changes-btn', function(){
        const sync_message = $("#sync_message").val();
        const sync_type = $('.bulk-sync-popup').attr("data-sync-type");
        var sync_ids = '';
        if(sync_type == 'selected_sync'){
            sync_ids = $('#selected_events').val();
        }
        //Initiate Step 2
        $(".sync_process .step-1").removeClass('process_pending').addClass('process_inprogress');
        packThings(sync_message,sync_type,sync_ids);
    });

    //Single sync..
    $(document).on('click', '.two-way-sync-btn', function(){
        var sync_ids = $(this).attr('data-id');
        var sync_id = $(this).attr('id');
        const sync_message = 'This is a single sync.';
        $( "#"+sync_id ).attr('disabled','disabled');
        //Initiate Step 2
        $('#btn-sync-'+sync_ids).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif">');
        packThings(sync_message,'single_sync',sync_ids);
    });

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
            console.log(data);
            if(data.success === true){
                //Complete Step 1
                $(".sync_process .step-1").removeClass('process_inprogress').addClass('process_complete');
                //Initiate Step 2
                $(".sync_process .step-2").removeClass('process_pending').addClass('process_inprogress');
                bulkSync(sync_message,data.data,sync_type,sync_ids); 
            }else{
                $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
            }
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }
    
    const bulkSync = (sync_message, data, sync_type, sync_ids) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes'); //Ajax action
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
                    $('#btn-sync-'+sync_ids).parent().html('<div class="single-sync-btn"><p class="sync_completed">Synced</p></div>');
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
                        $('.bulk-sync-btn').html('<a class="sync-complete" href="javascript:void(0);">Sync Complete</a>');
                        setTimeout( function() {
                            $('.bulk-sync-popup').hide();
                        }, 1000);
                    }, 2000);
                    
                }else{
                    $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
                }
            }
        });
    } 
});

function getEventsID(){
    var sync_selected_arr = [];
    jQuery(".sync_events_form tbody input[type=checkbox]:checked").each(function() {
         sync_selected_arr.push(jQuery(this).val());
    });
    if (sync_selected_arr.length > 0) {
        jQuery('.selected-sync-popup-btn').show();
    }else{
        jQuery('.selected-sync-popup-btn').hide();
    }
    var sync_selected_str = sync_selected_arr.toString(); 
    jQuery('#selected_events').val(sync_selected_str);
    var events_info = 'Total selected events: '+sync_selected_arr.length
    jQuery('.selected-events-info').html(events_info);
}

