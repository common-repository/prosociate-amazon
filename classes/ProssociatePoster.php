<?php
/**
 * Responsible for the product posting
 */
class ProssociatePoster {
    /**
     * Campaign to get the products
     * @var type
     */
    var $campaign;

    /**
     * For variation posting
     */
    private $variation = FALSE; // By default we not posting variations
    private $var_data;
    private $var_post_id;
    private $var_post;
    private $var_update_operation;
    private $var_post_options;
    private $var_offset = 0;
    private $var_mode;
    private $newData;

    public $isSubscription = false;

    private $external = false;

    /**
     * Param on url for the external products
     *
     * @var string
     */
    private static $externalProductUrl = 'product';

    /**
     * Construct
     * @param type $campaign
     * @param bool $isSubscription
     */
    public function __construct($campaign = null, $isSubscription = false) {

        if ($campaign) {
            $this->campaign = $campaign;
        }

        // Check if we are on subscription
        if($isSubscription)
            $this->isSubscription = true;

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_prossociate_iterate', array($this, 'ajax_iterate'));
    }

    /**
     * The actual posting function
     * TODO Should be trimmed down to parts for more organize / extensible code
     * @global type $wpdb
     */
    public function ajax_iterate() {
        global $wpdb;

        // Try to post
        try {

            // we want to both update and post new
            // get all post IDs associated with the campaign.
            // if there are any, update them accordingly. iterate through the update. after posting 10, cancel the process, send the JSON. this will retrigger.
            // it now checks for all post IDs again, and see when there last update date was. because we saved the campaign, this has changed. and now we don't update those.
            // once we update all the posts, we begin doing the normal search iteration operation
            // but, before we post, we check for duplicates. we do this by looking at the array of existing posts on the site. the array will contain ASIN information. if the ASIN matches on already, we handle the duplicate accordingly.

            @set_time_limit(0);

            ob_start(); // start the output buffer for logging

            // prevent cron from running when posting new products
            update_option('pros_active_cron', 'active_cron');
            $total_products_from_js = $_REQUEST['total_products'];
            $campaign_id = $_REQUEST['campaign_id'];
            $page = $_REQUEST['page'];
            $mode = $_REQUEST['mode'];
            $var_offset = $_REQUEST['var_offset'];
            $poster_offset = $_REQUEST['poster_offset'];
            $update_offset = $_REQUEST['update_offset'];
            $global_counter = $_REQUEST['global_counter'];

            // Check if we have HTTP REFERRER
            if(isset($_SERVER['HTTP_REFERER'])) {
                $dmParseUrl = parse_url($_SERVER['HTTP_REFERER']);
                $dmPageParam = str_replace('page=', '', $dmParseUrl['query']);
                // Check if we are on subscription
                if($dmPageParam === 'pros-subscription') {
                    $this->isSubscription = true;
                }
            }

            // Check if we are on description
            if($this->isSubscription) {
                $this->campaign = new ProsociateSubscription();
            } else {
                $this->campaign = new ProssociateCampaign();
            }
            $this->campaign->load($campaign_id);


            if ($mode == "update") { // we are updating existing posts
                $set_mode = "update"; // keep us in update mode for the JSON. if we are leaving update mode, we'll change this
                // get all the posts associated with the campaign, if any
                $assoc_posts = $this->campaign->associated_posts;

                // check if there are associated posts in the campaign
                if( $assoc_posts )
                {
                    $newAssoc_posts = array();
                    // Re create the array
                    // This is to fix the indexing
                    foreach( $assoc_posts as $post )
                    {
                        $newAssoc_posts[] = array(
                            'id' => $post['id'],
                            'asin' => $post['asin'],
                            'updated' => $post['updated']
                        );
                    }

                    // Total number of associated posts
                    $assocPostsCount = count( $assoc_posts );

                    // For loop iteration counter
                    $loopCounter = 0;

                    $global_counter = (int)$global_counter;
                    $poster_offset = (int)$poster_offset;

                    for( $counter = $update_offset; $counter < $assocPostsCount; $counter++ )
                    {
                        $global_counter++;
                        $poster_offset++;
                        // Increment the tracker
                        $loopCounter++;

                        // Update the update_offset
                        $update_offset = (int)$update_offset+ 1;

                        // Check if the post still exists
                        if( !get_post( $newAssoc_posts[$counter]['id'] ) )
                        {
                            // dissociate the post in the campaign
                            echo "Dissocating ";
                            echo $assoc_posts[$counter]['id'];
                            echo "...<br />";

                            // dissociate the post
                            $this->campaign->dissociate_post( $assoc_posts[$counter]['id'] );
                            $this->campaign->save();
                            break;
                        }

                        // Get the time 30 mins ago
                        $checktime = time() - (60 * 1);

                        // If the post was last updated later than 30 mins ago
                        if( $newAssoc_posts[$counter]['updated'] < $checktime )
                        {
                            $post_id = $newAssoc_posts[$counter]['id'];

                            // get ASIN from post
                            $ASIN = get_post_meta($post_id, '_pros_ASIN', true); // kind of unnecessary, because we have the ASIN in the $post array.
                            // get item from ASIN, like this:
                            $item = new ProssociateItem($ASIN);
                            // update the post
                            // shoudn't we have variations here? - dm
                            $post_id = $this->post($item, $post_id, true);

                            $this->campaign->associated_posts[$post_id] = array( "id" => $post_id, "asin" => $ASIN, "updated" => time() );
                            $this->campaign->save();

                            // If the post was successful. Check if variation exists
                            if ( isset( $item->data->VariationSummary ) )
                            {
                                // Delete old variations
                                echo "Deleting previous variations. <br />";
                                $this->delete_old_variations($post_id);
                                // Add variation data in the database
                                update_option( 'dm_pros_var_data', $this->var_data );
                                update_option( 'dm_pros_var_post_id', $this->var_post_id );
                                update_option( 'dm_pros_var_post', $this->var_post );
                                update_option( 'dm_pros_var_update_operation', $this->var_update_operation );
                                update_option( 'dm_pros_var_post_options', $this->var_post_options );

                                $set_mode = 'variation';
                                $var_offset = 0;
                                $poster_offset++;
                                $global_counter++;
                                //$data['poster_offset'] = $poster_handler;
                                //$complete = false;
                                break;
                            }

                            echo "Updated ";
                            echo $post_id;
                            echo " - ";
                            echo $ASIN;
                            echo "<br />";
                            // Just to separate logs
                            echo "------------------------ <br />";
                        }
                        else
                        {
                            $post_id = $newAssoc_posts[$counter]['id'];
                            $ASIN = get_post_meta($post_id, '_pros_ASIN', true);

                            echo "Updated ";
                            echo $post_id;
                            echo " - ";
                            echo $ASIN;
                            echo " less than 30 minutes ago, no update necessary...<br />";
                        } // end if last update time < 30 mins

                        // If all the associated posts was checked for update
                        $ctr = (int)$counter + 1; // to bypass the 0 index of array
                        if( $ctr == $assocPostsCount )
                        {
                            echo "<b><i>Update operation complete -  posts for any new products...</i></b><br /><br />";
                            $set_mode = "create";

                            break;
                        }

                        // here's where idea of posting 5 products per ajax came from
                        // Check if the loop executed 5 times
                        if( $loopCounter >= 5)
                        {
                            // we updated more than 5 posts
                            echo "<i>Updated five... iterating...</i><br /><br />";
                            break;
                        }

                    } // End for

                }
                else
                {
                    echo "<b><i>No posts need an update,  posts for any new products...</i></b><br /><br />";
                    $set_mode = "create";
                } // end if there are associated posts

                $log = ob_get_clean();

                $data['log'] = $log;
                $data['total_products'] = $total_products_from_js;
                $data['campaign_id'] = $campaign_id;
                $data['page'] = 1;
                $data['mode'] = $set_mode;
                $data['complete'] = false;
                $data['poster_offset'] = $poster_offset;
                $data['var_offset'] = $var_offset;
                $data['update_offset'] = $update_offset;
                $data['global_counter'] = $global_counter;

                $data = json_encode($data);

                echo $data;

                die();
            }
            else if ($mode == "create") // CREATE NEW POSTS
            {
                $log_messages = '';
                // poster offset
                $poster_handler = 0;

                // Result container
                $dmResults = array();
                // yuri - get selected product list
                $ASINs_string = $this->campaign->options["ASINs"];
                $ASINs = explode(',', $ASINs_string);

                // If we are using 'All' limit it to 5 page
                if($this->campaign->options['searchindex'] == 'All')
                    $maxPage = 5;
                else
                    $maxPage = 10;

                // Check if asin lists was given
                if(empty($this->campaign->options['dmasinlists']) || isset($this->campaign->options['dmasinlists']) == false) {
                    $isAsinLookUp = false;
                } else {
                    // Convert the asin lists to string separated with comma
                    $tempContainerString = str_replace(array("\r\n", "\r", "\n"), ",", $this->campaign->options['dmasinlists']);

                    // Convert the $tempContainerString to array
                    $tempContainerArray = explode(',', $tempContainerString);

                    // Count number of ASINs
                    $tempContainerArrayCount = count($tempContainerArray);

                    // Get the asins to be processed
                    $tempAsins = array(); // Asins container;
                    $asinCounter = (($page - 1) * 10); // The counter

                    // Only get 10 per loop
                    for($counter = 0; $counter <= 9; $counter++) {
                        if(($asinCounter + 1) > $tempContainerArrayCount)
                            break;

                        // Store the asin
                        $tempAsins[] = $tempContainerArray[$asinCounter];

                        $asinCounter++;
                    }

                    // Convert again the $tempAsins to string
                    $trueAsins = implode(',', $tempAsins);

                    $maxPage = ((int)ceil(count($tempContainerArray) / 10)) + 1;

                    // Set asin lists
                    $isAsinLookUp = true;
                }


                if((int)$page <= $maxPage) {
                    // This is to get products if nothing was checked
                    $search = new ProssociateSearch($this->campaign->options['keywords'], $this->campaign->options['searchindex'], $this->campaign->options['browsenode'], $this->campaign->options['sortby']);

                    //$minPrice = $search->makePrice($_POST['minprice']);
                    //$maxPrice = $search->makePrice($_POST['maxprice']);
                    $minPrice = $search->makePrice($this->campaign->options['minprice']);
                    $maxPrice = $search->makePrice($this->campaign->options['maxprice']);
                    $power_options = $search->parse_power_search_data($this->campaign->post_options['books_operator'], $this->campaign->post_options['books']);

                    $search->set_advanced_options($minPrice, $maxPrice, $this->campaign->options['availability'], $this->campaign->options['condition'], $this->campaign->options['manufacturer'], $this->campaign->options['brand'], $this->campaign->options['merchantid'], $this->campaign->options['minpercentageoff'], $this->campaign->options['item_title'],$power_options);
                    $search->page = $page;

                    if($isAsinLookUp) {
                        $search->setAsinLists($trueAsins);
                        // Get total products
                        //$total_products_from_js = $tempContainerArrayCount;
                    }

                    $search->merchantid = $this->campaign->options['merchantid'];

                    $search->execute('Small', $isAsinLookUp);

                    // Go through the results
                    foreach( $search->results as $result )
                    {
                        // Check if there are selected products
                        if( $ASINs_string != '' && count( $ASINs ) > 0 )
                        {
                            // Check if the result isn't selected
                            if( !in_array( $result['ASIN'], $ASINs ) )
                            {
                                continue;
                            }
                        }

                        // the result product was selected
                        $dmResults[] = $result;
                    }

                    $addPage = true;

                    $dmAsins = $this->getAsinsFromDmResults($dmResults);

                    // Iteration counter
                    $iterationCounter = 0;
                    // Loop through the results
                    for( $counter = (int)$poster_offset; $counter < count($dmResults); $counter++ )
                    {
                        // Reload the page
                        if( $iterationCounter == 10 )
                        {
                            $log_messages = 'Processing next batch of products' . $log_messages;
                            break;
                        }

                        // Make sure that we are tracking the products
                        $poster_handler = $counter + 1;

                        // the product doesn't exist until proven
                        /*
                        $does_exist = false;

                        // Check if there are associated posts on the campaign
                        if( $this->campaign->associated_posts )
                        {
                            // Loop through each of the associated posts
                            foreach( $this->campaign->associated_posts as $post )
                            {
                                // Check if the current selected product already exists
                                if( $post['asin'] == $dmResults[$counter]['ASIN'] )
                                {
                                    // prove that product exist
                                    $does_exist = true;
                                    break;
                                }
                            }
                        } // end if


                        // Check if the product already exist

                        if( $does_exist )
                        {
                            $log_messages = "Skipping " . $dmResults[$counter]['ASIN'] . ", already exists, continuing...<br />" . $log_messages;
                        }
                        else
                        {
                        */
                            echo "Attempting to post " . $dmResults[$counter]['ASIN'] . "...<br />";

                            // Load the current selected product data
                            // Amazon only
                            $item = new ProssociateItem( $dmResults[$counter]['ASIN'], 'Amazon' );

                            // import parent code
                            if($item->data->ParentASIN && !$isAsinLookUp){
                                //$dmResults[$counter]['ASIN'] = $item->data->ParentASIN;
                                //echo "Posting Parent ASIN " .$item->data->ParentASIN. "...<br />";

                                //$item = new ProssociateItem( $dmResults[$counter]['ASIN'], 'Amazon' );
                            }
                            // Check for duplicates
                            $checkIfDuplicate = $this->checkIfAsinExists($dmResults[$counter]['ASIN'], $dmAsins);

                            if($checkIfDuplicate != 0) {
                                // If duplicate update the product
                                //$global_counter++;
                                //echo 'Updating product..<br />';
                                //echo '------------------------<br />';
                                //continue;
                            } else {
                                // Create new product
                                $checkIfDuplicate = null;
                            }

                            // For unavailable DVD
                            if(isset($item->data->Offers->Offer) && !is_array($item->data->Offers->Offer)) {
                                if(is_string($item->data->Offers->Offer->Merchant->Name)) {
                                    if($item->data->Offers->Offer->Merchant->Name === 'Amazon Video On Demand')
                                        $item->isValid = false;
                                } // end if string
                            }

                            if($item->isValid) {
                                // Post the product
                                $post_id = $this->post($item, $checkIfDuplicate);

                            } else {
                                $post_id = false;
                            }

                            // Check if the product was posted
                            if( $post_id )
                            {
                              $post_data = array('post_id'=> $post_id, 'post_image'=> $item->data->LargeImage->URL, 'post_title'=> $item->ItemAttributes->Title, 'image_set'=> $item->data->ImageSets->ImageSet);
                              update_option( 'dm_pros_post_data', serialize($post_data) );
                                // Associate the product with the campaign
                                $this->campaign->associate_post( $post_id, $dmResults[$counter]['ASIN'] );
                                $this->campaign->save();

                                if($checkIfDuplicate == null)
                                    echo  "Posted " . $dmResults[$counter]['ASIN'] . ", continuing...<br />";
                                else
                                    echo  "Updated " . $dmResults[$counter]['ASIN'] . ", continuing...<br />";

                                echo "------------------------ <br />";

                                $global_counter++;

                                // Check if we are on external product
                                if($this->campaign->post_options['externalaffilate'] == 'affiliate') {
                                    $setVariation = false;
                                } else {
                                    $setVariation = true;
                                }
                            }
                            else
                            {
                                // Not posted
                                $global_counter++;
                                if($item->code == 100) {
                                    echo "Product ". $dmResults[$counter]['ASIN'] . " can't be posted because it has too many variations. <br />";
                                    // Try to die here. This might solve the issue of having unexpected token on the js.
                                    break;
                                }
                                else
                                {
                                    echo "Product " . $dmResults[$counter]['ASIN'] . " is unavailable. <br />";
                                }

                                // If the variation is not posted. Then we don't need to set it as mode variation
                                $setVariation = false;
                            } // end if $post_id

                            if($setVariation) {
                                // Check if the current product has variations
                                if ( isset( $this->newData->Variations ) )
                                {

                                    // force to recall the ajax to process the variations
                                    $addPage = false;
                                    break;
                                }
                            }

                        //} // end if $does_exit

                        $iterationCounter++;
                    } // end  for loop

                    if ($this->campaign->options['searchindex'] != 'All' && $this->campaign->options['searchindex'] != 'Blended') {
                        if ($search->totalpages >= 10) {
                            $search->totalpages = 10;
                        }
                    } else {
                        if ($search->totalpages >= 5) {
                            $search->totalpages = 5;
                        }
                    }


                    if( $addPage )
                    {
                        $page++;
                    }

                    // For safe keeping
                    $testCounter = $global_counter;

                    // Check if we had posted all products
                    if($testCounter >= $total_products_from_js) {
                        $testCounter = 96;
                    }

                    $complete = false;

                    $tempComplete = false;
                    if($testCounter == $total_products_from_js) {
                        $tempComplete = true;
                    }

                    if(isset($this->newData->Variations)) {
                        if($item->isValid) {
                            $tempComplete = true;
                        } else {
                            $tempComplete = false;
                        }
                    }

                    if($tempComplete) {
                        $complete = true;
                    } else {
                        $complete = false;
                    }
                }


                if ($page > $search->totalpages && $testCounter >= 70 ) {
                    $this->delete_products();
                    $complete = true;

                    // yuri - refresh attribute cache
                    $transient_name = 'wc_attribute_taxonomies';
                    $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                    set_transient($transient_name, $attribute_taxonomies);
                }

                // Fix the issue with asin look up
                if($isAsinLookUp) {
                    $newMaxPage = $maxPage;
                } else {
                    $newMaxPage = $maxPage + 1;
                }

                // If we are on maxPage = 5
                if($page >= $newMaxPage) {
                    $this->delete_products();
                    $complete = true;

                    // yuri - refresh attribute cache
                    $transient_name = 'wc_attribute_taxonomies';
                    $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                    set_transient($transient_name, $attribute_taxonomies);
                } else {
                    $complete = false;
                }


                if (isset($_REQUEST['proso-cron-key'])) {
                    if ($complete == true) {
                        $this->campaign->cron_mode = '';
                        $this->campaign->cron_page = '';
                        $this->campaign->cron_last_run_time = time();
                        $this->campaign->save();
                    } else {
                        $this->campaign->cron_mode = 'complete';
                        $this->campaign->cron_page = $page;
                        $this->campaign->save();
                    }
                }

                $data['total_products'] = $total_products_from_js;
                $data['campaign_id'] = $campaign_id;
                $data['page'] = $page;
                $data['mode'] = 'create';
                $data['update_offset'] = $update_offset;
                $data['global_counter'] = $global_counter;

                // If the post was successful. Check if variation exists and if it was posted
                if ( isset( $this->newData->Variations ) && $setVariation)
                {
                    // Delete variations before creating new ones.
                    $this->delete_variations($post_id);
                    // only make variable if we are not on affiliate / external
                    //if($this->campaign->post_options['externalaffilate'] === 'simple') {
                        // Add variation data in the database
                        update_option( 'dm_pros_var_data', $this->var_data );
                        update_option( 'dm_pros_var_post_id', $this->var_post_id );
                        update_option( 'dm_pros_var_post', $this->var_post );
                        update_option( 'dm_pros_var_update_operation', $this->var_update_operation );
                        update_option( 'dm_pros_var_post_options', $this->var_post_options );

                        $data['mode'] = 'variation';
                        $data['var_offset'] = 0;
                        $data['poster_offset'] = $poster_handler;
                        $complete = false;
                    //}
                }

                $log = ob_get_clean(); // get the contents of the output buffer. this way, if there are any error messages, hopefully they show up on the frontend. - later: this isn't happening. maybe we need try / catch blocks.

                $data['log'] = $log . $log_messages;


                $data['complete'] = $complete;

                $data = json_encode($data);

                echo $data;

                die();
            } //END CREATE NEW POSTS
            elseif( $mode == 'variation' )
            {
                $log_messages = '';
                // Get the variation data
                $var_data = get_option( 'dm_pros_var_data' );
                $var_post_id = get_option( 'dm_pros_var_post_id' );
                $var_post = get_option( 'dm_pros_var_post' );
                $var_update_operation = get_option( 'dm_pros_var_update_operation' );
                $var_post_options = get_option( 'dm_pros_var_post_options' );

                // Create the variations
                $this->set_woocommerce_variations( $var_data, $var_post_id, $var_post, $var_update_operation, $var_post_options, $var_offset, 3 );

                // yuri - refresh attribute cache
                $transient_name = 'wc_attribute_taxonomies';
                $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                set_transient($transient_name, $attribute_taxonomies);

                echo "Creating <b>" . $this->var_offset . "</b> product variations. <br />";


                $log = ob_get_clean();
                $data['log'] = $log . $log_messages;
                $data['total_products'] = $total_products_from_js;
                $data['campaign_id'] = $campaign_id;
                $data['page'] = $page;
                $data['complete'] = 'false';
                $data['mode'] = $this->var_mode;
                /*if($this->var_offset >= 10)
                $data['mode'] = 'create';*/
                $data['var_offset'] = $this->var_offset;
                $data['poster_offset'] = $poster_offset;
                $data['update_offset'] = $update_offset;
                $data['global_counter'] = $global_counter;

                $data = json_encode($data);

                $post_data = unserialize(get_option( 'dm_pros_post_data' ));
                $this->set_post_featured_thumb($post_data['post_image'], $post_data['post_title'], $post_data['post_id'], $post_data['image_set']);
                echo $data;
                die();
            }
            else if ($mode == 'complete') {
                delete_option( 'dm_pros_var_data' );
                delete_option( 'dm_pros_var_post_id' );
                delete_option( 'dm_pros_var_post' );
                delete_option( 'dm_pros_var_update_operation' );
                delete_option( 'dm_pros_var_post_options' );
                delete_option( 'dm_pros_post_data' );
                // Delete error products
                $this->delete_products();
                // If the posting products is complete reactivate cron
                update_option('pros_active_cron', 'not_active_cron');
                if (isset($_REQUEST['proso-cron-key'])) {
                    $this->campaign->cron_mode = '';
                    $this->campaign->cron_page = '';
                    $this->campaign->cron_last_run_time = time();
                    $this->campaign->save();
                }
            } else {
                delete_option( 'dm_pros_var_data' );
                delete_option( 'dm_pros_var_post_id' );
                delete_option( 'dm_pros_var_post' );
                delete_option( 'dm_pros_var_update_operation' );
                delete_option( 'dm_pros_var_post_options' );
                // If the posting products is complete reactivate cron
                update_option('pros_active_cron', 'not_active_cron');
                $data['log'] = 'Error';
                $data['campaign_id'] = $campaign_id;
                $data['page'] = $page;
                $data['complete'] = true;
                $data['mode'] = 'error';

                $data = json_encode($data);

                echo $data;

                die();
            }
        } catch (Exception $e) {
            // If the posting products is complete reactivate cron
            update_option('pros_active_cron', 'not_active_cron');
            var_dump($e);
            $log = ob_get_clean();

            $data['log'] = $log;
            $data['campaign_id'] = 'error';
            $data['page'] = 'error';
            $data['complete'] = true;
            $data['mode'] = 'error';

            $data = json_encode($data);

            echo $data;

            die();
        }
        ?>
        <?php

        die();
    }

    function start_process() {
        include PROSSOCIATE_ROOT_DIR . "/views/campaign/process.php";
    }

    private function delete_variations( $productParentId ) {
        // Get variations
        $args = array(
            'post_parent' => (int)$productParentId,
            'post_type' => 'product_variation',
            'post_status' => 'any'
        );
        $variationPosts = get_children( $args );

        // Get attachments of each variation
        foreach($variationPosts as $k) {
            $postId = $k->ID;
            // Get attachments
            $arg = array(
                'post_parent' => $postId,
                'post_type' => 'attachment',
                'post_status' => 'any'
            );
            $attachments = get_children( $arg );

            // Delete attachments
            foreach($attachments as $a) {
                wp_delete_attachment($a->ID, true);
            }

            // Delete post
            wp_delete_post($postId, true);
        }

        // Get the featured post ID attached on the main product
        $featuredImage = get_post_meta($productParentId, '_thumbnail_id', true);

        // Get main images
        $args1 = array(
            'post_parent' => (int)$productParentId,
            'post_type' => 'attachment',
            'post_status' => 'any'
        );
        $mainImages = get_children( $args1 );

        // Delete attachments
        foreach($mainImages as $b) {
            // Only delete images that are not the featured
            if((int)$featuredImage != $b->ID)
                wp_delete_attachment($b->ID, true);
        }
    }

    private function getAsinsFromDmResults($dmResults) {
        global $wpdb;

        // New container
        $newAsins = array();

        // Get only the ASINs
        foreach($dmResults as $k) {
            $newAsins[] = $k['ASIN'];
        }

        $queryIn = implode("','", $newAsins);

        // Custom query to get the post id of the asins
        $sql = "SELECT SQL_NO_CACHE post_id, meta_value FROM " . $wpdb->postmeta . " WHERE meta_key = '_pros_ASIN' AND meta_value IN ('" . $queryIn . "')";

        // Perform the query
        $query = $wpdb->get_results($sql);

        return $query;
    }

    /**
     * Check if product already posted
     * @param $asin
     * @return bool|int
     */
    private function checkIfAsinExists($asin, $existedPosts) {
        $return = 0;
        //echo $asin;
//var_dump($existedPosts);
        // Check if asin existed
        foreach($existedPosts as $k) {
            if($asin == $k->meta_value) {
                $return = (int)$k->post_id;
                break;
            }
        }

        return $return;
    }

    function post($item, $post_id = null, $fromUpdate = false) {

        $dmCheckifUpdate = false;
        // If updating products remove the prices meta fields to remove conflict
        if($post_id != null) {
            $dmCheckifUpdate = true;
            delete_post_meta($post_id, '_regular_price');
            delete_post_meta($post_id, '_sale_price');
            delete_post_meta($post_id, '_price');
        }

        // First we make sure that $this->external is false;
        $this->external = false;

        // Get the data
        $data = $item->data;

        // Check if we have amazon offers
        if($data->Offers->TotalOffers == 0) {
            $asin = $data->ASIN;
            unset($item); // Free some memory
            unset($data);
            $item = new ProssociateItem($asin);
            $data = $item->data;
            $this->newData = $data;
        } else {
            $this->newData = $data;
        }

        // Check if we have to import child products
        // Default: false

        if(!(isset($this->campaign->post_options['postchild']) && ($this->campaign->post_options['postchild'] == 'postchild'))) {
            if(isset($data->ParentASIN) && ($data->ParentASIN != $data->ASIN)) {
                return false;
            }
        }

        // Check if we have offers
        if(!isset($data->Offers) || $data->Offers->TotalOffers == 0) {
            // Get side-wide setting for posting auto affiliate
            $dmAutoAffiliate = get_option('prossociate_settings-dm-auto-affiliate', 'true');
            if($dmAutoAffiliate == 'true')
                $this->external = true;
            elseif($this->campaign->post_options['externalaffilate'] == 'affiliate') {
                $this->external = true;
            }
        }

        $finalPrice = '';
        $finalOffer = '';
        $finalSaleAmount = 0;
        $Availability = false;

        // Get list price
        $finalListPrice = '';
        $finalListAmount = 0;

        if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $finalListPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            $finalListAmount = $data->ItemAttributes->ListPrice->Amount;
        }

        // Check if we have offerlistings
        if($data->Offers->TotalOffers > 0) {
            // Check if array
            if(is_array($data->Offers->Offer)) {
                foreach($data->Offers->Offer as $offer) {
                    // Check if there's no offer listing
                    if(!isset($offer->OfferListing->OfferListingId)) {
                        continue;
                    } else {
                        $finalOffer = $offer->OfferListing->OfferListingId;
                        $finalPrice = $offer->OfferListing->Price->FormattedPrice;
                        $finalAmount = $offer->OfferListing->Price->Amount;
                        // Check if sale price is given
                        if(isset($offer->OfferListing->SalePrice)) {
                            $finalSalePrice = $this->reformat_prices($this->remove_currency_symbols($offer->OfferListing->SalePrice->FormattedPrice));
                            $finalSaleAmount = $offer->OfferListing->SalePrice->Amount;
                        } else {
                            $finalSaleAmount = 0;
                        }
                        break;
                    }
                }
            } else {
                // For non-array
                // Check if offer listing exists
                if(isset($data->Offers->Offer->OfferListing->OfferListingId)) {
                    // Make the product external if the merchant is Amazon Digital Services
                    if($data->Offers->Offer->Merchant->Name === 'Amazon Digital Services , Inc.')
                        $this->external = true;

                    $finalOffer = $data->Offers->Offer->OfferListing->OfferListingId;
                    $finalPrice = $data->Offers->Offer->OfferListing->Price->FormattedPrice;
                    $finalAmount = $data->Offers->Offer->OfferListing->Price->Amount;
                    // Check if sale price is given
                    if(isset($data->Offers->Offer->OfferListing->SalePrice)) {
                        $finalSalePrice = $this->reformat_prices($this->remove_currency_symbols($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice));
                        $finalSaleAmount = $data->Offers->Offer->OfferListing->SalePrice->Amount;
                    } else {
                        $finalSaleAmount = 0;
                    }
                }
            }
        } elseif($data->Offers->TotalOffers == 0 && !isset($data->Variations) && isset($data->VariationSummary)) {
            // if no offers
            if($this->external === false)
                return false;

            // This is for products without offers and variations listings
            // Example http://www.amazon.com/Sherri-Hill-21002/dp/
            if(isset($data->VariationSummary->LowestPrice)) {
                $finalPrice = $data->VariationSummary->LowestPrice->FormattedPrice;
                $finalAmount = $data->VariationSummary->LowestPrice->Amount;
            }
        }
        elseif($data->Offers->TotalOffers == 0 && isset($data->Variations)) {
      			// code added by ajaz
      			if(isset($data->VariationSummary->LowestPrice)) {
                $finalPrice = $data->VariationSummary->LowestPrice->FormattedPrice;
                $finalAmount = $data->VariationSummary->LowestPrice->Amount;
                //echo 'finalAmount:'.$finalAmount.'-';
            }// eof code added by ajaz

            $dmValuePresent = true;
            // Check if array
            if(is_array($data->Variations->Item)) {
                // Check if variation attributes is an array
                if(is_array($data->Variations->Item[0]->VariationAttributes->VariationAttribute)) {
                    // Check if value is present
                    if(!isset($data->Variations->Item[0]->VariationAttributes->VariationAttribute[0]->Value))
                        $dmValuePresent = false;
                } else {
                    if(!isset($data->Variations->Item[0]->VariationAttributes->VariationAttribute->Value))
                        $dmValuePresent = false;
                }
            } else {
                // Check if variation attributes is an array
                if(is_array($data->Variations->Item->VariationAttributes->VariationAttribute)) {
                    if(!isset($data->Variations->Item->VariationAttributes->VariationAttribute[0]->Value))
                        $dmValuePresent = false;
                } else {
                    if(!isset($data->Variations->Item->VariationAttributes->VariationAttribute->Value))
                        $dmValuePresent = false;
                }
            }

            // If Value isn't present dont import the product. It will create broken variations
            if(!$dmValuePresent) {
                // Check if we are creating a new product
                if($post_id == null) {
                    return false;
                } else {
                    // Delete the product
                    // Check if there are variations and delete it
                    $productVariations = $this->getProductVariations($post_id);
                    if(!empty($productVariations)) {
                        foreach($productVariations as $k) {
                            // Delete the images
                            $this->deleteImages($k->ID);
                            // Delete the product
                            $this->deleteProduct($k->ID);
                        }
                    }

                    // Delete the main product
                    // Delete the images
                    $this->deleteImages($post_id);
                    // Delete the product
                    $this->deleteProduct($post_id);

                    return false;
                }
            }

        }
        elseif($data->Offers->TotalOffers == 0  && isset($data->OfferSummary)) {
            // code added by ajaz
		        // if summary
            // This is for products without offers and variations listings
            // Example http://www.amazon.com/Sherri-Hill-21002/dp/
            if(isset($data->OfferSummary->LowestNewPrice)) {
                $finalPrice = $data->OfferSummary->LowestNewPrice->FormattedPrice;
                $finalAmount = $data->OfferSummary->LowestNewPrice->Amount;
            }else if(isset($data->OfferSummary->LowestUsedPrice) && isset($this->campaign->post_options['books_used']) ) {
                $finalPrice = $data->OfferSummary->LowestUsedPrice->FormattedPrice;
                $finalAmount = $data->OfferSummary->LowestUsedPrice->Amount;
            }

        } // eof code added by ajaz
         else {
            // if no offers
            if($this->external == false)
                return false;
        }

        // Make MP3Downloads products an Affiliate / External
        if($this->campaign->options['searchindex'] === 'MP3Downloads') {
            $this->external = true;
        }

        // Make Book product as E / A
        if($this->campaign->options['searchindex'] === 'Books') {
            $this->external = true;
        }

        if($this->external === false) {
            // Check if we are on the root appstore
            if($this->campaign->options['browsenode'] == '2350149011') {
                $this->external = true;
            }

            // Get the nodepath
            $nodePaths = $this->campaign->options['nodepath'];
            $nodePathsArray = explode(',', $nodePaths);

            // Check if Appstore For Android, Books and Kindle Store
            if(in_array('2350149011', $nodePathsArray) || in_array('283155', $nodePathsArray) || in_array('133140011', $nodePathsArray)) {
                $this->external = true;
            }
        }

        // If already external ignore this
        if(!$this->external) {
            // Post products with too low to display as external
            if($finalPrice === 'Too low to display' || $finalPrice == '') {
                // Check option if we will not post products without prices
                if(!isset($this->campaign->post_options['postfree'])) {
                    return false;
                }

                // Check if the option to automatically convert single to external is checked
                if(get_option('prossociate_settings-dm-auto-affiliate') == 'true') {
                    $this->external = true;
                } else {
                    return false;
                }
            }
        } else {
            if(isset($data->Variations) && $data->Variations->TotalVariations > 0) {

            } elseif($finalPrice === 'Too low to display' || $finalPrice == '') {

                // Check option if we will not post products without prices
                if(!isset($this->campaign->post_options['postfree'])) {
                    return false;
                }
            }
        }

        //TODO Check if the offerlisting price matches the min and max price
        // First check if we have maxprice
        if(isset($this->campaign->options['maxprice']) && !empty($this->campaign->options['maxprice'])) {
            // Check if we are not on variation
            if($this->external || !isset($data->Variations)) {
                // Check if the product price is greater than max price
                if($finalAmount > (int)str_replace(".", "", $this->campaign->options['maxprice'])) {
                    // Do not post
                    return false;
                }
            }
        }

        // Then check if we have min price
        if(isset($this->campaign->options['minprice']) && !empty($this->campaign->options['minprice'])) {
            // Check if we are not on variation
            if($this->external || !isset($data->Variations)) {
                // Check if product price has lesser value then min price
                if($finalAmount < (int)str_replace(".", "", $this->campaign->options['minprice'])) {
                    // Do not post
                    return false;
                }
            }
        }

        $dmPostDate = '';
        if ($post_id) {
            $update_operation = true;
            // If on update use the previously defined title. This is to avoid overridding of user-defined title
            $finalTitle = get_the_title($post_id);

            // Get existing excerpt
            $dmPost = get_post($post_id);
            $finalExcerpt = $dmPost->post_excerpt;

            // Get the existing post date
            $dmPostDate = $dmPost->post_date;
        } else {
            $update_operation = false;

            // If not on update generate a custom title
            // Limit title length
            $titleLength = get_option('prossociate_settings-title-word-length', 9999);
            if(!is_numeric($titleLength))
                $titleLength = 9999;

            $trimmedTitle = wordwrap($item->Title, $titleLength, "dmpros123", false);
            $explodedTitle = explode("dmpros123", $trimmedTitle);
            $finalTitle = $explodedTitle[0];

            $finalExcerpt = '';
        }

        $post_options = $this->campaign->post_options;
        $search_parameters = $this->campaign->search_parameters;
        $campaign_settings = $this->campaign->campaign_settings;


        // Check if we will post as draft as publish
        if(isset($post_options['draft']) && $post_options['draft'] === 'draft') {
            $dmPostStatus = 'draft';
        } else {
            $dmPostStatus = 'publish';
        }


        // ----------------------------------
        // Prepare content
        $product_content = '';
        if(isset($data->EditorialReviews)) {
            if (count($data->EditorialReviews->EditorialReview) == 1) {
            $product_content .= "<p class='pros_product_description'>";
            if ($data->EditorialReviews->EditorialReview->Source != "Product Description") {
                $product_content .= $data->EditorialReviews->EditorialReview->Source;
            }

            $reiview_content = $data->EditorialReviews->EditorialReview->Content;

            //content professor
            /*$cp_enabled_rev = get_option('prossociate_settings-dm-spin-cp-reviews');
            if($cp_enabled_rev == "true"){
                $response = prosociate_cprof_rewrite($reiview_content);
                    if(!is_array($response))
                    {
                        $reiview_content = $response;
                    }
            }*/

            $product_content .= $reiview_content;
            $product_content .= "</p>";
            } else {
                foreach ($data->EditorialReviews->EditorialReview as $er) {
                    $product_content .= "<p class='pros_product_description'>";
                    $product_content .=  $er->Source;
                    $er_content = $er->Content;
                    //content professor
                    /*$cp_enabled_rev = get_option('prossociate_settings-dm-spin-cp-reviews');
                        if($cp_enabled_rev == "true"){
                             $response = prosociate_cprof_rewrite($er_content);
                             if(!is_array($response))
                                {
                                    $er_content = $response;
                                }

                        }*/

                    $product_content .= $er_content;
                    $product_content .= "</p>";
                }
            }

        }



        if(isset($data->ItemAttributes)) {

             if (is_array($data->ItemAttributes->Feature)) {

            $product_content .= '<p class="pros_product_description">
                Features<ul>';
            foreach ($data->ItemAttributes->Feature as $feature) {

                /*$cp_enabled = get_option('prossociate_settings-dm-spin-cp-enable');
                if($cp_enabled == "true"){
                    $response = prosociate_cprof_rewrite($feature);
                    if(!is_array($response))
                    {
                        $feature = $response;
                    }
                }*/

                $product_content .= '<li>' . $feature.'</li>';
            }
            $product_content .= '</ul></p>';
            }
            else if (count($data->ItemAttributes->Feature) == 1) {
                $product_content .= '<p class="pros_product_description"><ul>';
                $feature = $data->ItemAttributes->Feature;

                /*$cp_enabled = get_option('prossociate_settings-dm-spin-cp-enable');
                if($cp_enabled == "true"){
                    $response = prosociate_cprof_rewrite($feature);
                    if(!is_array($response))
                    {
                        $feature = $response;
                    }
                }*/

                $product_content .= '<li>' . $feature.'</li>';
                $product_content .= '</ul></p>';
            }
        }
        $prodcut_content_az = $product_content;
        $sr_enabled = get_option('prossociate_settings-dm-spin-sr-enable');
        if( (isset($this->campaign->post_options['enablespin']) && ($this->campaign->post_options['enablespin'] == 'enablespin')) || $sr_enabled == "true" ) {
            echo "Requesting description spin"."<br />";
            $response = prosociate_sr_rewrite($product_content);
            if(!is_array($response))
            {
                $product_content = $response;
            }elseif($response['error']){
                echo  "Spin Rewriter: ".$response['error']."<br />";
            }
        }



        // ----------------------------------
        // SET UP THE POST ARRAY
        $post = array(
            'post_author' => $post_options['author'],
            //'post_content' => '[prosociate]',
            'post_content' => $product_content,
            'post_status' => $dmPostStatus,
            'post_title' => $finalTitle,
            'post_type' => $post_options['post_type'],
            'post_excerpt' => $finalExcerpt
        );

        if(isset($post_options['comment_status'])) {
            if($post_options['comment_status'] == 'open') {
                $post['comment_status'] = 'open';
            } else {
                $post['comment_status'] = 'closed';
            }
        }

        // -------- COMPILE DATES -----------
        if ($post_options['date_type'] == 'specific') {
            if($post_options['date'] != 'now') {
                $post_date = strtotime($post_options['date']);
                $post['post_date'] = date('Y-m-d H:i:s', $post_date);
            }
        } else if ($post_options['date_type'] == 'random') {
            $post_date = rand(strtotime($post_options['date_start']), strtotime($post_options['date_end']));
            $post['post_date'] = date('Y-m-d H:i:s', $post_date);
        }

        // Make sure that the post date isn't overridden, to prevent posting of previously scheduled products
        if(isset($dmPostDate) && !empty($dmPostDate)) {
            $post['post_date'] = $dmPostDate;
        }

        if(isset($post_options['ping_status'])) {
            if($post_options['ping_status'] == 'open') {
                $post['ping_status'] = 'open';
            } else {
                $post['ping_status'] = 'closed';
            }
        }

        // INSERT THE POST
        if ($post_id) { // we're updating an existing post
            $post['ID'] = $post_id;
        }

        // Check for availability
        if((isset($data->Offers->TotalOffers) && $data->Offers->TotalOffers > 0) || $this->external == true) {
            $Availability = true;
        }

        if($Availability || isset($data->Variations)) {
            $post_id = wp_insert_post($post);
            update_post_meta($post_id, '_pros_Available', "yes");

            // Set the stock
            update_post_meta($post_id, '_stock_status', 'instock');
        } else {
            // if product is not available
            // Check if we are updating a product
            if($post_id) {
                // if updating
                // Get the global option for availability
                if(get_option('prossociate_settings-dm-pros-prod-avail', false) == 'remove') {
                    // Check if there are variations and delete it
                    $productVariations = $this->getProductVariations($post_id);
                    if(!empty($productVariations)) {
                        foreach($productVariations as $k) {
                            // Delete the images
                            $this->deleteImages($k->ID);
                            // Delete the product
                            $this->deleteProduct($k->ID);
                        }
                    }

                    // Delete the main product
                    // Delete the images
                    $this->deleteImages($post_id);
                    // Delete the product
                    $this->deleteProduct($post_id);

                    return false;

                } else {
                    // Replace to outofstock
                    update_post_meta($post_id, '_stock_status', 'outofstock');
                }
            } else {
                return false;
            }
        }
        // insert original Amazon title
        update_post_meta( $post_id, 'original_ttl_az', $finalTitle );
        // insert original Amazon content
        update_post_meta( $post_id, 'original_cnt_az', $prodcut_content_az );
        // Save last update time
        update_post_meta( $post_id, '_pros_last_update_time', time() );

        // Check if there are variations
        $dmIsVariation = false;
        if (isset($data->VariationSummary) ) {
            $dmIsVariation = true;
        }

        if($update_operation) {
            $dmIsVariation = true;
        }

        if($fromUpdate) {
            $dmIsVariation = true;
        }

        $this->standard_custom_fields($data, $post_id, $dmIsVariation);

        // Check if there are variations
        if (isset($data->Variations) && $post_options['externalaffilate'] == 'simple') {
            $this->variation = TRUE;
            // Store the variation params
            $this->var_data = $data->ASIN;
            $this->var_post_id = $post_id;
            $this->var_post = $post;
            $this->var_update_operation = $update_operation;
            $this->var_post_options = $post_options;
        } elseif (isset($data->Variations) && $post_options['externalaffilate'] == 'affiliate') {

            // Get the price of the very first variation
            if(is_array($data->Variations->Item)) {
                $finalPrice = $data->Variations->Item[0]->Offers->Offer->OfferListing->Price->FormattedPrice;
            } else {
                $finalPrice = $data->Variations->Item->Offers->Offer->OfferListing->Price->FormattedPrice;
            }

            // Prices for external variable products
            // Get the sale price
            $finalSaleAmount = 0;
            if(isset($data->VariationSummary->LowestSalePrice)) {
                $finalSalePrice = $this->reformat_prices($this->remove_currency_symbols($data->VariationSummary->LowestSalePrice->FormattedPrice));
                $finalSaleAmount = $data->VariationSummary->LowestSalePrice->Amount;
            }
        }

        // INSERT FEATURED IMAGES
        if ($post_options['download_images'] == 'on' && $dmCheckifUpdate === false) {
            $this->set_post_images($data, $post_id, $dmIsVariation);
        }

        // WooCommerce support
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

            // Check if we will add attributes
            $dmAdditionAttr = get_option('prossociate_settings-dm-add-attr','true');
            if($dmAdditionAttr != 'true') {
                $this->set_woocommerce_attributes($data, $post_id, $post, $update_operation, $post_options);
            }

            if ($post_options['post_type'] == 'product') {
                $this->set_woocommerce_fields($data, $post_id, $dmIsVariation);
                wp_set_post_terms($post_id, 'simple', 'product_type', false);
            }

            // If user set the product to be an affiliate
            if ($post_options['externalaffilate'] == 'affiliate' || $this->external === true) {
                wp_set_post_terms($post_id, 'external', 'product_type', false);
                update_post_meta($post_id, '_dmaffiliate', 'affiliate');
            }
        }

        // Auto-generate categories
        if (isset($post_options['auto_category']) && $post_options['auto_category'] == 'yes') {
            $createdCats = $this->set_categories($data->BrowseNodes, $dmIsVariation);

            // Assign the post on the categories created
            wp_set_post_terms( $post_id,  $createdCats, 'product_cat' );
        }

        // If users selected categories then put the campaigns on those categories
        if(isset($post_options['dm_select_category'])) {
            if ($post_options['dm_select_category'] == 'yes') {
                $forcedAssignedCats = $this->campaign->options['dmcategories'];

                // Remove the 0 term id
                $removeZeroTermId = array_shift($forcedAssignedCats);

                // Assign the post on the categories created
                wp_set_post_terms( $post_id,  $forcedAssignedCats, 'product_cat', false );
            }
        }

        // If auto-create category and assign category is checked
        if((isset($post_options['dm_select_category']) && $post_options['dm_select_category'] == 'yes') && (isset($post_options['auto_category']) && $post_options['auto_category'] == 'yes')) {
            // Make the created category, subcategory
            global $wpdb;
            $sql = "UPDATE $wpdb->term_taxonomy SET parent = ". $forcedAssignedCats[0] ." WHERE term_id IN (". implode(',', $createdCats) .")";
            $wpdb->query($sql);

            // Merge 2 arrays
            $finalCatArray = array_merge($forcedAssignedCats, $createdCats);

            // Assign the post on the categories created
            wp_set_post_terms( $post_id,  $finalCatArray, 'product_cat', false );

            // Delete cache
            delete_option('product_cat_children');
        }


        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Add the offer id and price
        update_post_meta($post_id, '_dmpros_offerid', $finalOffer);
        update_post_meta($post_id, '_price', $finalProcessedPrice);
        update_post_meta($post_id, '_regular_price', $finalProcessedPrice);

        // Handle prices with Too low to display
        if($finalPrice === 'Too low to display') {
            update_post_meta($post_id, '_price', '0');
            update_post_meta($post_id, '_regular_price', '0');
            update_post_meta($post_id, '_filterTooLowPrice', 'true');
        } elseif($finalSaleAmount > 0) {  // Handle the regular / sale price
            update_post_meta($post_id, '_regular_price',$finalProcessedPrice);
            update_post_meta($post_id, '_sale_price', $finalSalePrice);
            update_post_meta($post_id, '_price', $finalSalePrice);
        }

        // For external products, try to insert list price if possible
        if(($this->external || $post_options['externalaffilate'] == 'affiliate') && !empty($finalListPrice) && ($finalListAmount != 0)) {
            // Check if the list price is lower
            $finalListProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalListPrice));

            if($finalListAmount > $finalAmount) {
                update_post_meta($post_id, '_sale_price', $finalProcessedPrice);
                update_post_meta($post_id, '_price', $finalProcessedPrice);
                update_post_meta($post_id, '_regular_price',$finalListProcessedPrice);
            } elseif($finalListAmount < $finalAmount) {
                update_post_meta($post_id, '_sale_price', $finalListProcessedPrice);
                update_post_meta($post_id, '_price', $finalListProcessedPrice);
                update_post_meta($post_id, '_regular_price',$finalProcessedPrice);
            }
        }

        // Check if we have valid post id
        if(is_int($post_id))
            $this->wordpressSeobyYoastIntegration($post_id, $finalTitle);

        // WP Wizard Cloak Integration
        $this->wpWizardCloakIntegration($post_id, $data->DetailPageURL);

        // Insert ASIN as SKU
        update_post_meta($post_id, '_sku', $data->ASIN);

        // return the post ID
        return $post_id;
    }

    /**
     * Add WP Wizard Cloak integration
     * @param int $postId
     * @param string $buyUrl
     */
    private function wpWizardCloakIntegration($postId, $buyUrl) {
        if(empty($buyUrl))
            return;

        // Check if WP Wizard Cloak is activated
        if (in_array('wp-dynamic-links/plugin.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // Save the original url on another meta
            update_post_meta($postId, '_orig_buy_url', $buyUrl);
            // Cloak the link
            $cloak = new Prosociate_WPWizardCloak($postId, $buyUrl);
            // Get the cloaked link
            if(!empty($cloak->cloakedLink)) {
                $cloakLink = get_bloginfo('wpurl') . '?track=' . $cloak->cloakedLink;
                update_post_meta($postId, '_product_url', $cloakLink);
            }
        }
    }

    /**
     * Add wordpress seo by yoast integration
     * @param int $post_id
     * @param string $finalTitle
     */
    private function wordpressSeobyYoastIntegration($post_id, $finalTitle) {
        // Check if WP Seo is activated
        if(!in_array('wordpress-seo/wp-seo.php' ,apply_filters('active_plugins', get_option('active_plugins'))))
            return;

        // Check if we already have a seo title and seo desc
        $seoTitle = get_post_meta($post_id, '_yoast_wpseo_title', true);
        $seoDesc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);

        if(empty($seoTitle) || $seoTitle === false) {
            // If no title given, automatically build it
            // Trim the title with 70 chars
            $title = substr($finalTitle, 0, 70);
        } else {
            $title = $seoTitle;
        }

        if(empty($seoDesc) || $seoDesc === false) {
            // Get the description
            $description = '';
            $EditorialReviews = unserialize(get_post_meta($post_id, '_pros_EditorialReviews', true));

            if(isset($EditorialReviews->EditorialReview)) {
                if (count($EditorialReviews->EditorialReview) == 1) {
                    $description .= $EditorialReviews->EditorialReview->Content;
                } else {
                    foreach ($EditorialReviews->EditorialReview as $er) {
                        $description .= $er->Content;
                    }
                }
            }

            // Trim the description with 156 chars
            $desc = substr($description, 0, 156);
        } else {
            $desc = $seoDesc;
        }


        // Update the meta for wordpress seo by yoast
        update_post_meta($post_id, '_yoast_wpseo_title', $title);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
    }

    /**
     * Create the categories for the product
     * Note: We will not be using ASIN as the slug because the nodeID is dynamically changing
     * @param object $browseNodes
     */
    function set_categories($browseNodes, $isVariation = false) {
        if(!$isVariation) {
            echo "Generating categories.. <br />";
        }
        // Notes
        // we need to consider if it only has 1 branch of category

        // The woocommerce product taxonomy
        $wooTaxonomy = "product_cat";

        // Categories for the product
        $createdCategories = array();

        // Category container
        $categories = array();

        // Count the top browsenodes
        $topBrowseNodeCounter = 0;



        // Check if we have multiple top browseNode
        if( is_array( $browseNodes->BrowseNode ) )
        {
            foreach( $browseNodes->BrowseNode as $browseNode )
            {
                // Create a clone
                $currentNode = $browseNode;

                // Track the child layer
                $childLayer = 0;

                // Inifinite loop, since we don't know how many ancestral levels
                while( true )
                {
                    $validCat = true;

                    // Replace html entities
                    $dmCatName = str_replace( '&', 'and', $currentNode->Name );
                    $dmCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode->Name ) );
                    $dmCatSlug = $currentNode->BrowseNodeId . '-' . $dmCatSlug;

                    $dmTempCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode->Name ) );

                    if( $dmTempCatSlug == 'departments' ) {
                        $validCat = false;
                    }
                    elseif( $dmTempCatSlug == 'featured-categories' ) {
                        $validCat = false;
                    }
                    elseif( $dmTempCatSlug == 'categories' ) {
                        $validCat = false;
                    }
                    elseif( $dmTempCatSlug == 'products' ) {
                        $validCat = false;
                    }
                    elseif( $dmTempCatSlug == 'all-products') {
                        $validCat = false;
                    }

                    // Check if we will make the cat
                    if( $validCat ) {
                        $categories[0][] = array(
                            'name' => $dmCatName,
                            'slug' => $dmCatSlug
                        );
                    }

                    // Check if the current node has a parent
                    if( isset($currentNode->Ancestors->BrowseNode->Name) )
                    {
                        // Set the next Ancestor as the current node
                        $currentNode = $currentNode->Ancestors->BrowseNode;
                        $childLayer++;
                        continue;
                    }
                    else
                    {
                        // There's no more ancestors beyond this
                        break;
                    }
                } // end infinite while

                // Increment the tracker
                $topBrowseNodeCounter++;
            } // end foreach
        }
        else
        {
            // Handle single branch browsenode

            // Create a clone
            $currentNode = $browseNodes->BrowseNode;

            // Inifinite loop, since we don't know how many ancestral levels
            while (true)
            {
                // Always true unless proven
                $validCat = true;

                // Replace html entities
                $dmCatName = str_replace( '&', 'and', $currentNode->Name );
                $dmCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode->Name ) );
                $dmCatSlug = $currentNode->BrowseNodeId . '-' . $dmCatSlug;

                $dmTempCatSlug = sanitize_title( str_replace( '&', 'and', $currentNode->Name ) );

                if( $dmTempCatSlug == 'departments' ) {
                    $validCat = false;
                }
                elseif( $dmTempCatSlug == 'featured-categories' ) {
                    $validCat = false;
                }
                elseif( $dmTempCatSlug == 'categories' ) {
                    $validCat = false;
                }
                elseif( $dmTempCatSlug == 'products' ) {
                    $validCat = false;
                }
                elseif( $dmTempCatSlug == 'all-products') {
                    $validCat = false;
                }

                // Check if we will make the cat
                if( $validCat ) {
                    $categories[0][] = array(
                        'name' => $dmCatName,
                        'slug' => $dmCatSlug
                    );
                }

                // Check if the current node has a parent
                if (isset($currentNode->Ancestors->BrowseNode->Name))
                {
                    // Set the next Ancestor as the current node
                    $currentNode = $currentNode->Ancestors->BrowseNode;
                    continue;
                }
                else
                {
                    // There's no more ancestors beyond this
                    break;
                }
            } // end infinite while

        } // end if browsenode is an array

        // Tracker
        $catCounter = 0;

        // Make the parent at the top
        foreach( $categories as $category )
        {
            $categories[$catCounter] = array_reverse( $category );
            $catCounter++;
        }

        // Current top browsenode
        $categoryCounter = 0;

        // Loop through each of the top browsenode
        foreach( $categories as $category )
        {
            // The current node
            $nodeCounter = 0;
            // Loop through the array of the current browsenode
            foreach( $category as $node )
            {
                // Check if we're at parent
                if( $nodeCounter == 0 )
                {
                    // Check if term exists
                    $checkTerm = term_exists( str_replace( '&', 'and', $node['slug'] ), $wooTaxonomy );
                    if( empty( $checkTerm ) )
                    {
                        // Create the new category
                        $newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'] ) );

                        // Add the created category in the createdCategories
                        // Only run when the $newCat is an error
                        if( gettype($newCat) != 'object' ) {
                            $createdCategories[] = $newCat['term_id'];
                        }
                    }
                    else
                    {
                        // if term already exists add it on the createdCats
                        $createdCategories[] = $checkTerm['term_id'];
                    }
                }
                else
                {
                    // The parent of the current node
                    $parentNode = $categories[$categoryCounter][$nodeCounter - 1];
                    // Get the term id of the parent
                    $parent = term_exists( str_replace( '&', 'and', $parentNode['slug'] ), $wooTaxonomy );

                    // Check if the category exists on the parent
                    $checkTerm = term_exists( str_replace( '&', 'and', $node['slug'] ), $wooTaxonomy );

                    if( empty( $checkTerm ) )
                    {
                        $newCat = wp_insert_term( $node['name'], $wooTaxonomy, array( 'slug' => $node['slug'], 'parent' => $parent['term_id'] ) );

                        // Add the created category in the createdCategories
                        $createdCategories[] = $newCat['term_id'];
                    }
                    else
                    {
                        $createdCategories[] = $checkTerm['term_id'];
                    }
                }

                $nodeCounter++;
            }

            $categoryCounter++;
        } // End top browsenode foreach

        // Delete the product_cat_children
        // This is to force the creation of a fresh product_cat_children
        delete_option( 'product_cat_children' );

        $returnCat = array_unique($createdCategories);

        // return an array of term id where the post will be assigned to
        return $returnCat;
    }

    function set_woocommerce_attributes($data, $post_id, $post, $update_operation, $post_options) {
        if(!$update_operation) {
            echo "Creating attributes for product {$data->ASIN}. <br />";
        }
        global $wpdb;
        global $woocommerce;

        // yuri - convert Amazon attributes into woocommerce attributes
        $_product_attributes = array();
        $position = 0;

        foreach( $data->ItemAttributes as $key => $value )
        {
            if (!is_object($value))
            {
                // For clothing size hack
                if($key === 'ClothingSize') {
                    $key = 'Size';
                }

                // yuri - change dimension name as woocommerce attribute name
                //$attribute_name = $woocommerce->attribute_taxonomy_name(strtolower($key));
				$attribute_name = wc_attribute_taxonomy_name(strtolower($key));
                $_product_attributes[$attribute_name] = array(
                    'name' => $attribute_name,
                    'value' => '',
                    'position' => $position++,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
                $this->add_attribute_value($post_id, $key, $value);
            }
        }

        // yuri - update product attribute
        update_post_meta($post_id, '_product_attributes', $_product_attributes);
    }

    // yuri - add attribute values
    function add_attribute_value($post_id, $key, $value) {
        global $wpdb;
        global $woocommerce;

        // get attribute name, label
        $attribute_label = $key;
        $attribute_name = woocommerce_sanitize_taxonomy_name($key);

        // set attribute type
        $attribute_type = 'select';

        // check for duplicates
        $attribute_taxonomies = $wpdb->get_var("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = '$attribute_name'");

        if ($attribute_taxonomies) {
            // update existing attribute
            $wpdb->update(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
                    'attribute_label' => $attribute_label,
                    'attribute_name' => $attribute_name,
                    'attribute_type' => $attribute_type,
                    'attribute_orderby' => 'name'
                ), array('attribute_name' => $attribute_name)
            );
        } else {
            // add new attribute
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies', array(
                    'attribute_label' => $attribute_label,
                    'attribute_name' => $attribute_name,
                    'attribute_type' => $attribute_type,
                    'attribute_orderby' => 'name'
                )
            );
        }

        // avoid object to be inserted in terms
        if (is_object($value))
            return;

        // add attribute values if not exist
        //$taxonomy = $woocommerce->attribute_taxonomy_name($attribute_name);
        $taxonomy = wc_attribute_taxonomy_name($attribute_name);

        if( is_array( $value ) )
        {
            $values = $value;
        }
        else
        {
            $values = array($value);
        }

        // check taxonomy
        if( !taxonomy_exists( $taxonomy ) )
        {
            // add attribute value
            foreach ($values as $attribute_value) {
                if(is_string($attribute_value)) {
                    // add term
                    $name = stripslashes($attribute_value);
                    $slug = sanitize_title($name);
                    if( !term_exists($name) ) {
                        if( $slug != '' && $name != '' ) {
                            $wpdb->insert(
                                $wpdb->terms, array(
                                    'name' => $name,
                                    'slug' => $slug
                                )
                            );

                            // add term taxonomy
                            $term_id = $wpdb->insert_id;
                            $wpdb->insert(
                                $wpdb->term_taxonomy, array(
                                    'term_id' => $term_id,
                                    'taxonomy' => $taxonomy
                                )
                            );
                        }
                    }
                } // End if
            } //  End foreach
        }
        else
        {
            // get already existing attribute values
            $attribute_values = array();
            $terms = get_terms($taxonomy);
            foreach ($terms as $term) {
                $attribute_values[] = $term->name;
            }

            // DM
            // Check if $attribute_value is not empty
            if( !empty( $attribute_values ) )
            {
                foreach( $values as $attribute_value )
                {
                    if( !in_array( $attribute_value, $attribute_values ) )
                    {
                        // add new attribute value
                        wp_insert_term($attribute_value, $taxonomy);
                    }
                }
            }
        }

        // Add terms
        if( is_array( $value ) )
        {
            foreach( $value as $dm_v )
            {
                if(is_string($dm_v)) {
                    wp_insert_term( $dm_v, $taxonomy );
                }
            }
        }
        else
        {
            if(is_string($value)) {
                wp_insert_term( $value, $taxonomy );
            }
        }

        // link to woocommerce attribute values
        if( !empty( $values ) )
        {
            //pre_print_r( 'Values not empty ');
            foreach( $values as $term )
            {

                if( !is_object( $term ) )
                {
                    $term = sanitize_title($term);

                    $term_taxonomy_id = $wpdb->get_var( "SELECT tt.term_taxonomy_id FROM {$wpdb->terms} AS t INNER JOIN {$wpdb->term_taxonomy} as tt ON tt.term_id = t.term_id WHERE t.slug = '{$term}' AND tt.taxonomy = '{$taxonomy}'");

                    if( $term_taxonomy_id )
                    {
                        $checkSql = "SELECT * FROM {$wpdb->term_relationships} WHERE object_id = {$post_id} AND term_taxonomy_id = {$term_taxonomy_id}";
                        if( !$wpdb->get_var($checkSql) ) {
                            $wpdb->insert(
                                $wpdb->term_relationships, array(
                                    'object_id' => $post_id,
                                    'term_taxonomy_id' => $term_taxonomy_id
                                )
                            );
                        }
                    }

                }

            }

        }
    }

    private function delete_products() {
        $today = getdate();
        $lastPosts = new WP_Query( array(
            'year' => $today["year"],
            'monthnum' => $today["mon"],
            'day' => $today['mday'],
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => '',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        foreach($lastPosts->posts as $post ) {
            wp_delete_post($post->ID, true);
        }
    }

    private function delete_old_variations($post_id) {
        if(is_int($post_id)) {
            $args = array(
                'post_parent' => $post_id,
                'post_type' => 'product_variation'
            );

            $remove_posts = get_posts($args);

            if (is_array($remove_posts) && count($remove_posts) > 0) {

                foreach ($remove_posts as $remove_post) {
                    echo "Removing Variation Post " . $remove_post->ID;
                    echo "...<br />";
                    wp_delete_post($remove_post->ID, true);
                }
            }
        }
    }

    function set_woocommerce_variations($asin, $post_id, $post, $update_operation, $post_options, $offset = 0, $variationPerRequest = 5 ) {

        global $woocommerce; // yuri - refer woocommerce
        // if $update_operation is true, we should delete all the existing variations.
        // wordpress delete all child posts

        $item = new ProssociateItem($asin);
        $data = $item->data;

        $variation_post = $post;

        if ($data->Variations->TotalVariations > 0) {

            // its not a simple product, it is a variable product
            wp_set_post_terms($post_id, 'variable', 'product_type', false);

            // initialize the variation dimensions array
            if (count($data->Variations->VariationDimensions->VariationDimension) == 1) {
                $VariationDimensions[$data->Variations->VariationDimensions->VariationDimension] = array();
            } else {
                // Check if VariationDimension is given
                if($data->Variations->VariationDimensions->VariationDimension) {
                    foreach ($data->Variations->VariationDimensions->VariationDimension as $dim) {
                        $VariationDimensions[$dim] = array();
                    }
                }
            }

            // loop through the variations, make a variation post for each of them
            if (count($data->Variations->Item) == 1) {
                $variation_item = $data->Variations->Item;
                $VariationDimensions = $this->variation_post($variation_item, $variation_post, $post_options, $post_id, $VariationDimensions);
                $offset ++;
                $this->var_mode = 'create'; // Return the mode to 'update'
                // Just to separate the logs
                //echo "------------------------ <br />";
            } else {

                // if the variation still has items
                $this->var_mode = 'variation';

                // Loop through the variation
                for( $varCounter = 1; $varCounter <= $variationPerRequest; $varCounter++ )
                {
                    // Check if there are still variations
                    if( $offset > ((int)$data->Variations->TotalVariations - 1) )
                    {
                        // Break the loop
                        break;
                    }
                    elseif( $offset == ((int)$data->Variations->TotalVariations - 1) )
                    {
                        // If we're at the last variation. To stop the variation iteration
                        $this->var_mode = 'create'; // Return the mode to 'update'
                    }

                    // Select the specifc variation
                    $variation_item = $data->Variations->Item[$offset];
                    // Create the variation post
                    $VariationDimensions = $this->variation_post($variation_item, $variation_post, $post_options, $post_id, $VariationDimensions);

                    // Increase the offset
                    $offset++;
                }

            }
            // Set the offset
            $this->var_offset = $offset;
            $tempProdAttr = get_post_meta( $post_id, '_product_attributes', true );
            if(!is_array($tempProdAttr)) {
                $tempProdAttr = unserialize($tempProdAttr);
                //$tempProdAttr = $prodAttr;
            }


            if(is_array($VariationDimensions)) {
                foreach( $VariationDimensions as $name => $values )
                {
                    if($name != '') {
                        $this->add_attribute_value($post_id, $name, $values);
                        //$dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($name));
                        $dimension_name = wc_attribute_taxonomy_name(strtolower($name));
                        $tempProdAttr[$dimension_name] = array(
                            'name' => $dimension_name,
                            'value' => '',
                            'position' => 0,
                            'is_visible' => 1,
                            'is_variation' => 1,
                            'is_taxonomy' => 1,
                        );
                    }
                }
            } else {
                // TODO not sure if nothing will be here
            }

            update_post_meta($post_id, '_product_attributes', $tempProdAttr);
        }
    }

    /**
     * Get the right term slug
     * @param string $value Variation value e.g Black, Green
     * @param string $taxonomy Taxonomy value e.g 'pa_color', 'pa_size'
     * @return string
     */
    private function variationMeta($value, $taxonomy) {
        global $wpdb;
        $sql = "SELECT {$wpdb->terms}.slug FROM {$wpdb->terms} INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
WHERE {$wpdb->terms}.name =  '{$value}'
AND {$wpdb->term_taxonomy}.taxonomy = '{$taxonomy}'";
        $result = $wpdb->get_var($sql);
        // If no result was found
        if($result === null)
            return '';
        else
            return $result;
    }

    function variation_post($variation_item, $variation_post, $post_options, $post_id, $VariationDimensions) {
        // Check if we have offerlistings
        if(isset($variation_item->Offers)) {
            // Check if array
            if(is_array($variation_item->Offers->Offer)) {
                foreach($variation_item->Offers->Offer as $offer) {
                    // Check if there's no offer listing
                    if(!isset($variation_item->OfferListing->OfferListingId)) {
                        continue;
                    } else {
                        // Check if we have sale price
                        if(isset($offer->OfferListing->SalePrice)) {
                            $finalPrice = $offer->OfferListing->SalePrice->FormattedPrice;
                            $finalAmount = $offer->OfferListing->SalePrice->Amount;
                        } else {
                            $finalPrice = $offer->OfferListing->Price->FormattedPrice;
                            $finalAmount = $offer->OfferListing->Price->Amount;
                        }
                        $finalOffer = $offer->OfferListing->OfferListingId;
                        break;
                    }
                }
            } else {
                // For non-array
                // Check if offer listing exists
                if(isset($variation_item->Offers->Offer->OfferListing->OfferListingId)) {
                    // Check for sale price
                    if(isset($variation_item->Offers->Offer->OfferListing->SalePrice)) {
                        $finalPrice = $variation_item->Offers->Offer->OfferListing->SalePrice->FormattedPrice;
                        $finalAmount = $variation_item->Offers->Offer->OfferListing->SalePrice->Amount;
                    } else {
                        $finalPrice = $variation_item->Offers->Offer->OfferListing->Price->FormattedPrice;
                        $finalAmount = $variation_item->Offers->Offer->OfferListing->Price->Amount;
                    }
                    $finalOffer = $variation_item->Offers->Offer->OfferListing->OfferListingId;
                }
            }
        } else {
            // if no offers
            return false;
        }

        global $woocommerce; // yuri - refer woocommerce
        global $wpdb;

        if(isset($variation_item->Title)) {
            $vTitle = $variation_item->Title;
        } else {
            $vTitle = '';
        }
        $variation_post['post_title'] = $vTitle;
        $variation_post['post_type'] = 'product_variation';
        $variation_post['post_parent'] = $post_id;
        $variation_post['ID'] = null;

        $variation_post_id = wp_insert_post($variation_post);

        // UPDATE POST META WITH SERIALIZED PRODUCT DATA
        $this->standard_custom_fields($variation_item, $variation_post_id, true);

        // INSERT FEATURED IMAGES
        if ($post_options['download_images'] == 'on') {
            $this->set_post_images($variation_item, $variation_post_id, true);
        }


        // SET WOOCOMMERCE FIELDS
        if ($variation_post['post_type'] == 'product_variation') {
            $this->set_woocommerce_fields($variation_item, $variation_post_id, true);
        }


        // Compile all the possible variation dimensions
        if(is_array($variation_item->VariationAttributes->VariationAttribute)) {
            foreach ($variation_item->VariationAttributes->VariationAttribute as $va) {
                $this->add_attribute_value($post_id, $va->Name, $va->Value);

                $curarr = $VariationDimensions[$va->Name];
                $curarr[$va->Value] = $va->Value;

                $VariationDimensions[$va->Name] = $curarr;

                // SET WOOCO VARIATION ATTRIBUTE FIELDS / yuri - change dimension name as woocommerce attribute name
                //$dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($va->Name));
                $dimension_name = wc_attribute_taxonomy_name(strtolower($va->Name));

                // Get proper slug for variation e.g (black-12, green)
                $varMetaSlug = $this->variationMeta($va->Value, $dimension_name);

                update_post_meta($variation_post_id, 'attribute_' . $dimension_name, $varMetaSlug);

            }
        } else {
            $dmName = $variation_item->VariationAttributes->VariationAttribute->Name;
            $dmValue = $variation_item->VariationAttributes->VariationAttribute->Value;

            $this->add_attribute_value($post_id, $dmName, $dmValue);

            $curarr = $VariationDimensions[$dmName];
            $curarr[$dmValue] = $dmValue;

            $VariationDimensions[$dmName] = $curarr;

            // SET WOOCO VARIATION ATTRIBUTE FIELDS / yuri - change dimension name as woocommerce attribute name
            $dimension_name = wc_attribute_taxonomy_name(strtolower($dmName));

            // Get proper slug for variation e.g (black-12, green)
            $varMetaSlug = $this->variationMeta($dmValue, $dimension_name);

            update_post_meta($variation_post_id, 'attribute_' . $dimension_name, $varMetaSlug);
        }

        // yuri - refresh attribute cache
        $dmtransient_name = 'wc_attribute_taxonomies';
        $dmattribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
        set_transient($dmtransient_name, $dmattribute_taxonomies);

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Try to get the listing price
        $finalListingPrice = 0;
        if(isset($variation_item->ItemAttributes->ListPrice->Amount)) {
            $finalListingPrice = $variation_item->ItemAttributes->ListPrice->Amount; // Get the listing int amount
            $finalProcessedListingPrices = $this->reformat_prices($this->remove_currency_symbols($variation_item->ItemAttributes->ListPrice->FormattedPrice));
        }

        // Add the offer id and price
        update_post_meta($variation_post_id, '_dmpros_offerid', $finalOffer);
        update_post_meta($variation_post_id, '_price', $finalProcessedPrice);
        update_post_meta($variation_post_id, '_regular_price', $finalProcessedPrice);

        // Handle the regular / sale price
        if($finalAmount < $finalListingPrice) {
            update_post_meta($variation_post_id, '_regular_price',$finalProcessedListingPrices);
            update_post_meta($variation_post_id, '_sale_price', $finalProcessedPrice);
            update_post_meta($variation_post_id, '_price', $finalProcessedPrice);
        }

        // Insert ASIN as SKU
        update_post_meta($variation_post_id, '_sku', $variation_item->ASIN);

        return $VariationDimensions;
    }

    function standard_custom_fields($data, $post_id, $isVariation = false ) {
        if(!$isVariation) {
            echo "Inserting meta fields for {$data->ASIN}. <br />";
        }

        if(isset($data->ItemAttributes)) {
            update_post_meta($post_id, '_pros_ItemAttributes', serialize($data->ItemAttributes));
        }
        if(isset($data->Offers)) {
            update_post_meta($post_id, '_pros_Offers', serialize($data->Offers));
        }
        if(isset($data->OfferSummary)) {
            update_post_meta($post_id, '_pros_OfferSummary', serialize($data->OfferSummary));
        }
        if(isset($data->SimilarProducts)) {
            update_post_meta($post_id, '_pros_SimilarProducts', serialize($data->SimilarProducts));
        }
        if(isset($data->Accessories)) {
            update_post_meta($post_id, '_pros_Accessories', serialize($data->Accessories));
        }
        if(isset($data->ASIN)) {
            update_post_meta($post_id, '_pros_ASIN', $data->ASIN);
        }
        if(isset($data->ParentASIN)) {
            update_post_meta($post_id, '_pros_ParentASIN', $data->ParentASIN);
        }
        if(isset($data->DetailPageURL)) {
            update_post_meta($post_id, '_pros_DetailPageURL', $data->DetailPageURL);
        }
        if(isset($data->CustomerReviews)) {
            update_post_meta($post_id, '_pros_CustomerReviews', serialize($data->CustomerReviews));
        }
        if(isset($data->EditorialReviews)) {
            update_post_meta($post_id, '_pros_EditorialReviews', serialize($data->EditorialReviews));
        }
        if(isset($data->VariationSummary)) {
            update_post_meta($post_id, '_pros_VariationSummary', serialize($data->VariationSummary));
        }
        if(isset($data->Variations->VariationDimensions)) {
            update_post_meta($post_id, '_pros_VariationDimensions', serialize($data->Variations->VariationDimensions));
        }

        if(isset($data->Variations->TotalVariations)) {
            if ($data->Variations->TotalVariations > 0) {
                if (count($data->Variations->Item) == 1) {
                    update_post_meta($post_id, '_pros_FirstVariation', serialize($data->Variations->Item));
                } else {
                    update_post_meta($post_id, '_pros_FirstVariation', serialize($data->Variations->Item[0]));
                }
            }
        }
    }

    private function set_post_featured_thumb($image_url, $title, $post_id, $imageSet) {

      //echo $image_url.'-'.$post_id.'<br>';
      $ira = get_option('prossociate_settings-dm-pros-remote-img', 'no');
      $filename = substr(md5($image_url), 0, 12) . "." . pathinfo($image_url, PATHINFO_EXTENSION);
      //echo $image_url.'<br>';
      $file='';
      if(empty($ira) || $ira == 'no'){
        $upload_dir = wp_upload_dir();
        //$image_data = file_get_contents($image_url);
        $dmImage = wp_remote_get($image_url, array('user-agent' => "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0", 'timeout' => 10));
        //var_dump($dmImage);
        $image_data = wp_remote_retrieve_body($dmImage);
        //var_dump($image_data);

        //echo $upload_dir['path'];
        //echo wp_mkdir_p($upload_dir['path']).':dir:';

        /*$ext = pathinfo($filename, PATHINFO_EXTENSION);
        echo $filename = 'prodcut_'.sanitize_file_name($title).'.'.$ext;*/
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        $file_added = file_put_contents($file, $image_data);
      }
      else if($ira == 'yes'){
        $file = $image_url;
      }

      $wp_filetype = wp_check_filetype($filename, null);
      $attachment = array(
          'post_mime_type' => $wp_filetype['type'],
          'post_title' => $title,
          'post_content' => '',
          'post_status' => 'inherit'
      );
      //echo $file.'<br>';
      $attach_id = wp_insert_attachment($attachment, $file, $post_id);

      require_once(ABSPATH . 'wp-admin/includes/image.php');
      if(empty($ira) || $ira == 'no'){
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
      }else{
        $this->set_attachment_metadata( $attach_id, $imageSet );
      }



      return $attach_id;
    }

    function set_attachment_metadata( $attach_id, $imageSet ) {
			$imageSet = isset($imageSet) ? $imageSet : array();
      $image_sizes = array();
			if ( empty($imageSet) || !is_array($imageSet) ) {
				$image_sizes['large'] = array(
					'url'			=> $imageSet->LargeImage->URL,
					'width'			=> $imageSet->LargeImage->Width->_,
					'height'		=> $imageSet->LargeImage->Height->_,
				);
				$image_sizes['thumbnail'] = array(
          'url'			=> $imageSet->ThumbnailImage->URL,
					'width'			=> $imageSet->ThumbnailImage->Width->_,
					'height'		=> $imageSet->ThumbnailImage->Height->_,
				);
        $image_sizes['small'] = array(
          'url'			=> $imageSet->SmallImage->URL,
					'width'			=> $imageSet->SmallImage->Width->_,
					'height'		=> $imageSet->SmallImage->Height->_,
				);
        $image_sizes['tiny'] = array(
          'url'			=> $imageSet->TinyImage->URL,
					'width'			=> $imageSet->TinyImage->Width->_,
					'height'		=> $imageSet->TinyImage->Height->_,
				);
        $image_sizes['medium'] = array(
          'url'			=> $imageSet->MediumImage->URL,
					'width'			=> $imageSet->MediumImage->Width->_,
					'height'		=> $imageSet->MediumImage->Height->_,
				);
        $image_sizes['swatch'] = array(
          'url'			=> $imageSet->SwatchImage->URL,
					'width'			=> $imageSet->SwatchImage->Width->_,
					'height'		=> $imageSet->SwatchImage->Height->_,
				);
			}

			$attach_data = array(
				'file'			=> 0,
				'width'			=> 0,
				'height'		=> 0,
				'sizes'			=> array(),
				'image_meta' 	=> array(
					'aperture' => '0',
					'credit' => '',
					'camera' => '',
					'caption' => '',
					'created_timestamp' => '0',
					'copyright' => '',
					'focal_length' => '0',
					'iso' => '0',
					'shutter_speed' => '0',
					'title' => '',
					'orientation' => '0',
					'keywords' => array (),
				),
			);
			$original = $this->_choose_image_original( 'large', $image_sizes );
			if ( !empty($original) ) {
				//$wp_filetype = wp_check_filetype( basename( $original['url'] ), null );
				$attach_data = array_replace_recursive($attach_data, array(
					'file'			=> $original['url'],
					'width'			=> $original['width'],
					'height'		=> $original['height'],
					//'mime-type'		=> $wp_filetype['type'],
				));
			}

			$wp_sizes = $this->get_image_sizes();
			//var_dump('<pre>', 'attach_id', $attach_id, 'image_sizes', $image_sizes, 'wp_sizes', $wp_sizes, '</pre>');
			//var_dump('<pre>', '---------------------------------', '</pre>');
			foreach ($wp_sizes as $size => $props) {

				//var_dump('<pre>', $size, '---------------','</pre>');
				$found_size = $this->_choose_image_size_from_amazon( $props, $image_sizes );
				//var_dump('<pre>',$found_size,'</pre>');

				if ( !empty($found_size) ) {
					$wp_filetype = wp_check_filetype( basename( $found_size['url'] ), null );
					$attach_data['sizes']["$size"] = array(
						'file'			=> basename( $found_size['url'] ),
						'width'			=> $found_size['width'],
						'height'		=> $found_size['height'],
						'mime-type'		=> $wp_filetype['type'],
					);
				}
			}
			//var_dump('<pre>', $attach_data, '</pre>'); die('debug...');
			wp_update_attachment_metadata( $attach_id, $attach_data );
		}

    function _choose_image_original( $size_alias='large', $image_sizes=array() ) {
			if ( empty($image_sizes) ) return false;

			// selected size as original
			if ( isset($image_sizes["$size_alias"]) ) {
				return $image_sizes["$size_alias"];
			}

			// we try to find biggest image by width
			$current = array('url' => '', 'width' => 0, 'height' => 0);
			foreach ($image_sizes as $_size => $props) {
				if ( (int) $props['width'] <= (int) $current['width'] ) {
					continue 1;
				}
				$current = $props;
			}
			return $current;
		}

    public function get_image_sizes() {
			$wp_sizes = $this->get_wp_image_sizes();
      $allow_sizes = unserialize(get_option('prossociate_settings-dm-pros-remote-image-sizes'));

			$allowed = isset($allow_sizes) ? $allow_sizes : array();
			$allowed = !empty($allowed) && is_array($allowed) ? $allowed : array();

			if ( empty($allowed) ) return $wp_sizes;
			foreach ( $wp_sizes as $size => $props ) {
				if ( !in_array($size, $allowed) ) {
					unset($wp_sizes["$size"]);
				}
			}
			return $wp_sizes;
		}

    /**
		 * List available image sizes with width and height following
		 */
		/**
		 * Get size information for all currently-registered image sizes.
		 *
		 * @global $_wp_additional_image_sizes
		 * @uses   get_intermediate_image_sizes()
		 * @return array $sizes Data for all currently-registered image sizes.
		 */
		public function get_wp_image_sizes() {
			global $_wp_additional_image_sizes;

			$sizes = array();

			foreach ( get_intermediate_image_sizes() as $_size ) {
				if ( in_array( $_size, array('thumbnail', 'medium', 'medium_large', 'large') ) ) {
					$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
					$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
					$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
				} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
					$sizes[ $_size ] = array(
						'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
						'height' => $_wp_additional_image_sizes[ $_size ]['height'],
						'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
					);
				}
			}

			return $sizes;
		}

    function _choose_image_size_from_amazon( $size, $image_sizes=array() ) {
			if ( empty($image_sizes) ) return false;

			$diff = array();
			foreach ($image_sizes as $_size => $props) {
				// found exact width
				if ( (int) $size['width'] == (int) $props['width'] ) {
					return $props;
				}
				$diff["$_size"] = (int) $props['width'] - (int) $size['width'];
			}
			$positive = array_filter( $diff, array($this, '_positive') );
			$negative = array_filter( $diff, array($this, '_negative') );

			$found = false; $found_pos = false; $found_neg = false;
			if ( !empty($positive) ) {
				$found_pos = min( $positive );
			}
			if ( !empty($negative) ) {
				$found_neg = max( $negative );
			}

			if ( !empty($found_pos) && !empty($found_neg) ) {
				if ( $found_pos > 100 && ( $found_pos > ceil(3 * abs($found_neg)) ) ) {
					$found = $found_neg;
				} else {
					$found = $found_pos;
				}
			}
			else if ( !empty($found_pos) ) {
				$found = $found_pos;
			}
			else if ( !empty($found_neg) ) {
				$found = $found_neg;
			}
			if ( empty($found) ) return false;

			$found_size = array_search( $found, $diff );
			if ( empty($found_size) ) return false;
			return $image_sizes["$found_size"];
		}

		// you can: from php 4 use create_function; from php 5.3 use anonymous function
		function _positive( $v ) {
			return $v >= 0;
		}
		function _negative( $v ) {
			return $v < 0;
		}

    function set_post_images($data, $post_id, $variation = false ) {
        if(!$variation) {
            echo "Downloading product images for {$data->ASIN}. <br />";
        }

        if(isset($data->ImageSets->ImageSet)) {
            if (count($data->ImageSets->ImageSet) == 1) {

                $i = $data->ImageSets->ImageSet;

                $image_url = $i->LargeImage->URL;

                $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                set_post_thumbnail($post_id, $attach_id);
            } else {
                if (isset($data->ImageSets->ImageSet)) {
                    // Count the number of images
                    $imageSetCount = count($data->ImageSets->ImageSet);
                    // Gallery ids container
                    $dmGalleryIds = array();
                    foreach ($data->ImageSets->ImageSet as $k => $i) {
                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                        // Check if on the first image
                        if ($k == 0) {

                          if(isset($data->LargeImage->URL)) {
                          //if(isset($data->LargeImage->URL) && (in_array('2350149011', $nodePathsArray) || in_array('283155', $nodePathsArray) || in_array('133140011', $nodePathsArray)) ) {
                            // fix wrong feature image
                            //echo 'posting main image '.$nodePathsArray;
                            $attach_id = $this->set_post_featured_thumb($data->LargeImage->URL, $data->ItemAttributes->Title, $post_id, $i);
                          }
                          $set = set_post_thumbnail($post_id, $attach_id);
                        }

                        // Check if we're on variation
                        if( $variation && !$this->external ) {
                            // Allow more images for external variations
                            // Process only 1 image for variations
                            break;
                        }

                        // Store the post_id of the images to be attached as gallery
                        if ($k > 0) {
                            $dmGalleryIds[] = $attach_id;
                        }

                        // If we're on the last image
                        if ($k == ($imageSetCount - 1)) {
                            // Set the gallery
                            update_post_meta($post_id, '_product_image_gallery', implode(',', $dmGalleryIds));
                        }
                    }
                } else {
                    // Not it will go here when there are no available image
                    // If that's the case, we will get the image from the first variation

                    // Check if we have variation
                    if(is_array($data->Variations->Item)) {
                        if($data->Variations->Item[0]) {
                            $i = $data->Variations->Item[0];

                            // same code
                            $image_url = $i->LargeImage->URL;

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                            set_post_thumbnail($post_id, $attach_id);
                        } else {
                            echo "How do we end up here? Images bug: " . $post_id;
                        }
                    } else {
                        // If Variations->Item is not a n array
                        if($data->Variations->Item) {
                            $i = $data->Variations->Item;

                            // same code
                            $image_url = $i->LargeImage->URL;

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                            set_post_thumbnail($post_id, $attach_id);
                        } else {
                            echo "How do we end up here? Images bug: " . $post_id;
                        }
                    }
                }
            }
        } else {
            // If we don't have images from the product itself. We get it from the variations
            if(isset($data->Variations->Item)) {
                // Check if we have variation
                if(is_array($data->Variations->Item)) {
                    if($data->Variations->Item[0]) {
                        $i = $data->Variations->Item[0];

                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                        set_post_thumbnail($post_id, $attach_id);
                    } else {
                        echo "How do we end up here? Images bug: " . $post_id;
                    }
                } else {
                    // If Variations->Item is not a n array
                    if($data->Variations->Item) {
                        $i = $data->Variations->Item;

                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id, $i);

                        set_post_thumbnail($post_id, $attach_id);
                    } else {
                        echo "How do we end up here? Images bug: " . $post_id;
                    }
                }
            }
        }
    }

    function set_woocommerce_fields($data, $post_id, $isVariation = false) {
        if(!$isVariation) {
            echo "Populating cart info for {$data->ASIN}. <br />";
        }

        //if(isset($data->DetailPageURL)) {
            update_post_meta($post_id, '_product_url', get_bloginfo('wpurl') . '?' . self::$externalProductUrl . '=' . $post_id);
        //}

        // SET PRICES
        //$this->set_woocommerce_fields_prices($data, $post_id);

        // SET CUSTOM FIELDS
        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_downloadable', 'no');
        update_post_meta($post_id, '_virtual', 'no');

        // Set total sales, fixes the issue where products aren't displayed on Sort by Popularity
        update_post_meta($post_id, 'total_sales', 0);


        // SET AVAILABILITY

        if (get_post_meta($post_id, "_pros_Available", true) == "no") {
            update_post_meta($post_id, '_stock_status', "outofstock");
        }
    }

    function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
    }

    function set_woocommerce_fields_prices($data, $post_id) {
        // in case there is no price
        $backup_price = '';
        if (isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $backup_price = $data->ItemAttributes->ListPrice->FormattedPrice;
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $backup_price = $data->Offers->Offer->OfferListing->Price->FormattedPrice;
        }

        // If there's no other prices available (like ASIN: B00BTCWOQG, Disney Pixar Cars 2013 Diecast Flo Wheel Well Motel 7/11)
        if( isset($data->OfferSummary->LowestNewPrice->FormattedPrice) ) {
            $backup_price = $data->OfferSummary->LowestNewPrice->FormattedPrice;
        }

        // remove dollar signs from price
        $backup_price = $this->remove_currency_symbols($backup_price);
        // format the price
        $backup_price = $this->reformat_prices($backup_price);

        if (isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $data->ItemAttributes->ListPrice->FormattedPrice = $this->remove_currency_symbols($data->ItemAttributes->ListPrice->FormattedPrice);
            // format the price
            $data->ItemAttributes->ListPrice->FormattedPrice = $this->reformat_prices($data->ItemAttributes->ListPrice->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $data->Offers->Offer->OfferListing->Price->FormattedPrice = $this->remove_currency_symbols($data->Offers->Offer->OfferListing->Price->FormattedPrice);
            // Replace comma with period
            $data->Offers->Offer->OfferListing->Price->FormattedPrice = $this->reformat_prices($data->Offers->Offer->OfferListing->Price->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice)) {
            $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice = $this->remove_currency_symbols($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
            // Replace comma with period
            $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice = $this->reformat_prices($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
        }

        if (isset($data->Offers->Offer->OfferListing->Price->FormattedPrice) && isset($data->ItemAttributes->ListPrice->FormattedPrice)) {

            if ($data->Offers->Offer->OfferListing->Price->FormattedPrice == $data->ItemAttributes->ListPrice->FormattedPrice) {
                // only set the regular price
                update_post_meta($post_id, '_regular_price', $data->ItemAttributes->ListPrice->FormattedPrice);
                update_post_meta($post_id, '_price', $data->ItemAttributes->ListPrice->FormattedPrice);
            }

            if ($data->Offers->Offer->OfferListing->Price->FormattedPrice < $data->ItemAttributes->ListPrice->FormattedPrice) {
                //  set the regular price and sale price
                update_post_meta($post_id, '_regular_price', $data->ItemAttributes->ListPrice->FormattedPrice);
                update_post_meta($post_id, '_price', $data->Offers->Offer->OfferListing->Price->FormattedPrice);
                update_post_meta($post_id, '_sale_price', $data->Offers->Offer->OfferListing->Price->FormattedPrice);
            }

            // Check if we problems with the price
            if(!get_post_meta($post_id, '_regular_price', true)) {
                // Convert the price to proper integer
                $regPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
                $salePrice = $data->Offers->Offer->OfferListing->Price->FormattedPrice;

                $regPrice = (int)str_replace(',', '', $regPrice);
                $salePrice = (int)str_replace(',', '', $salePrice);

                if($salePrice == $regPrice || $salePrice > $regPrice ) {
                    update_post_meta($post_id, '_regular_price', $regPrice);
                    update_post_meta($post_id, '_price', $regPrice);
                }

                if($salePrice < $regPrice) {
                    update_post_meta($post_id, '_regular_price', $regPrice);
                    update_post_meta($post_id, '_price', $salePrice);
                    update_post_meta($post_id, '_sale_price', $salePrice);
                }
            }

        }
        else {
            // only one price is available - it doesnt matter if it is the sale or regular price. we have to show it as regular, because we cant show a higher price as the regular.
            if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
                $insertRegPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            } else {
                $insertRegPrice = $backup_price;
            }

            if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
                $insertPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            } else {
                $insertPrice = $backup_price;
            }
            update_post_meta($post_id, '_regular_price', $insertRegPrice);
            update_post_meta($post_id, '_price', $insertPrice);
        }

        // Add the saleprice
        if(isset($data->Offers->Offer->OfferListing->SalePrice->FormattedPrice)) {
            update_post_meta($post_id, '_price', $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
            update_post_meta($post_id, '_sale_price', $data->Offers->Offer->OfferListing->SalePrice->FormattedPrice);
        }

    }

    function reformat_prices($price) {
        switch( AWS_COUNTRY ) {
            // Germany
            case 'de':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // France
            case 'fr':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // Spain
            case 'es':
                $formatPrice = $this->reformat_price_de($price);
                break;
            // Italy
            case 'it':
                $formatPrice = $this->reformat_price_de($price);
                break;
            default:
                $formatPrice = str_replace(',', '', $price);
                break;
        }

        return $formatPrice;
    }

    function reformat_price_de($price) {
        // Convert the string to array
        $priceArray = str_split($price);
        foreach ($priceArray as $k => $v) {
            // Check if a period
            if ($v == '.') {
                // Convert the period to comma
                $priceArray[$k] = '';
            } elseif ($v == ',') {
                // Convert comma to period
                $priceArray[$k] = '.';
            }
        }
        // Convert the array to a string
        $formatPrice = implode('', $priceArray);

        return $formatPrice;
    }

    function admin_enqueue_scripts() {
        wp_register_script('prossociate_poster', PROSSOCIATE_ROOT_URL . '/js/ProssociatePoster.js');
        wp_enqueue_script('prossociate_poster');
    }

    /**
     * Delete associated images for a product
     * @param int $productId
     */
    private function deleteImages($productId) {
      if(empty($productId)){
        return;
      }
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
     * Delete a product
     * @param int $productId
     */
    private function deleteProduct($productId) {
        // Delete
        wp_delete_post($productId, true);
    }

}

function my_shutdown_function() {

    $log = ob_get_clean();

    $data['log'] = $log;
    $data['campaign_id'] = 'error';
    $data['page'] = 'error';
    $data['complete'] = true;
    $data['mode'] = 'error';

    $data = json_encode($data);

    echo $data;
}
