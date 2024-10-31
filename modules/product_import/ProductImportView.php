<?php
if(isset($_POST['csv_upload']) && check_admin_referer( 'upload_csv_file', 'csv_upload_nonce' ) ) {
?>
    <div class="wrap">
    <div class="dm-process-wrap">
        <div class='pros_loader'>
            <img src='<?php echo PROSSOCIATE_ROOT_URL; ?>/images/ajax-loader.gif' id='img-process' style='vertical-align: middle;'>
            <h2 style='display: inline;'>Processing...</h2>
        </div>

        <div style='font-style: italic;'>
            <?php

            //ob_implicit_flush(1);

            ?>
        </div>
        <div id="dm-process">
            <div id="dm-progress">
                <div id="dm-progressbar-label"></div>
                <div id="progressbar"><div class="progress-label"><span id="dm-progressLabel">Initializing the process</span></div></div>
                <div id="dm-progress-meter">
                    <?php echo "Processing - <span id='dm-progress-count'>1</span>/" . get_option('total_asins') . " products. Do not close your browser."; ?>
                </div>
            </div>
            <a id="dm-show-logs" href="#">+ Show Logs</a>
        </div>
        <div id='pros_logspot' style="display: none;">
        <?php
        do_action('show_import_log');
/*ob_implicit_flush(1);

for($i=0; $i<10; $i++){
var_dump($_POST);
var_dump($_FILES);
    //this is for the buffer achieve the minimum size in order to flush data
    echo '<p>processing data for ASIN'.$i.'</p>';

    sleep(1);
}*/


        ?>
        </div>
    </div>
</div>
<script type="text/javascript">
            jQuery(document).ready(function() {

                    var progressbar = jQuery( "#progressbar" ),
                      progressLabel = jQuery( ".progress-label" );

                    progressbar.progressbar({
                      value: false,
                      //change: function() {
                        //progressLabel.text( progressbar.progressbar( "value" ) + "%" );
                      //}
                      complete: function() {
                        progressLabel.text( "100%" );
                      }
                    });
                    // pass argument posted here.
                /*trigger_process_import();*/

            });
            jQuery(document).ready(function() {
                //alert('here');
                trigger_process_import(<?php echo get_option('total_asins'); ?> );
            });
        </script>
<?php
}else{
?>
<div class="wrap">
    <h2>Import Product </h2>

    <!-- Yuri -->
    <form id="import_form" name='import_form' method="post" action=""  enctype="multipart/form-data">

        <!-- louis nav tabs addition -->
        <h2 class="nav-tab-wrapper">
            <a href="#" id='tabs-search-link' class="nav-tab nav-tab-active">Upload File</a>
            <a href="#" id='tabs-post-link' class="nav-tab ">Optional Settings</a>
        </h2>
        <!-- louis nav tabs addition -->


        <div id="tabs-search">
            <div class="dm-tab-search" xmlns="http://www.w3.org/1999/html">

    <br />
    <div id="dmPros_advanceSearch" style="margin-top:10px;">
        <!--textarea id="dmasinlists" name="dmasinlists" rows="10" cols="65" style="margin-left:13px; margin-top:5px; border-color: #000000;" id="dmasinlists" name="dmasinlists"><?php echo $dmAsinLists; ?></textarea-->
        
            <input type="file" name="asin_csv"><br>
            Upload CSV file products ASINs.<br>
            <input type="submit" name="csv_upload" value="Upload"><br>
            <?php wp_nonce_field( 'upload_csv_file', 'csv_upload_nonce' ); ?>
            <input type="hidden" name="download_images" value="on">

    </div>

    <br /><br />

</div>
<br clear='all' />

        </div>

        <div id="tabs-post" style='display: none;'>
            <?php include "settingImport.php"; ?>
        </div>

    </form>

</div>
<script type="text/javascript">
   jQuery("#tabs-post-link").click(function() {
            jQuery("#tabs-search").hide();
            jQuery("#tabs-settings").hide();
            jQuery("#tabs-post").show();

            jQuery('#tabs-post-link').addClass('nav-tab-active');
            jQuery('#tabs-settings-link').removeClass('nav-tab-active');
            jQuery("#tabs-search-link").removeClass('nav-tab-active');

        });
        jQuery("#tabs-settings-link").click(function() {
            jQuery("#tabs-post").hide();
            jQuery("#tabs-search").hide();
            jQuery("#tabs-settings").show();

            jQuery('#tabs-settings-link').addClass('nav-tab-active');
            jQuery('#tabs-search-link').removeClass('nav-tab-active');
            jQuery("#tabs-post-link").removeClass('nav-tab-active');

        });
        jQuery("#tabs-search-link").click(function() {
            jQuery("#tabs-post").hide();
            jQuery("#tabs-settings").hide();
            jQuery("#tabs-search").show();

            jQuery('#tabs-search-link').addClass('nav-tab-active');
            jQuery('#tabs-settings-link').removeClass('nav-tab-active');
            jQuery("#tabs-post-link").removeClass('nav-tab-active');
        });

        // optional settings
        jQuery('#dm_select_category_post_options').click(function(){
        var dmischeck = jQuery('#post_options-dm_select_category').is(':checked');
        if( dmischeck )
        {
            //jQuery('#post_options-dm_select_category').prop("checked", true);
            jQuery('#post_options-dm_select_category').removeAttr('checked');
            jQuery('#poststuff').hide();
        } else {
            //jQuery('#post_options-dm_select_category').prop("checked", false);
            jQuery('#post_options-dm_select_category').attr('checked','checked');
            jQuery('#poststuff').show();
        }
    });

    // For the category meta box
    jQuery('#post_options-dm_select_category').change(function(e){
        var dmischeck = jQuery('#post_options-dm_select_category').attr('checked');
        if( dmischeck === 'checked' )
        {
            jQuery('#poststuff').show();
        }
        else {
            jQuery('#poststuff').hide();
        }

    });
</script>

<?php } ?>
