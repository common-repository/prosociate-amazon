<?php
// Check if "Select categories was checked"
$dmDisplay = 'none';
if (isset($campaign->post_options['dm_select_category'])) {
    if ($campaign->post_options['dm_select_category'] == 'yes') {
        $dmDisplay = 'inline';
        $checkDMCategory = 'checked="checked"';
    } else {
        $checkDMCategory = '';
    }
} else {
    $checkDMCategory = '';
}

if (isset($campaign->post_options['date_type'])) {
    if ($campaign->post_options['date_type'] == 'specific') {
        $checkSpecificDate = 'checked="checked"';
    } else {
        $checkSpecificDate = '';
    }

    if ($campaign->post_options['date_type'] == 'random') {
        $checkRandomDate = 'checked="checked"';
    } else {
        $checkRandomDate = '';
    }
}

if (isset($campaign->post_options['date_start'])) {
    $postDateStart = $campaign->post_options['date_start'];
} else {
    $postDateStart = '';
}

if (isset($campaign->post_options['date_end'])) {
    $postDateEnd = $campaign->post_options['date_end'];
} else {
    $postDateEnd = '';
}

if(isset($campaign->post_options['download_images'])) {
    if($campaign->post_options['download_images'] == 'on') {
        $postDownloadImage = 'checked="checked"';
    } else {
        $postDownloadImage = '';
    }
} else {
    $postDownloadImage = '';
}

if(isset($campaign->post_options['manual_gallery'])) {
    if($campaign->post_options['manual_gallery'] == 'on') {
        $postManualGallery = 'checked="checked"';
    } else {
        $postManualGallery = '';
    }
} else {
    $postManualGallery = '';
}

if(isset($campaign->post_options['auto_category'])) {
    if($campaign->post_options['auto_category'] == 'yes') {
        $checkAutoCategory = 'checked="checked"';
    } else {
        $checkAutoCategory = '';
    }
} else {
    $checkAutoCategory = '';
}

if(isset($campaign->post_options['comment_status'])) {
    if($campaign->post_options['comment_status'] == 'open') {
        $checkCommentStatus = 'checked="checked"';
    } else {
        $checkCommentStatus = '';
    }
} else {
    $checkCommentStatus = '';
}

if(isset($campaign->post_options['ping_status'])) {
    if($campaign->post_options['ping_status'] == 'open') {
        $checkPingStatus = 'checked="checked"';
    } else {
        $checkPingStatus = '';
    }
} else {
    $checkPingStatus = '';
}

if(isset($campaign->post_options['excerpt'])) {
    if($campaign->post_options['excerpt'] =='on' ) {
        $checkPostExcerpt = 'checked="checked"';
    } else {
        $checkPostExcerpt = '';
    }
} else {
    $checkPostExcerpt = '';
}

if(isset($campaign->post_options["excerpt_template"])) {
    $checkExcerptTemplate = $campaign->post_options["excerpt_template"];
} else {
    $checkExcerptTemplate = '';
}

$dmExternalAffiliateChoices = array(
    'simple' => 'Simple/Variable',
    'affiliate' => 'External/Affiliate'
);
$dmExternalAffiliate = "simple";
if(isset($campaign->post_options['externalaffilate'])) {
    $dmExternalAffiliate = $campaign->post_options['externalaffilate'];
}

if(isset($campaign->post_options['postfree'])) {
    if($campaign->post_options['postfree'] == 'postfree') {
        $checkPostFree = 'checked="checked"';
    } else {
        $checkPostFree = '';
    }
} else {
    $checkPostFree = '';
}
$checkPostChild = 'checked="checked"';
if(isset($campaign->post_options['postchild'])) {
    if($campaign->post_options['postchild'] == 'postchild') {
        $checkPostChild = 'checked="checked"';
    } else {
        $checkPostChild = '';
    }
} else {
    $checkPostChild = '';
}

if(isset($campaign->post_options['enablespin'])) {
    if($campaign->post_options['enablespin'] == 'enablespin') {
        $checkEnableSpin = 'checked="checked"';
    } else {
        $checkEnableSpin = '';
    }
} else {
    $checkEnableSpin = '';
}


if(isset($campaign->post_options['draft']) && $campaign->post_options['draft'] == 'draft') {
    $checkPostStatus = 'checked="checked"';
} else {
    $checkPostStatus = '';
}
?>

<div style='display: none;'>

    <b>Create posts as...</b><br />

    <select name='post_options[post_type]' id='post_options_post_type'>

        <?php
            echo "<option value='product'>Product</option>";

        ?>
    </select>
    <br />

    <br />

</div>



<h3>Post Dates</h3>

<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Dates</th>
        <td class="forminp">
            <fieldset>

                <input type="radio" name="post_options[date_type]" value="specific" <?php //echo $checkSpecificDate; ?> id="date_type_specific" />
                <label for="date_type_specific">All Products Have The Specified Post Date</label> <input type="text" class="datepicker" name="post_options[date]" value="now" style="width: 150px;"/>

                <br />

                <input type="radio" name="post_options[date_type]" value="random" <?php //echo $checkRandomDate; ?> id="date_type_random" />
                <label for="date_type_random">Posts Have Randomized Dates Between...</label>

                <div style='padding-top: 6px;'><input type="text" class="datepicker" name="post_options[date_start]" value="" /> and
                    <input type="text" class="datepicker" name="post_options[date_end]" value="" /></div>

                <p class="description">Use plain English in the date boxes! Examples: "now", "+3 months", "January 15th, 2013", "yesterday"</p>
            </fieldset>
        </td>
    </tr>

</table>



<div style='display: none;'>
    <br /><br />
    <b>Images</b><br />
    <input type='checkbox' name='post_options[download_images]' id='post_options[download_images]' value='on' <?php echo $postDownloadImage; ?> /> <label for='post_options[download_images]'>Download Product Images And Add Them To The Media Gallery/Featured Image</label>
    <br />
    <input type='checkbox' name='post_options[manual_gallery]' id='post_options[manual_gallery]' value='on' <?php echo $postManualGallery; ?> /> <label for='post_options[manual_gallery]'>Insert Manual Image Gallery Into Posts (Only use if your theme does not automatically display an image gallery)</label>
</div>


<h3>Categories</h3>

<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Choose Categories</th>
        <td class="forminp">

            <input type='checkbox' name='post_options[dm_select_category]' id='post_options-dm_select_category' value='yes' <?php echo $checkDMCategory; ?> /> <span id="dm_select_category_post_options"><label for='post_options[dm_select_category]'>Assign Products to the Selected Categories</label></span><br />
            <div id="poststuff" class="metabox-holder" style="display: <?php echo $dmDisplay; ?>;">
                <?php $meta_boxes = do_meta_boxes('prosociate_page_prossociate_addedit_campaign', 'side', $test_object = ''); ?>
            </div>

        </td>
    </tr>
    <tr valign="top" class="">
        <th scope="row" class="titledesc">Auto-Create Categories</th>
        <td class="forminp">


            <input type='checkbox' name='post_options[auto_category]' id='post_options[auto_category]' value='yes' <?php echo $checkAutoCategory; ?> /> <label for='post_options[auto_category]'>Auto-Create Product Categories from Amazon BrowseNodes</label><br />

            <p class="description">Replicate Amazon categories for selected products.</p>

        </td>
    </tr>

</table>

<h3>Advanced Options</h3>

<table class="form-table">



    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Product As</th>
        <td class="forminp">
            <select class="dmExtAff" name="post_options[externalaffilate]" id="post_options[externalaffilate]">
                <?php foreach($dmExternalAffiliateChoices as $k => $v) {
                    $selected = '';
                    if($k == $dmExternalAffiliate)
                        $selected = ' selected=selected';
                    echo "<option value='{$k}'{$selected}>{$v}</option>";
                } ?>
            </select>
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Status</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[draft]' id='post_options[draft]' value='draft' <?php echo $checkPostStatus; ?> /> <label for='post_options[draft]'>Post As Draft</label><br />
        </td>
    </tr>


    <tr valign="top" class="">
        <th scope="row" class="titledesc">Discussion</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[comment_status]' id='post_options[comment_status]' value='open' <?php echo $checkCommentStatus; ?> /> <label for='post_options[comment_status]'>Allow Comments</label><br />
            <input type='checkbox' name='post_options[ping_status]' id='post_options[ping_status]' value='open' <?php echo $checkPingStatus; ?> /> <label for='post_options[ping_status]'>Allow Trackbacks/Pingbacks</label><br />
        </td>
    </tr>


    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post Author</th>
        <td class="forminp">
            <?php
                if(isset($post['author'])) {
                    wp_dropdown_users(array('name' => 'post_options[author]', 'selected' => $post['author']));
                } else {
                    wp_dropdown_users(array('name' => 'post_options[author]'));
                }
            ?>
            <?php ; ?>
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post "Free" Products</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[postfree]' id='post_options[postfree]' value='postfree' <?php echo $checkPostFree; ?> /> <label for='post_options[postfree]'>Post "Free" Products</label><br />
        </td>
    </tr>

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Post "Child" Products</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[postchild]' id='post_options[postchild]' value='postchild' <?php echo $checkPostChild; ?> /> <label for='post_options[postchild]'>Post "Child" Products</label><br />
        </td>
    </tr>
    <tr valign="top" class="">
        <th scope="row" class="titledesc">Enable Text Spinning</th>
        <td class="forminp">
            <input type='checkbox' name='post_options[enablespin]' id='post_options[enablespin]' value='enablespin' <?php echo $checkEnableSpin; ?> /> <label for='post_options[enablespin]'>Enable Text Spinning</label><br />
        </td>
    </tr>

</table>

<div style='display: none;'>
    <input type='checkbox' name='post_options[excerpt]' id='post_options[excerpt]' value='on' <?php echo $checkPostExcerpt; ?> />
    <label for='post_options[excerpt]'>Post Excerpt</label> <input type='text' name='post_options[excerpt_template]' value='<?php echo $checkExcerptTemplate; ?>' /><br />
</div>

<h3>Campaign Friendly Name</h3>


<table class="form-table">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">Friendly Name</th>
        <td class="forminp">
            <fieldset>
                <input type='text' name='campaign_name' id='campaign_name' style='width: 250px;' value='<?php echo $campaign->name; ?>'><br />
                <input type='hidden' id='campaign_id' name='campaign_id' value='<?php echo $campaign->id; ?>' />
                <p class="description">Shown on the Manage Campaigns screen.</p>
            </fieldset>
        </td>
    </tr>

</table>

<table class="form-table" style="display:none;">

    <tr valign="top" class="">
        <th scope="row" class="titledesc">New Search Results</th>
        <td class="forminp">
            <fieldset>
                <select name='campaign_settings[reperform]'>
                    <option value='create' <?php echo ($campaign->campaign_settings['reperform'] == 'create' ? 'selected' : ''); ?>>Create a new post for each product that hasn't already been posted.</option>
                    <option value='existing' <?php echo ($campaign->campaign_settings['reperform'] == 'existing' ? 'selected' : ''); ?>>Ignore new products, only update existing posts.</option>
                </select>
                <p class="description">When new products appear in the search results.</p>
            </fieldset>
        </td>
    </tr>
</table>

<div class="dm-campaign-post-button dm-hide-first" style="display:none;">
    <input style="font-size: 14px;" class="wp-core-ui button-primary dm-save-campaign-button" type='button' name='pros_submit_type' value='Create Product Posts' /><br />
    <!-- Yuri -->
</div>
