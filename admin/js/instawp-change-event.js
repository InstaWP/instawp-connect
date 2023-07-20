jQuery(document).ready(function ($) {

    //Syncing enabled disabled
    jQuery('.syncing_enabled_disabled input[type="checkbox"]').click(function(){
        var sync_status = 0;
        if(jQuery(this).prop("checked") == true){
            console.log("Checkbox is checked.");
            sync_status = 1;
        }else{
            console.log("Checkbox is unchecked.");
            sync_status = 0;
        }
        syncing_enabled_disabled(sync_status);
    });

    //selected sync btn...

    //bulk sync btn...
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        get_events_summary();
        $('.bulk-sync-popup').show();
        $('.bulk-sync-popup').attr("data-sync-type", "bulk_sync");
        $('.bulk-events-info').show();
        $("#destination-site").val($("#staging-site-sync").val());
        $('.selected-events-info').hide();
        $('.sync_error_success_msg').html('');
        $('#sync_message').val('');
        $(".sync_process .step-1").removeClass('process_inprogress').removeClass('process_complete');
        $(".sync_process .step-2").removeClass('process_inprogress').removeClass('process_complete');
        $(".sync_process .step-3").removeClass('process_inprogress').removeClass('process_complete');
        $(".bulk-sync-btn").html('<a class="changes-btn sync-changes-btn" href="javascript:void(0);"><span>Sync Changes</span></a>');
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

    $(document).on('click', 'div#event-sync-pagination a.page-numbers', function(event){
        event.preventDefault();
        const url = $(this).attr('href');
        const urlParams = new URLSearchParams(url);
        const page = urlParams.get('epage') !=null ?urlParams.get('epage') : 1;
        get_site_events(page);
    });

    $(document).on('change', '#staging-site-sync', function(){
        get_site_events();
    });
    
    $(document).on('click', '.instawp-refresh-events', function(){
        get_site_events();
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
        const dest_connect_id = $("#destination-site").val();
         $(".sync_error_success_msg").html('');
         if(dest_connect_id == null){
            $(".sync_error_success_msg").html('<p class="error">No site found.</p>');
            return;
         }
        var sync_ids = '';
        if(sync_type == 'selected_sync'){
            sync_ids = $('#selected_events').val();
        }
        //Initiate Step 2
        $(this).addClass('disable-a loading');
        $(".sync_process .step-1").removeClass('process_pending').addClass('process_inprogress');
        packThings(sync_message,sync_type,sync_ids,dest_connect_id);
    });

    //Single sync..
    $(document).on('click', '.btn-single-sync', function(){
        var sync_ids = $(this).attr('data-id');
        var sync_id = $(this).attr('id');
        const dest_connect_id = $("#staging-site-sync").val();
        const sync_message = 'This is a single sync.';
        $(this).attr('disabled','disabled');
        $(this).removeClass('two-way-sync-btn').addClass('loading')
        //Initiate Step 2
        //$('#btn-sync-'+sync_ids).parent().find('.sync-loader').html('<img src="'+ajax_obj.plugin_images_url+'/loaders/loader.gif" style="width:20px">');
        packThings(sync_message,'single_sync',sync_ids,dest_connect_id);
    });

    const baseCall = async (body) => {
        return await fetch(ajax_obj.ajax_url, {
            method: "POST",
            credentials: 'same-origin',
            body
        });
    }

    const get_events_summary = async () => {
        let formData = new FormData();
        formData.append('action', 'get_events_summary');
        baseCall(formData).then((response) => response.json()).then((data) => {
           $("#post_change_event_count").html(data.data.results.post_new);
           $("#post_delete_event_count").html(data.data.results.post_delete);
           $("#post_trash_event_count").html(data.data.results.post_trash);
           $("#post_other_event_count").html(data.data.results.others);

        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }

    const get_site_events = async (page=1) => {
        let site_id =  $("#staging-site-sync").val();
        let current_page =  $("#staging-site-sync").data('page');
        console.log('current_page',current_page);
        if(current_page == undefined) return;
        let formData = new FormData();
        formData.append('action', 'get_site_events');
        formData.append('epage', page);
        formData.append('connect_id', site_id);
        $("#part-sync-results").html('<tr><td colspan="4" class="event-sync-cell loading"></td></tr>');
        baseCall(formData).then((response) => response.json()).then((data) => {
           $("#part-sync-results").html(data.data.results);
           $("#event-sync-pagination").html(data.data.pagination);
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }

    /**
     * call the function for load events
     */
    get_site_events();

    const syncing_enabled_disabled = async (sync_status) => {
        let formData = new FormData();
        formData.append('action', 'syncing_enabled_disabled');
        formData.append('sync_status', sync_status);
        baseCall(formData).then((response) => response.json()).then((data) => {
            console.log(data);
            jQuery('.syncing_status_msg').html('<p class="success">'+data.message+'</p>');
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }

    const packThings = async (sync_message,sync_type,sync_ids,dest_connect_id) => {
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
                bulkSync(sync_message,data.data,sync_type,sync_ids, dest_connect_id); 
            }else{
                $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
            }
        }).catch((error) => {
            console.log("Error Occurred: ", error);   
            $(".sync-changes-btn").removeClass('disable-a loading');
        });
        
    }
    
    const bulkSync = (sync_message, data, sync_type, sync_ids, dest_connect_id) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes'); //Ajax action
        formData.append('sync_message', sync_message);
        formData.append('sync_type', sync_type);
        formData.append('dest_connect_id', dest_connect_id);
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
                    var element = $('#btn-sync-'+sync_ids).parent().parent().find('.synced_status');
                    element.removeClass('pending').addClass('completed');
                    element.html(synced_status);
                    $('#btn-sync-'+sync_ids).parent().html('<div class="single-sync-btn"><p class="sync_completed">Synced</p></div>');
                }else{
                    $('#btn-sync-'+sync_ids).addClass('two-way-sync-btn').removeClass('loading').attr('disabled',false);
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
                        $(".sync-changes-btn").removeClass('disable-a loading');
                        $('.bulk-sync-btn').html('<a class="sync-complete" href="javascript:void(0);">Sync Completed</a>');
                        setTimeout( function() {
                            $('.bulk-sync-popup').hide();
                            get_site_events();
                        }, 1000);
                    }, 2000);
                    
                }else{
                    $(".sync-changes-btn").removeClass('disable-a loading');
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

