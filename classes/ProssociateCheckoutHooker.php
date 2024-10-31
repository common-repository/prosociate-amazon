<?php

class ProssociateCheckoutHooker {

    public function __construct() {
        add_action('wp', array($this, 'checkoutRedirectAmazon'));
    }

    /**
     * Redirect the checkout to Amazon
     */
    public function checkoutRedirectAmazon() {

        global $post;
        global $woocommerce;

        if (isset($post->ID) && !is_admin()  && $post->ID == woocommerce_get_page_id('checkout')) {

            if(isset($woocommerce->cart->cart_contents) && count($woocommerce->cart->cart_contents) > 0) {
                
                $cartcount = 0;
                $public_key = AWS_API_KEY;
                $private_key = AWS_API_SECRET_KEY;
                $associate_tag = AWS_ASSOCIATE_TAG;

                foreach ($woocommerce->cart->cart_contents as $item) {
                    // yuri - get product data
                    $prod_post_id = $item['product_id'];

                    if ($item['variation_id']) {
                        $prod_post_id = $item['variation_id'];
                        /*$redirected = (int) get_post_meta($item['variation_id'], '_pros_redirected', true);
                        update_post_meta($item['variation_id'], '_pros_redirected', (int)($redirected+1));*/
                    }

                    $quantity = $item['quantity'];
                    $ASIN = get_post_meta($prod_post_id, '_pros_ASIN', true);
                    $_tmp = get_post_meta($prod_post_id, '_pros_Offers');
                    $Offers = unserialize($_tmp[0]);
                    $_tmp = get_post_meta($prod_post_id, '_pros_OfferSummary');
                    $OfferSummary = unserialize($_tmp[0]);

                    //redirect record for product stats
                    $redirected = (int) get_post_meta($prod_post_id, '_pros_redirected', true);
                    update_post_meta($prod_post_id, '_pros_redirected', (int)($redirected+1));                    

                    // Check if the product isn't Prosociate
                    if(empty($ASIN))
                        return;

                    // Check if Offers are available
                    if (isset($Offers->Offer)) {
                        // For array
                        if (is_array($Offers->Offer)) {
                            // Loop through all the Offers
                            // This is to make sure that we will get what we need
                            foreach ($Offers->Offer as $dm) {
                                // If offerlisting is not given
                                if (!isset($dm->OfferListing))
                                    continue;
                                // If Offerlistingid isn't given
                                if (!isset($dm->OfferListing->OfferListingId)) {
                                    continue;
                                } else {
                                    // If Offerlisting is given
                                    $dmOffer = $dm->OfferListing->OfferListingId;
                                    break;
                                }
                            } // end foreach
                        } // end if array
                        else {
                            $dmOffer = $Offers->Offer->OfferListing->OfferListingId;
                        }
                    } // end if isset($Offers->Offer)

                    $OID = $dmOffer;


                    // Get official offer idcxc
                    $OID = get_post_meta($prod_post_id, 'dmpros_offerid', true);

                    if(empty($OID) || $OID == false) {
                        $OID = get_post_meta($prod_post_id, '_dmpros_offerid', true);
                    }

                    if (empty($OID) || $OID == false) {
                        continue;
                    }

                    if ($cartcount == 0) {
                        // generate signed URL
                        $request = aws_signed_request(AWS_COUNTRY, array(
                            'Operation' => 'CartCreate',
                            'Item.1.OfferListingId' => $OID,
                            'Item.1.Quantity' => $quantity
                        ), $public_key, $private_key, $associate_tag);


                        // Bypass allow_furl_open = 0 issue with other hosts
                        $response = wp_remote_retrieve_body(wp_remote_get($request));

                        $pxml = simplexml_load_string($response);
                        // pre_print_r($pxml);

                        $HMAC = $pxml->Cart->HMAC;
                        $CartId = $pxml->Cart->CartId;
                    } else {

                        $request = aws_signed_request(AWS_COUNTRY, array(
                            'Operation' => 'CartAdd',
                            'HMAC' => $HMAC,
                            'CartId' => $CartId,
                            'Item.1.OfferListingId' => $OID,
                            'Item.1.Quantity' => $quantity
                        ), $public_key, $private_key, $associate_tag);

                        // do request (you could also use curl etc.)
                        $response = wp_remote_retrieve_body(wp_remote_get($request));

                        $pxml = simplexml_load_string($response);
                        // pre_print_r($pxml);

                        $HMAC = $pxml->Cart->HMAC;
                        $CartId = $pxml->Cart->CartId;
                    }

                    $cartcount++;
                }

                // yuri - check for exceptions
                if ($cartcount == 0) {
                    echo "<h2>One or more of the products in the shopping cart are unable to be added to the Amazon cart. If you are the site admin it is recommended you re-post these products as “External/Affiliate” instead of “Simple/Variable</h2>";
                    echo "<br />";
                    echo "<a href='" . get_option('siteurl') . "/?page_id=" . woocommerce_get_page_id('cart') . "'>Back to Cart</a>";
                } else {
                    //var_dump($pxml->Cart);
                    //var_dump($pxml);
                    if (!isset($pxml->Cart->PurchaseURL)) {
                        echo "<h2>One or more of the products in the shopping cart are unable to be added to the Amazon cart. If you are the site admin it is recommended you re-post these products as “External/Affiliate” instead of “Simple/Variable</h2>";
                        echo "<br />";
                        echo "<a href='" . get_option('siteurl') . "/?page_id=" . woocommerce_get_page_id('cart') . "'>Back to Cart</a>";
                    } else {
                        // Check if we need to redirect
                        if(get_option('prossociate_settings-dm-pros-redirection', false) === 'true') {
                            include_once(PROSSOCIATE_ROOT_DIR . '/views/display/redirect.php');
                            die();
                        } else {
                            header("Location: " . $pxml->Cart->PurchaseURL);
                        }
                    }
                    return;
                }
            } else {
                return;
            }
        } else {
            return;
        }
    }

}