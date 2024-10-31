
function trigger_process_import(total_products, page, var_offset, update_offset, global_counter, complete) {
    //alert('here');
    // yuri - default values
    if (page === undefined || page === null || page === '')
        page = 1;
    
    if (var_offset === undefined || var_offset === null || var_offset === '')
        var_offset = 0;

    if (update_offset === undefined || update_offset === null || update_offset === '')
        update_offset = 0;

    if (global_counter === undefined || global_counter === null || global_counter === '')
        global_counter = 1;

    // Update the progress meter
    jQuery("#dm-progress-count").html(global_counter);

    // Logic for the progress bar
    // Select the progressbar
    var progressbar = jQuery("#progressbar");
    // Unit to be added on the progress bar
    var progressUnit = progressbar.progressbar("value") || 0;
    // Progress bar label
    var progressLabel = jQuery("#dm-progressLabel");
    var progressBarLabel = "";

    // Check if we are on the first iteration
    if (parseInt(global_counter) === 0 ) {
        progressBarLabel = "Collecting product data from Amazon. ";
        progressUnit = 0;
    }
    // When the process is almost done or done and just waiting to go over through the pages
    else if (parseInt(global_counter) === parseInt(total_products)) {
        // progressunit to fill 80
        progressUnit = Math.floor((parseInt(global_counter) / parseInt(total_products)) * 100);
        // Show 90%
        // Finalizing the process
        if (progressUnit > 95) {
            progressUnit = 95;
        }

        progressBarLabel = "All products were processed. ";
        
        if (complete === null || complete === undefined) {
            progressUnit = 99;
            progressBarLabel = "Finalizing the process. ";
        }
    }
    // the progress between 10% - 90%
    else {
        // progressunit to fill 80
        progressUnit = Math.floor((parseInt(global_counter) / parseInt(total_products)) * 100);

        if (progressUnit > 95) {
            progressUnit = 95;
        }

        //if (mode === 'create') {
            progressBarLabel = "Posting new product. ";
        /*} else if (mode === 'variation') {
            progressBarLabel = "Creating product variations. ";
        } else if (mode === 'update') {
            progressBarLabel = "Updating existing products. ";
        }*/

    }

    // Set the value
    progressbar.progressbar("value", progressUnit);
    progressLabel.text(progressBarLabel + progressbar.progressbar("value") + "%");

    var data = {
        action: 'prossociate_iterate_import',
        total_products: total_products,        
        page: page,
        var_offset: var_offset,
        update_offset: update_offset,
        global_counter: global_counter
    };

    setTimeout(dm_call_ajax_import(data), 3000);

}

function dm_call_ajax_import(data) {
    //console.log(data);
    jQuery.post(ajaxurl, data, function(response, status) {
        var progressbar = jQuery("#progressbar");
        // Unit to be added on the progress bar
        var progressUnit = progressbar.progressbar("value") || 0;
        // Progress bar label
        var progressLabel = jQuery("#dm-progressLabel");
        obj = JSON.parse(response);

        currenthtml = jQuery('#pros_logspot').html();

        jQuery('#pros_logspot').html(currenthtml + obj.log);


        if (obj.complete == true) {

            currenthtml = jQuery('#pros_logspot').html();
            
            if(obj.mode === 'error') {
                jQuery('#pros_logspot').html("<b>Error</b><br /><br />" + currenthtml);

                jQuery('.pros_loader').html('<h2>Error Occurred</h2>');

                progressbar.progressbar("value", 100);
                jQuery('.progress-label').text("See the logs for more details");
            }
            else {
                jQuery('#pros_logspot').html("<b>Posting complete.</b><br /><br />" + currenthtml);

                jQuery('.pros_loader').html('<h2>Complete</h2>');
                jQuery('#dm-progress-count').html( obj.total_products );

                progressbar.progressbar("value", 100);

                progressLabel.text( "100%" );
            }

        } else {


            trigger_process_import(obj.total_products, obj.page, obj.var_offset, obj.update_offset, obj.global_counter, obj.complete);

        }

    }).fail(function(){
        console.log(data);
        //data.poster_offset = data.poster_offset + 10;
        trigger_process_import(data.total_products, data.page, data.var_offset, data.update_offset, data.global_counter, data.complete);
    });
}

jQuery(document).ready(function($) {
    jQuery("#dm-show-logs").click(function() {
        var checkLogs = jQuery("#pros_logspot").css('display');
        // check if logs is already displayed
        if (checkLogs === 'none') {
            jQuery("#dm-show-logs").html("- Hide Logs");
            jQuery("#pros_logspot").show();
        } else {
            jQuery("#pros_logspot").hide();
            jQuery("#dm-show-logs").html("+ Show Logs");
        }
    });
});
