<?php
/*
* Define class ProsociateStatsProd
* Make sure you skip down to the end of this file, as there are a few
* lines of code that are very important.
*/


if (class_exists('ProsociateStatsProd') != true) {
    class ProsociateStatsProd
    {

		public $the_plugin = null;

		private $module_folder = '';
		private $module = '';

		static protected $_instance;

        /*
        * Required __construct()
        */
        public function __construct()
        {

			if (is_admin()) {
	            //add_action('admin_menu', array( &$this, 'adminMenu' ));
	            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_statsview_script'));
			}

			if ( !is_admin()) {

				$this->addFrontFilters();
			}

        }

		/**
	    * Singleton pattern
	    *
	    * @return ProsociateStatsProd Singleton instance
	    */
	    static public function getInstance()
	    {
	        if (!self::$_instance) {
	            self::$_instance = new self;
	        }

	        return self::$_instance;
	    }

		public function display_index_page()
		{
			//$this->printBaseInterface();
		}

		/**
		 * frontend methods: update hits & add to cart for amazon product!
		 *
		 */
		public function addFrontFilters() {
			add_action('wp', array( $this, 'frontend' ), 0);

			add_action('woocommerce_add_to_cart', array($this, 'add_to_cart'), 1, 6); // add item to cart
			add_action('wp_ajax_woocommerce_add_to_cart', array($this, 'add_to_cart_ajax'), 0);
			add_action('wp_ajax_nopriv_woocommerce_add_to_cart', array($this, 'add_to_cart_ajax'), 0);
		}

		public function frontend() {
			global $wpdb, $wp;

			// $currentUri = home_url(add_query_arg(array(), $wp->request));

			if ( is_single() ) {
				global $post;
				$post_id = (int)$post->ID;

				// verify if it's an woocommerce amazon product!
				$this->verify_product_isamazon($post_id);
				if ( $post_id <= 0 || !$this->verify_product_isamazon($post_id) )
					return false;

				// update hits

        //ob_start();
        $ft_posts = array();
        //var_dump($_COOKIE['pros_recent_visited']);
        $ft_posts =  unserialize(@$_COOKIE['pros_recent_visited']);
        if(!empty($ft_posts) && !in_array($post_id, $ft_posts) ) {
          $ft_posts[]=$post_id;
          $hits = (int) get_post_meta($post_id, '_pros_hits', true);
          update_post_meta($post_id, '_pros_hits', (int)($hits+1));
          //var_dump($_COOKIE['pros_recent_visited']);

          $ser_posts = serialize($ft_posts);
          //var_dump($ser_posts);
          //$cookie = setcookie( 'pros_recent_visited', $ser_posts ,time() + ( DAY_IN_SECONDS * 31 ),'/',$_SERVER['SERVER_NAME']);
          $cookie = setcookie( 'pros_recent_visited', $ser_posts ,time() + ( DAY_IN_SECONDS * 31 ));
          //var_dump($cookie);
        }
			}
			//var_dump(unserialize($_COOKIE['pros_recent_visited']));
		}

		public function add_to_cart_validation( $passed, $product_id, $quantity, $variation_id='', $variations='' ) {
			if ( !is_admin() ) {
				$post_id = $product_id;

				// verify if it's an woocommerce amazon product!
				if ( $post_id <= 0 || !$this->verify_product_isamazon($post_id) )
					return false;

				$addtocart = (int) get_post_meta($post_id, '_pros_addtocart', true);
				update_post_meta($post_id, '_pros_addtocart', (int)($addtocart+1));

                $addtocart2 = (int) get_post_meta($post_id, '_pros_addtocart_prev', true);
                update_post_meta($post_id, '_pros_addtocart_prev', (int)($addtocart2+1));

				return true;
			}
		}

		public function add_to_cart_ajax() {
			global $woocommerce;

			check_ajax_referer( 'add-to-cart', 'security' );

			$product_id = (int) apply_filters('woocommerce_add_to_cart_product_id', $_POST['product_id']);

			$passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, 1);

			if ($passed_validation && $woocommerce->cart->add_to_cart($product_id, 1)) :
				// Return html fragments
				$data = apply_filters('add_to_cart_fragments', array());

				$post_id = $product_id;

				// verify if it's an woocommerce amazon product!
				if ( $post_id <= 0 || !$this->verify_product_isamazon($post_id) )
					return false;

				$addtocart = (int) get_post_meta($post_id, '_pros_addtocart', true);
				update_post_meta($post_id, '_pros_addtocart', (int)($addtocart+1));

            	$addtocart2 = (int) get_post_meta($post_id, '_pros_addtocart_prev', true);
            	update_post_meta($post_id, '_pros_addtocart_prev', (int)($addtocart2+1));
			else :
				// If there was an error adding to the cart, redirect to the product page to show any errors
				$data = array(
					'error' => true,
					'product_url' => get_permalink( $product_id )
				);
				$woocommerce->set_messages();
			endif;

			echo json_encode( $data );

			die();
		}

		public function add_to_cart( $cart_item_key='', $product_id='', $quantity='', $variation_id='', $variation='', $cart_item_data='' ) {

			if ( !is_admin() ) {
				$post_id = $product_id;

				// verify if it's an woocommerce amazon product!
				if ( $post_id <= 0 || !$this->verify_product_isamazon($post_id) )
					return false;

				$addtocart = (int) get_post_meta($post_id, '_pros_addtocart', true);
				update_post_meta($post_id, '_pros_addtocart', (int)($addtocart+1));

                $addtocart2 = (int) get_post_meta($post_id, '_pros_addtocart_prev', true);
                update_post_meta($post_id, '_pros_addtocart_prev', (int)($addtocart2+1));

				return true;
			}
		}


		/*
		* printBaseInterface, method
		* --------------------------
		*
		* this will add the base DOM code for you options interface
		*/
		private function printBaseInterface()
		{
            // Initialize the wwcAmzAffTailSyncMonitor class
            require_once( $this->the_plugin->cfg['paths']['plugin_dir_path'] . '/modules/synchronization/tail.php' );
            $syncTail = new wwcAmzAffTailSyncMonitor($this->the_plugin);

            $syncTail->printBaseInterface( 'stats_prod' );
		}

		/**
		 * verifies if product is amazon
		 */
		public function verify_product_isamazon($post_id){
            $ASIN = get_post_meta($post_id, '_pros_ASIN', true);
            if(empty($ASIN)){
                return false;
            }
            return true;
		}
		public function display_productstats()
	    { //echo PROSSOCIATE_ROOT_DIR . '/modules/product_stats/ProductStatsView.php';
	    	include_once(PROSSOCIATE_ROOT_DIR . '/modules/product_stats/ProductStatsView.php');
	    }

	    public function admin_enqueue_statsview_script(){

	    	wp_enqueue_style('prossociate_enhanced_css', PROSSOCIATE_ROOT_URL.'/css/enhanced.css', array(), '1.0');
	    }



    }


}

// Initialize the ProsociateStatsProd class
//$ProsociateStatsProd = ProsociateStatsProd::getInstance();
