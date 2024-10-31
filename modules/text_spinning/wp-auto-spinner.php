<?php

function pros_spinner_mce_css( $mce_css ) {
	if ( ! empty( $mce_css ) )
		$mce_css .= ',';

	$mce_css .= PROSSOCIATE_ROOT_URL.'/css/synonyms-highlight.css';

	return $mce_css;
}

add_filter( 'mce_css', 'pros_spinner_mce_css' );

/* ------------------------------------------------------------------------*
* Function Selected
* ------------------------------------------------------------------------*/
	if(! function_exists('opt_selected')){
		function opt_selected($src,$val){
			if (trim($src)==trim($val)) echo ' selected="selected" ';
		}
	}

/* Add a new meta box to the admin menu. */
add_action( 'admin_menu', 'pros_spinner_create_meta_box' );

/**
 * Function for adding meta boxes to the admin.
 */
function pros_spinner_create_meta_box() {

	$wp_spinner_types = get_option('wp_spinner_types',array('product') );

	foreach ($wp_spinner_types as $type){

		add_meta_box( 'pros_spinner-meta-boxes', 'Prosociate Text Spinning ', 'pros_spinner_meta_boxes', $type , 'normal', 'high' );

	}

}
function pros_spinner_meta_boxes(){
	require_once('meta.php');
	pros_spinner_metabox();
}

/**
 * Function for adding header style sheets and js
 */
function pros_spinner_admin_head() {
		 echo '<link rel="stylesheet" type="text/css" href="'.PROSSOCIATE_ROOT_URL.'/css/spin.style.css">';
         echo '<script src="'.PROSSOCIATE_ROOT_URL.'/js/main.wpautospinner.js" type="text/javascript"></script>';
}
add_action('admin_head-post-new.php', 'pros_spinner_admin_head');
add_action('admin_head-post.php', 'pros_spinner_admin_head');


//synonyms page
function pros_spin_thesaurus_style() {
	 echo '<link rel="stylesheet" type="text/css" href="'.PROSSOCIATE_ROOT_URL.'/css/spin.style.css">';
     echo '<script src="'.PROSSOCIATE_ROOT_URL.'/js/synonyms.wpautospinner.js" type="text/javascript"></script>';
}

function pros_spin_my_thesaurus_style() {
	echo '<link rel="stylesheet" type="text/css" href="' .PROSSOCIATE_ROOT_URL.'/css/spin.style.css">';
	echo '<script src="'.PROSSOCIATE_ROOT_URL.'/js/thesaurus.wpautospinner.js" type="text/javascript"></script>';
}

//edit page
//add_action('admin_print_scripts-' . 'edit.php', 'pros_spinner_admin_edit');




function pros_spinner_synonyms_fn(){
	require_once(dirname(__FILE__).'/synonyms.php');
	pros_spinner_synonyms();
}

function pros_spinner_thesaurus(){
	require_once(dirname(__FILE__).'/thesaurus.php');
	pros_spinner_thesaurus_f();
}

/**
 * Filter the content to check if the post is spinned or not if not spinned let's spin it.
 */
//add_filter( 'the_title', 'pros_spinner_the_content_filter', 20 );
//add_filter( 'the_title_rss', 'pros_spinner_the_content_filter_rss', 20 );


function pros_spinner_the_content_filter( $post_id ) {



	//read post
	global $post;


	//check if auto spin is enabled or not
	$autospin=get_option('pros_spin',array());
	if(!in_array('OPT_AUTO_SPIN_ACTIVE',$autospin)){return $title ;}

	//check if single post
    if ( 1){

    	//check if spinned or not

    	$post_arr=get_post( $post_id);
    	if($spinned == 'spinned') {
    	 	return $title ;
    	}

    	//ok it is not spinned check if manual spinning disabled
    	if( ! in_array('OPT_AUTO_SPIN_ACTIVE_MANUAL',$autospin)){
    		//now manual spining is active let's check if this is a manual
    		$manual=get_post_meta($post_id,'pros_spinner_manual_flag',1);
    		if($manual == 'manual'){
    			//manual post and manual is active should be spinned
    			return $title ;
    		}
    	}

    	//check if deserve spin or in execluded category
    	$execl=get_option('pros_spin_execl',array());
    	if(!in_category($execl,$post_id)){

    		//SPIN THE POST
			$content=$post_arr->post_content;
    		$ttl=$post_arr->post_title;
    		$originalcontent=$content;

   	 		//classes
			require_once(dirname(__FILE__) .'/inc/class.spin.php');
			require_once(dirname(__FILE__) .'/inc/class.spintax.php');

			$spin=new pros_spin_spin($post_id,$ttl,$content);
			$spinned=$spin->spin();
			$spinned_ttl=$spinned['spinned_ttl'];
			$spinned_cnt=$spinned['spinned_cnt'];
			$spintaxed_ttl=$spinned['spintaxed_ttl'];
			$spintaxed_cnt=$spinned['spintaxed_cnt'];
			$content=$spintaxed_cnt;
			$post->post_content=$content;

			//update the post
			  $my_post = array();
			  $my_post['ID'] = $post_id;
			  $my_post['post_content'] = $content ;

			  //check if we should updat the slug .
			  if(in_array('OPT_AUTO_SPIN_SLUG',$autospin)){
			  	$my_post['post_name']='';
			  }

			  //update spinned title if allowed
			  if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){

			  	$my_post['post_title']=$spintaxed_ttl;
			  	$post->post_title=$spintaxed_ttl;
			  }else{

			  }

			  //update it's status to spined
			  update_post_meta($post_id,'pros_spinner_spinned_flag','spinned');

			  pros_spinner_log_new('Already Posted Post >> Do Spin','Post with id {'.$post_id.'} is already posted but eligiable to be spinned . spinned successfully .' );

			// Update the post into the database
			  remove_filter('content_save_pre', 'wp_filter_post_kses');
			  wp_update_post( $my_post );
			  add_filter('content_save_pre', 'wp_filter_post_kses');

    		if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){

    			return $spintaxed_ttl ;
    		}else{

    			return $title;
    		}

    	}else{

    		return $title ;
    	}
	}else{

		return $title ;
	}

}// end filtering

function pros_spinner_the_content_filter_rss( $title ) {


	global $post;


	//check if auto spin is enabled or not
	$autospin=get_option('pros_spin',array());

	if(!in_array('OPT_AUTO_SPIN_ACTIVE',$autospin)){
		return $title ;
	}

	if ( 1 ){

		//check if spinned or not
		$post_id=get_the_id();


		$spinned=get_post_meta($post_id,'pros_spinner_spinned_flag',1);

		//get the post

		$post_arr=get_post( $post_id);

		if($spinned == 'spinned') {

			return $title ;
		}

		//check if deserve spin or not
		$execl=get_option('pros_spin_execl',array());



		if(!in_category($execl,$post_id)){


			//let's spin this post

			$content=$post_arr->post_content;
			$ttl=$post_arr->post_title;
			$originalcontent=$content;



			require_once(dirname(__FILE__) .'/inc/class.spin.php');
			require_once(dirname(__FILE__) .'/inc/class.spintax.php');


			$spin=new pros_spin_spin($post_id,$ttl,$content);

			$spinned=$spin->spin();


			$spinned_ttl=$spinned['spinned_ttl'];
			$spinned_cnt=$spinned['spinned_cnt'];
			$spintaxed_ttl=$spinned['spintaxed_ttl'];
			$spintaxed_cnt=$spinned['spintaxed_cnt'];

			$content=$spintaxed_cnt;

			$post->post_content=$content;

			//update the post
			$my_post = array();
			$my_post['ID'] = $post_id;
			$my_post['post_content'] = $content ;

			//update spinned title if allowed
			if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){
				$my_post['post_title']=$spintaxed_ttl;
				$post->post_title=$spintaxed_ttl;
			}

			//check if we should updat the slug .
			if(in_array('OPT_AUTO_SPIN_SLUG',$autospin)){
				$my_post['post_name']='';
			}



			//update it's status to spined
			update_post_meta($post_id,'pros_spinner_spinned_flag','spinned');

			pros_spinner_log_new('Already Posted Post >> Do Spin','Post with id {'.$post_id.'} is already posted but eligiable to be spinned . spinned successfully .' );

			// Update the post into the database
			remove_filter('content_save_pre', 'wp_filter_post_kses');
			wp_update_post( $my_post );
			add_filter('content_save_pre', 'wp_filter_post_kses');

			if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){

				return $spintaxed_ttl ;
			}else{

				return $title;
			}

		}else{

			return $title ;
		}
	}else{

		return $title ;
	}

}// end filtering



$wp_spinner_types = get_option('wp_spinner_types',array('post','product') );

foreach ($wp_spinner_types as $type){

	add_action('publish_'.$type,'pros_spinner_publish');

}

//SPIN ON PUBLISH
function pros_spinner_publish($post_id){

	// if quick edit mode ignore it .
 	if ( stristr($_SERVER['HTTP_REFERER'], 'edit.php') )  return  ;


	global $post;

	//check if already checked if yes return
	$checked= get_post_meta($post_id,'pros_spinner_checked',1);

	if(trim($checked) != '') return ;

	//set checked flag to yes
	update_post_meta($post_id,'pros_spinner_checked' , 'yes');

	//INSTANT SPIN :  manual + manual spin enabled    or   auto + auto spin enabled + spin on publish enabled
	$autospin=get_option('pros_spin',array());

	if (  ( isset($_POST['publish']) && in_array('OPT_AUTO_SPIN_ACTIVE_MANUAL',$autospin) ) || ( ! isset($_POST['publish'])  ) &&  in_array('OPT_AUTO_SPIN_ACTIVE',$autospin) && in_array('OPT_AUTO_SPIN_PUBLISH',$autospin)  ){

		//INSTANT SPIN
		pros_spinner_log_new('New Post >> Publish','New post with id {'.$post_id.'} is going to be published and deserve spinning' );

		$execl=get_option('pros_spin_execl',array());

		if(in_category($execl,$post_id)){

			pros_spinner_log_new('New Post >> Cancel Spin','Post in an execluded from spinning category . ignore post .' );
			return;
		}else{
			pros_spinner_post_spin($post_id);
		}





	}elseif(  ! isset($_POST['publish'])    &&  in_array('OPT_AUTO_SPIN_ACTIVE',$autospin) ){

		//SCHEDULED SPIN
		pros_spinner_log_new('New Post >> Publish','New post with id {'.$post_id.'} is going to be published sent to spin queue' );

		//add the scheduled spin meta
		update_post_meta($post_id , 'pros_spinner_scheduled' , 'yes');

	}


return;












	//Manual post check
	if (isset($_POST['publish'])){
		update_post_meta($post_id,'pros_spinner_manual_flag','manual');
	}

	//if publish action disabled return
	$opt= get_option('pros_spin',array());
	if(! in_array('OPT_AUTO_SPIN_PUBLISH', $opt)) return ;

	//get the post
	$post_arr=get_post( $post_id );

	//if no spin shortcode exists
	if(stristr($post_arr->post_content, '{nospin}')){
		echo ' post contains no spin tag';

	}



	pros_spinner_log_new('New Post >> Publish','New post with id {'.$post_id.'} is going to be published' );


	//check if auto spin is enabled or not
	$autospin=get_option('pros_spin',array());
	if(!in_array('OPT_AUTO_SPIN_ACTIVE',$autospin)){
		pros_spinner_log_new('New Post >> Cancel Spin','Automated spinning is disabled . ignore post .' );
		return;
	}

	//check if it is already spinned checked
	$spinned=get_post_meta($post_id,'pros_spinner_spinned_flag',1);
	if($spinned == 'spinned') {

		pros_spinner_log_new('New Post >> Cancel Spin','Post {'.$post_id.'} already passed spinning filter . ignore it' );

		return;

	}

	//check if it is manual and manual spin disabled
	 if (isset($_POST['publish'])){
	 	if(! in_array('OPT_AUTO_SPIN_ACTIVE_MANUAL',$autospin)){
	 		update_post_meta($post_id,'pros_spinner_spinned_flag',true);
	 		pros_spinner_log_new('New Post >> Cancel Spin','Manual post and manual posts spinning disabled . ignore post .' );
	 		return ;
	 	}
	 }

	//check if deserve spin or not
	$execl=get_option('pros_spin_execl',array());

 	if(in_category($execl,$post_id)){

 		pros_spinner_log_new('New Post >> Cancel Spin','Post in an execluded from spinning category . ignore post .' );
 		return;
 	}




	//let's spin this post

 	$content=$post_arr->post_content;
	$ttl=$post_arr->post_title;
	$originalcontent=$content;

	//spin libs
	require_once(dirname(__FILE__) .'/inc/class.spin.php');
	require_once(dirname(__FILE__) .'/inc/class.spintax.php');

	//spin start
	$spin=new pros_spin_spin($post_id,$ttl,$content);
	$spinned=$spin->spin();

	//spinned cnt
	$spinned_ttl=$spinned['spinned_ttl'];
	$spinned_cnt=$spinned['spinned_cnt'];
	$spintaxed_ttl=$spinned['spintaxed_ttl'];
	$spintaxed_cnt=$spinned['spintaxed_cnt'];
	$content=$spintaxed_cnt;

	//update the post
	$my_post = array();
	$my_post['ID'] = $post_id;
	$my_post['post_content'] = $content ;

	//update spinned title if allowed
	if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){
		$my_post['post_title']=$spintaxed_ttl;
		@$post->post_title=$spintaxed_ttl;
	}

	//check if we should updat the slug .
	if(in_array('OPT_AUTO_SPIN_SLUG',$autospin)){
		$my_post['post_name']='';
	}


	//update it's status to spined
	update_post_meta($post_id,'pros_spinner_spinned_flag','spinned');

	pros_spinner_log_new('New Post >> Do Spin','Post with id {'.$post_id.'} spinned successfully .' );

	// Update the post into the database
	remove_filter('content_save_pre', 'wp_filter_post_kses');
	wp_update_post( $my_post );
	add_filter('content_save_pre', 'wp_filter_post_kses');
}

/*
 * Spin this post function
 * @post_id: post id to spin
 * return : none
 *
 */
function pros_spinner_post_spin($post_id){

	//let's spin this post
	//get the post
	$post_arr=get_post( $post_id );

	//spin options
	$autospin=get_option('pros_spin',array());

	$content=$post_arr->post_content;
	$ttl=$post_arr->post_title;
	$originalcontent=$content;

	//spin libs
	require_once(dirname(__FILE__) .'/inc/class.spin.php');
	require_once(dirname(__FILE__) .'/inc/class.spintax.php');

	//spin start
	$spin=new pros_spin_spin($post_id,$ttl,$content);
	$spinned=$spin->spin_wrap();

	//spinned cnt
	$spinned_ttl=$spinned['spinned_ttl'];
	$spinned_cnt=$spinned['spinned_cnt'];
	$spintaxed_ttl=$spinned['spintaxed_ttl'];
	$spintaxed_cnt=$spinned['spintaxed_cnt'];
	$content=$spintaxed_cnt;


	//update the post
	$my_post = array();
	$my_post['ID'] = $post_id;

	if(! in_array('OPT_AUTO_SPIN_DEACTIVE_CNT',$autospin)){
		$my_post['post_content'] = $content ;
	}

	//update spinned title if allowed
	if(in_array('OPT_AUTO_SPIN_ACTIVE_TTL',$autospin)){
		$my_post['post_title']=$spintaxed_ttl;

	}

	//check if we should updat the slug .
	if(in_array('OPT_AUTO_SPIN_SLUG',$autospin)){
		$my_post['post_name']='';
	}



	pros_spinner_log_new('New Post >> Do Spin','Post with id {'.$post_id.'} spinned successfully .' );

	// Update the post into the database
	remove_filter('content_save_pre', 'wp_filter_post_kses');
	wp_update_post( $my_post );
	add_filter('content_save_pre', 'wp_filter_post_kses');

}

/*
 * differ if the post is manually spinned or not by saving
 */
add_action( 'save_post', 'pros_spinner_save_meta_data' );

function pros_spinner_save_meta_data($post_id){

 	//SCHEDULED POSTS TO QUEUE

	if ( ! wp_is_post_revision ( $post_id ) ) {
		$publish='';
		@$publish=$_POST['post_status'];
		$autospin=get_option('pros_spin',array());

		if( (trim($publish) == 'publish' &&  isset($_POST['post_date']) )){
			//this is a scheduled post let's schedule for spin if eligible

			//check if already checked if yes return
			$checked= get_post_meta($post_id,'pros_spinner_checked',1);

			if(trim($checked) != '') return ;

			//set checked flag to yes
			update_post_meta($post_id,'pros_spinner_checked' , 'yes');


			//if manual enabled schedule
			if(  in_array('OPT_AUTO_SPIN_ACTIVE_MANUAL',$autospin)   ){
				//schedule it manual post spinning enable
				//SCHEDULED SPIN
				pros_spinner_log_new('New Post >> Schedule','New scheduled post with id {'.$post_id.'}   sent to spin queue' );

				//add the scheduled spin meta
				update_post_meta($post_id , 'pros_spinner_scheduled' , 'yes');
			}

			//scheduled post
		}else{

			//Draft posts
			//SPIN Draft posts
			$status = get_post_status($post_id);

			if($status == 'draft' && trim($publish) == '' && in_array('OPT_AUTO_SPIN_DRAFT', $autospin)){

				$spinned_cnt = get_post_meta($post_id,'spinned_cnt',1);

				if(trim($spinned_cnt) != '') return;

				pros_spinner_log_new('New Post >>Draft Schedule','New draft post with id {'.$post_id.'}   sent to spin queue' );

				//add the scheduled spin meta
				update_post_meta($post_id , 'pros_spinner_scheduled' , 'yes');

			}



		}




	}//not revision



}

/**
 * custom request for metabox buttons
 */
function pros_spinner_parse_request($wp) {

	// only process requests with "my-plugin=ajax-handler"
	if (array_key_exists('pros_spinner', $wp->query_vars)) {

		if($wp->query_vars['pros_spinner'] == 'ajax'){

			require_once('p_ajax.php');
			exit;

		}elseif($wp->query_vars['pros_spinner'] == 'cron'){

			pros_spinner_spin_function();
			exit;
		}elseif($wp->query_vars['pros_spinner'] == 'test'){
			require_once 'test.php';
 			exit;
		}
	}
}
add_action('parse_request', 'pros_spinner_parse_request');



function pros_spinner_query_vars($vars) {
	$vars[] = 'pros_spinner';
	return $vars;
}
add_filter('query_vars', 'pros_spinner_query_vars');

/*
 * bulk pin ajax
 */
//require_once 'asajax.php';

/*
 * LOG PAGE
*/
//require_once 'log.php';


/*
 * spinner scheduler one post each 30 second
 */
//require_once('spinner_schedule.php');

/*
 * DB TABLES
 */

//register_activation_hook( __FILE__, 'create_table_pros_spinner' );
//require_once 'tables.php';

/*
 * custom coulmn spin status
 *
 */

/*
$wp_spinner_types = get_option('wp_spinner_types',array('post','product') );

foreach ($wp_spinner_types as $type){
	add_filter('manage_'.$type.'_posts_columns' , 'pros_spinner_posts_columns');
}


add_action("manage_posts_custom_column",  "pros_spinner_columns_display");

//add field function
function pros_spinner_posts_columns($columns){

	return array_merge($columns,
              array('Spin_Status' => 'Spin<br>Status' ));

}

//display field
function pros_spinner_columns_display($coulmn){
	if($coulmn == 'Spin_Status'){
		global $post;
		$post_id=$post->ID;

		//check if scheduled
		$sched=get_post_meta($post_id, 'pros_spinner_scheduled', true);

		if(trim($sched) != ''){
			echo 'Scheduled';
		}else{

			//not scheduled check if spinned
			$spinned_cnt=get_post_meta($post_id, 'spinned_cnt', true);

			if( !empty($spinned_cnt) ){

				echo 'Spinnned';

			}else{
				echo '--';
			}

		}

	}
}

*/


// nospin shortcode skip
function pros_spinner_nospin_shortcode( $atts, $content = null ) {
	return $content;
}
add_shortcode( 'nospin', 'pros_spinner_nospin_shortcode' );


/*
 * deandev widget
 */
//require_once 'widget.php';

/*
 * update
 */
//require_once 'updated.php';

/*
 * rating
 */
//require_once('rating.php');

/*
 * License
 */
//require_once 'aslicense.php';

/*
 * Ajax requests
 */
//require_once 'pajax.php';
