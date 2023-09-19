jQuery(document).ready(function ($) {

    jQuery('.instawp_select2').select2({
        width:"180px",
    });

    //select2 for default user settings
    jQuery('.instawp_select2_ajax').select2({
        width:"180px",
        ajax: {
            dataType: 'json',
            delay: 100,
            processResults: function (res) {
                const $text     = res.data.opt_col.text;
                const $id       = res.data.opt_col.id;
                const results   = res.data.results.map((element) => {
                    return {
                        text: $text != undefined ? element[$text] : element.text,
                        id: $text != undefined ? element[$id] : element.id,
                    }
                });
                return {
                    results:  results
                };
            },
            cache: true
        }
    })

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


    //bulk sync btn...
    $(document).on('click', '.bulk-sync-popup-btn', function(){
        const site = $("#staging-site-sync").val();
        if( !site || site == undefined || site == '' ){
            alert( ajax_obj.trans.create_staging_site_txt );
            return;
        }
        get_events_summary();
        $('.bulk-sync-popup').show();
        $('.bulk-sync-popup').attr("data-sync-type", "bulk_sync");
        $('.bulk-events-info').show();
        $('.selected-events-info').hide();
        $('.sync_error_success_msg').html('');
        $('#sync_message').val('');

        //progress bar
        $(".event-progress-text").html('')
        $(".progress-wrapper").addClass('hidden');
        $(".event-progress-bar>div").css('width', '0%');

        $("#destination-site").val($("#staging-site-sync").val());
        $(".sync_process .step-1").removeClass('process_inprogress').removeClass('process_complete');
        $(".sync_process .step-2").removeClass('process_inprogress').removeClass('process_complete');
        $(".sync_process .step-3").removeClass('process_inprogress').removeClass('process_complete');
        $(".bulk-sync-btn").html('<a class="changes-btn sync-changes-btn" href="javascript:void(0);"><span>Sync</span></a>');
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

    // $("#select-all-event").click(function(){
    //     $('.single-event-cb:checkbox').not(this).prop('checked', this.checked);
    // });

    display_event_action_dropdown =  () => {
        if( $('.single-event-cb:checked').length == 0 ){
            $("#instawp-delete-events").addClass('hidden');
        }else{
            $("#instawp-delete-events").removeClass('hidden');
        }
    }

    $(document).on('click','#select-all-event', function(e) {   
        $("body").find('.single-event-cb:checkbox').not(this).prop('checked', this.checked);
        display_event_action_dropdown();
    });
    
    $(document).on('click','.single-event-cb', function(e) {
        if ($('.single-event-cb:checked').length == $('.single-event-cb').length) {
            $("body").find('#select-all-event').prop('checked', true);
        } else {
            $("body").find('#select-all-event').prop('checked', false);
        }
        display_event_action_dropdown();
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

    $(document).on('click', '#instawp-delete-events', function(){
        const selectedEvents = [];
        $('.single-event-cb:checked').each(function(){
            selectedEvents.push($(this).val());
        });
        if(selectedEvents.length > 0){
            if( confirm('Are you sure?') ){
                let formData = new FormData();
                let site_id =  $("#staging-site-sync").val();
                formData.append('site_id', site_id);
                formData.append('action', 'instawp_delete_events');
                formData.append('ids',  selectedEvents);
                baseCall(formData).then((response) => response.json()).then((data) => {
                    get_site_events();  
                    display_event_action_dropdown();
                    $("body").find('#select-all-event').prop('checked', false);
                }).catch((error) => {

                });
            }  
        }
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
        formData.append('action',       'get_events_summary');
        formData.append('connect_id',   $("#destination-site").val() );
        baseCall(formData).then((response) => response.json()).then(( response ) => {            
           $("#event-type-list").html(response.data);
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }

   
    const get_site_events = async (page=1) => {
        let site_id =  $("#staging-site-sync").val();
        let current_page =  $("#staging-site-sync").data('page');

        if(current_page == undefined) return;
        let formData = new FormData();
        formData.append('action', 'get_site_events');
        formData.append('epage', page);
        formData.append('connect_id', site_id);

        $("#part-sync-results").html('<tr><td colspan="5" class="event-sync-cell loading"></td></tr>');

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
            if( data.sync_status == 1 ){
                $("#wp-toolbar").find('ul:first').append(ajax_obj.data.event_toolbar_html);
            }else{
                $(".instawp-sync-status-toolbar").remove();
            }
            jQuery('.syncing_status_msg').html('<p class="success">'+data.message+'</p>');
        }).catch((error) => {
            console.log("Error Occurred: ", error);
        });
    }

    
    //Bulk&Selected sync process...
    $(document).on('click', '.sync-changes-btn', function(){

        const sync_message = $("#sync_message").val();
        const sync_type = $('.bulk-sync-popup').attr("data-sync-type");
        const dest_connect_id = $("#destination-site").val();
         $(".sync_error_success_msg").html('');
         
        //Initiate Step 2
        $(this).addClass('disable-a loading');
        let formData = new FormData();
        formData.append('action',       'instawp_calculate_events');
        formData.append('connect_id',   dest_connect_id );
        baseCall(formData).then((response) => response.json()).then((data) => {
            
            if( data.success ){
                $(".progress-wrapper").removeClass('hidden');
                $(".event-progress-text").html(data.data.progress_text)
                packThings(sync_message,sync_type,dest_connect_id, page=1);
            }else{
                $(".sync-changes-btn").removeClass('disable-a loading');
                $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
            }

        }).catch((error) => {
           
        });
    });
    
    $(document).on('change', '#destination-site', function(){
        get_events_summary();
    });

    const packThings = async (sync_message,sync_type,dest_connect_id, page) => {
        let formData = new FormData();
        formData.append('action', 'pack_things');
        formData.append('sync_type', sync_type);
        formData.append('sync_message', sync_message);
        formData.append('page', page);
        $(".sync_process .step-1").removeClass('process_pending').addClass('process_inprogress');
        baseCall(formData).then((response) => response.json()).then((data) => {
            console.log('data', data);
            if(data.success === true){
                //Complete Step 1
                $(".sync_process .step-1").removeClass('process_inprogress').addClass('process_complete');
                //Initiate Step 2
                $(".sync_process .step-2").removeClass('process_pending').addClass('process_inprogress');
                bulkSync(sync_message,data.data,sync_type, dest_connect_id, page); 
            }else{
                $(".sync-changes-btn").removeClass('disable-a loading');
                $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
            }
        }).catch((error) => {
            console.log("Error Occurred: ", error);   
            $(".sync-changes-btn").removeClass('disable-a loading');
        });
        
    }
    
    const bulkSync = (sync_message, data, sync_type, dest_connect_id, page) => {
        let formData = new FormData();
        formData.append('action', 'sync_changes');
        formData.append('sync_message', sync_message);
        formData.append('sync_type', sync_type);
        formData.append('dest_connect_id', dest_connect_id);
        formData.append('sync_ids', '');
        formData.append('data', data);
        formData.append('page', page);
        baseCall(formData).then((response) => response.json()).then((data) => { 
            if(data.success === true){
                const paging = data.data.paging_data;

                $(".sync_process .step-2").removeClass('process_inprogress').addClass('process_complete');
                //Initiated Step3
                
                $(".event-progress-text").html(paging.progress_text)
                $(".event-progress-bar>div").css('width', paging.percent_completed+'%');
                $(".sync_process .step-3").removeClass('process_pending').addClass('process_inprogress');

                var syncIds = $("#id_syncIds").val();
                var array = syncIds.split(',');
                var index = array.indexOf(paging.sync_id);
                if(index === -1){
                    array.push(paging.sync_id);
                    $("#id_syncIds").val(array.join(','))
                }

                if( paging.page < paging.total_page ){
                    packThings(sync_message,sync_type,dest_connect_id, paging.next_page);
                }
                
                if( paging.percent_completed == 100 && paging.total_page == paging.page ){
                    $('.bulk-sync-btn').html('<a class="sync-complete" href="javascript:void(0);">Processing...</a>');
                    updateSyncStatus();
                }
            }else{
                $(".sync-changes-btn").removeClass('disable-a loading');
                $('.sync_error_success_msg').html('<p class="error">'+data.message+'</p>');  
            }
        });
    }

    const updateSyncStatus = () => {
        let formData = new FormData();
        formData.append('action',       'update_sync_status');
        formData.append('sync_ids',     $("#id_syncIds").val() );
        formData.append('connect_id',   $("#destination-site").val() );

        baseCall(formData).then((response) => response.json()).then(( data ) => { 

            $(".sync-changes-btn").removeClass('disable-a loading');
            $(".sync_process .step-3").removeClass('process_inprogress').addClass('process_complete');
            $('.bulk-sync-btn').html('<a class="sync-complete" href="javascript:void(0);">Sync Completed</a>');

            setTimeout( function() {
                $("#id_syncIds").val('');
                $('.bulk-sync-popup').hide();
                get_site_events();
            }, 2000);
            
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

