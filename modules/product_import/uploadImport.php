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

    <br />
    <div id="dmPros_advanceSearch" style="margin-top:10px;">  
        <textarea rows="10" cols="65" style="margin-left:13px; margin-top:5px; border-color: #000000;" id="dmasinlists" name="dmasinlists"><?php echo $dmAsinLists; ?></textarea><br />
        One ASIN per line
    </div>

    <br /><br />

</div>
<br clear='all' />

