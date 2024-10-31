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

<h3 style="display: none">Update Settings</h3>

<table class="form-table" style="display: none">

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

<div class="dm-campaign-post-button dm-hide-first" style="display: none;">
    <input class="wp-core-ui button-primary dm-save-campaign-button" type='button' name='pros_save_submit_post' value='Save Campaign & Post Products' /><br />
    <!-- Yuri -->
    <input type="hidden" id="pros_submit_type" name="pros_submit_type" value="" />
</div>
