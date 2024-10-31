<?php
class Prosociate_Cron {
    /**
     * API key to prevent spam
     * @var int
     */
    private static $apiKey;

    /**
     * Only instance of the object
     *
     * @var null|self
     */
    private static $instance = null;

    /**
     * Number of seconds before update. Commonly 1 day (86400)
     */
    const UPDATE_SECONDS = 86400;
    //const UPDATE_SECONDS = 10;

    function __construct() {
        // notes
        // 1.] No image handling to lessen processing and requests
        // 2.] Both requests and processing should be at minimum
        // 3.] Separate processing for non-variable and variable products might lessen the number of processes.
        add_action('wp_loaded', array($this, 'captureGet'));
    }

    /**
     * Singleton
     *
     * @return null|Prosociate_Cron
     */
    public static function getInstance() {
        if(self::$instance === null)
            self::$instance = new self;

        return self::$instance;
    }

    /**
     * Set the api key
     */
    public function getApi() {
        // Check if we dont have the api key yet
        if(self::$apiKey === null || self::$apiKey === '')
            self::$apiKey = get_option('prossociate_settings-dm-cron-api-key', '');
    }

    /**
     * Capture the cron starter
     */
     public function captureGet() {
         // Get api
         $this->getApi();

         // get file parameter if absolute path cron job used
         $param = @getopt("p:");

         // Check if we have the proper initializers and api key

         if( (isset($_GET['proscron']) && $_GET['proscron'] == self::$apiKey) || (isset($param['p']) && $param['p'] == self::$apiKey) ) {
             update_option('prossociate_settings-hide-cron', 'hide');
             $this->startCron();
         }
     }

    /**
     * Start the cron process
     */
    private function startCron() {
        $products = $this->getProducts();
        // Prevent further actions if no products need to be updated
        if(isset($_GET['mode']) && $_GET['mode'] =='testsub'){
          $products = '';
        }
        if(empty($products)) {
            // If no products needs update. Do the subscription cron
            new ProsociateCronSubscription();
        } else {
            // Get if we need to delete
            $deleteUnavailable = get_option('prossociate_settings-dm-pros-prod-avail', false);
            $deleteUnavailableVar = get_option('prossociate_settings-dm-pros-prod-avail-var', false);

            // Process each product
            foreach($products as $product) {
                // Cache the product ID

                $dmProductId = $product->ID;

                // Get asin of product
                $asin = get_post_meta($product->ID, '_pros_ASIN', true);

                // Get data of the asin
                $data = $this->getData($asin);

                // Check if we got the data
                /*if($data === false || $data == false || $data == '')
                    continue;*/

                // Check for the availability of the product
                $available = $this->checkAvailability($data);

                // If available update the product
                if($available) {
                    // Update product meta
                    $this->updateProduct($product->ID, $data);
                    // Make it have "stock"
                    update_post_meta($product->ID, '_stock_status', 'instock');
                } else {
                    $byPassExternal = false;
                    // Check if the product is external
                    $isExternal = get_post_meta( $product->ID, '_dmaffiliate', true );

                    // If the product is external and it has variationsummary then it has stocks
                    if(!empty($isExternal) && isset($data->VariationSummary))
                        $byPassExternal = true;

                    // Check if variable product
                    if(isset($data->Variations) || $byPassExternal) {

                        // Check for variations without attribute value
                        if($this->checkVariationAttributesValue($data, $product->ID)) {

                            // Update the parent product
                            $this->updateProduct($product->ID, $data, true);

                            if( function_exists('wc_get_product') ){
                                $product = wc_get_product($product->ID);
                                if( $product->is_type( 'external' ) ){

                                } else {
                                    // Check if we have a variation
                                    if( $product->is_type( 'variable' ) ) {
                                        // Product Children
                                        $prodSiteChildren = array();

                                        // Variations
                                        $prodVars = array();

                                        // Get the children
                                        $prodChildren = $product->get_children();

                                        // If we are on variation
                                        // Process variable products here
                                        $items = $this->getVariations($data->Variations);

                                        $item_count = count($items);
                                         if($item_count == 1){
                                           $prodVars[] = $items->ASIN;
                                         }else{
                                          foreach( $items as $item ) {
                                              $prodVars[] = $item->ASIN;
                                          }
                                        }

                                        foreach( $prodChildren as $pc ) {
                                            // Get the SKU
                                            $pcSku = get_post_meta( $pc, '_sku', true );

                                            $prodSiteChildren[] = $pcSku;
                                        }

                                        // Get the product variable that are not available
                                        $notAvailableVar = array_diff($prodSiteChildren, $prodVars);

                                        // Check if we have not available variations in the website
                                        if( !empty($notAvailableVar) ) {

                                            foreach( $notAvailableVar as $var ) {
                                                // Get the not available variation product ID
                                                $notAvailableProductId = $this->getProductBySku( $var );

                                                // Check if we need to delete
                                                if($deleteUnavailable == 'remove' || ( $deleteUnavailableVar == 'remove' && $deleteUnavailable == 'draft') ) {
                                                    // Delete the images
                                                    $this->deleteImages($notAvailableProductId);
                                                    // Delete the product
                                                    $this->deleteProduct($notAvailableProductId);
                                                } elseif($deleteUnavailable == 'change' || ( $deleteUnavailableVar == 'change' && $deleteUnavailable == 'draft') ) {

                                                    // Set the stock to "out of stock"
                                                    update_post_meta( $notAvailableProductId, '_stock_status', 'outofstock' );
                                                    $terms = array();
                                                    $terms[] = 'outofstock';
                                                    $terms[] = 'exclude-from-search';
                                          					$terms[] = 'exclude-from-catalog';
                                                    wp_set_post_terms( $notAvailableProductId, $terms, 'product_visibility', false);
                                                    //$outofstock_term = get_term_by( 'slug', 'outofstock', 'product_visibility');


                                                }
                                            }
                                        }

                                        // Update the variations
                                        $this->updateVariations($items);

                                        if(count($prodSiteChildren) == count($notAvailableVar)){
                                          if($deleteUnavailable == 'remove') {
                                              // Delete the main product
                                              // Delete the images
                                              $this->deleteImages($dmProductId);
                                              // Delete the product
                                              $this->deleteProduct($dmProductId);
                                          } elseif($deleteUnavailable == 'change') {
                                              // Update product meta
                                              $this->updateProduct($dmProductId, $data);
                                              // Make out of stock
                                              update_post_meta($dmProductId, '_stock_status', 'outofstock');
                                              $terms = array();
                                              $terms[] = 'outofstock';
                                              $terms[] = 'exclude-from-search';
                                              $terms[] = 'exclude-from-catalog';
                                              wp_set_post_terms( $dmProductId, $terms, 'product_visibility', false);
                                          } elseif($deleteUnavailable == 'draft') {
                                              // Make as Draft
                                              wp_update_post( array('ID' => $dmProductId, 'post_status' => 'draft') );

                                          }
                                        }
                                    }

                                }
                            }
                            // If variation make it on stock
                            update_post_meta($dmProductId, '_stock_status', 'instock');
                            // Process variable products here
                            $items = $this->getVariations($data->Variations);

                            // Update the variations
                            $this->updateVariations($items);
                        }

                    } else {
                        // Check if we need to delete
                        if($deleteUnavailable == 'remove') {
                            // Check if there are variations and delete it
                            $productVariations = $this->getProductVariations($product->ID);
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
                            $this->deleteImages($product->ID);
                            // Delete the product
                            $this->deleteProduct($product->ID);
                        } elseif($deleteUnavailable == 'change') {
                            // Update product meta
                            $this->updateProduct($product->ID, $data);
                            // Make out of stock
                            update_post_meta($product->ID, '_stock_status', 'outofstock');
                            $terms = array();
                            $terms[] = 'outofstock';
                            $terms[] = 'exclude-from-search';
                            $terms[] = 'exclude-from-catalog';
                            wp_set_post_terms( $product->ID, $terms, 'product_visibility', false);
                        } elseif($deleteUnavailable == 'draft') {
                            // Make as Draft
                            $prodcutID = $product->ID;
                            wp_update_post( array('ID' => $prodcutID, 'post_status' => 'draft') );

                        }
                    }
                }

                // Update the update time
                update_post_meta($dmProductId, '_pros_last_update_time', time());
            }// end of foreach products
        }// end of else
        // remove post if have no children
        $visibility = get_term_by( 'slug', 'outofstock', 'product_visibility' );
        $args = array( 'post_type' => 'product','posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => array(
        array(
        'key' => '_stock_status',
        'value' => 'outofstock',
        'compare' => '='
        )
        )
        /*,
        'tax_query' => array(
        array(
        'taxonomy' => 'product_visibility',
        'field' => 'slug',
        'terms' => array('outofstock')
        )
        )*/
        );
        //echo '-page'.$page.'-';
        //wp_reset_query();
        $loop = new WP_Query( $args );
        while ($loop->have_posts()) {
          $loop->the_post();
          $product_id = get_the_ID();
          $product_obj = wc_get_product($product_id);
          if( is_object($product_obj) && $product_obj->is_type( 'variable' ) ) {
            $children = $product_obj->get_children();
            if( (is_array($children) && count($children) == 0) ||  empty($children)){
            // Delete the images
              $this->deleteImages($product_id);
              // Delete the product
              $this->deleteProduct($product_id);
              echo 'page '.$page.' Variable '.$product_id.' deleted because of empty variations.<br>';
              //continue;
            }
          }
        }
        // Update Pros Cron Job timer
        update_option('prosLastCronTime', time());
    }

    /**
     * Get product by SKU
     *
     * @param $sku
     * @return null|string
     */
    public function getProductBySku( $sku ) {
        global $wpdb;

        $product_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku )
        );

        // If we have product ID
        if ( $product_id )
            return $product_id;

        return null;
    }

    /**
     * Get products that needs to be updated
     * @return array
     */
    public function getProducts() {
        // Get products that are not updated within 24 hours
        $timeLessDay = time() - self::UPDATE_SECONDS;
        if(isset($_GET['mode']) && $_GET['mode'] =='teststd'){
          $timeLessDay = time() - 10;
        }
        $products = new WP_Query(
            array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 200, // Assuming that we can only process less than 100 products per request
                'meta_query' => array(
                    array(
                        'key' => '_pros_last_update_time',
                        'value' => $timeLessDay,
                        'compare' => '<='
                    )
                )
            )
        );

        // Make sure we reset
        wp_reset_postdata();

        return $products->posts;
    }

    /**
     * Get product data from amazon
     * @param $asin
     * @return object|false
     */
    private function getData($asin) {
        // Instantiate the lib
        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        // Include the merchant ID
        $amazonEcs->optionalParameters(array('MerchantId' => 'All'));

        // Try to get the data
        try {
            $response = $amazonEcs->responseGroup('Large,Variations,OfferFull,VariationOffers,EditorialReview')->lookup($asin);
            $data = $response->Items->Item;
        } catch(Exception $exception) {
            $data = false;
        }

        return $data;
    }

    /**
     * Check if variation has attribute values
     * @param $data
     * @param int $post_id
     * @return bool
     */
    private function checkVariationAttributesValue($data, $post_id) {
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
        }

        return $dmValuePresent;
    }

    /**
     * Update the prices of the product
     * @param int $productId
     * @param object $data
     * @param bool $isVariation
     */
    private function updatePrices($productId, $data, $isVariation = false) {

        // Get list price
        $finalListPrice = '';
        $finalListAmount = 0;

        if(isset($data->ItemAttributes->ListPrice->FormattedPrice)) {
            $finalListPrice = $data->ItemAttributes->ListPrice->FormattedPrice;
            $finalListAmount = $data->ItemAttributes->ListPrice->Amount;
        }

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
        } elseif($data->Offers->TotalOffers === 0 && !isset($data->Variations) && isset($data->VariationSummary)) {
            // This is for products without offers and variations listings
            // Example http://www.amazon.com/Sherri-Hill-21002/dp/
            if(isset($data->VariationSummary->LowestPrice)) {
                $finalPrice = $data->VariationSummary->LowestPrice->FormattedPrice;
                $finalAmount = $data->VariationSummary->LowestPrice->Amount;
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

        // If variation
        if($isVariation) {
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

        $isExternal = get_post_meta( $productId, '_dmaffiliate', true );

        if( !empty( $isExternal ) && isset($data->Variations) ) {
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

        // Handle the price
        $finalProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalPrice));

        // Add the offer id and price
        update_post_meta($productId, '_dmpros_offerid', $finalOffer);
        update_post_meta($productId, '_price', $finalProcessedPrice);
        update_post_meta($productId, '_regular_price', $finalProcessedPrice);

        // Handle prices with Too low to display
        if($finalPrice === 'Too low to display') {
            update_post_meta($productId, '_price', '0');
            update_post_meta($productId, '_regular_price', '0');
            update_post_meta($productId, '_filterTooLowPrice', 'true');
        } elseif($finalSaleAmount > 0) {  // Handle the regular / sale price
            update_post_meta($productId, '_regular_price',$finalProcessedPrice);
            update_post_meta($productId, '_sale_price', $finalSalePrice);
            update_post_meta($productId, '_price', $finalSalePrice);
        }

        // For external products, try to insert list price if possible
        if(!empty( $isExternal ) && !empty($finalListPrice) && ($finalListAmount != 0)) {
            // Check if the list price is lower
            $finalListProcessedPrice = $this->reformat_prices($this->remove_currency_symbols($finalListPrice));

            if($finalListAmount > $finalAmount) {
                update_post_meta($productId, '_sale_price', $finalProcessedPrice);
                update_post_meta($productId, '_price', $finalProcessedPrice);
                update_post_meta($productId, '_regular_price',$finalListProcessedPrice);
            } elseif($finalListAmount < $finalAmount) {
                update_post_meta($productId, '_sale_price', $finalListProcessedPrice);
                update_post_meta($productId, '_price', $finalListProcessedPrice);
                update_post_meta($productId, '_regular_price',$finalProcessedPrice);
            }
        }

        // Change the stock to instock
        update_post_meta($productId, '_stock_status', 'instock');

         // fix to update serialized product meta.
        $prodAttr = get_post_meta( $productId, '_product_attributes', true );
        if(is_string($prodAttr)) {
            $tempProdAttr = unserialize($prodAttr);
            update_post_meta($productId, '_product_attributes', $tempProdAttr);
        }

        // Update product description
// ----------------------------------
        // Prepare content


        //$sr_enabled = get_option('prossociate_settings-dm-spin-sr-enable');
        $sr_cron_update = get_option('prossociate_settings-dm-spin-sr-cron-update');
        $sr_cron_spin = get_option('prossociate_settings-dm-spin-sr-cron-spin');

        if( $sr_cron_update == "true" || $sr_cron_spin == "true"){

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
                elseif (count($data->ItemAttributes->Feature) == 1) {
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
                //$sr_cron_spin = get_option('prossociate_settings-dm-spin-sr-cron-spin');
                $sr_cron_spin = "false";
                if($sr_cron_spin == "true"){
                    $response = prosociate_sr_rewrite($product_content);
                    if(!is_array($response))
                    {
                        $product_content = $response;

                    }
                }
                $post_arr = array('ID'=> $productId,'post_content' => $product_content);
                $post_res = wp_update_post($post_arr);
        }
    } // end of description update

    /**
     * Delete all the price metas
     * @param int $productId
     */
    private function deletePrices($productId) {
        delete_post_meta($productId, '_regular_price');
        delete_post_meta($productId, '_sale_price');
        delete_post_meta($productId, '_price');
    }

    /**
     * Reformat the price
     * @param $price
     * @return mixed
     */
    private function reformat_prices($price) {
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

    /**
     * @param $price
     * @return string
     */
    private function reformat_price_de($price) {
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

    /**
     * Remove the currency symbol
     * @param $x
     * @return mixed
     */
    private function remove_currency_symbols($x) {
        $x = preg_replace('/[^0-9-.,]/', '', $x);

        // strip spaces, just in case
        $x = str_replace(" ", "", $x);

        return $x;
    }

    /**
     * Update the fields of the products
     * @param int $productId
     * @param object $data
     */
    private function updateCustomFields($productId, $data) {
        if(isset($data->ItemAttributes)) {
            update_post_meta($productId, '_pros_ItemAttributes', serialize($data->ItemAttributes));
        }
        if(isset($data->Offers)) {
            update_post_meta($productId, '_pros_Offers', serialize($data->Offers));
        }
        if(isset($data->OfferSummary)) {
            update_post_meta($productId, '_pros_OfferSummary', serialize($data->OfferSummary));
        }
        if(isset($data->SimilarProducts)) {
            update_post_meta($productId, '_pros_SimilarProducts', serialize($data->SimilarProducts));
        }
        if(isset($data->Accessories)) {
            update_post_meta($productId, '_pros_Accessories', serialize($data->Accessories));
        }
        if(isset($data->ASIN)) {
            update_post_meta($productId, '_pros_ASIN', $data->ASIN);
            // Add sku
            update_post_meta($productId, '_sku', $data->ASIN);
        }

        if(isset($data->ParentASIN)) {
            update_post_meta($productId, '_pros_ParentASIN', $data->ParentASIN);
        }
        if(isset($data->DetailPageURL)) {
            update_post_meta($productId, '_pros_DetailPageURL', $data->DetailPageURL);
        }
        if(isset($data->CustomerReviews)) {
            update_post_meta($productId, '_pros_CustomerReviews', serialize($data->CustomerReviews));
        }
        if(isset($data->EditorialReviews)) {
            update_post_meta($productId, '_pros_EditorialReviews', serialize($data->EditorialReviews));
        }
        if(isset($data->VariationSummary)) {
            update_post_meta($productId, '_pros_VariationSummary', serialize($data->VariationSummary));
        }
        if(isset($data->Variations->VariationDimensions)) {
            update_post_meta($productId, '_pros_VariationDimensions', serialize($data->Variations->VariationDimensions));
        }

        if(isset($data->DetailPageURL)) {
            // Set the product url
            $this->wpWizardCloakIntegration($productId, $data->DetailPageURL);
        }

        if(isset($data->Variations->TotalVariations)) {
            if ($data->Variations->TotalVariations > 0) {
                if (count($data->Variations->Item) == 1) {
                    update_post_meta($productId, '_pros_FirstVariation', serialize($data->Variations->Item));
                } else {
                    update_post_meta($productId, '_pros_FirstVariation', serialize($data->Variations->Item[0]));
                }
            }
        }
    }

    /**
     * Process all the meta fields of product
     * @param int $productId
     * @param object $data
     * @param bool $isVariation
     */
    private function updateProduct($productId, $data, $isVariation = false) {
        // Delete current prices
        $this->deletePrices($productId);
        // Update the price
        $this->updatePrices($productId, $data, $isVariation);
        // Update the custom fields
        $this->updateCustomFields($productId, $data);
        //update modified date
        $upd_args = array('ID'=> $productId,'post_modified'=> current_time( 'mysql' ),'post_modified_gmt'=> current_time( 'mysql',1 )  );
        wp_update_post($upd_args);
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
        //add_option($productId.'-images-to-delete',serialize((array)$images));
        // Delete images
        foreach( (array) $images as $image) {
          wp_delete_attachment($image->ID, true);
        }
    }

    /**
     * Delete a product
     * @param int $productId
     */
    private function deleteProduct($productId) {
        if(empty($productId)){
          return;
        }
        // Delete
        wp_delete_post($productId, true);
    }

    /**
     * Check if the product is available
     * @param object $data
     * @return bool
     */
    private function checkAvailability($data) {
        $available = false;

        // Check for availability
        if(isset($data->Offers->TotalOffers) && $data->Offers->TotalOffers > 0) {
            $available = true;
        }

        // Check if ebook
        // This is to fix the issue where ebooks are being deleted upon cron update
        if(isset($data->ItemAttributes->ProductGroup) && $data->ItemAttributes->ProductGroup == 'eBooks') {
            $available = true;
        }

        return $available;
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
     * Get the variations from the data
     * @param object $varData
     * @return array
     */
    private function getVariations($varData) {
        // Check if we have variations
        if(isset($varData->Item) && !empty($varData->Item)) {
            return $varData->Item;
        } else {
            return array();
        }
    }

    /**
     * Update the variations of a product
     * @param array $variations
     */
    private function updateVariations($variations) {
        // Check if on array
        if(is_array($variations)) {
            // Loop through all variations
            foreach($variations as $var) {
                // Get the post id of variation
                $postId = $this->getProductByAsin($var->ASIN);
                // Check if we dont any variations
                if($postId === 0) {
                    // TODO create a new variation
                } else {
                    // Check if we need to update the product
                    $update = $this->isLessThanADay($postId);
                    if($update) {
                        // Delete prices
                        $this->deletePrices($postId);
                        // Update prices
                        $this->updatePrices($postId, $var);
                        // Update the last cron time meta
                        update_post_meta($postId, '_pros_last_update_time', time());
                        // Insert sku
                        update_post_meta($postId, '_sku', $var->ASIN);
                    }
                }
            }
        } else {
            // Not array
            // Get the post id of variation
            $postId = $this->getProductByAsin($variations->ASIN);
            // Check if we dont any variations
            if($postId === 0) {
                // TODO create a new variation
            } else {
                // Check if we need to update the product
                $update = $this->isLessThanADay($postId);
                if($update) {
                    // Delete prices
                    $this->deletePrices($postId);
                    // Update prices
                    $this->updatePrices($postId, $variations);
                    // Update the last cron time meta
                    update_post_meta($postId, '_pros_last_update_time', time());
                    // Insert sku
                    update_post_meta($postId, '_sku', $variations->ASIN);
                }
            }
        }
    }

    /**
     * Get post id of a product by asin
     * @param string $asin
     * @return int
     */
    private function getProductByAsin($asin) {
        // By default we dont have any product ID
        $productId = 0;

        // Get product
        $products = get_posts(array(
            'post_status' => array('publish', 'draft'),
            'post_type' => array('product', 'product_variation'),
            'meta_query' => array(
                array(
                    'key' => '_pros_ASIN',
                    'value' => $asin,
                    'compare' => '='
                )
            )
        ));

        // Check if we got any product
        if(!empty($products)) {
            // Only get the first post ID
            $productId = $products[0]->ID;
        }

        return $productId;
    }

    /**
     * Check if we need to update a product by determining it's last update time
     * @param int $postId
     * @return bool
     */
    private function isLessThanADay($postId) {
        // Update by default
        $update = true;

        // Get last update time
        $lastUpdateTime = get_post_meta($postId, '_pros_last_update_time', true);

        // Check if we get last update time
        if(!empty($lastUpdateTime)) {
            // Check if the last update was within 24 hours
            if((int)$lastUpdateTime >= (time() - self::UPDATE_SECONDS)) {
                $update = false;
            }
        }

        return $update;
    }

    /**
     * Get the right term slug
     * @param string $value Taxonomy value e.g 'pa_color', 'pa_size'
     * @param string $key Variation value e.g Black, Green
     * @return string
     */
    private function getVariationMeta($value, $key) {
        global $wpdb, $woocommerce;
        //$metaValue = $woocommerce->attribute_taxonomy_name(strtolower($value));
        $metaValue = wc_attribute_taxonomy_name(strtolower($value));

        $sql = "SELECT {$wpdb->terms}.slug FROM {$wpdb->terms} INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
WHERE {$wpdb->terms}.name =  '{$key}'
AND {$wpdb->term_taxonomy}.taxonomy = '{$metaValue}'";
        $result = $wpdb->get_var($sql);

        // If no result was found
        if($result === null)
            return '';
        else
            return $result;
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
        if (in_array('wpwizardcloak/plugin.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // Save the original url on another meta
            update_post_meta($postId, '_orig_buy_url', $buyUrl);
            // Cloak the link
            $cloak = new Prosociate_WPWizardCloak($postId, $buyUrl);
            // Get the cloaked link
            if(!empty($cloak->cloakedLink)) {
                $cloakLink = get_bloginfo('wpurl') . '?cloaked=' . $cloak->cloakedLink;
                update_post_meta($postId, '_product_url', $cloakLink);
            }
        } else {
            update_post_meta($postId, '_product_url', $buyUrl);
        }
    }
}
Prosociate_Cron::getInstance();
