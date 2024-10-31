<?php
class ProssociateItemMultiple {

    var $ASIN, $DetailPageURL, $CustomerReviews;
    var $Images;
    var $data;
    var $isValid = true;
    var $code;

    public function __construct($asin, $merchant = 'All') {

        $this->ASIN = $asin;

        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        $amazonEcs->optionalParameters(array('MerchantId' => $merchant));
        
        if($asin) {
            try {
                $response = $amazonEcs->responseGroup('Large,Variations,OfferFull,VariationOffers,EditorialReview')->lookup($asin);
            } catch(Exception $exception) {
                //echo 'Caught in try/catch';
                $this->isValid = false;
                $this->code = 100; // Means we have memory issue
            }
        }


        if($this->isValid) {            
            $this->data = $response->Items->Item;           
        }

    }

    public function dump() {
        proso_pre_print_r($this->data);
    }

}
