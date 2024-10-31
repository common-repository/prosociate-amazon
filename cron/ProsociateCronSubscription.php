<?php
class ProsociateCronSubscription {
    /**
     * Option name for the status of Cron Subscription
     *
     * @var string
     */
    private static $option = 'prosCronSubsStatus';

    /**
     * Option name for the current subscription ID that is running the cron
     *
     * @var string
     */
    private static $optionSubsId = 'prosCronSubsId';

    /**
     * Option name for the current page we are getting asins
     *
     * @var string
     */
    private static $optionSubsPage = 'prosCronSubsPage';

    /**
     * Option name for the current asins
     *
     * @var string
     */
    private static $optionSubsAsins = 'prosCronSubsAsins';

    /**
     * Option name for the new asins
     *
     * @var string
     */
    private static $optionsNewAsins = 'prosCronSubsNewAsins';

    /**
     * Option name for parent asin
     *
     * @var string
     */
    private static $optionsSubsParentAsin = 'pronCronSubsParentAsin';

    /**
     * Option name for the parent post id for variations
     *
     * @var string
     */
    private static $optionsSubsParentPostId = 'prosCronSubsParentPostId';

    /**
     * Offset to locate variation item
     *
     * @var int
     */
    private static $optionsSubsVarOffset = 'prosCronSubsVarOffset';

    /**
     * Option name to see if we are from variation
     *
     * @var string
     */
    private static $optionsSubsIsFromVariation = 'prosCronSubsIsFromVariation';

    /**
     * Number of variations to be posted per request
     *
     * @var int
     */
    private static $variationPerRequest = 5;

    /**
     * Container for the susbcription campaign object
     *
     * @var null
     */
    private $campaign = null;

    /**
     * Checker if we are in variation
     *
     * @var bool
     */
    private $isVariation = false;

    /**
     * Steps
     *
     * 1.] Get the status
     * 2.] Get the subscription from db
     * 3.] Get the asins from page 1.
     * 4.] See if there are new asins
     * 5.] Save new asins.
     * 6.] Repeat steps 4 - 5 until page 10
     * 7.] Post new products
     */
    function __construct() {
        // Get status
        $status = get_option(self::$option, 'getSubscriptionId');

        //$this->resetOptions();
        // Check status
        $this->checkStatus($status);
    }

    /**
     * Get the status of the subscription
     *
     * @param string $status
     */
    private function checkStatus($status) {
        switch($status) {

            case 'getSubscriptionId':
                $subscriptionId = $this->getSubscriptionId();

                // Check if we got a subscription id to be updated
                if($subscriptionId !== null) {
                    update_option(self::$optionSubsId, $subscriptionId);
                    // Proceed to next step
                    update_option(self::$option, 'getAsins');
                    // Recursive
                    $this->checkStatus('getAsins');
                } else {
                    // If we have no subscription id
                    update_option(self::$optionSubsId, 'false');
                }
                break;

            case 'getAsins':
                // Get the subscription Id
                $subscriptionId = get_option(self::$optionSubsId, 'false');
                if($subscriptionId === false || $subscriptionId == 'false' ) {
                    // If no subscription to be updated
                    update_option(self::$option, 'getSubscriptionId');
                } else {
                    // If we will get asins from result pages
                    // Get the asins
                    $asins = $this->getAsins($subscriptionId);

                    // Check if asins are empty
                    if(empty($asins)) {
                        // Update the last run time of the subscription Id
                        $this->updateLastRunTime($subscriptionId);
                        // If no results then reset everything
                        $this->resetOptions();
                    } else {
                        // If not empty
                        // Save the asins
                        update_option(self::$optionSubsAsins, $asins);
                        // Proceed to next step
                        update_option(self::$option, 'checkNewAsins');

                        $this->checkStatus('checkNewAsins'); // recursive
                    }
                }
                break;

            case 'checkNewAsins':
                // Get asins
                $asins = get_option(self::$optionSubsAsins, array());

                // Get new asins
                $newAsins = $this->checkNewAsins($asins);

                // Save new asins
                update_option(self::$optionsNewAsins, $newAsins);

                // Proceed to next step
                update_option(self::$option, 'postNewProduct');
                $this->checkStatus('postNewProduct');

                break;
            case 'postNewProduct':
                // Get the new asins
                $newAsins = get_option(self::$optionsNewAsins, array());

                // Check if new asins were found
                if(empty($newAsins)) {
                    // No new asins
                } else {
                    // Post new asins
                    foreach($newAsins as $asin) {
                        if(!$this->isAsinExist($asin))
                            $postId = $this->postNewProduct($asin);
                        // If we are on variation.
                        if($this->isVariation) {
                            // Save the parent asin
                            update_option(self::$optionsSubsParentAsin, $asin);

                            // Save the parent post id
                            update_option(self::$optionsSubsParentPostId, $postId);

                            // Proceed to next step
                            update_option(self::$option, 'postNewVariation');
                            break;
                        }
                    }

                    if($this->isVariation) {
                        // TODO Update option here that we are on variation
                        $this->checkStatus('postNewVariation');
                    }


                    // yuri - refresh attribute cache
                    global $wpdb;
                    $transient_name = 'wc_attribute_taxonomies';
                    $attribute_taxonomies = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "woocommerce_attribute_taxonomies");
                    set_transient($transient_name, $attribute_taxonomies);
                }

                // Only do this if we are not on variation
                // TODO what if we are on last asin in a page and a variation
                if(!$this->isVariation) {
                  //die('!vari');
                    // Get the page
                    $page = get_option(self::$optionSubsPage, 'false');
                    // If page === false then we are on max page
                    if($page === false) {
                        //die('false');
                        $subscriptionId = get_option(self::$optionSubsId, 'false');
                        $this->updateLastRunTime($subscriptionId);
                        // reset everything
                        $this->resetOptions();
                    } else {
                        //die('empty');
                        // All new asins on the page is posted. Redo the process
                        update_option(self::$option, 'getAsins');
                    }
                }
                break;
            case 'postNewVariation':
                // Get the parent asin
                $asin = get_option(self::$optionsSubsParentAsin, 'false');
                if($asin == 'false') {
                    // Reset variation offset
                    update_option(self::$optionsSubsVarOffset, 0);
                    update_option(self::$optionsSubsParentAsin, 'false');
                    update_option(self::$optionsSubsParentPostId, 'false');
                    // Set to post new asins
                    update_option(self::$option, 'postNewProduct');
                } else {
                    // Post variation
                    $this->postNewProduct($asin, true);
                    // Check we need to redo
                    if($this->isVariation) {
                        // We still have variations
                        update_option(self::$option, 'postNewVariation');
                    } else {
                        // Reset variation offset
                        update_option(self::$optionsSubsVarOffset, 0);
                        update_option(self::$optionsSubsParentAsin, 'false');
                        update_option(self::$optionsSubsParentPostId, 'false');
                        update_option(self::$option, 'postNewProduct');
                        //fix for missing main image issue.
                        $post_data = unserialize(get_option( 'cron_dm_pros_post_data' ));
                        //$this->set_post_featured_thumb($post_data['post_image'], $post_data['post_title'], $post_data['post_id']);

                    }
                }
                break;
            default:
                break;
        }
    }

    /**
     * Get a subscription ID
     *
     * @return null|string
     */
    private function getSubscriptionId() {
        // Get the subscription here
        global $wpdb;

        // Table name
        $tableName = $wpdb->prefix . PROSSOCIATE_PREFIX . 'prossubscription';
        $timeLessDay = time() - 86400;
        if(isset($_GET['mode']) && $_GET['mode'] =='testsub'){
          $timeLessDay = time() - 10;
        }


        $sql = $wpdb->get_var("SELECT * FROM $tableName WHERE last_run_time < $timeLessDay");

        return $sql ;
    }


    /**
     * Get the asins on a specific page number of the subscription campaign
     *
     * @param string $subscriptionId
     * @return array
     */
    private function getAsins($subscriptionId) {
        // Get page
        $page = (int)get_option(self::$optionSubsPage, '1');

        // Create new instance of subscription
        $campaign = $this->getSubscriptionCampaignInstance($subscriptionId);

        // Create instance of search and perform the search
        $search = new ProssociateSearch($campaign->options['keywords'], $campaign->options['searchindex'], $campaign->options['browsenode'], $campaign->options['sortby']);
        $power_options = $search->parse_power_search_data($this->campaign->post_options['books_operator'], $this->campaign->post_options['books']);
        $search->set_advanced_options($campaign->options['minprice'], $campaign->options['maxprice'], $campaign->options['availability'], $campaign->options['condition'], $campaign->options['manufacturer'], $campaign->options['brand'], $campaign->options['merchantid'], $campaign->options['minpercentageoff'], $campaign->options['item_title'], $power_options);
        $search->page = $page;
        $search->merchantid = $campaign->options['merchantid'];
        $search->execute('Small', false);

        // Increment page
        $page++;
        // Check if we need to add a new page
        if($campaign->options['searchindex'] == 'All') {
            if($page > 5)
                $page = false;
        } else {
            if($page > 10)
                $page = false;
        }

        // For campaigns that has limited pages
        if($page > $search->response->Items->TotalPages)
            $page = false;

        // Save the page
        update_option(self::$optionSubsPage, $page);

        return $search->results;
    }

    /**
     * Get the new asins from an array of asins
     *
     * @param array $asins
     * @return array
     */
    private function checkNewAsins($asins) {
        if(empty($asins))
            return;

        // New asins container
        $newAsins = array();

        // Loop through all the asins
        foreach($asins as $asin) {
            // Check if asin exist
            if(!$this->isAsinExist($asin['ASIN'])) {
                // Doesn't exist
                $newAsins[] = $asin['ASIN'];
            }
        }

        return $newAsins;
    }

    /**
     * Check if asin already existed
     *
     * @param string $asin
     * @return bool
     */
    private function isAsinExist($asin) {
        $exist = false;

        // Get post with the asin
        $args = array(
            'post_status' => array('publish', 'draft', 'private'),
            'post_type' => 'product',
            'meta_key' => '_pros_ASIN',
            'meta_value' => $asin
        );

        $query = new WP_Query($args);

        // If no posts found
        if(!empty($query->posts)) {
            $exist = true;
        }

        wp_reset_postdata();

        return $exist;
    }

    /**
     * Check if item is valid
     *
     * @param string $asin
     * @param bool $isVariation
     * @return int
     */
    private function postNewProduct($asin, $isVariation = false) {
        // Get product data
        $item = $this->getData($asin);

        // If we are on variation
        if($isVariation) {
            // Get the parent id
            $parentId = get_option(self::$optionsSubsParentPostId, 'false');
            // Post the variation
            $this->postVariation($item, $parentId);
            return;
        }

        // For unavailable DVD
        if(isset($item->data->Offers->Offer) && !is_array($item->data->Offers->Offer)) {
            if(is_string($item->data->Offers->Offer->Merchant->Name)) {
                if($item->data->Offers->Offer->Merchant->Name === 'Amazon Video On Demand')
                    $item->isValid = false;
            } // end if string
        }

        // Check if the item is valid
        if($item->isValid === true) {
            $postId = $this->post($item);
            $post_data = array('post_id'=> $postId, 'post_image'=> $item->data->LargeImage->URL, 'post_title'=> $item->ItemAttributes->Title);
            update_option( 'cron_dm_pros_post_data', serialize($post_data) );

        }

        return $postId;
    }

    private function postVariation($item, $post_id) {

        global $woocommerce;

        // Get the data
        $data = $item->data;

        // Get offset
        $offset = (int)get_option(self::$optionsSubsVarOffset, 0);

        // Only post variation if possible
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
                $VariationDimensions = $this->variation_post($variation_item, $post_id, $VariationDimensions);
                $offset++;
                // Product done
                $this->isVariation = false;
            } else {
                // if the variation still has items
                $this->isVariation = true;

                // Loop through the variation
                for( $varCounter = 1; $varCounter <= self::$variationPerRequest; $varCounter++ )
                {
                    // Check if there are still variations
                    if( $offset > ((int)$data->Variations->TotalVariations - 1) )
                    {
                        $this->isVariation = false;
                        // Break the loop
                        break;
                    }
                    elseif( $offset == ((int)$data->Variations->TotalVariations - 1) )
                    {
                        // If we're at the last variation. To stop the variation iteration
                        $this->isVariation = false;
                    }

                    // Select the specifc variation
                    $variation_item = $data->Variations->Item[$offset];
                    // Create the variation post
                    $VariationDimensions = $this->variation_post($variation_item, $post_id, $VariationDimensions);

                    // Increase the offset
                    $offset++;
                }

            }

            // Save the offset
            update_option(self::$optionsSubsVarOffset, $offset);

            $prodAttr = get_post_meta( $post_id, '_product_attributes', true );
            if(is_string($prodAttr)) {
                $tempProdAttr = unserialize($prodAttr);
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
     * Set product as external or simple
     *
     * @param int $postId
     * @param string $productType
     */
    private function setProductType($postId, $productType = 'simple') {
        // Get external term id
        global $wpdb;

        // Get term id for external
        $termIdExternal = $wpdb->get_var("SELECT term_id FROM " . $wpdb->terms . " WHERE name = '{$productType}'");

        if($termIdExternal === null)
            return;

        $wpdb->insert($wpdb->term_relationships, array(
                'object_id' => $postId,
                'term_taxonomy_id' => $termIdExternal,
                'term_order' => 0
            ),
            array(
            '%d',
            '%d',
            '%d'
        ));
    }

    /**
     * Assign the product to categories
     *
     * @param int $postId
     * @param array $cats
     */
    private function assignCategories($postId, $cats) {
        if(empty($cats))
            return;

        global $wpdb;

        // Process each cat id
        foreach($cats as $cat) {
            // Make sure we have an int
            if(!is_numeric($cat))
                continue;

            $wpdb->insert($wpdb->term_relationships, array(
                    'object_id' => $postId,
                    'term_taxonomy_id' => (int)$cat,
                    'term_order' => 0
                ),
                array(
                    '%d',
                    '%d',
                    '%d'
                )
            );
        }
    }

    /**
     * Post a new product
     *
     * @param $item
     * @return bool|int|WP_Error
     */
    private function post($item) {
        // Get the subscription id
        $subscriptionId = get_option(self::$optionSubsId, 'false');
        if($subscriptionId == 'false')
            return;

        // Make sure that we have an instance of the subscription campaign
        $this->getSubscriptionCampaignInstance($subscriptionId);

        // First we make sure that $this->external is false;
        $external = false;

        // Get the data
        $data = $item->data;
        // Check if we have amazon offers
        if(isset($data->Offers->TotalOffers) && ($data->Offers->TotalOffers === 0)) {
            $asin = $data->ASIN;
            unset($item); // Free some memory
            unset($data);
            $item = new ProssociateItem($asin);
            $data = $item->data;
        }

        // Check if we have to import child products
        // Default: false
        if(!(isset($this->campaign->post_options['postchild']) && ($this->campaign->post_options['postchild'] == 'postchild'))) {
            if(isset($data->ParentASIN) && ($data->ParentASIN != $data->ASIN)) {
                return false;
            }
        }

        // Check if we have offers
        if((!isset($data->Offers) || $data->Offers->TotalOffers === 0) && !isset($data->Variations)) {
            // Get side-wide setting for posting auto affiliate
            $dmAutoAffiliate = get_option('prossociate_settings-dm-auto-affiliate', 'true');
            if($dmAutoAffiliate == 'true')
                $external = true;
            elseif($this->campaign->post_options['externalaffilate'] == 'affiliate') {
                $external = true;
            }
        }

        $finalPrice = 0;
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
        } elseif($data->Offers->TotalOffers === 0 && !isset($data->Variations) && isset($data->VariationSummary)) {
            // if no offers
            if($external === false)
                return false;

            // This is for products without offers and variations listings
            // Example http://www.amazon.com/Sherri-Hill-21002/dp/
            if(isset($data->VariationSummary->LowestPrice)) {
                $finalPrice = $data->VariationSummary->LowestPrice->FormattedPrice;
                $finalAmount = $data->VariationSummary->LowestPrice->Amount;
            }
        } elseif($data->Offers->TotalOffers === 0 && isset($data->Variations)) {
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
                return false;
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
            if($external === false)
                return false;
        }

        // Make MP3Downloads products an Affiliate / External
        if($this->campaign->options['searchindex'] === 'MP3Downloads') {
            $external = true;
        }

        // Make Book product as E / A
        if($this->campaign->options['searchindex'] === 'Books') {
            $external = true;
        }


        if($external === false) {
            // Get the nodepath
            $nodePaths = $this->campaign->options['nodepath'];
            $nodePathsArray = explode(',', $nodePaths);

            // Check if Appstore For Android, Books and Kindle Store
            if(in_array('2350149011', $nodePathsArray) || in_array('283155', $nodePathsArray) || in_array('133140011', $nodePathsArray)) {
                $external = true;
            }
        }


        // If already external ignore this
        if(!$external) {
			if(isset($data->Variations) && $data->Variations->TotalVariations > 0) {

			} else {
				// Post products with too low to display as external
				//if($finalPrice == 'Too low to display' || $finalPrice == '' || $finalPrice === 0) {
                if($finalPrice == 'Too low to display' || $finalPrice == '') {

					// Check option if we will not post products without prices
					if(!isset($this->campaign->post_options['postfree'])) {

						return false;
					}

					// Check if the option to automatically convert single to external is checked
					if(get_option('prossociate_settings-dm-auto-affiliate') == 'true') {
						$external = true;
					} else {

						return false;
					}
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
        // First check if we have maxprice
        if(isset($this->campaign->options['maxprice']) && !empty($this->campaign->options['maxprice'])) {
            // Check if we are not on variation
            if($external || !isset($data->Variations)) {
                // Check if the product price is greater than max price
                if($finalAmount > (int)str_replace(".", "", $this->campaign->options['maxprice'])) {
                    // Do not post
                    return false;
                }
            }
        }

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
                $product_content .= "<b>" . $data->EditorialReviews->EditorialReview->Source . "</b><br />";
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
                    $product_content .= "<b>" . $er->Source . "</b><br />";
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
                <b>Features</b><ul>';
            foreach ($data->ItemAttributes->Feature as $feature) {

                /*$cp_enabled = get_option('prossociate_settings-dm-spin-cp-enable');
                if($cp_enabled == "true"){
                    $response = prosociate_cprof_rewrite($feature);
                    if(!is_array($response))
                    {
                        $feature = $response;
                    }
                }*/

                $product_content .= "<li>" . $feature.'</li>';
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

                $product_content .= "<li>" . $feature.'</li>';
                $product_content .= '</ul></p>';
            }
        }


        $sr_enabled = get_option('prossociate_settings-dm-spin-sr-enable');
        if( (isset($this->campaign->post_options['enablespin']) && ($this->campaign->post_options['enablespin'] == 'enablespin')) || $sr_enabled == "true" ) {
                $response = prosociate_sr_rewrite($product_content);
                if(!is_array($response))
                {
                    $product_content = $response;
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

        if(isset($post_options['ping_status'])) {
            if($post_options['ping_status'] == 'open') {
                $post['ping_status'] = 'open';
            } else {
                $post['ping_status'] = 'closed';
            }
        }

        // Check for availability
        if((isset($data->Offers->TotalOffers) && $data->Offers->TotalOffers > 0) || $external == true) {
            $Availability = true;
        }

        if($Availability || isset($data->Variations)) {
            $post_id = wp_insert_post($post);
            update_post_meta($post_id, '_pros_Available', "yes");

            // Set the stock
            update_post_meta($post_id, '_stock_status', 'instock');
        } else {
            return false;
        }

        // Save last update time
        update_post_meta( $post_id, '_pros_last_update_time', time() );

        // Check if there are variations
        $dmIsVariation = false;
        if (isset($data->VariationSummary) ) {
            $dmIsVariation = true;
        }


        $this->standard_custom_fields($data, $post_id, $dmIsVariation);

        // INSERT FEATURED IMAGES
        if ($post_options['download_images'] == 'on') {
            $this->set_post_images($data, $post_id, $dmIsVariation);
        }

        // Check if there are variations
        if (isset($data->Variations) && $post_options['externalaffilate'] == 'simple') {
            // Product is a variation
            $this->isVariation = true;
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
            if ($post_options['externalaffilate'] == 'affiliate' || $external === true) {
                // Set as external
                //$this->setProductType($post_id, 'external');
                wp_set_post_terms($post_id, 'external', 'product_type', false);
                update_post_meta($post_id, '_dmaffiliate', 'affiliate');
            } else {
                // Set product as simple
                //$this->setProductType($post_id);
            }
        }

        // Auto-generate categories
        if ($post_options['auto_category'] == 'yes') {
            $createdCats = $this->set_categories($data->BrowseNodes, $dmIsVariation);
            // Assign the post on the categories created
            $this->assignCategories($post_id, $createdCats);
        }

        // If users selected categories then put the campaigns on those categories
        if(isset($post_options['dm_select_category'])) {
            if ($post_options['dm_select_category'] == 'yes') {
                $forcedAssignedCats = $this->campaign->options['dmcategories'];

                // Remove the 0 term id
                $removeZeroTermId = array_shift($forcedAssignedCats);
                // Assign the post on the categories created
                $this->assignCategories($post_id, $forcedAssignedCats);
                //wp_set_post_terms($post_id,  $forcedAssignedCats, 'product_cat', false );
            }
        }

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

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
        if(($external || $post_options['externalaffilate'] == 'affiliate') && !empty($finalListPrice) && ($finalListAmount != 0)) {
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
     * Post variations
     *
     * @param object $variation_item
     * @param int $post_id
     * @param $VariationDimensions
     * @return bool
     */
    function variation_post($variation_item, $post_id, $VariationDimensions) {
        // Make sure that we have an instance of the subscription campaign
        $subscriptionId = get_option(self::$optionSubsId, 'false');
        $this->getSubscriptionCampaignInstance($subscriptionId);

        // Get post options
        $post_options = $this->campaign->post_options;

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

        $variation_post = array();
        $variation_post['post_title'] = $vTitle;
        $variation_post['post_author'] = $post_options['author'];
        $variation_post['post_type'] = 'product_variation';
        $variation_post['post_status'] = 'publish';
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
            //$dimension_name = $woocommerce->attribute_taxonomy_name(strtolower($dmName));
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

    /**
     * Get data of an asin from Amazon
     *
     * @param $asin
     * @return ProssociateItem
     */
    private function getData($asin) {
        // Instantiate an item
        $item = new ProssociateItem($asin, 'Amazon');

        $data = $item->data;

        if($data->Offers->TotalOffers === 0) {
            $asin = $data->ASIN;
            unset($item); // Free some memory
            unset($data);
            $item = new ProssociateItem($asin);
        }

        return $item;
    }

    /**
     * Load subscription
     *
     * @param int $subscriptionId
     * @return null|ProsociateSubscription
     */
    private function getSubscriptionCampaignInstance($subscriptionId) {
        // Check if no instance is present
        if($this->campaign === null) {
            // Create new instance of subscription
            $this->campaign = new ProsociateSubscription();
            $this->campaign->load($subscriptionId);
        }

        return $this->campaign;
    }

    private function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
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

    function standard_custom_fields($data, $post_id, $isVariation = false ) {
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

    function set_post_images($data, $post_id, $variation = false ) {
        if(isset($data->ImageSets->ImageSet)) {
            if (count($data->ImageSets->ImageSet) == 1) {

                $i = $data->ImageSets->ImageSet;

                $image_url = $i->LargeImage->URL;

                $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

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

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        // Check if on the first image
                        if ($k == 0) {
                            // Make the first image the featured image
                            if(isset($data->LargeImage->URL)){
                              // fix wrong feature image
                              $attach_id_large = $this->set_post_featured_thumb($data->LargeImage->URL, $data->ItemAttributes->Title, $post_id);
                              set_post_thumbnail($post_id, $attach_id_large);
                            }else{
                              set_post_thumbnail($post_id, $attach_id);
                            }
                        }

                        // Check if we're on variation
                        if( $variation ) {
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

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                            set_post_thumbnail($post_id, $attach_id);
                        }
                    } else {
                        // If Variations->Item is not a n array
                        if($data->Variations->Item) {
                            $i = $data->Variations->Item;

                            // same code
                            $image_url = $i->LargeImage->URL;

                            $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                            set_post_thumbnail($post_id, $attach_id);
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

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        set_post_thumbnail($post_id, $attach_id);
                    }
                } else {
                    // If Variations->Item is not a n array
                    if($data->Variations->Item) {
                        $i = $data->Variations->Item;

                        // same code
                        $image_url = $i->LargeImage->URL;

                        $attach_id = $this->set_post_featured_thumb($image_url, $data->ItemAttributes->Title, $post_id);

                        set_post_thumbnail($post_id, $attach_id);
                    }
                }
            }
        }
    }

    function set_woocommerce_attributes($data, $post_id, $post, $update_operation, $post_options) {
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
                //var_dump($key);
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

    function set_woocommerce_fields($data, $post_id, $isVariation = false) {
        if(isset($data->DetailPageURL)) {
            update_post_meta($post_id, '_product_url', $data->DetailPageURL);
        }

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

    function set_categories($browseNodes, $isVariation = false) {
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
                if( $nodeCounter === 0 )
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
                $cloakLink = get_bloginfo('wpurl') . '?cloaked=' . $cloak->cloakedLink;
                update_post_meta($postId, '_product_url', $cloakLink);
            }
        }
    }

    private function set_post_featured_thumb($image_url, $title, $post_id) {
      $ira = get_option('prossociate_settings-dm-pros-remote-img', 'no');
      $filename = substr(md5($image_url), 0, 12) . "." . pathinfo($image_url, PATHINFO_EXTENSION);
      $file='';
      if(empty($ira) || $ira == 'no'){
        $upload_dir = wp_upload_dir();
        //$image_data = file_get_contents($image_url);
        $dmImage = wp_remote_get($image_url);
        $image_data = wp_remote_retrieve_body($dmImage);

        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents($file, $image_data);
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

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        if(empty($ira) || $ira == 'no'){
          $attach_data = wp_generate_attachment_metadata($attach_id, $file);
          wp_update_attachment_metadata($attach_id, $attach_data);
        }

        return $attach_id;
    }

    /**
     * Update the last cron run time
     *
     * @param int $subscriptionId
     */
    private function updateLastRunTime($subscriptionId) {
        if(!is_numeric($subscriptionId))
            return;

        global $wpdb;
        // table name
        $tableName = $wpdb->prefix . PROSSOCIATE_PREFIX . 'prossubscription';

        $wpdb->update($tableName, array('last_run_time' => time()), array('id' => (int)$subscriptionId), array('%d'));
    }

    /**
     * Reset all the settings to default
     */
    private function resetOptions() {
        update_option(self::$option, 'getSubscriptionId');
        update_option(self::$optionSubsId, 'false');
        update_option(self::$optionSubsPage, '1');
        update_option(self::$optionSubsAsins, array());
        update_option(self::$optionsNewAsins, array());
        update_option(self::$optionsSubsParentAsin, 'false');
        update_option(self::$optionsSubsParentPostId, 'false');
        update_option(self::$optionsSubsVarOffset, 0);
        update_option(self::$optionsSubsIsFromVariation, 'false');
        update_option( 'cron_dm_pros_post_data', array() );
    }
}
