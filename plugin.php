<?php
/*
  Plugin Name: Prosociate Lite
  Plugin URI: http://www.prosociate.com/
  Description: The best WordPress plugin for Amazon Associates.
  Version: 3.0.0.2
  Author: Prosociate
 */
//error_reporting(0);
//error_reporting(E_ALL);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
// Prevent direct access
if (!function_exists('add_action')) {
    die('Im just a plugin and can\'t do anything alone');
}

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_PROS_STORE_URL', 'http://www.prosociate.com/' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

// the name of your product. This should match the download name in EDD exactly
define( 'EDD_PROS_ITEM_NAME', 'Prosociate' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file


define('PROSSOCIATE_ROOT_DIR', str_replace('\\', '/', dirname(__FILE__)));
define('PROSSOCIATE_ROOT_URL', rtrim(plugin_dir_url(__FILE__), '/'));
define('PROSSOCIATE_PREFIX', 'pros_');
define('AWS_API_KEY', get_option('prossociate_settings-aws-public-key'));
define('AWS_API_SECRET_KEY', get_option('prossociate_settings-aws-secret-key'));
define('AWS_ASSOCIATE_TAG', get_option('prossociate_settings-associate-id'));
define('AWS_COUNTRY', get_option('prossociate_settings-associate-program-country'));

define('PMLC_PREFIX', 'pmlc_');

// ------- amazon sort order translations
$proso_sort_order['relevancerank'] = 'Relevance';
$proso_sort_order['salesrank'] = 'Best Selling';
$proso_sort_order['pricerank'] = 'Price: low to high';
$proso_sort_order['inverseprice'] = 'Price: high to low';
$proso_sort_order['-launch-date'] = 'Newest arrivals';
$proso_sort_order['sale-flag'] = 'On Sale';

$proso_sort_order['price'] = 'Price: low to high';
$proso_sort_order['-price'] = 'Price: high to low';

$proso_sort_order['reviewrank'] = 'Average customer review: high to low';

$proso_sort_order['pmrank'] = 'Featured Items';
$proso_sort_order['psrank'] = 'Projected Sales';

$proso_sort_order['inverse-pricerank'] = 'Price: high to low';

$proso_sort_order['titlerank'] = 'Alphabetical: A to Z';
$proso_sort_order['-titlerank'] = 'Alphabetical: Z to A';

$proso_sort_order['daterank'] = 'Newest published';

if (!defined('PROSOCIATE_INSTALLED')) {
    define('PROSOCIATE_INSTALLED', '1.0.1');
}

require_once( ABSPATH . DIRECTORY_SEPARATOR . 'wp-admin' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'meta-boxes.php' );
require "libraries/AmazonECS.class.php";
require "libraries/aws_signed_request.php";
include "framework/framework-load.php";
include "classes/ProssociateSearch.php";
include "classes/ProssociateCampaign.php";
include "classes/ProssociateCampaignController.php";
include "classes/ProssociateItem.php";
include "classes/ProssociatePoster.php";
include "classes/ProssociateDisplay.php";
include "classes/ProssociateCheckoutHooker.php";
include "classes/ProsociateSubscription.php";
include "cron/ProsociateCron.php";
include "external/wpwizardcloak.php";
require_once("cron/ProsociateCronSubscription.php");
require_once("classes/ProsociateGeoTarget.php");

require "modules/product_stats/ProductStats.php";
require "modules/product_import/ProductsImport.php";

if(!function_exists('pros_spinner_create_meta_box')){
    require_once "modules/text_spinning/wp-auto-spinner.php";
}
// Check if WP Dynamic Links isn't installed
if(!class_exists('PMLC_GeoIPCountry_Record')) {
    require_once("geotarget/pmlc_model.php");
    require_once("geotarget/pmlc_model_record.php");
    require_once("geotarget/record.php");
}

//text spinning library
require "libraries/rewriter_request.php";

include "classes/ProssociateItemMultiple.php";

//require "wp-updates-plugin.php";


// Require updater
//require_once('wp-updates-plugin.php');
//new WPUpdatesPluginUpdater_194( 'http://wp-updates.com/api/2/plugin', plugin_basename(__FILE__));

// utility
if (!function_exists('proso_pre_print_r')) {
    function proso_pre_print_r($x) {
        echo "<pre>";
        print_r($x);
        echo "</pre>";
    }
}

class Prossociate {
    /**
     * The Campaign Controller. Only load if we are on a campaign-related admin page. And needs to be setup before upon initialization
     *
     * Source File: /classes/ProssociateCampaignController.php
     * @var object  ProssociateCampaignController
     */
    public $PCC;

    /**
     * Only load the display at the frontend. Also contains the shortcode [prossociate]
     *
     * Source File: /classes/ProssociateDisplay.php
     * @var object ProssociateDisplay
     */
    public $Display;

    /**
     * The poster. It does ajax iterative requests to bypass the php max execution time
     *
     * Source File: /classes/ProssociateDisplay.php
     * @var object ProssociateDisplay
     */
    public $Poster;

    /**
     * Responsible for making the purchase to AmazonECS
     *
     * Source File: /classes/ProssociateCheckoutHooker.php
     * @var object ProssociateCheckoutHooker
     */
    public $CheckoutHooker;

    /**
     * The Productstats. It displays products stats     *
     * Source File: /modules/products_stats/ProductStats.php
     * @var object Productstats
     */
    public $Productstats;

    /**
     * The Productstats. It displays products import     *
     * Source File: /modules/products_import/ProductsImport.php
     * @var object ProductsImport
     */
    public $ProductImport;

    /**
     * Instance container
     * @var object  Prossociate
     */
    protected static $instance;

    public static $amazon_images_path = '.images-amazon.';
    /**
     * Construct
     */
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activation'));
        register_deactivation_hook(__FILE__, array($this, 'deactivation'));

        // Instantiate the objects (TODO delete the comments)
        $this->PCC = new ProssociateCampaignController;
        $this->Display = new ProssociateDisplay;
        $this->Poster = new ProssociatePoster;
        $this->CheckoutHooker = new ProssociateCheckoutHooker;
        $this->Productstats = new ProsociateStatsProd;
        $this->ProductImport = new ProsociateProductImport;

        add_action('admin_init', array($this, 'addSettings'));

        add_action('admin_menu', array($this, 'admin_menu'), 10);
        add_action('admin_notices', array($this, 'notifications'));
        add_action('admin_notices', array($this, 'first_time_notice'));

        // force user to add amazon access key first
        add_action('admin_init', array($this, 'settings_redirect'));
        add_action('admin_notices', array($this, 'amazon_keys_required'));

        add_filter('woocommerce_product_tabs', array($this, 'reviewTabs'), 40);
		    add_action('init', array($this, 'woocommercePrice'));

        // Style for amazon disclaimer
        add_action('wp_head', array($this, 'addAmazonDisclaimerStyle'));

        // JS script for settings page
        add_action('admin_print_scripts-prosociate_page_prossociate_settings', array($this, 'addJsSettingsPage'));

        // Custom metabox
        add_action('add_meta_boxes', array($this, 'prosociate_wc_meta_box'));
        add_action('save_post', array($this, 'prosociate_wc_meta_box_save'));

        // Add checking for geo-target
        add_action('wp_loaded', array($this, 'geoTargetCatcher'));

        // Cron notice
        add_action('admin_notices', array($this, 'noticeCron'));

        // Add hide notice action
        add_action('wp_loaded', array($this, 'hideNoticeCron'));

        // Activate woocommerce programatically
        add_action('wp_loaded', array($this, 'activateWoocommerce'));

        // Woocommerce external.php template filter
        add_filter('wc_get_template', array($this, 'woocommerceExternalButtonFilter'), 20, 2);

        add_filter('woocommerce_product_add_to_cart_url', array($this, 'woocommerceExternalButtonFilterLoop'), 20, 2);

        //display remote images
        add_filter( "wp_get_attachment_url", array($this, '_attachment_url'), 0, 2);

        //fix spin text error
        add_filter( 'the_content', array($this, 'html_fix'), 0);

    }

    /**
     * Activate wooCommerce
     */
    public function activateWoocommerce() {
        // Check if we will activate woocommerce
        if(isset($_GET['proswoocommerceactivate'])) {
            // Check if woocommece is not yet installed
            if(!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                // Require the plugin.php
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');

                // Activate woocommerce
                activate_plugin('woocommerce/woocommerce.php');

                // Redirect to /wp-admin
                wp_redirect(admin_url());
                exit;
            }
        } elseif((isset($_GET['page']) && $_GET['page'] == 'wc-about') && (isset($_GET['wc-installed']) && $_GET['wc-installed'] == 'true')) {
            // Redirect to Prosociate Final Setup page
            wp_redirect(admin_url('admin.php?page=prossociate_settings&message=2'));
            exit;
        }
    }

    /**
     * Hide notice
     */
    public function hideNoticeCron() {
        if(!is_admin())
            return;

        // Check if we got the notice
        if(isset($_GET['proscron_notice']) && $_GET['proscron_notice'] == 'hide') {
            update_option('prossociate_settings-hide-cron', 'hide');
        } elseif(isset($_GET['proscron_notice']) && $_GET['proscron_notice'] == 'perm_hide') {
            update_option('prossociate_settings-hide-cron', 'perm_hide');
        }
    }

    /**
     * Display cron notice
     */
    public function noticeCron() {
        // Get cron instance
        $cron = Prosociate_Cron::getInstance();
        // Get products that needs update
        $products = $cron->getProducts();
        // Check if cron was hidden
        $cronHidden = get_option('prossociate_settings-hide-cron', '');

        $cronHide = false;
        // Check if cron is hidden or on hide cron notice
        if($cronHidden == 'hide' || $cronHidden == 'perm_hide') {
            $cronHide = true;
        }
        if(isset($_GET['proscron_notice']) && ($_GET['proscron_notice'] == 'hide' || $_GET['proscron_notice'] == 'perm_hide')) {
            // Check if the "Hide notice" link was clicked
            $cronHide = true;
        }
        if(AWS_API_KEY == false) {
            // If the settings wasn't yet saved
            $cronHide = true;
        }

        // Check if we have products that needs updating
        if(!$cronHide) { ?>
            <div class="error">
                <p>
                    <a href="<?php echo admin_url('admin.php?page=cron_notice_page'); ?>">Configure the cron job</a> to ensure all your product data is kept up to date and new products are posted for subscription campaigns.<br/>
                    <a href="<?php echo admin_url('?proscron_notice=hide'); ?>">Hide Notice</a>
                </p>
            </div>
        <?php }
    }

    /**
     * Catch all Prosociate external product
     */
    public function geoTargetCatcher() {
        // Make sure we are on front end
        if(is_admin()) {
            return;
        }

        if(isset($_GET['product']) ) {
            $num_of_vars =  count($_GET);
            $ASIN = get_post_meta($_GET['product'], '_pros_ASIN', true);
         //die(var_dump($_GET));
            if(empty($ASIN)){
                return;
            }
            if(!is_numeric($_GET['product'])){
                return;
            }
            if($num_of_vars >1 ){
                return;
            }

            // Get the post id
            $postId = (int)$_GET['product'];

            //$ip = "195.81.186.116";
            $ip = $_SERVER['REMOTE_ADDR'];

            // Create new Geo Target instance
            $geo = new Prosociate_Geo_Target($postId, $ip);
            // Get url
            $url = $geo->getProductUrl();
            // Redirect to proper destination
            $geo->redirect();
        }
    }

    /**
     * Add the meta box on products
     */
    public function prosociate_wc_meta_box() {
        add_meta_box('prosociate_meta_box',
            'Prosociate',
            array($this, 'pros_product_section_inner_custom_box'),
            'product', 'normal', 'high'
        );
    }

    /**
     * Callback for the custom meta box
     * @param $post
     */
    public function pros_product_section_inner_custom_box( $post ) {

        // Add an nonce field so we can check for it later.
        wp_nonce_field( 'pros_product_section_inner_custom_box', 'pros_product_section_inner_custom_box_nonce' );

        /*
         * Use get_post_meta() to retrieve an existing value
         * from the database and use the value for the form.
         */
        $value = get_post_meta( $post->ID, '_pros_alt_prod_desc', true );

        $placeHolder = '';
        // If no value
        if(!$value || empty($value)) {
            $placeHolder = 'placeholder="Enter your description here... HTML is allowed" ';
            $value = '';
        }

        echo '<p><label for="myplugin_new_field">';
        echo 'Override the Product Description (useful for SEO)';
        echo '</label><br />';
        echo '<textarea '. $placeHolder .'style="width: 100%" rows="6" id="myplugin_new_field" name="pros_alt_prod_desc">' . esc_attr( $value ) . '</textarea></p>';
        echo '<p>Product ASIN: <strong>' . get_post_meta($post->ID, '_pros_ASIN', true) . '</strong></p>';
    }

    /**
     * Save the custom meta box
     * @param $post_id
     * @return mixed
     */
    public function prosociate_wc_meta_box_save( $post_id ) {
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if ( ! isset( $_POST['pros_product_section_inner_custom_box_nonce'] ) )
            return $post_id;

        $nonce = $_POST['pros_product_section_inner_custom_box_nonce'];

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $nonce, 'pros_product_section_inner_custom_box' ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check the user's permissions.
        if ( 'page' == $_POST['post_type'] ) {

            if ( ! current_user_can( 'edit_page', $post_id ) )
                return $post_id;

        } else {

            if ( ! current_user_can( 'edit_post', $post_id ) )
                return $post_id;
        }

        /* OK, its safe for us to save the data now. */

        // Sanitize user input.
        $mydata = htmlentities($_POST['pros_alt_prod_desc'], ENT_QUOTES, "UTF-8");

        if($mydata === 'Enter your description here... HTML is allowed')
            $mydata = '';

        // Update the meta field in the database.
        update_post_meta( $post_id, '_pros_alt_prod_desc', $mydata );
    }

	public function woocommercePrice() {
		add_filter('woocommerce_get_price_html', array($this, 'filterPrice'));
	}

	public function filterPrice($price) {
		if(is_admin())
			return $price;

        // Get post object
        global $post;

        // Check if it's a prosociate product
        if(get_post_meta($post->ID, '_pros_ASIN', true) == '')
            return $price;

        // Get the settings
        $displayByTime = get_option('prossociate_settings-pros-dis-display-time', 'true');
        $displayByLocation = get_option('prossociate_settings-pros-dis-display-individual', 'true');
        $lastUpdateTime = get_post_meta($post->ID, '_pros_last_update_time', true);

        if($displayByLocation == 'true') {
            // Only do the filter if we're on the individual product page
            if(is_single()) {
                $price = $this->filterPriceByTime($price, $displayByTime, $lastUpdateTime);
            }
        } else {
            // Only do the filtration by time
            $price = $this->filterPriceByTime($price, $displayByTime, $lastUpdateTime);
        }

        // If we need to display the disclaimer regarding of the refreshed time
        return $price;
	}

    /**
     * Do filtration of price if product is not refreshed within 24 hours.
     * @param $price
     * @param $displayByTime
     * @param $lastUpdateTime
     * @return string
     */
    private function filterPriceByTime($price, $displayByTime, $lastUpdateTime) {
        if($displayByTime == 'false') {
            if((int)$lastUpdateTime <= (time() - 86400)) {
                // Product was not updated within 24 hours
                $newPrice = $this->alterPrice($price, $lastUpdateTime);
            }
            else {
                // if product was updated within the last 24 hours. Still display the price.
                $newPrice = $price;
            }
        } else {
            // Display regardless
            $newPrice = $this->alterPrice($price, $lastUpdateTime);
        }

        return $newPrice;
    }

    /**
     * Changes to be done on the price
     * @param $price
     * @param $lastUpdateTime
     * @return string
     */
    private function alterPrice($price, $lastUpdateTime) {
        global $post;
        // Get date format
        $dateDisplay = get_option('prossociate_settings-pros-date-format', 'true');
        // Set default
        if($dateDisplay == false || empty($dateDisplay))
            $dateDisplay = '(as of %%M%%/%%D%%/%%Y%% at %%TIME%% UTC)';

        // Get date
        $date = date('m/d/Y', $lastUpdateTime);
        // Convert to array
        $arrDate = explode('/', $date);
        // Get parts
        $month = $arrDate[0];
        $day = $arrDate[1];
        $year = $arrDate[2];
        // Get time
        $time = date('H:i', $lastUpdateTime);
        // Convert the $dateDisplay
        $str2 = str_replace(
            array('%%DATE%%', '%%TIME%%', '%%M%%', '%%D%%', '%%Y%%'),
            array($date, $time, $month, $day, $year),
            $dateDisplay
        );

        $tooLowToDisplay = get_post_meta($post->ID, '_price', true);

        if($tooLowToDisplay == '' || $tooLowToDisplay == '0')
            $price = 'Too low to display';

        $price .= "<div class='prosamazondis'>{$str2}</div>";

        return $price;
    }

	public function reviewTabs($tabs) {
        if(isset($tabs['reviews'])) {
            $tabs['reviews']['title'] = "Reviews";
        }

        return $tabs;
	}

    /**
     * Check if the instance is already created. If not, create an instance
     */
    static public function getInstance() {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function check_amazon_notice() {
        if(isset($_GET['page'])) {
            $page = $_GET['page'];
        } else {
            $page ='';
        }

        if(isset($_GET['settings-updated'])) {
            $settingsUpdated = $_GET['settings-updated'];
        } else {
            $settingsUpdated = '';
        }

        if ($page == 'prossociate_settings' && $settingsUpdated == 'true') {
            // Display notice that the settings are saved
            $this->settings_saved_notice();

            $message = $this->check_amazon();

            if(stristr( $message, 'Prosociate was able to connect')){
                $this->check_amazon_success($message);
                update_option('pros_valid_amazon_keys', 'valid');
            }
            else{
              $this->check_amazon_fail($message);
              // Update option
              update_option('pros_valid_amazon_keys', 'valid');
            }
        }
    }

    /**
     * Display notice that the settings are saved.
     */
    public function settings_saved_notice() { ?>
        <div class="updated">
            <p>Your settings were successfully saved.</p>
        </div>
    <?php }

    public function check_amazon_fail($message) {
        ?>
        <div class="error">
            <p><?php echo $message;?></p>
        </div>
    <?php }

    public function check_amazon_success($message) {
        ?>
        <div id="connected" class="updated">
            <?php
                // Check if it's first time
                if(isset($_GET['message']) && $_GET['message'] == '2') {
                    $message1 = "Prosociate was able to successfully connect to Amazon. <a href='" . admin_url('admin.php?page=prosociate/plugin.php') . "'>Click here to get started</a>.";
                } else {
                    $message1 = "Prosociate was able to connect to Amazon with the specified AWS Key Pair and Associate ID.";
                }
            ?>
            <p><?php echo $message; ?></p>
        </div>
    <?php
    }

    private function check_amazon() {
        $message = 'Prosociate was not able to connect to Amazon with the specified AWS Key Pair and Associate ID. Please triple-check your AWS Keys and Associate ID.';

        // Get the keys
        $awsApiKey = get_option('prossociate_settings-aws-public-key');
        $awsApiSecret = get_option('prossociate_settings-aws-secret-key');
        $awsCountry = get_option('prossociate_settings-associate-program-country');
        $awsAssociateTag = get_option('prossociate_settings-associate-id');

        // Try
        try {
            // Do a test connection
            $tryConnect = new AmazonECS($awsApiKey, $awsApiSecret, $awsCountry, $awsAssociateTag);
            $tryConnect->responseGroup('Small');
            $tryConnect->category('Apparel');
            $tryResponse = $tryConnect->search('*', 1036592);



            // Check if we don't have valid request, e.g AWS keys aren't associated with Amazon Associates Program
            if(isset($tryResponse->Items->Request->IsValid) && ($tryResponse->Items->Request->IsValid === 'True')) {
              $message = 'Prosociate was able to connect to Amazon with the specified AWS Key Pair and Associate ID.';
            }
            elseif(isset($tryResponse->Items->Request->IsValid) && ($tryResponse->Items->Request->IsValid === 'False')){
              if(isset($tryResponse->Items->Request->Errors)){
                $message = $tryResponse->Items->Request->Errors->Error->Message;
              }
            }
            elseif($tryResponse->getMessage()){
              $message = $tryResponse->getMessage();
            }
        } catch (Exception $e) {
            $message = 'Some exception has occured.';
        }
        return $message;
    }

    /**
     * Redirect user to the settings page if the amazon access isn't populated
     */
    public function settings_redirect() {
        $redirect = TRUE;

        // Check if all amazon access is given
        if (!( AWS_API_KEY == '' || AWS_API_KEY == NULL || AWS_API_KEY == FALSE )) {
            if (!( AWS_API_SECRET_KEY == '' || AWS_API_SECRET_KEY == NULL || AWS_API_SECRET_KEY == FALSE )) {
                if (!( AWS_ASSOCIATE_TAG == '' || AWS_ASSOCIATE_TAG == NULL || AWS_ASSOCIATE_TAG == FALSE )) {
                    $redirect = FALSE;
                }
            }
        }

        if (get_option('pros_valid_amazon_keys', 'invalid') == 'invalid') {
            $redirect = TRUE;
        }

        // Check if we need to redirect
        if ($redirect) {
            if(isset($_GET['page']))
                $page = $_GET['page'];
            else
                $page = '';

            if ($page == 'prossociate_addedit_campaign' || $page == 'prossociate_manage_campaigns' || $page == 'pros-subscription' || $page == 'pros-manage-subscription' || $page == 'prossociate_post_products') {
                // Admin url
                $admin_url = admin_url('admin.php');

                // The settings url
                $url = add_query_arg(array(
                    'page' => 'prossociate_settings',
                    'message' => 1
                        ), $admin_url);

                wp_redirect($url);
                exit();
            }
        }
    }

    public function amazon_keys_required() {
        if(isset($_GET['page'])) {
            $page = $_GET['page'];
        } else {
            $page = '';
        }

        if(isset($_GET['message'])) {
            $message = $_GET['message'];
        } else {
            $message = '';
        }

        if(isset($_GET['settings-updated'])) {
            $settingsUpdated = $_GET['settings-updated'];
        } else {
            $settingsUpdated = '';
        }

        if ($page == 'prossociate_settings' && $message == 1 && $settingsUpdated != 'true') {
            ?>
            <div class="error">
                <p>Please enter in your Associate ID, AWS Access Key ID, and AWS Secret Access Key</p>
            </div>
        <?php
        }
    }

    /**
     * Display an admin notice upon plugin activation
     */
    public function first_time_notice() {
        // Check if the plugin was installed before
        if (!get_option('prosociate_installed')) {
            add_option('prosociate_installed', 'PROSOCIATE_INSTALLED');
            // Set the default
            add_option('prossociate_settings-iframe-width', 600);
            add_option('prossociate_settings-iframe-height', 600);
            add_option('prossociate_settings-iframe-position', 'comment_form');
			add_option('prossociate_settings-title-word-length', 9999);
            add_option('prossociate_settings-title-word-length', '');
            add_option('prossociate_settings-dm-cron-api-key', '');
            ?>
            <div class="updated">
                <p>Thanks for installing Prosociate.</p>
            </div>
        <?php
        }
    }

    /**
     * Notify the user if woocommerce is an active plugin
     */
    public function notifications() {
        // Check if "Woocommerce" is installed (but not activated)
        if(!file_exists(WP_CONTENT_DIR . "/plugins/woocommerce")) {
            echo '<div id="message" class="error prosociate_admin_msg"><p><strong>Prosociate</strong> - you must <a href="' . admin_url("plugin-install.php?tab=plugin-information&amp;plugin=woocommerce&amp;TB_iframe=true&amp;width=600&amp;height=550") . '" class="thickbox">install WooCommerce</a> to use Prosociate.</p><p>If this message appears after installing WooCommerce, you must Activate it from the Plugins screen.</p></div>';
        } elseif (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            echo '<div id="message" class="error prosociate_admin_msg"><p><strong>Prosociate</strong> - you must <a href="' . admin_url("?proswoocommerceactivate=1") . '">Activate WooCommerce</a> to use Prosociate.</p></div>';
        } elseif (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // Woocommerce is installed
            // Check if Woocommerce pages needs to be installed
            if(get_option('_wc_needs_pages') == 1) {
                // Check if we need to display a notice to install pages
                echo '<div id="message" class="error prosociate_admin_msg"><p><strong>Prosociate</strong> - you must click "Install Woocommerce Pages".</p></div>';
            }
        }
    }

    /**
     * the homepage
     */
    public function home() {
        include PROSSOCIATE_ROOT_DIR . "/views/home/home.php";
    }

    /**
     * Display cron notice page
     */
    public function cronNoticePage() {
        include_once(PROSSOCIATE_ROOT_DIR . '/views/home/cronNotice.php');
    }

    /**
     * Build the admin menu
     */
    public function admin_menu() {
        // Admin Parent Menu
        add_menu_page('Prosociate', 'Prosociate', 'manage_options', __FILE__, array($this, 'home'), PROSSOCIATE_ROOT_URL . "/images/favicon.png");

        // Subpages
        add_submenu_page(__FILE__, 'Add Products', 'Home', 'manage_options', __FILE__, array($this, 'home'));
        //add_submenu_page(__FILE__, 'Post Products', 'Post Products', 'manage_options', 'prossociate_post_products', array($this->PCC, 'post_products'));
        add_submenu_page(__FILE__, 'New Campaign', 'New Campaign', 'manage_options', 'prossociate_addedit_campaign', array($this->PCC, 'addedit'));
        add_submenu_page(__FILE__, 'Manage Campaigns', 'Manage Campaigns', 'manage_options', 'prossociate_manage_campaigns', array($this->PCC, 'manage_campaigns'));
        //add_submenu_page(null, 'Add Subscription', 'Add Subscription', 'manage_options', 'pros-subscription', array($this->PCC, 'addedit'));
        //add_submenu_page(__FILE__, 'Manage Subscriptions', 'Mg. Subscriptions', 'manage_options', 'pros-manage-subscription', array($this->PCC, 'manage_campaigns'));
        //add_submenu_page(__FILE__, 'Products Stats','Products Stats','manage_options', 'pros-products-stats',array($this->Productstats, 'display_productstats'));
        //add_submenu_page(__FILE__, 'Products Import','Products Import','manage_options', 'pros-products-import',array($this->ProductImport, 'display_product_import'));
        //add_submenu_page(__FILE__, 'Plugin License','Plugin License','manage_options', 'pros-license','edd_pros_license_page');

        //$synonymsSlug=add_submenu_page( __FILE__,  'Synonyms','Thesaurus', 'manage_options', 'pros_spinner', 'pros_spinner_synonyms_fn' );
        //add_action('admin_head-'.$synonymsSlug, 'pros_spin_thesaurus_style');
        //$synonymsSlug=add_submenu_page( __FILE__, 'Thesaurus', 'My Thesaurus', 'manage_options', 'pros_spinner_thesaurus', 'pros_spinner_thesaurus' );
        //add_action('admin_head-'.$synonymsSlug, 'pros_spin_my_thesaurus_style');

        // Add page for cron notice page
        add_submenu_page(null, 'Cron Notice', 'Cron Notice', 'manage_options', 'cron_notice_page', array($this, 'cronNoticePage'));


        // Set up options page
        $settings = new SoflyyOptionsPage('Settings', 'prossociate_settings', __FILE__, 'Prosociate: Settings');
        $settings->add_field('Associate ID', 'associate-id', 'text', 'Register for an Associate ID <a target="_blank" href="https://affiliate-program.amazon.com/">here</a>.');
        $settings->add_field('Associate Program Country', 'associate-program-country', 'select', 'Choose a country.', array(
            'com' => 'United States',
            'co.uk' => 'United Kingdom',
            'co.jp' => 'Japan',
            'de' => 'Germany',
            'fr' => 'France',
            'ca' => 'Canada',
            'es' => 'Spain',
            'it' => 'Italy',
            'cn' => 'China',
            'in' => 'India'
                )
        );
        $settings->add_field('AWS Access Key ID', 'aws-public-key');
        $settings->add_field('AWS Secret Access Key', 'aws-secret-key', 'text', 'Get your AWS Access Key ID and AWS Secret Access Key <a target="_blank" href="https://console.aws.amazon.com/iam/home?#security_credential">here</a>.');

        // check if amazon keys are correct
        add_action('admin_notices', array($this, 'check_amazon_notice'));

        // Add the category meta-box
        add_meta_box('categorydiv', __('Product Categories'), array( $this, 'product_categories_meta_box'), 'prosociate_page_prossociate_addedit_campaign', 'side', 'core');


        add_meta_box('categorydiv', __('Product Categories'), array( $this, 'product_subscription_categories_meta_box'), 'prosociate_page_pros-subscription', 'side', 'core');
    }

    /**
     * Compliance settings
     */
    public function addSettings() {
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-css', array($this, 'sanitize_styles'));
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-display-individual');
        register_setting('prossociate_settings', 'prossociate_settings-pros-dis-display-time');
        register_setting('prossociate_settings', 'prossociate_settings-pros-date-format');
        register_setting('prossociate_settings', 'prossociate_settings-pros-too-low-display-text');

        // Register the settings for fields that are moved to Advanced
        register_setting('prossociate_settings', 'prossociate_settings-iframe-width');
        register_setting('prossociate_settings', 'prossociate_settings-iframe-height');
        register_setting('prossociate_settings', 'prossociate_settings-title-word-length');
        register_setting('prossociate_settings', 'prossociate_settings-dm-cron-api-key');
        register_setting('prossociate_settings', 'prossociate_settings-dm-pros-redirection');
        register_setting('prossociate_settings', 'prossociate_settings-dm-pros-autocart-external');
        register_setting('prossociate_settings', 'prossociate_settings-dm-pros-prod-avail');
        register_setting('prossociate_settings', 'prossociate_settings-dm-pros-remote-img');
        register_setting('prossociate_settings', 'prossociate_settings-dm-add-attr', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-auto-affiliate', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-disable-reviews', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-fix-geo-links', array($this, 'sanitizeCheckBox'));

        // Settings for Text Spinning
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-username');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-password');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-quality');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-enable', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-reviews', array($this, 'sanitizeCheckBox'));

        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-account');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-lang');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-cp-syn');

        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-email');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-api');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-quality');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-enable', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-cron-update', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-sr-cron-spin', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-fix-custom-desc', array($this, 'sanitizeCheckBox'));
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-display-custom-desc', array($this, 'sanitizeCheckBox'));

        /*register_setting('prossociate_settings', 'prossociate_settings-dm-spin-bs-username');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-bs-password');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-bs-quality');
        register_setting('prossociate_settings', 'prossociate_settings-dm-spin-bs-enable', array($this, 'sanitizeCheckBox'));*/



        // Register setting for geo target countries
        $countries = self::geoCountries();
        foreach($countries as $k => $v) {
            register_setting('prossociate_settings', 'prossociate_settings-associate-id-' . $k);
        }

        add_settings_section('prosdisstyle', '', array($this, 'complianceSettings'), 'dm-pros-sections');
        add_settings_section('prosgeotarget', '', array($this, 'geoTargetSettings'), 'dm-pros-sections');
        add_settings_section('prosreq', '', array($this, 'requirementsSettings'), 'dm-pros-sections');
        add_settings_section('prosadvanced', '', array($this, 'advancedSettings'), 'dm-pros-sections');
        add_settings_section('textspinning', '', array($this, 'textspinningSettings'), 'dm-pros-sections');
    }

    /**
     * Make the option false for checkbox that are not checked
     *
     * @param $input
     * @return string
     */
    public function sanitizeCheckBox($input) {
        if($input == 'true') {
            $newInput = 'true';
        } else {
            $newInput = 'false';
        }

        return $newInput;
    }

    public function sanitize_styles($input) {
        $input = esc_html($input);

        return $input;
    }

    /**
     * Advanced settings
     */
    public function advancedSettings() {
        // Get options
        $width = get_option('prossociate_settings-iframe-width', 600);
        $height = get_option('prossociate_settings-iframe-height', 600);
        $wordLength = get_option('prossociate_settings-title-word-length', 9999);
        $api = get_option('prossociate_settings-dm-cron-api-key', '');
        $redirect = get_option('prossociate_settings-dm-pros-redirection', 'false');
        $avail = get_option('prossociate_settings-dm-pros-prod-avail', 'remove');
        $remoteImg = get_option('prossociate_settings-dm-pros-remote-img', 'no');
        $addAttr = get_option('prossociate_settings-dm-add-attr','true');
        $autoAffiliate = get_option('prossociate_settings-dm-auto-affiliate','true');
        $autoCartExt = get_option('prossociate_settings-dm-pros-autocart-external', 'true');
        $amazonReviews = get_option('prossociate_settings-dm-disable-reviews', 'false');
        $fixGeoLinks = get_option('prossociate_settings-dm-fix-geo-links', 'false');

        // Redirect options
        $redOptions = array(
            'false' => 'Disable',
            'true' => 'Enable'
        );

        // Auto cart external options
        $autoCartOptions = array(
            'true' => 'Enable',
            'false' => 'Disable'
        );

        // Available Options
        $availOptions = array(
            'remove' => 'Remove unavailable product',
            'change' => 'Change product stock status to "out of stock" for unavailable products.'
        );

        // Available Options
        $remoteImgOptions = array(
            'no' => 'No',
            'yes' => 'Yes'
        );

        // Additional attr options
        if($addAttr == 'true') {
            $addAttrOptions = ' checked="checked"';
        } else {
            $addAttrOptions = '';
        }

        // Auto Affiliate options
        if($autoAffiliate == 'true') {
            $autoAffiliateOptions = ' checked="checked"';
        } else {
            $autoAffiliateOptions = '';
        }

        // Amazon Review options
        if($amazonReviews == 'true') {
            $amazonReviewsOptions = ' checked="checked"';
        } else {
            $amazonReviewsOptions = '';
        }

        // Fix geo-links options
        if($fixGeoLinks == 'true') {
            $fixGeoLinksOptions = ' checked="checked"';
        } else {
            $fixGeoLinksOptions = '';
        }
        ?>
        <div id="tabs-advanced-settings" style="display: none;">
            <h3>Advanced</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-iframe-width">Customer Reviews IFrame Width</label>
                    </th>
                    <td>
                        <input name="prossociate_settings-iframe-width" type="text" id="prossociate_settings-iframe-width" value="<?php echo $width; ?>" class="regular-text">
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-iframe-height">Customer Reviews IFrame Height</label>
                    </th>
                    <td>
                        <input name="prossociate_settings-iframe-height" type="text" id="prossociate_settings-iframe-height" value="<?php echo $height; ?>" class="regular-text">
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-title-word-length">Max Length for Product Titles</label>
                    </th>
                    <td>
                        <input name="prossociate_settings-title-word-length" type="text" id="prossociate_settings-title-word-length" value="<?php echo $wordLength; ?>" class="regular-text">
                        <p class="description">Limit the number of characters in product titles. Does not apply retroactively.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-cron-api-key">Cron API Key</label>
                    </th>
                    <td>
                        <?php echo get_bloginfo('wpurl'); ?>/?proscron=<input name="prossociate_settings-dm-cron-api-key" type="text" id="prossociate_settings-dm-cron-api-key" value="<?php echo $api; ?>" style="width: 5em;">
                        <?php
                        // Check if the cron wasn't run for the last 24 hours
                        $dmLastCron = get_option('prosLastCronTime', time());
                        if($dmLastCron < (time() - 86400)) {
                        // Check if we can still display the notice
                        $dmCronNotice = get_option('prossociate_settings-hide-cron', '');
                        if($dmCronNotice != 'perm_hide') {
                        ?>
                        <p id="cron-error" class="description" style="background: #fff; border-left: 4px solid #dd3d36; -webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); box-shadow: 0 1px 1px 0 rgba(0,0,0,.1); margin: 5px 0 15px; padding: 5px 15px;">
                            <a href="<?php echo admin_url('admin.php?page=cron_notice_page'); ?>">Configure the cron job</a> to ensure all your product data is kept up to date and new products are posted for subscription campaigns.<br/>
                            <a href="<?php echo admin_url('?proscron_notice=perm_hide'); ?>">Hide Notice</a>
                        </p>
                        <?php }
                        } ?>
                        <p class="description">Optional: Create a cron job in your web hosting control panel to run this URL. It is recommended to be run every 2 minutes. It will automatically update product data that is more than 24 hours old. See <a href="<?php echo admin_url('admin.php?page=cron_notice_page'); ?>">How to Setup Cron Job.</a></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-pros-redirection">Enable redirection page</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-dm-pros-redirection" id="prossociate_settings-dm-pros-redirection">
                            <?php foreach($redOptions as $k => $v) {
                                if($k == $redirect)
                                    $selected = ' selected="selected"';
                                else
                                    $selected = '';

                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                            } ?>
                        </select>
                        <p class="description">Disable: users will be sent straight to Amazon on checkout <br> Enabled: for Simple/Variable products, users will first be shown the redirect screen, then sent to Amazon.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-pros-autocart-external">Enable 90-day Cookie for External / Affiliate</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-dm-pros-autocart-external" id="prossociate_settings-dm-pros-autocart-external">
                            <?php foreach($autoCartOptions as $k => $v) {
                                if($k == $autoCartExt)
                                    $selected = ' selected="selected"';
                                else
                                    $selected = '';

                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                            } ?>
                        </select>
                        <p class="description"></p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-pros-prod-avail">Product Availability</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-dm-pros-prod-avail" id="prossociate_settings-dm-pros-prod-avail">
                            <?php foreach($availOptions as $k => $v) {
                                if($k == $avail)
                                    $selected = ' selected="selected"';
                                else
                                    $selected = '';

                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                            } ?>
                        </select>
                        <p class="description">When a product is no longer "available" on Amazon.</p>
                    </td>
                </tr>

                <!--tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-pros-remote-img">Use Remote Images</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-dm-pros-remote-img" id="prossociate_settings-dm-pros-remote-img">
                            <?php /*foreach($remoteImgOptions as $k => $v) {
                                if($k == $remoteImg)
                                    $selected = ' selected="selected"';
                                else
                                    $selected = '';

                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                            }*/ ?>
                        </select>
                        <p class="description">Import images to server or use remote url.</p>
                    </td>
                </tr-->

                <tr valign="top">
                    <th scope="row">Attributes/Additional Information</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-add-attr" id="prossociate_settings-dm-add-attr" value="true"<?php echo $addAttrOptions; ?>/> <label for="prossociate_settings-dm-add-attr">Do not add attributes that aren’t present for variations.</label>
                        <p class="description">Use this to stop the Additional Information tab from filling up with lots of unnecessary product attributes. Uncheck this and all attributes will be added.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Shopping Cart Disabled</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-auto-affiliate" id="prossociate_settings-dm-auto-affiliate" value="true"<?php echo $autoAffiliateOptions; ?>/> <label for="prossociate_settings-dm-auto-affiliate">Automatically post product as external / affiliate when they can't be added to the shopping cart.</label>
                        <p class="description">Amazon disables the on-site shopping cart for some products. Check this option and Prosociate will post those products as External/Affiliate instead.</p>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">Amazon Reviews</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-disable-reviews" id="prossociate_settings-dm-disable-reviews" value="true"<?php echo $amazonReviewsOptions; ?>/> <label for="prossociate_settings-dm-disable-reviews">Disable Amazon Reviews</label>
                    </td>
                </tr>

                <!--tr valign="top">
                    <th scope="row">Fix Geo-target links</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-fix-geo-links" id="prossociate_settings-dm-fix-geo-links" value="true"<?php echo $fixGeoLinksOptions; ?>/> <label for="prossociate_settings-dm-fix-geo-links">Fix geo-target links.</label>
                        <p class="description">Check this if you upgrade from 1.2.1 to 2.0. This allow geo-targeting for your existing External / Affiliate products.</p>
                    </td>
                </tr-->
            </table>
        </div>
    <?php }

    /**
     * Text Spinning settings
     */
    public function textspinningSettings() {

        // CP Quality options
        $cpQualityOptions = array(
            'ok' => 'OK',
            'better' => 'Better',
            'ideal' => 'Ideal'
        );

        $cpAccountTypeOptions = array(
            'free' => 'Free',
            'paid' => 'Paid'
        );
        $cpLanguageOptions = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian'
        );

        $cpSynOptions = array(
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
            '8' => '8',
            '9' => '8',
            '10' => '10'
        );


         // SR Quality options
        $srQualityOptions = array(
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High'
        );

        // BS Quality options
        $bsQualityOptions = array(
            'good' => 'Good',
            'better' => 'Better',
            'best' => 'Best'
        );

        $cp_username = get_option('prossociate_settings-dm-spin-cp-username');
        $cp_password = get_option('prossociate_settings-dm-spin-cp-password');
        $cp_quality = get_option('prossociate_settings-dm-spin-cp-quality');
        $cp_enable = get_option('prossociate_settings-dm-spin-cp-enable');
        $cp_enable_reviews = get_option('prossociate_settings-dm-spin-cp-reviews');
        $cp_account = get_option('prossociate_settings-dm-spin-cp-account');
        $cp_lang = get_option('prossociate_settings-dm-spin-cp-lang');
        $cp_syn = get_option('prossociate_settings-dm-spin-cp-syn');

        $sr_email = get_option('prossociate_settings-dm-spin-sr-email');
        $sr_api = get_option('prossociate_settings-dm-spin-sr-api');
        $sr_quality = get_option('prossociate_settings-dm-spin-sr-quality');
        $sr_enable = get_option('prossociate_settings-dm-spin-sr-enable');
        $sr_enable_cron_update = get_option('prossociate_settings-dm-spin-sr-cron-update');
        $sr_enable_cron_spin = get_option('prossociate_settings-dm-spin-sr-cron-spin');
        $sr_enable_fix_desc = get_option('prossociate_settings-dm-spin-fix-custom-desc');
        $sr_enable_display_desc = get_option('prossociate_settings-dm-spin-display-custom-desc');

        $bs_username = get_option('prossociate_settings-dm-spin-bs-username');
        $bs_password = get_option('prossociate_settings-dm-spin-bs-password');
        $bs_quality = get_option('prossociate_settings-dm-spin-bs-quality');
        $bs_enable = get_option('prossociate_settings-dm-spin-bs-enable');

        // enable Spinrewriter
        $sr_enableOptions='';
        if($sr_enable == 'true') {
            $sr_enableOptions = ' checked="checked"';
        }

        $sr_enableOptions_cron_update = '';
        if($sr_enable_cron_update == 'true') {
            $sr_enableOptions_cron_update = ' checked="checked"';
        }

        $sr_enableOptions_cron_spin = '';
        if($sr_enable_cron_spin == 'true') {
            $sr_enableOptions_cron_spin = ' checked="checked"';
        }
        $sr_enableOptions_fix_desc = '';
        if($sr_enable_fix_desc == 'true') {
            $sr_enableOptions_fix_desc = ' checked="checked"';
        }
        $sr_enableOptions_display_desc = '';
        if($sr_enable_display_desc == 'true') {
            $sr_enableOptions_display_desc = ' checked="checked"';
        }



        ?>
        <div id="tabs-textspinning-settings" style="display: none;">
            <h3>SpinRewriter</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-spin-sr-email">Email</label>
                    </th>
                    <td>
                        <input name="prossociate_settings-dm-spin-sr-email" type="text" id="prossociate_settings-dm-spin-sr-email" value="<?php echo $sr_email; ?>" class="regular-text">
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-spin-sr-api">API Key</label>
                    </th>
                    <td>
                        <input name="prossociate_settings-dm-spin-sr-api" type="text" id="prossociate_settings-dm-spin-sr-api" value="<?php echo $sr_api; ?>" class="regular-text">
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">
                        <label for="prossociate_settings-dm-spin-sr-quality">Quality</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-dm-spin-sr-quality" id="prossociate_settings-dm-spin-sr-quality">
                            <?php foreach($srQualityOptions as $k => $v) {
                                if($k == $sr_quality)
                                    $selected = ' selected="selected"';
                                else
                                    $selected = '';

                                echo '<option value="'.$k.'"'.$selected.'>'.$v.'</option>';
                            } ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable SpinRewriter</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-spin-sr-enable" id="prossociate_settings-dm-spin-sr-enable" value="true"<?php echo $sr_enableOptions; ?>/> <label for="prossociate_settings-dm-spin-sr-enable">Enable text spinning</label>
                        <p class="description">Global setting applies to all import types of products. Register for Spinrewriter API Key
<a target="_blank" href="https://www.spinrewriter.com/">here</a></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Update via cron</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-spin-sr-cron-update" id="prossociate_settings-dm-spin-sr-cron-update" value="true"<?php echo $sr_enableOptions_cron_update; ?>/> <label for="prossociate_settings-dm-spin-sr-cron-update">Enable Update Description</label>
                        <p class="description">Enabled will update prdoucts description while updating price.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Spin and Update via cron</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-spin-sr-cron-spin" id="prossociate_settings-dm-spin-sr-cron-spin" value="true"<?php echo $sr_enableOptions_cron_spin; ?>/> <label for="prossociate_settings-dm-spin-sr-cron-spin">Enable Spin and Update Description</label>
                        <p class="description">Enabled will rewrite prdoucts description before update.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Display Custom Description</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-spin-display-custom-desc" id="prossociate_settings-dm-spin-display-custom-desc" value="true"<?php echo $sr_enableOptions_display_desc; ?>/> <label for="prossociate_settings-dm-spin-display-custom-desc">Display Custom Short Description</label>
                        <p class="description">Enabled  will display custom description instead of content area text.</p>
                    </td>
                </tr>

                <!--tr valign="top">
                    <th scope="row">Fix Custom Description</th>
                    <td>
                        <input type="checkbox" name="prossociate_settings-dm-spin-fix-custom-desc" id="prossociate_settings-dm-spin-fix-custom-desc" value="true"<?php echo $sr_enableOptions_fix_desc; ?>/> <label for="prossociate_settings-dm-spin-fix-custom-desc">Fix Custom Description</label>
                        <p class="description">Enabled  will transfer custom description to main content area.</p>
                    </td>
                </tr-->
            </table>
        </div>
    <?php }


    /**
     * Output for the Geo Target tab on Settings page
     */
    public function geoTargetSettings() { ?>
        <div id="tabs-geotarget-settings" style="display: none;">
            <h3>Geo Targeting Settings</h3>
            <div style="padding-left: 10px;">
            <p>Earn more commissions from international visitors by sending them to international versions of Amazon’s website.</p>
            <p>To enable geo-targeting for a particular country, enter your Associate ID for that country in the box below.</p>
            <p>When a visitor clicks a Buy Now button on your site, Prosociate will check the visitor’s country, check to see if the product also exists on the Amazon website for that visitor’s country, and if so, redirect the visitor to the product page on their own country’s Amazon website.</p>
            <p>If a visitor is from a non-geo-targeting-enabled country they will be sent to the country specified on the General tab.</p>
            <p>Example:</p>
            <p>If you choose United States (Amazon.com) as your default country on the General tab, add your Amazon United Kingdom (Amazon.co.uk) Associate ID below, and post a product, here’s what will happen.</p>
            <p>If a visitor from the United Kingdom comes to your site and clicks the Buy link, Prosociate will check if the product is present on Amazon.co.uk. If so, the visitor will be sent to the product page on Amazon.co.uk.</p>
            <p>If the visitor isn’t from the UK, or the same product isn’t present on Amazon.co.uk, the visitor will be sent to the product page on Amazon.com. </p>
            </div>
            <table class="form-table">
                <?php
                $countries = self::geoCountries();
                // Counter to get the associate
                $ctr = 0;
                foreach($countries as $k => $v) {
                    $this->generateGeoTargetFields($k, $v, $ctr);
                    $ctr++;
                }
                ?>
            </table>
        </div>
    <?php }

    /**
     * Requirements settings
     */
    public function requirementsSettings() {
        $soap = extension_loaded('soap') ? '<span style="color: #008000;">Enabled</span>' : '<span style="color: #FF0000;">Not Enabled</span>';
        $openSSL = extension_loaded('openssl') ? '<span style="color: #008000;">Enabled</span>' : '<span style="color: #FF0000;">Not Enabled</span>';
        ?>
        <div id="tabs-requirements-settings" style="display: none;">
            <h3>Requirements</h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">SOAP</th>
                    <td>
                        <?php echo $soap; ?>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row">OpenSSL</th>
                    <td>
                        <?php echo $openSSL; ?>
                    </td>
                </tr>
            </table>
        </div>
    <?php }

    public function complianceSettings() {
        $css = get_option('prossociate_settings-pros-dis-css');
        $displayByLocation = get_option('prossociate_settings-pros-dis-display-individual', 'true');
        $displayByTime = get_option('prossociate_settings-pros-dis-display-time', 'true');
        $dateDisplay = get_option('prossociate_settings-pros-date-format', false);
        if(!$css)
            $css = '';

        // Set defaults
        if($dateDisplay == false || empty($dateDisplay))
            $dateDisplay = '(as of %%M%%/%%D%%/%%Y%% at %%TIME%% UTC)';
        ?>
        <div id="tabs-compliance-settings" style="display: none;">
            <h3>Compliance</h3>
            <div style='padding-left: 10px'>
                <p style="font-weight: bold; font-size: 14px;">Amazon’s TOS requires a disclaimer to be placed next to all prices that haven't been refreshed in the last 24 hours.</p>

                Prosociate periodically refreshes the data on your site. How often the data is refreshed depends on the number of visitors to your site (the more, the more often the data is refreshed), and the number of products (the more, the less often the data is refreshed).

            </div>

            <p style="padding-left: 10px"><label for='prossociate_settings-pros-date-format'>Translate (as of 10/20/2015 at 09:23 UTC) <a id="dm-pros-default-link" href="#">reset to default</a></label><br />
                <input type="text" id="prossociate_settings-pros-date-format" name="prossociate_settings-pros-date-format"
                    style="width: 300px;" value="<?php echo $dateDisplay; ?>"/>
                <script type="text/javascript">
                    function dmRestoreDefault() {
                        document.getElementById('prossociate_settings-pros-date-format').value = '(as of %%M%%/%%D%%/%%Y%% at %%TIME%% UTC)';
                    }
                    document.getElementById('dm-pros-default-link').addEventListener("click", dmRestoreDefault, false);
                </script>
            </p>

            <table class='form-table'>
                <tr valign="top">
                    <th scope="row">
                        <label>CSS for Price Disclaimer</label>
                    </th>
                    <td>
                        <textarea name="prossociate_settings-pros-dis-css" cols="55" rows="6"><?php echo $css; ?></textarea>
                        <p class="description">Will be applied to <code>(as of 10/20/2015 at 09:23 UTC)</code></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label>Where to show the disclaimer</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-pros-dis-display-individual" >
                            <?php
                                $selected = '';
                                if($displayByLocation == 'false')
                                    $selected = ' selected=selected';
                            ?>
                            <option value='true'>Only show on individual product pages.</option>
                            <option value='false'<?php echo $selected; ?>>Show everywhere prices are displayed.</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label>When to show the disclaimer</label>
                    </th>
                    <td>
                        <select name="prossociate_settings-pros-dis-display-time" >
                            <?php
                            $selected = '';
                            if($displayByTime == 'false')
                                $selected = ' selected=selected';
                            ?>
                            <option value='true'>Display disclaimer for all products.</option>
                            <option value='false'<?php echo $selected; ?>>Only display disclaimer if the pricing data is more than 24 hours old.</option>
                        </select>
                    </td>
                </tr>
            </table>
            <div style='padding-left: 10px'>
                <p style="font-weight: bold; font-size: 14px;">Amazon’s TOS requires you to place the following text somewhere on your site in a way that is clearly visible to users. <br />
                    We recommend placing it in your footer:</p>

                <p style="font-style: italic">“CERTAIN CONTENT THAT APPEARS ON THIS SITE COMES FROM AMAZON SERVICES LLC. <br />
                    THIS CONTENT IS PROVIDED 'AS IS' AND IS SUBJECT TO CHANGE OR REMOVAL AT ANY TIME.”</p>
            </div>
        </div>
    <?php }

    public function addAmazonDisclaimerStyle() {
        // Get the style
        $css = get_option('prossociate_settings-pros-dis-css');
        // if there are no custom style don't show it in the front end
        if(!$css)
            return; ?>
        <style type="text/css">
            .prosamazondis {<?php echo $css; ?>}
        </style>
    <?php }

    /**
     * Deactivation hook
     */
    public function deactivation() {
        wp_clear_scheduled_hook('dm_pros_check_cron');
    }

    /**
     * Create the new table on the database for the plugin
     */
    public function activation() {
        // Create the tables
        // Check if cron time checker
        if(!get_option('prossociate_settings-title-word-length')) {
            update_option('prossociate_settings-title-word-length', 9999);
        }

        // Create api key on first time
        if(!get_option('prossociate_settings-dm-cron-api-key')) {
            update_option('prossociate_settings-dm-cron-api-key', substr(sha1(rand()), 0, 5));
        }

        // Check for the date format settings
        if(!get_option('prossociate_settings-pros-date-format')) {
            update_option('prossociate_settings-pros-date-format', '(as of %%M%%/%%D%%/%%Y%% at %%TIME%% UTC)');
        }

        // create/update required database tables
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require PROSSOCIATE_ROOT_DIR . '/schema.php';

        dbDelta($plugin_queries);

        // Import the data to the geo table
        $this->importGeoTargetData();
    }

    /**
     * Import data to geo target table
     */
    private function importGeoTargetData() {
        // [import GeoIPCountry database]
        $csv = NULL;
        is_file(PROSSOCIATE_ROOT_DIR . '/data/GeoIPCountryWhois.csv') and $csv = fopen(PROSSOCIATE_ROOT_DIR . '/data/GeoIPCountryWhois.csv', 'r'); // try raw file
        if (empty($csv) and function_exists('zip_open')) { // try zip archive directly from maxmind.com
            if (($zip = fopen('http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip', 'r'))) {
                $tmp_zip_name = tempnam(sys_get_temp_dir(), 'zip');
                if (($tmp_zip = fopen($tmp_zip_name, 'w'))) {
                    if ( ! stream_copy_to_stream($zip, $tmp_zip)) {
                        fclose($tmp_zip);
                        unlink($tmp_zip_name);
                    } else {
                        fclose($tmp_zip);
                        $csv = fopen('zip://' . $tmp_zip_name . '#GeoIPCountryWhois.csv', 'r');
                    }
                }
                fclose($zip);
            }
        }
        if (empty($csv) and function_exists('gzopen')) { // try gz
            is_file(PROSSOCIATE_ROOT_DIR . '/data/GeoIPCountryWhois.csv.gz') and ($csv = fopen('compress.zlib://' . PROSSOCIATE_ROOT_DIR . '/data/GeoIPCountryWhois.csv.gz', 'r'));
        }

        if ($csv) {
            global $wpdb;
            $record = new PMLC_GeoIPCountry_Record();
            $record->truncateTable();

            while ( ! feof($csv)) {
                $i = 0; $values = array();
                while (FALSE !== ($data = fgets($csv)) and $i < 10000) {
                    $data = trim($data);
                    if ('' != $data) {
                        $values[] = '(' . $data . ')';
                        $i++;
                    }
                }
                if ($values) {
                    $sql = 'INSERT INTO ' . $record->getTable() . ' (begin_ip, end_ip, begin_num, end_num, country, name) VALUES ' . implode(',', $values);
                    $wpdb->query($sql);
                }
            }
            fclose($csv);
        }
        if ( ! empty($tmp_zip_name) and is_file($tmp_zip_name)) { // unlink temporary file used for uploading zip archive
            unlink($tmp_zip_name);
        }
        // [/import GeoIPCountry database]
    }

    /**
     * Display post categories form fields.
     *
     * @since 2.6.0
     *
     * @param object $post
     */
    public function product_categories_meta_box($post, $box) {
        $campaign_id = isset($_REQUEST['campaign_id']) ? $_REQUEST['campaign_id'] : null;
        if( $campaign_id != null ) {
            global $wpdb;

            $dmSql = "Select options FROM " . $wpdb->prefix . "pros_campaigns WHERE id = '{$campaign_id}'";

            $dmResult = $wpdb->get_col( $dmSql );

            $dmUnserialized = unserialize($dmResult[0]);

            $dmSelectedCats = $dmUnserialized['dmcategories'];

            // Make sure we have an array
            if(is_array($dmSelectedCats)) {
                $removeZeroTermId = array_shift($dmSelectedCats);
            }

        }

        $defaults = array('taxonomy' => 'product_cat');
        if (!isset($box['args']) || !is_array($box['args']))
            $args = array();
        else
            $args = $box['args'];
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        $tax = get_taxonomy($taxonomy);
        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
                <li class="tabs"><a href="#<?php echo $taxonomy; ?>-all"><?php echo $tax->labels->all_items; ?></a></li>
                <li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop"><?php _e('Most Used'); ?></a></li>
            </ul>

            <div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
                <ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
        <?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
                </ul>
            </div>

            <div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
        <?php
        $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
        echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
        $dmPostId = isset($post->ID) ? $post->ID : 0;
        ?>
                <ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy ?>" class="categorychecklist form-no-clear">
                    <?php wp_terms_checklist($dmPostId, array('taxonomy' => $taxonomy, 'popular_cats' => $popular_ids)) ?>
                </ul>
                <?php if($campaign_id != null){ ?>
                    <script type=''>
                        <?php if(count($dmSelectedCats) > 0) {
                            foreach($dmSelectedCats as $dmSelectedCat) { ?>
                            jQuery("#in-product_cat-<?php echo $dmSelectedCat; ?>").attr("checked", "checked");

                            <?php }
                        } ?>
                    </script>
                <?php } ?>
            </div>
        <?php if (current_user_can($tax->cap->edit_terms)) : ?>
                <div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
                    <h4>
                        <a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js">
                    <?php
                    /* translators: %s: add new taxonomy label */
                    printf(__('+ %s'), $tax->labels->add_new_item);
                    ?>
                        </a>
                    </h4>
                    <p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
                        <input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr($tax->labels->new_item_name); ?>" aria-required="true"/>
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
            <?php echo $tax->labels->parent_item_colon; ?>
                        </label>
                            <?php wp_dropdown_categories(array('taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new' . $taxonomy . '_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;')); ?>
                        <input type="button" id="<?php echo $taxonomy; ?>-add-submit" data-wp-lists="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add" class="button category-add-submit" value="<?php echo esc_attr($tax->labels->add_new_item); ?>" />
                            <?php wp_nonce_field('add-' . $taxonomy, '_ajax_nonce-add-' . $taxonomy, false); ?>
                        <span id="<?php echo $taxonomy; ?>-ajax-response"></span>
                    </p>
                </div>
        <?php endif; ?>
        </div>
        <?php
    }

    public function product_subscription_categories_meta_box($post, $box) {
        $campaign_id = isset($_REQUEST['campaign_id']) ? $_REQUEST['campaign_id'] : null;
        if( $campaign_id != null ) {
            global $wpdb;

            $dmSql = "Select options FROM " . $wpdb->prefix . "pros_prossubscription WHERE id = '{$campaign_id}'";

            $dmResult = $wpdb->get_col( $dmSql );

            $dmUnserialized = unserialize($dmResult[0]);

            $dmSelectedCats = $dmUnserialized['dmcategories'];

            // Make sure we have an array
            if(is_array($dmSelectedCats)) {
                $removeZeroTermId = array_shift($dmSelectedCats);
            }

        }

        $defaults = array('taxonomy' => 'product_cat');
        if (!isset($box['args']) || !is_array($box['args']))
            $args = array();
        else
            $args = $box['args'];
        extract(wp_parse_args($args, $defaults), EXTR_SKIP);
        $tax = get_taxonomy($taxonomy);
        ?>
        <div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
            <ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
                <li class="tabs"><a href="#<?php echo $taxonomy; ?>-all"><?php echo $tax->labels->all_items; ?></a></li>
                <li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop"><?php _e('Most Used'); ?></a></li>
            </ul>

            <div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
                <ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
                    <?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
                </ul>
            </div>

            <div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
                <?php
                $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
                echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
                $dmPostId = isset($post->ID) ? $post->ID : 0;
                ?>
                <ul id="<?php echo $taxonomy; ?>checklist" data-wp-lists="list:<?php echo $taxonomy ?>" class="categorychecklist form-no-clear">
                    <?php wp_terms_checklist($dmPostId, array('taxonomy' => $taxonomy, 'popular_cats' => $popular_ids)) ?>
                </ul>
                <?php if($campaign_id != null){ ?>
                    <script type=''>
                        <?php if(count($dmSelectedCats) > 0) {
                            foreach($dmSelectedCats as $dmSelectedCat) { ?>
                        jQuery("#in-product_cat-<?php echo $dmSelectedCat; ?>").attr("checked", "checked");

                        <?php }
                    } ?>
                    </script>
                <?php } ?>
            </div>
            <?php if (current_user_can($tax->cap->edit_terms)) : ?>
                <div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
                    <h4>
                        <a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js">
                            <?php
                            /* translators: %s: add new taxonomy label */
                            printf(__('+ %s'), $tax->labels->add_new_item);
                            ?>
                        </a>
                    </h4>
                    <p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
                        <input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr($tax->labels->new_item_name); ?>" aria-required="true"/>
                        <label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
                            <?php echo $tax->labels->parent_item_colon; ?>
                        </label>
                        <?php wp_dropdown_categories(array('taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new' . $taxonomy . '_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;')); ?>
                        <input type="button" id="<?php echo $taxonomy; ?>-add-submit" data-wp-lists="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add" class="button category-add-submit" value="<?php echo esc_attr($tax->labels->add_new_item); ?>" />
                        <?php wp_nonce_field('add-' . $taxonomy, '_ajax_nonce-add-' . $taxonomy, false); ?>
                        <span id="<?php echo $taxonomy; ?>-ajax-response"></span>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    <?php
    }

    public function addJsSettingsPage() { ?>
        <script type="text/javascript">
            window.onload = dmInit;

            function dmInit() {
                var general = document.getElementById('tabs-general-settings-link');
                general.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-general-settings').style.display = 'inline';
                    document.getElementById('tabs-advanced-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    document.getElementById('tabs-requirements-settings').style.display = 'none';
                    //document.getElementById('tabs-geotarget-settings').style.display = 'none';

                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-requirements-settings-link').className = 'nav-tab';
                    //document.getElementById('tabs-geotarget-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-advanced-settings-link').className = 'nav-tab';

                    //document.getElementById('tabs-textspinning-settings').style.display = 'none';
                    //document.getElementById('tabs-textspinning-settings-link').className = 'nav-tab';

                    this.className = this.className + " nav-tab-active";
                }

                var compliance = document.getElementById('tabs-compliance-settings-link');
                compliance.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-compliance-settings').style.display = 'inline';
                    document.getElementById('tabs-advanced-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-requirements-settings').style.display = 'none';
                    //document.getElementById('tabs-geotarget-settings').style.display = 'none';

                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-requirements-settings-link').className = 'nav-tab';
                    //document.getElementById('tabs-geotarget-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-advanced-settings-link').className = 'nav-tab';

                    //document.getElementById('tabs-textspinning-settings').style.display = 'none';
                    //document.getElementById('tabs-textspinning-settings-link').className = 'nav-tab';

                    this.className = this.className + " nav-tab-active";
                };

                /*var geotarget = document.getElementById('tabs-geotarget-settings-link');
                geotarget.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-geotarget-settings').style.display = 'inline';
                    document.getElementById('tabs-advanced-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    document.getElementById('tabs-requirements-settings').style.display = 'none';

                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-requirements-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-advanced-settings-link').className = 'nav-tab';

                    document.getElementById('tabs-textspinning-settings').style.display = 'none';
                    document.getElementById('tabs-textspinning-settings-link').className = 'nav-tab';

                    this.className = this.className + " nav-tab-active";
                };*/

                var requirements = document.getElementById('tabs-requirements-settings-link');
                requirements.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-requirements-settings').style.display = 'inline';
                    document.getElementById('tabs-advanced-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    //document.getElementById('tabs-geotarget-settings').style.display = 'none';

                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    //document.getElementById('tabs-geotarget-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-advanced-settings-link').className = 'nav-tab';

                    //document.getElementById('tabs-textspinning-settings').style.display = 'none';
                    //document.getElementById('tabs-textspinning-settings-link').className = 'nav-tab';

                    this.className = this.className + " nav-tab-active";
                };

                var advanced = document.getElementById('tabs-advanced-settings-link');
                advanced.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-advanced-settings').style.display = 'inline';
                    document.getElementById('tabs-requirements-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    //document.getElementById('tabs-geotarget-settings').style.display = 'none';

                    document.getElementById('tabs-requirements-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    //document.getElementById('tabs-geotarget-settings-link').className = 'nav-tab';

                    //document.getElementById('tabs-textspinning-settings').style.display = 'none';
                    //document.getElementById('tabs-textspinning-settings-link').className = 'nav-tab';

                    this.className = this.className + " nav-tab-active";
                };

                /*var textspinning = document.getElementById('tabs-textspinning-settings-link');
                advanced.onclick = function(e) {
                    e.preventDefault();
                    document.getElementById('tabs-advanced-settings').style.display = 'none';
                    document.getElementById('tabs-requirements-settings').style.display = 'none';
                    document.getElementById('tabs-general-settings').style.display = 'none';
                    document.getElementById('tabs-compliance-settings').style.display = 'none';
                    //document.getElementById('tabs-geotarget-settings').style.display = 'none';

                    document.getElementById('tabs-requirements-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-general-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-compliance-settings-link').className = 'nav-tab';
                    //document.getElementById('tabs-geotarget-settings-link').className = 'nav-tab';
                    document.getElementById('tabs-advanced-settings-link').className = 'nav-tab';

                    //document.getElementById('tabs-textspinning-settings').style.display = 'inline';
                    this.className = this.className + " nav-tab-active";
                };*/
            }

        </script>
    <?php }

    /**
     * Generate fields for the Geo Targeting tab on settings page
     *
     * @param string $countryAbbr
     * @param string $countrySuffix
     * @param int $index
     */
    private function generateGeoTargetFields($countryAbbr, $countrySuffix, $index) {
        // If the country suffix is the same as AWS Country then display the default associate tag
        if($countrySuffix == AWS_COUNTRY) {
            $assocId = AWS_ASSOCIATE_TAG;
            $disabled = ' disabled="disabled"';
        } else {
            // Get the appropriate tag
            $assocId = get_option('prossociate_settings-associate-id-' . $countryAbbr, '');
            $disabled = '';
        }
        ?>
        <tr valign="top">
            <th scope="row">
                <label for="prossociate_settings-associate-id-<?php echo $countryAbbr;?>">Associate ID - Amazon.<?php echo $countrySuffix; ?></label>
            </th>
            <td>
                <input name="prossociate_settings-associate-id-<?php echo $countryAbbr;?>" type="text" id="prossociate_settings-associate-id-<?php echo $countryAbbr;?>" value="<?php echo $assocId; ?>" class="regular-text"<?php echo $disabled; ?>>
                <p class="description">Register for an Associate ID <a target="_blank" href="<?php echo self::getAssociateUrls($index); ?>">here</a>.</p>
            </td>
        </tr>
    <?php }

    /**
     * Get the associate url
     *
     * @param string $index
     * @return string
     */
    private static function getAssociateUrls($index) {
        // Build the associate url
        $associateUrls = array(
            'http://affiliate-program.amazon.com/',
            'https://affiliate-program.amazon.co.uk/',
            'https://affiliate.amazon.co.jp/',
            'http://partnernet.amazon.de/',
            'https://partenaires.amazon.fr/',
            'https://associates.amazon.ca/',
            'https://afiliados.amazon.es/',
            'https://programma-affiliazione.amazon.it/',
            'https://associates.amazon.cn/',
            'https://affiliate-program.amazon.in/',
        );

        return $associateUrls[$index];
    }

    /**
     * Countries for geo targeting
     *
     * @return array
     */
    public static function geoCountries() {
        $countries = array(
            'com' => 'com',
            'uk' => 'co.uk',
            'jp' => 'co.jp',
            'de' => 'de',
            'fr' => 'fr',
            'ca' => 'ca',
            'es' => 'es',
            'it' => 'it',
            'cn' => 'cn',
            'in' => 'in'
        );

        return $countries;
    }

    /**
     * Point the location of the woocommerce/templates/single-product/add-to-cart/external.php
     * to prosociate/views/display/external.php
     *
     * @param $located
     * @param $template_name
     *
     * @return string
     */
    public function woocommerceExternalButtonFilter($located, $template_name) {
        // Check if this is enabled on the settings
        $fixGeoLinks = get_option('prossociate_settings-dm-fix-geo-links', 'false');

        if($fixGeoLinks == 'true') {
            // If we are on external.php
            if($template_name === 'single-product/add-to-cart/external.php') {
                $located = plugin_dir_path(__FILE__) . 'views/display/external.php';
            }
        }

        return $located;
    }

    /**
     * External / Affiliate geo-link fix for the Shop or other product-archive page
     *
     * @param $permalink
     * @param $object
     * @return string
     */
    public function woocommerceExternalButtonFilterLoop($permalink, $object) {
        // Check if this is enabled on the settings
        $fixGeoLinks = get_option('prossociate_settings-dm-fix-geo-links', 'false');

        if($fixGeoLinks == 'true') {
            // Check if we are on external
            if($object->product_type === 'external') {
                // Check if it has pros ASIN
                $asin = get_post_meta($object->id, '_pros_ASIN', true);
                if($asin) {
                    $permalink = site_url() . '?product=' . $object->id;
                } else {
                    $product_url = get_post_meta($object->id, '_product_url', true);
                    $permalink = esc_url( $product_url );
                }
            }
        }

        return $permalink;
    }

    public function _attachment_url( $url='', $post_id=0 ) {
      global $product;
      /*var_dump($product->id);
      // mandatory - must be amazon product
      echo 'imgimg';
      var_dump($post_id);
var_dump($url);*/
//die();
      $asin = get_post_meta($post_id, '_pros_ASIN', true);
      $ira = get_option('prossociate_settings-dm-pros-remote-img', false);
      //var_dump($asin);
      /*if ( $ira == 'no' || empty($asin) ) {
        return $url;
      }*/

      // mandatory rule - must have amazon url
      $rules = array();
      $rules[0] = strpos( $url, self::$amazon_images_path );
      $rules = $rules[0];

      if ( $rules ) {
        $uploads = wp_get_upload_dir();
        $url = str_replace( $uploads['baseurl'] . '/', '', $url );
      }
      $url = $this->amazon_url_to_ssl( $url );
      return $url;
    }

    public function amazon_url_to_ssl( $url='' ) {
      if (empty($url)) return $url;
      if (!$this->is_ssl()) return $url;

      // http://ecx.images-amazon TO https://images-na.ssl-images-amazon
      $newurl = preg_replace('/^http\:\/\/ec(.){0,1}\.images\-amazon/imu', 'https://images-na.ssl-images-amazon', $url);
      return !empty($newurl) ? $newurl : $url;
    }
    // Determine if SSL is used.
		public function is_ssl() {
			if ( isset($_SERVER['HTTPS']) ) {
				if ( 'on' == strtolower($_SERVER['HTTPS']) )
					return true;
				if ( '1' == $_SERVER['HTTPS'] )
					return true;
			} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
				return true;
			}

			// HTTP_X_FORWARDED_PROTO: a de facto standard for identifying the originating protocol of an HTTP request, since a reverse proxy (load balancer) may communicate with a web server using HTTP even if the request to the reverse proxy is HTTPS
			if ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ) {
				if ( 'https' == strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) )
					return true;
			}
			if ( isset($_SERVER['HTTP_X_FORWARDED_SSL']) ) {
				if ( 'on' == strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) )
					return true;
				if ( '1' == $_SERVER['HTTP_X_FORWARDED_SSL'] )
					return true;
			}
			return false;
		}

    // fix html errors come up on text spinning
    public function html_fix($content){
      $doc = new DOMDocument();
      $doc->substituteEntities = false;
      $html = preg_replace('/<+\s+/', '<', $content);
      $html = mb_convert_encoding($html, 'html-entities', 'utf-8');
      @$doc->loadHTML($html);
      $html = $doc->saveHTML();
      return $html;
    }

}

// Get / create an instance
Prossociate::getInstance();

/** start of plugin updater code **/
if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) {
    // load our custom updater
    include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
}

function pros_plugin_updater() {

    // retrieve our license key from the DB
    $license_key = trim( get_option( 'edd_pros_license_key' ) );
    // setup the updater
    $edd_updater = new EDD_SL_Plugin_Updater( EDD_PROS_STORE_URL, __FILE__, array(
            'version'   => '3.0.0.1',               // current version number
            'license'   => $license_key,        // license key (used get_option above to retrieve from DB)
            'item_name' => EDD_PROS_ITEM_NAME,  // name of this plugin
            'slug' => 'prosociate',  // slug of this plugin
            'author'    => 'Prosociate'  // author of this plugin
        )
    );
}
add_action( 'admin_init', 'pros_plugin_updater', 0 );

include( dirname( __FILE__ ) . '/pros-license-options.php' );
/** end of plugin updater code **/
