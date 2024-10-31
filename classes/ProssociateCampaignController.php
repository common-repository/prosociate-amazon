<?php
/**
 * The campaign controller. Only load on campaign-related pages
 */
class ProssociateCampaignController {
    /**
     * Checker if we are on subscription
     *
     * @var bool
     */
    public $isSubscription = false;
    /**
     * Construct
     */
    public function __construct() {
        // Get the current url (Not sure yet while we need to do a str_replace
        $url_to_here = str_replace( '%7E', '~', $_SERVER['REQUEST_URI']);
        // Convert the current url to an array
        $url_to_here = explode("&", $url_to_here);
        // Get the first element of the array and store it
        $url_to_here = $url_to_here[0];
        $this->url_to_here = $url_to_here;

        // Get what campaign type to be posted
        if(isset($_GET['dm_campaign_type'])) {
            // If subscription
            if($_GET['dm_campaign_type'] === 'dm_subs') {
                header("Location: " . admin_url('admin.php?page=pros-subscription'), true);
                exit;
            } elseif($_GET['dm_campaign_type'] === 'dm_standard') {
                // If standard
                header("Location: " . admin_url('admin.php?page=prossociate_addedit_campaign'), true);
                exit;
            } else {
                // Post standard campaign as default
                header("Location: " . admin_url('admin.php?page=prossociate_addedit_campaign'), true);
                exit;
            }
        }

        // Check if subscription
        if(isset($_GET['page']) && ($_GET['page'] === 'pros-subscription' || $_GET['page'] === 'pros-manage-subscription')) {
            $this->isSubscription = true;
        }

        if(isset($_GET['page'])) {
            if($_GET['page'] == 'prossociate_post_products')
                add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_post_products_scripts'));
            if($_GET['page'] == 'prossociate_addedit_campaign' || $this->isSubscription)
                add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            if((($_GET['page'] === 'prossociate_manage_campaigns' || $_GET['page'] === 'pros-manage-subscription') && (isset($_GET['action']) && $_GET['action'] === 'delete')) || isset( $_POST['mass_ids'] ))
                add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts_delete'));
        }
        add_action('wp_ajax_prossociate_search', array($this, 'ajax_print_serps'));

        // For deleting campaigns
        add_action('wp_ajax_prosociate_campaignDelete', array($this, 'ajaxDeleteCampaigns'));

        $search = new ProssociateSearch('','');

        add_action( 'init', array( $this, 'pagination' ) );

        // Check for mass delete
        if( isset( $_GET['dm-mass-delete'] ) )
        {
            if( $_GET['dm-mass-delete'] == 1 )
            {
                add_action( 'admin_notices', array( $this, 'mass_delete_success' ) );
            }
            else
            {
                add_action( 'admin_notices', array( $this, 'mass_delete_fail' ) );
            }
        }

        // If we are on create campaign or subscription page
        if(isset($_GET['page']) && $_GET['page'] == 'prossociate_addedit_campaign') {
            add_action('admin_notices', array($this, 'geoTargetingNotice'));
        }
    }

    /**
     * Display the notice if one of the geo-targeting fields on the settings page is populated
     */
    public function geoTargetingNotice() {
        // By default we will not display notice
        $displayNotice = false;

        // Get the countries
        $countries = Prossociate::geoCountries();

        // Loop through the countries
        foreach($countries as $k => $v) {
            // Get the option
            $option = get_option('prossociate_settings-associate-id-' . $k, '');

            // Check if the option has a value
            if(!empty($option)) {
                // If one option has value. Display notice
                $displayNotice = true;
                break;
            }
        }

        if($displayNotice) { ?>
            <div class="error">
                <p>
                    Alert: For Geo-Targeting to work properly, you must change your <strong>Post Products As</strong> setting to <em>External/Affiliate</em>, not <em>Simple/Variable</em>. You can change this setting in the Optional Settings tab below.
                </p>
            </div>
        <?php }
    }

    public function post_products() {
        require_once(PROSSOCIATE_ROOT_DIR . '/views/campaign/post_product.php');
    }

    /**
     * Save / Edit Campaign
     */
    public function addedit() {
        if($this->isSubscription) {
            $campaign = new ProsociateSubscription();
        } else {
            //echo 'test1:';var_dump($this->options);
            $campaign = new ProssociateCampaign();
        }

        // Check if we're editing a campaign
        // Also it's good to be sure that we are passing an int
        if( isset($_REQUEST['campaign_id']) && !empty($_REQUEST['campaign_id']) )
        {
            // Load the campaign
            $campaign->load( $_REQUEST['campaign_id'] );
        }
        else
        {
            // Load the defaults. Meaning create a new one
        	$campaign->defaults();
        }

        // yuri - check submit button
        if( isset($_REQUEST['campaign_type']) ) {
            $campaign_parameters['keywords'] = $_REQUEST['keyword'];
            $campaign_parameters['item_title'] = $_REQUEST['item_title'];
            $campaign_parameters['searchindex'] = $_REQUEST['searchindex'];
            // yuri - add sortby, browsenode, category, selected ASINs parameter
            $campaign_parameters['category'] = $_REQUEST['category'];
            $campaign_parameters['browsenode'] = $_REQUEST['browsenode'];
            $campaign_parameters['nodepath'] = $_REQUEST['nodepath'];
            $campaign_parameters['sortby'] = $_REQUEST['sortby'];
            $campaign_parameters['ASINs'] = $_REQUEST['ASINs'];
            // yuri - add advanced search options
            $campaign_parameters['minprice'] = $_REQUEST['dmminprice'];
            $campaign_parameters['maxprice'] = $_REQUEST['dmmaxprice'];
            $campaign_parameters['availability'] = $_REQUEST['availability'];
            $campaign_parameters['condition'] = $_REQUEST['condition'];
            $campaign_parameters['manufacturer'] = $_REQUEST['manufacturer'];
            $campaign_parameters['brand'] = $_REQUEST['brand'];
            $campaign_parameters['merchantid'] = $_REQUEST['merchantid']; // TODO have to clean it somehow
            $campaign_parameters['minpercentageoff'] = $_REQUEST['minpercentageoff'];

            $campaign_parameters['dmasinlists'] = $_REQUEST['dmasinlists'];
            $campaign_parameters['useasinlists'] = isset($_REQUEST['useasinlists']) ? $_REQUEST['useasinlists'] : '';
            //$campaign_parameters['dmAdditionalAttributes'] = $_REQUEST['dmAdditionalAttributes'];
            //$campaign_parameters['dmAutoAffiliate'] = $_REQUEST['dmAutoAffiliate'];
//var_dump($_POST);
            // Categories
            if(isset($_REQUEST['tax_input']['product_cat'])) {
                $campaign_parameters['dmcategories'] = $_REQUEST['tax_input']['product_cat'];
            } else {
                $campaign_parameters['dmcategories'] = '';
            }

            $campaign_parameters['dmnode'] = $_REQUEST['dmnode'];
            $campaign->options = $campaign_parameters;
            //echo 'test:'; var_dump($campaign->options);
            // Set post options from array form field
            $post_options = $_REQUEST['post_options'];
            $campaign->post_options = $post_options;

            // search parameters (doesnt work yet)
            if(isset($_REQUEST['search_parameters'])) {
                $search_parameters = $_REQUEST['search_parameters'];
            } else {
                $search_parameters = '';
            }

            $campaign->search_parameters = $search_parameters;
            //echo 'mgs:2';var_dump($campaign->search_parameters);
            // campaign settings
            $campaign_settings = $_REQUEST['campaign_settings'];
            $campaign->campaign_settings = $campaign_settings;

            $campaign->name = $_REQUEST['campaign_name'];

            // if campaign name is leave blank. Use the keywords as the name
            if( $_REQUEST['campaign_name'] == '' || empty($_REQUEST['campaign_name']) )
            {
                if($_REQUEST['keyword'] == '')
                    $campaign->name = $_REQUEST['category'];
                else
                    $campaign->name = $_REQUEST['keyword'];
            }

            $campaign->search($campaign_parameters['dmasinlists']);

            if( $_REQUEST['campaign_id'] )
            {
               $campaign->id = $_REQUEST['campaign_id'];
            }

            $campaign->save();

            if( isset($_REQUEST['campaign_type']) )
            {
                $campaign->post();
            }

        }

        if( !isset($_REQUEST['campaign_type']) ) {
            if($this->isSubscription) {
                include PROSSOCIATE_ROOT_DIR."/views/campaign/subscription/subscription.php";
            } else {
                include PROSSOCIATE_ROOT_DIR."/views/campaign/addedit.php";
            }
        }

    }

    /**
     * Ajax for the display results
     */
    public function ajax_print_serps() {
        $browsenode = $_POST['browsenode'];

        // yuri - add sortby, browsenode parameter
        $search = new ProssociateSearch($_POST['keyword'], $_POST['searchindex'], $browsenode, $_POST['sortby'], $_POST['page'], $_POST['category']);

        $minPrice = $search->makePrice($_POST['minprice']);
        $maxPrice = $search->makePrice($_POST['maxprice']);
        $search->set_advanced_options($minPrice, $maxPrice,$_POST['availability'],$_POST['condition'],$_POST['manufacturer'],$_POST['brand'],$_POST['merchantid'],$_POST['minpercentageoff'],$_POST['item_title']);

        // handles the specified asins and pagination
        if(isset($_POST['dmasinlists']) && !empty($_POST['dmasinlists'])){
            // We need to get the appropriate asins of the selected page
            // Convert the asin lists to array
            // Convert the asin lists to string separated with comma
            $tempContainerString = str_replace(array("\r\n", "\r", "\n"), ",", $_POST['dmasinlists']);

            // Convert the $tempContainerString to array
            $tempContainerArray = explode(',', $tempContainerString);

            // Count number of ASINs
            $tempContainerArrayCount = count($tempContainerArray);

            // Compute the total number of pages
            $totalPages = ceil($tempContainerArrayCount / 10);

            // Get the current page number
            $page = (int)$_POST['page'];

            // Asin counter
            $asinCounter = ($page - 1) * 10;

            // asin lists container
            $asinLists = array();

            // Only 10 asins per page
            for($ctr = 0; $ctr <= 9; $ctr++) {

                if($ctr > $tempContainerArrayCount - 1)
                    break;

                // Make sure we don't process more than array count
                // example we can't use arr[24] if we only have 21 asins
                // Minus 1 because array starts on index 0 in php
                if($page >= $totalPages) {
                    $dmTempContCount = $tempContainerArrayCount % 10;
                    if(($dmTempContCount != 0) && ($ctr > $dmTempContCount - 1))
                        break;
                }

                // Store the asin
                $asinLists[] = $tempContainerArray[$asinCounter];

                $asinCounter++;
            }

            // Convert the asinlists to string
            $stringAsinLists = implode(',', $asinLists);

            $search->setAsinLists($stringAsinLists);
            $isAsinLookUp = true;
        } else {
            $isAsinLookUp = false;
        }

        // For merchant id
        if($_POST['merchantid'] != '')
            $search->merchantid = $_POST['merchantid'];

        $search->execute('Small,OfferSummary,Images,Variations,VariationOffers,Offers,OfferFull', $isAsinLookUp);

        include PROSSOCIATE_ROOT_DIR."/views/campaign/ajax_print_serps.php";

        die();
    }

    /**
     * Manage campaigns logic
     * For the page - wp-admin/admin.php?page=prossociate_manage_campaigns
     */
    public function manage_campaigns() {
        // Get the current action
        if(isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];
        } else {
            $action = '';
        }

        // Check if mass delete was confirmed
        if( isset( $_POST['mass_ids'] ) )
        {
            include PROSSOCIATE_ROOT_DIR."/views/campaign/deleting.php";
            die();
        }

        // Confirm delete associated posts on mass campaign delete
        if( isset( $_POST['campaign'] ) )
        {
            $dmMassDelete = true;
            include PROSSOCIATE_ROOT_DIR."/views/campaign/delete.php";
        }

        // When deleting a campaign individually
        if( $action == 'delete' )
        {
            //$campaign = new ProssociateCampaign();
            if( isset($_REQUEST['is_confirmed']) )
            {
                // Unset the action
                $action = null;

                include PROSSOCIATE_ROOT_DIR."/views/campaign/deleting.php";
                die();
            }
            else
            {
            	include PROSSOCIATE_ROOT_DIR."/views/campaign/delete.php";
            }
        }

        // Check if there's no action
        if( ! ($action || isset( $_POST['campaign'] ) ))
        {
            // If there's no action, display existing campaigns

            // Check if the page number is given and if it's a number
            if( isset( $_GET['pagi'] ) && is_numeric($_GET['pagi'] ) )
            {
                $currentPage = $_GET['pagi'];
            }
            else
            {
                // Default to the first page
                $currentPage = 1;
            }

            // Number of campaigns to show per page
            $campaignsPerPage = 10;

            // Total number of existing campaigns
            $numberOfCampaigns = $this->count_campaigns($this->isSubscription);

            // Compute for the number of pages
            $numberOfPages = ceil( $numberOfCampaigns / $campaignsPerPage );

            // Set the order
            $order = '';
            $orderBy = '';

            // Check for order
            if( isset( $_GET['order'] ) )
            {
                $orderBy = $_GET['order_by'];
            }

            // The pagination
            $campaigns = $this->pagination( $campaignsPerPage, $currentPage, $order, $orderBy, $this->isSubscription );


            if($this->isSubscription) {
                $headTitle = 'Subscriptions';
                $linkUrl = 'pros-manage-subscription';
                $actionUrl = 'pros-subscription';
            } else {
                $headTitle = 'Campaigns';
                $linkUrl = 'prossociate_manage_campaigns';
                $actionUrl = 'prossociate_addedit_campaign';
            }

            //  The display
            include PROSSOCIATE_ROOT_DIR."/views/campaign/manage.php";

        }

    }

    /**
     * Delete multiple campaigns
     * @global type $wpdb
     * @param array $campaignIds Campaign ids to be deleted
     * @param boolean True if associated posts will also be deleted
     * @return boolean
     */
    private function delete_campaigns( $campaignIds, $deleteAssociatedPosts = FALSE ) {
        global $wpdb;

        // Check if $campaignids are not empty
        if( !empty( $campaignIds ) )
        {
            // Fail until tested
            $success = 0;

            // Check if associated posts will be deleted
            if( $deleteAssociatedPosts )
            {
                foreach( $campaignIds as $campaignId )
                {
                    // Delete the associated posts of each campaign
                    // TODO this is not the best way to do this
                    $campaign = new ProssociateCampaign();

                    $campaign->delete_associated_posts( $campaignId );
                }
            }

            // Clean the ids
            $sanitizeIds = $this->sanitize_ids( $campaignIds );

            // Check if the $campaignIds are sanitized
            if( $sanitizeIds !== FALSE )
            {
                // Number of campaigns to be deleted
                $numberOfCampaigns = count($sanitizeIds);

                // Set a counter
                $counter = 1;

                // The SQL
                $sql = "DELETE FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . "campaigns WHERE id IN (";

                // Loop through all the $campaignIds to be deleted
                foreach( $sanitizeIds as $sanitizeId )
                {
                    // If we are on the last element on the array don't add ","
                    // Also add the closing parenthesis
                    if( $counter === $numberOfCampaigns )
                    {
                        $sql .= "{$sanitizeId})";
                    }
                    else
                    {
                        $sql .= "{$sanitizeId},";
                    }

                    $counter++;
                }

                // Do the operation and check if it's successful
                if( $wpdb->query($sql) !== FALSE )
                {
                    // massive delete is a success
                    $success = 1;
                }
            }


        }

        return $success;
    }

    /**
     * Sanitize an array of ids to make sure each of the element is an integer
     *
     * @param array $ids the array to be sanitized
     */
    private function sanitize_ids( $ids ) {
        // Fail until tested
        $success = FALSE;

        // The container for the new array
        $sanitizeArray = array();

        // Check if the $ids are not empty
        if( !empty( $ids ) )
        {
            // Assuming the all the $ids passed are integer
            $sanitizeSuccess = TRUE;

            // Loop each of the $ids
            foreach( $ids as $id )
            {
                // Check if there's a non integer element on the ids
                if( !is_numeric( $id ) )
                {
                    // Failed
                    $sanitizeSuccess = FALSE;
                    // End the loop
                    break;
                }
                else
                {
                    // Convert the type of $id to integer for safety measures
                    // Store it to the new array
                    $sanitizeArray[] = (int)$id;
                }
            }
            // Check if the sanitizing process is completed
            if( $sanitizeSuccess )
            {
                // Return the new array
                $success = $sanitizeArray;
            }
        }

        return $success;
    }

    /**
     * Provide pagination feature in managing campaigns
     * @param int $currentPage the current page
     * @param int $campaignsPerPage the number of campaigns to be displayed per page
     * @param string $orderBy Order of campaigns
     * @param string $order ASC or DESC
     * @param bool $isSubscription
     * @return object The campaigns to be displayed
     */
    public function pagination( $campaignsPerPage = 10, $currentPage = 1, $orderBy = 'id', $order = 'ASC', $isSubscription = false ) {
        global $wpdb;

        // Accepted $orderBy
        // TODO Keywords
        $acceptedOrderBy = array( 'id', 'name', 'last', 'last_run_time' );

        // Check if the $order and $orderBy parameter is safe.
        if( !in_array( $orderBy, $acceptedOrderBy ) )
        {
            // Default the $orderBy to id
            $orderBy = 'id';
        }

        // Get the number of campaigns
        $numberOfCampaigns = $this->count_campaigns($this->isSubscription);

        // Check if we are on subscription
        if($this->isSubscription) {
            $tableName = 'prossubscription';
        } else {
            $tableName = 'campaigns';
        }


        // Compute where to start retrieving data
        $offset = ($currentPage - 1) * $campaignsPerPage;

        // Get only the campaigns that needs to be displayed
        if( (int)$offset == 0 ) {
            $query = "SELECT * FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . $tableName . " ORDER BY {$orderBy} {$order} LIMIT 0 , 10";
        } else {
            $query = "SELECT * FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . $tableName . " ORDER BY {$orderBy} {$order} LIMIT {$offset}, {$campaignsPerPage}";
        }


        // Get the campaigns
        $campaigns = $wpdb->get_results($query);

        return $campaigns;
    }

    /**
     * Count the number of campaigns in the database
     *
     * @param bool $isSubscription
     * @return int
     */
    private function count_campaigns($isSubscription = false) {
        global $wpdb;

        // Check if we are on normal campaigns or on subscription
        if($isSubscription) {
            $tablePrefix = 'prossubscription';
        } else {
            $tablePrefix = 'campaigns';
        }

        // Count the campaign
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}" . PROSSOCIATE_PREFIX . $tablePrefix;

        $results = $wpdb->get_col( $query );

        return $results[0];
    }

    /**
     * Admin notice if mass delete was a success
     */
    public function mass_delete_success() { ?>
        <div class="updated">
            <p><?php _e( 'Campaigns were successfully deleted', 'my-text-domain' ); ?></p>
        </div>
    <?php }

    /**
     * Admin notice if mass delete failed
     */
    public function mass_delete_fail() { ?>
        <div class="error">
            <p><?php _e( 'A problem occurred while deleting campaigns', 'my-text-domain' ); ?></p>
        </div>
    <?php }

    /**
     * For deleting campaigns
     */
    public function ajaxDeleteCampaigns() {
        $campId = (int)$_POST['campId'];
        $deleteAssoc = $_POST['deletePosts']; // note: this should be treated as string for consistency
        $massIds = $_POST['massIds'];
        $isSubscription = $_POST['isSubscription'];

        // Response holder
        $response = array();

        // Check if we are on subscription
        if($isSubscription == 'false') {
            $campaign = new ProssociateCampaign();
        } else {
            $campaign = new ProsociateSubscription();
        }
        // Create instance of campaign

        // Guilty until proven
        $complete = 'false';

        // Check if we will delete associated posts
        if($deleteAssoc == 'true') {
            // Check if we are on mass delete
            if($massIds !== '0' && $campId == 0) {
                // Convert to array
                $massIdsArr = explode('-', $massIds);
                // Check if not empty
                if(!empty($massIdsArr) && $massIdsArr[0] != '') {
                    // Delete the associated posts of the campaign
                    $allDeleted = $this->deleteCampaign((int)$massIdsArr[0], $campaign);
                    // If all was deleted, delete the campaign too
                    if($allDeleted) {
                        $response['message'] = 'Campaign ' . $massIdsArr[0] . ' was deleted.';
                        // Delete the campaign
                        $campaign->dbdelete((int)$massIdsArr[0]);
                        // Delete the first element
                        unset($massIdsArr[0]);
                        // Recreate the mass ids
                        $massIdsString = implode('-', $massIdsArr);
                        $massIds = $massIdsString;
                    } else {
                        $response['message'] = "Deleted 20 Products from Campaign " . $massIdsArr[0];
                    }
                } else {
                    $complete = 'true';
                    $response['message'] = 'All Selected Campaigns are deleted';
                }
            } else {
                // We are on single delete
                // Delete associated posts
                $allDeleted = $this->deleteCampaign($campId, $campaign);

                // Check if everything was deleted
                if($allDeleted) {
                    // Complete
                    $complete = 'true';
                    // Delete the campaign
                    $campaign->dbdelete($campId);
                    $response['message'] = 'Campaign deleted successfully.';
                } else {
                    $response['message'] = '20 Products was deleted. Continuing..';
                }
            }

        } else {
            // Complete
            $complete = 'true';

            // Check if we are on mass delete
            if($massIds !== '0' && $campId == 0) {
                // Convert to array
                $massIdsArr = explode('-', $massIds);

                // Delete all campaign
                foreach($massIdsArr as $k) {
                    // Delete the campaign
                    $campaign->dbdelete((int)$k);
                }

                $response['message'] = 'Campaigns deleted successfully.';
            } else {
                $response['message'] = 'Campaign deleted successfully.';

                // Delete the campaign
                $campaign->dbdelete($campId);
            }
        }

        // Include complete
        $response['complete'] = $complete;
        $response['action'] = 'prosociate_campaignDelete';
        $response['campId'] = $campId;
        $response['massIds'] = $massIds;
        $response['deletePosts'] = $deleteAssoc;
        $response['isSubscription'] = $isSubscription;

        echo json_encode($response);

        die();
    }


    /**
     * @param int $campId
     * @param ProssociateCampaign $campaign
     * @return bool
     */
    private function deleteCampaign($campId, $campaign) {
        $limitDelete = 20;

        $allDeleted = true;

        // Get associated posts
        $assocPosts = $campaign->getAssociatedPosts($campId);

        // Unserialized the results
        $assocPostsUnser = unserialize($assocPosts->associated_posts);

        $ctr = 0;

        if(!empty($assocPostsUnser)) {
            // Go on through each of the assoc posts
            foreach($assocPostsUnser as $k => $v) {
                // Check if we have a post
                if(get_post($k) === null)
                    continue;

                // Limit the delete to 20 per loop
                if($ctr >= $limitDelete) {
                    $allDeleted = false;
                    break;
                }

                // Check if there are variations and delete it
                $productVariations = $this->getProductVariations($k);
                if(!empty($productVariations)) {
                    foreach($productVariations as $a) {
                        // Delete the images
                        $this->deleteImages($a->ID);
                        // Delete the product
                        $this->deleteProduct($a->ID);
                    }
                }

                // Delete the main product
                // Delete the images
                $this->deleteImages($k);
                // Delete the product
                $this->deleteProduct($k);

                $ctr++;

                // If we are here then all products was deleted
                $allDeleted = true;
            }
        }

        return $allDeleted;
    }

    /**
     * Retrieve the product_variations
     * @param int $postId
     * @return array|bool
     */
    private function getProductVariations($postId) {
        $children = get_children(array(
            'post_parent' => $postId,
            'post_type' => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'any'
        ));

        return $children;
    }

    /**
     * Delete associated images for a product
     * @param int $productId
     */
    private function deleteImages($productId) {
        // Get main images
        $args = array(
            'post_parent' => (int)$productId,
            'post_type' => 'attachment',
            'post_status' => 'any'
        );
        $images = get_children($args);

        // Delete images
        foreach($images as $image) {
            wp_delete_attachment($image->ID, true);
        }
    }

    /**
     * Delete a product
     * @param int $productId
     */
    private function deleteProduct($productId) {
        // Delete
        wp_delete_post($productId, true);
    }


    /**
     * The scripts to be loaded on all campaign-related pages on the admin panel
     */
    public function admin_enqueue_scripts() {

        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_script('post');
        wp_enqueue_script('jquery');

        wp_register_script('jquery-ui-gapi', '//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/jquery-ui.min.js');
        wp_enqueue_script('jquery-ui-gapi');

        wp_register_style('jquery-ui-gapi', '//ajax.googleapis.com/ajax/libs/jqueryui/1.9.2/themes/base/jquery-ui.css');
        wp_enqueue_style('jquery-ui-gapi');

        // Check if woocommerce is activated
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
            global $woocommerce;
            wp_enqueue_script('jquery-dm-tiptip', $woocommerce->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js');
        }

        wp_register_script('prossociate_campaign_controller', PROSSOCIATE_ROOT_URL.'/js/ProssociateCampaignController.js', array(), '2.0');
        wp_enqueue_script('prossociate_campaign_controller');

        wp_register_script('jquery-jstree', PROSSOCIATE_ROOT_URL.'/libraries/jstree/jquery.jstree.js');
        wp_enqueue_script('jquery-jstree');

        wp_register_style('jquery-jstree', PROSSOCIATE_ROOT_URL.'/libraries/jstree/themes/classic/style.css');
        wp_enqueue_style('jquery-jstree');

        wp_register_style('pros_admin_style', PROSSOCIATE_ROOT_URL.'/css/admin_style.css');
        wp_enqueue_style('pros_admin_style');
    }

    /**
     * Enqueue JS for Delete page
     */
    public function admin_enqueue_scripts_delete() {
        wp_enqueue_script('pros_delete_js', PROSSOCIATE_ROOT_URL . '/js/ProsociateCampaignDelete.js', array('jquery'), '1.0.0');
    }

    /**
     * Enqueue CSS for Post New Products page
     */
    public function admin_enqueue_post_products_scripts() {
        wp_enqueue_style('pros_post_new_product_css', PROSSOCIATE_ROOT_URL . '/css/admin_post_new_product_style.css', array(), '1.0.0');
    }

}
