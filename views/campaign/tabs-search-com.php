<?php
$jsTreeStyle = " style='display: none'";
$manualNode = '';
$manualBox = " disabled='disabled'";
if($campaign->options["dmnode"] == 'manual') {
    $checkedManual = ' checked=checked';
    $manualNode = $campaign->options["category"];
    $manualBox = '';
} elseif($campaign->options["dmnode"] == 'tree') {
    $checkedTree = ' checked=checked';
    $jsTreeStyle = "";
} else {
    $checkedManual = '';
    $checkedTree = ' checked=checked';
    $jsTreeStyle = "";
}

if(isset($campaign->options['minprice']))
    $minPrice = $campaign->options['minprice'];
else
    $minPrice = '';

if(isset($campaign->options['maxprice']))
    $maxPrice = $campaign->options['maxprice'];
else
    $maxPrice = '';

if(isset($campaign->options['dmasinlists']))
    $dmAsinLists = $campaign->options['dmasinlists'];
else
    $dmAsinLists = '';

if(isset($campaign->options['useasinlists']) && $campaign->options['useasinlists'] === 'useasinlists')
    $useasinlists = ' checked=checked';
else
    $useasinlists = '';

global $woocommerce;
?>
<div class="dm-tab-search" xmlns="http://www.w3.org/1999/html">

    Category <input type='text' class="<?php echo $campaign->options['browsenode']; ?>" name='category' id='category' readonly='readonly' value='<?php echo $campaign->options["category"]; ?>' />

    Sort by <select name='sort' id='sort'><option selected="selected">Default</option></select>
    <input type='hidden' id='sortby' name='sortby' value='<?php echo $campaign->options["sortby"]; ?>' />

    Keywords <small>(optional)</small> <input type='text' name='keyword' id='keyword' style='width: 300px;' value='<?php echo $campaign->options["keywords"]; ?>' />
    Title <input type='text' name='item_title' id='item_title' style='width: 200px;' value='<?php echo $campaign->options["item_title"]; ?>' />
    <input class="button button-primary" type='button' id='pros_search_button' value='Find Products' />

    <br />
    <a href="#" class="dmShowAdvanceSearchFilter">Advanced Search +</a>
    <a href="#" class="dmShowAdvanceSearchFilter" style="display: none;">Advanced Search  -</a>

    <div id="dmPros_advanceSearch" style="margin-top:10px; display: none;">
        Min. Price: <input placeholder="no min" id="dmminprice" type="text" name="dmminprice" value="<?php echo $minPrice; ?>"/>
        Max Price: <input placeholder="no max" id="dmmaxprice" type="text" name="dmmaxprice" value="<?php echo $maxPrice; ?>" />
        <a href="#" class="help_tip" data-tip="Enter prices without the currency symbol and with the decimal separator, i.e. “500.00” or “2.300,00"><img style="vertical-align: middle;" src="<?php echo $woocommerce->plugin_url(); ?>/assets/images/help.png" height="16" width="16" /></a>
        <br /><br />
        <input style="margin-right: 5px;" type="checkbox" name="dmAmazonOnly" id="dmAmazonOnly" value="true"/><label for="dmAmazonOnly">Ignore offers from 3rd party sellers, only post products with offers directly from Amazon‏</label>
        <input type="hidden" id="dmAmazonOnlyCheck" name="dmAmazonOnlyCheck" value=""/>
        <br />
        <input type="checkbox" name="useasinlists" id="useasinlists" value="useasinlists" style="margin-right: 3px;"<?php echo $useasinlists; ?>/> <label for="useasinlists">Ignore search parameters, post the following ASINs</label> <br />
        <textarea rows="10" cols="65" style="margin-left:13px; margin-top:5px; border-color: #000000;" id="dmasinlists" name="dmasinlists"><?php echo $dmAsinLists; ?></textarea><br />
        One ASIN per line
    </div>

    <br /><br />

    <input type='hidden' id='dmnode' name='dmnode' value='<?php echo $campaign->options['dmnode']; ?>'/>
    <input type='hidden' id='searchindex' name='searchindex' value='<?php echo $campaign->options["searchindex"]; ?>' />
    <input type='hidden' id='browsenode' name='browsenode' value='<?php echo $campaign->options["browsenode"]; ?>' />
    <input type='hidden' id='nodepath' name='nodepath' value='<?php echo $campaign->options["nodepath"]; ?>' />
    <input type='hidden' id='ASINs' name="ASINs" value='<?php echo $campaign->options["ASINs"]; ?>' />
    <input type='hidden' id='tmp_nodepath' value='' />

    <div id='pros_adv_search' style='display: block;'>

        <u><b>Advanced Search</b></u>
        <br />
        <br />

        <label for="availability_chk">Availabile Product Only</label>
        <input type="checkbox" id="availability_chk" name="availability_chk" value="Available" <?php if (!empty($campaign->options["availability"])) echo 'checked="checked"'; ?> onchange="check_availability_options(this)" />
        <input type="hidden" id="availability" name="availability" value="<?php echo $campaign->options["availability"]; ?>" />
        <br />

        <label for="merchantid_chk">Amazon Product Only</label>
        <input type="checkbox" id="merchantid_chk" name="merchantid_chk" value="Amazon" <?php if (!empty($campaign->options["merchantid"])) echo 'checked="checked"'; ?> onchange="check_availability_options(this)" />
        <input type="hidden" id="merchantid" name="merchantid" value="<?php echo $campaign->options["merchantid"]; ?>" />
        <br />

        <label for="condition">Condition</label>
        <select id="condition" name="condition">
            <option value="New" selected="selected">New</option>
            <option value="Used">Used</option>
            <option value="Collectible">Collectible</option>
            <option value="Refurbished">Refurbished</option>
            <option value="All">All</option>
        </select>
        <br />

        <label for="manufacturer">Manufacturer</label>
        <input type="text" id="manufacturer" name="manufacturer" value="<?php echo $campaign->options["manufacturer"]; ?>" />
        <br />

        <label for="brand">Brand</label>
        <input type="text" id="brand" name="brand" value="<?php echo $campaign->options["brand"]; ?>" />
        <br />

        <label for="minpercentageoff">Min Percentage Off</label>
        <input type="text" id="minpercentageoff" name="minpercentageoff" value="<?php echo $campaign->options["minpercentageoff"]; ?>" />

    </div>
    <div id="pros_serps_wrapper">

        <!-- For no category -->
        <div id="dm-overlay-no-category" class="dm-overlay-class dm-waiter-search" style="display: none;">
            <div class="dm-waiter-img-category">
                <div class="dm-waiter-text">Please select a category...</div>
            </div>
        </div>
        <div id="dm-waiter-search-overlay" style="display: none;">

        </div>
        <div id='pros_serps'>

        </div>
    </div>
</div>
<!-- For loading amazon -->
<div id="dm-overlay-search" class="dm-overlay-class dm-waiter-search" style="display: none;">
    <div class="dm-waiter-img">
        <div class="dm-waiter-text">Loading results from Amazon...</div>
    </div>
</div>

<?php if( !isset($_REQUEST['campaign_id']) ) { ?>
    <div id="dm-pros-new-campaign" class="about-text">
        Find Amazon products to add by entering search keywords and a category
    </div>
<?php } ?>

<div title='Choose a category' style='display: none;' id='cattree_container' style='width: 400px;'>
    <div class="dmBrowseNodeChoice">
        <input name="dmBrowseNode" type="radio" id="dmCatManual" value="manual"<?php echo $checkedManual; ?>/> <label for='dmCatManual'>Manually Specify a BrowseNode ID</label>
        <div style="text-align: center; margin-bottom: 5px;"><input<?php echo $manualBox; ?> id="dmBrowseNodeText" type="text" value="<?php echo $manualNode; ?>"/></div>
        <input name="dmBrowseNode" type="radio" id="dmCatNormal" value="tree"<?php echo $checkedTree; ?>/> <label for='dmCatNormal'>Choose a BrowseNode</label>
        <div id='jstree_upper_container'<?php echo $jsTreeStyle; ?>>
            <div id='jstree_container'></div>
        </div>
    </div>
</div>

<br clear='all' />

<!-- Yuri -->
<script type="text/javascript">
    var selected_cnt = 0;
    <?php
    if (isset($campaign->options)) {
        ?>
    jQuery(document).ready(function() {
        // load search parameters
        load_browsenode_sortvalues('<?php echo $campaign->options["searchindex"]; ?>', '<?php echo $campaign->options["sortby"]; ?>');
        jQuery("#condition").val('<?php echo $campaign->options["condition"]; ?>');
    });
    <?php
}
?>
</script>