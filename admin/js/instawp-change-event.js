jQuery(document).ready(function ($) {
    $(document).on('click', '.two-way-sync-btn', function(){
        var btn_id = $(this).attr('data-id'); 
            console.log(btn_id);
        });
});
