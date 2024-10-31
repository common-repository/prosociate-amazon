<?php
class Prosociate_Geo_Target {
    /**
     * Post ID of the product
     *
     * @var string|null
     */
    private $postId = null;

    /**
     * IP address of the visitor / $_SERVER['REMOTE_ADDR']
     *
     * @var string|null
     */
    private $ip = null;

    /**
     * Appropriate proper url where the user will be redirected
     *
     * @var string|null
     */
    private $productUrl = null;

    /**
     * Appropriate country for the user
     *
     * @var string|null
     */
    private $country = null;

    /**
     * Appropriate associate tag
     *
     * @var string|null
     */
    private $associateTag = null;

    /**
     * Constructor
     *
     * @param int $postId Post ID of the external product
     * @param string $ip IP address of the visitor / $_SERVER['REMOTE_ADDR']
     */
    public function __construct($postId, $ip) {
        // Set properties
        $this->postId = $postId;
        $this->ip = $ip;

        // Get and set the product url
        $this->productUrl = $this->getProductUrl();
    }

    /**
     * Redirect the visitor to the product url
     */
    public function redirect() {
        // Get the offerlisting id
        $offerListingId = get_post_meta($this->postId, '_dmpros_offerid', true);
        //redirect record for product stats
        $redirected = (int) get_post_meta($this->postId, '_pros_redirected', true);
        update_post_meta($this->postId, '_pros_redirected', (int)($redirected+1));                    
        // If we have no offerlisting id, redirect to the product url
        if(empty($offerListingId)) {
            wp_redirect($this->productUrl);

            exit;
        } else {
            // Check if this feature i  s enabled
            $autoCartFeature = get_option('prossociate_settings-dm-pros-autocart-external','true');
            $autoCartFeature = false;
            if($autoCartFeature == 'true') {
                // Try to cart it before redirection
                $url = $this->cartTheProduct($offerListingId);
            } else {
                $url = '';
            }

            // Check if we got a "carted" url
            if($url != '') {
                wp_redirect($url);
                exit;
            } else {
                wp_redirect($this->productUrl);
                exit;
            }
        }
    }

    /**
     * Force the product to be "carted" if possible. If not return empty string
     *
     * @param $offerListingId
     * @return string
     */
    private function cartTheProduct($offerListingId) {
        // generate signed URL
        $request = aws_signed_request($this->country, array(
            'Operation' => 'CartCreate',
            'Item.1.OfferListingId' => $offerListingId,
            'Item.1.Quantity' => 1
        ), AWS_API_KEY, AWS_API_SECRET_KEY, $this->associateTag);


        // Perform request
        $response = wp_remote_retrieve_body(wp_remote_get($request));

        // Convert response to xml
        $xml = simplexml_load_string($response);

        // Get cart url
        if(isset($xml->Cart->PurchaseURL)) {
            $return = $xml->Cart->PurchaseURL;
        } else {
            $return = '';
        }

        return $return;
    }

    /**
     * Get the product url appropriate to the visitors geo-location
     *
     * @return bool|mixed
     */
    public function getProductUrl() {
        // If we already have the product url. Just return it
        if($this->productUrl != null)
            return $this->productUrl;

        // Get the appropriate country for the visitor
        $this->country = $this->getCountry();

        // Get the appropriate associate tag based on the country
        $this->associateTag = $this->getAssociateTag($this->country);

        // Get asin of the product
        $asin = $this->getAsinByProductId();

        // Default url
        $url = $this->getDefaultProductUrl();

        // If we got an asin
        if($asin != false) {
            // Create an instance of Amazon ECS
            $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, $this->country, $this->associateTag);

            // Look for the asin
            $request = $amazonEcs->lookup($asin);

            // Check for the validity of request
            if(isset($request->Items->Item)) {
                // If isset then we can get the new url
                $url = $request->Items->Item->DetailPageURL;
            }
        }

        return $url;
    }

    /**
     * Get the proper country to be used on AWS Class
     *
     * @return string
     */
    private function getCountry() {
        // Get the country abbreviation
        $countryAbbr = $this->getCountryAbbr();

        // Base from the visitors location create instance of search
        switch($countryAbbr) {
            case 'US': // United States
                $country = 'com';
                break;
            case 'GB': // United Kingdom
                $country = 'co.uk';
                break;
            case 'JP': // Japan
                $country = 'co.jp';
                break;
            case 'DE': // Germany
                $country = 'de';
                break;
            case 'FR': // France
                $country = 'fr';
                break;
            case 'CA': // Canada
                $country = 'ca';
                break;
            case 'ES': // Spain
                $country = 'es';
                break;
            case 'IT': // Italy
                $country = 'it';
                break;
            case 'CN': // China
                $country = 'cn';
                break;
            case 'IN':
                $country = 'in';
                break;
            default:
                $country = AWS_COUNTRY;
                break;
        }

        return $country;
    }

    /**
     * Get the abbreviation of the country where the visitor is from.
     *
     * @return string
     */
    private function getCountryAbbr() {
        // By default
        $country = AWS_COUNTRY;

        // Create instance of GeoIpCountry Record
        $geoip = new PMLC_GeoIPCountry_Record();

        // Check if we got an ip
        if(!$geoip->getByIp($this->ip)->isEmpty()) {
            $country = $geoip->country;
        }

        return $country;
    }

    /**
     * Get asin of a product
     *
     * @return bool|mixed
     */
    private function getAsinByProductId() {
        // Get asin
        $asin = get_post_meta((int)$this->postId, '_pros_ASIN', true);

        // Make sure we got the asin
        if(empty($asin))
            return false;

        return $asin;
    }

    /**
     * Get default url of a product
     *
     * @return bool|mixed
     */
    private function getDefaultProductUrl() {
        // Get default url
        $url = get_post_meta($this->postId, '_pros_DetailPageURL', true);

        // Check if we got a url
        if($url == false)
            return false;
        else
            return $url;
    }


    /**
     * Get the appropriate associate tag
     *
     * @param string $country
     * @return string
     */
    private function getAssociateTag($country) {
        // Default associate tag
        $associateTag = AWS_ASSOCIATE_TAG;

        // if the country for the user is the same as the AWS country use the default tag
        if($country != AWS_COUNTRY) {
            // Some countries have different slug / string on the database
            if($country == 'co.uk')
                $dbCountry = 'uk';
            elseif($country == 'co.jp')
                $dbCountry = 'jp';
            else
                $dbCountry = $country;

            // Get the associate tag for the specified country
            $associateTag = get_option('prossociate_settings-associate-id-' . $dbCountry, '');

            // If no associate tag for the specified country. Use the default
            if(empty($associateTag))
                $associateTag = AWS_ASSOCIATE_TAG;
        }

        return $associateTag;
    }
}