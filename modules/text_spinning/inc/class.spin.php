<?php 
/**
 * spin an article using a treasure database : treasure.dat
 */


class pros_spin_spin{
	
	public $id;
	public $title;
	public $post;
	
	public $article; // spinned article
	
	//coming two variables to be used with the function replaceExecludes
	public $htmlfounds; //not to spin
	public $execludewords; //execluded words from spinning
	
	function pros_spin_spin($id,$title,$post){
		$this->id=$id;
		$this->title=$title;
		$this->post=$post;
	}
	
	/**
	 * function spin wrap : this plugin is a wraper for spin that switches between api spin and internal spin
	 */
	function spin_wrap(){
		
		//check if spinrewriter active 
		$opt= get_option('pros_spin',array());
		
		if( in_array( 'OPT_AUTO_SPIN_REWRITER' , $opt)){
			
			return $this->spin_rewriter();
			
		}elseif( in_array( 'OPT_AUTO_SPIN_WORDAI' , $opt) ){
		
			return $this->spin_wordai();

		}elseif( in_array( 'OPT_AUTO_SPIN_TBS' , $opt) ){
			
				return $this->spin_tbs();
				
				
		}elseif( in_array( 'OPT_AUTO_SPIN_CP' , $opt) ){
						
					return $this->spin_cp();
					
		}elseif( in_array( 'OPT_AUTO_SPIN_SC' , $opt) ){
		
			return $this->spin_sc();

		}elseif( in_array( 'OPT_AUTO_SPIN_CR' , $opt) ){
			
			return $this->spin_cr();
			
			
		}else{
			
			return $this->spin();
			
		}
		
		
	}
	
	/**
	 * function spin rewriter : using the spin rewriter api 
	 */
	function spin_rewriter(){
	 
		
		//chek if username and passowrd found
		$pros_spinner_email= get_option('pros_spinner_email','');
		$pros_spinner_password=get_option('pros_spinner_password','');
		$opt= get_option('pros_spin',array());
		$pros_spinner_quality = get_option('pros_spinner_quality','medium');
		$pros_spinner_execlude = get_option ( 'pros_spinner_execlude', '' );
		 
		//execlude title words
		if(in_array('OPT_AUTO_SPIN_TITLE_EX',$opt)){
			$extitle=explode(' ', $this->title);

			$pros_spinner_execlude = explode("\n", $pros_spinner_execlude);
			$pros_spinner_execlude= array_filter( array_merge($pros_spinner_execlude ,$extitle ));
			$pros_spinner_execlude=implode(",", $pros_spinner_execlude);
		
		
		}else{
			
			$pros_spinner_execlude = array_filter( explode("\n", $pros_spinner_execlude));
			$pros_spinner_execlude=implode(",", $pros_spinner_execlude);
			
		}
		
		
		pros_spinner_log_new('spinning', 'Trying to use spinrewriter api');
		
		if(trim($pros_spinner_email) != '' && trim($pros_spinner_password) != '' ){
			
			//running a quote call 
			require_once("SpinRewriterAPI.php");
			
			// Authenticate yourself.
			$spinrewriter_api = new SpinRewriterAPI($pros_spinner_email, $pros_spinner_password);
				
			// Make the actual API request and save response as a native PHP array.
			$api_response = $spinrewriter_api->getQuota();
			
			//check if response is a valid response i.e is array
			if(isset($api_response['status'])){
				
				//check if reponse status is OK or Error 
				if($api_response['status'] == 'OK'){
					
					//let's check if quote available 
					if($api_response['api_requests_available'] > 0){
						
						pros_spinner_log_new('SpinRewriter', 'Quota '. $api_response['api_requests_available']);
						
						$protected_terms = "John, Douglas Adams, then";
						$spinrewriter_api->setProtectedTerms($pros_spinner_execlude);
						
						// (optional) Set whether the One-Click Rewrite process automatically protects Capitalized Words outside the article's title.
						if(in_array('OPT_AUTO_SPIN_AutoProtectedTerms', $opt)){
							$spinrewriter_api->setAutoProtectedTerms(true);
						}else{
							$spinrewriter_api->setAutoProtectedTerms(false);
						}
						
						// (optional) Set the confidence level of the One-Click Rewrite process.
						$spinrewriter_api->setConfidenceLevel($pros_spinner_quality);
						
						
						// (optional) Set whether the One-Click Rewrite process uses nested spinning syntax (multi-level spinning) or not.
						if(in_array('OPT_AUTO_SPIN_NestedSpintax', $opt)){
							$spinrewriter_api->setNestedSpintax(true);
						}else{
							$spinrewriter_api->setNestedSpintax(false);
						}
					 
						
						// (optional) Set whether Spin Rewriter rewrites complete sentences on its own.
						if(in_array('OPT_AUTO_SPIN_AutoSentences', $opt)){
							$spinrewriter_api->setAutoSentences(true);
						}else{
							$spinrewriter_api->setAutoSentences(false);
						}
					 
						
						// (optional) Set whether Spin Rewriter rewrites entire paragraphs on its own.
						if(in_array('OPT_AUTO_SPIN_AutoParagraphs', $opt)){
							$spinrewriter_api->setAutoParagraphs(true);
						}else{
							$spinrewriter_api->setAutoParagraphs(false);
						}
						
						// (optional) Set whether Spin Rewriter writes additional paragraphs on its own.
						if(in_array('OPT_AUTO_SPIN_AutoNewParagraphs', $opt)){
							$spinrewriter_api->setAutoNewParagraphs(true);
						}else{
							$spinrewriter_api->setAutoNewParagraphs(false);
						}
						
						// (optional) Set whether Spin Rewriter changes the entire structure of phrases and sentences.
						if(in_array('OPT_AUTO_SPIN_AutoSentenceTrees', $opt)){	
							$spinrewriter_api->setAutoSentenceTrees(true);
						}else{
							$spinrewriter_api->setAutoSentenceTrees(false);
						}
						
						// (optional) Set the desired spintax format to be used with the returned spun text.
						$spinrewriter_api->setSpintaxFormat("{|}");
						
						
						// Make the actual API request and save response as a native PHP array.
						$text = "John will book a room. Then he will read a book by Douglas Adams.";
						
						$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
						
						$api_response2 = $spinrewriter_api->getTextWithSpintax($article);
						
						//validate reply with OK 
						if(isset($api_response2['status'])){
							
							//status = OK
							if($api_response2['status']== 'OK'){
								
								pros_spinner_log_new('SpinRewriter', 'status is ok i.e valid content returned' );

								$article=$api_response2['response'];
								
								$this->article=$article;
								
								
								//now article contains the synonyms on the form {test|test2}
								return $this->update_post();
								
							}else{
								pros_spinner_log_new('SpinRewriter says', $api_response2['response'] );
							}
							
						}else{
							pros_spinner_log_new('SpinRewriter', 'We could not get valid response ' );
						}
						
						 
						
					}else{
						pros_spinner_log_new('SpinRewriter says', $api_response['response'] );
					}
					
				}else{
					pros_spinner_log_new('SpinRewriter says', $api_response['response'] );
				}
				
			}else{
				pros_spinner_log_new('spinning', 'Trying to use spinrewriter api');
			}
			 
		}//found email and password 
		
		pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
		
	}
	
	/**
	 * WordAI spinning function
	 * 
	 */
	function spin_wordai(){
		
		
		pros_spinner_log_new('spinning', 'Trying to use WordAi api');
		
		//wordai options
		$pros_spinner_wordai_email = trim( get_option('pros_spinner_wordai_email',''));
		$pros_spinner_wordai_password = trim(get_option('pros_spinner_wordai_password',''));
		$pros_spinner_wordai_quality = trim( get_option('pros_spinner_wordai_quality','75'));
		 
		
		$opt= get_option('pros_spin',array());
		
		//check if email and password is saved
		if(trim($pros_spinner_wordai_email) != '' && trim($pros_spinner_wordai_password) != ''){
 
			//good we now have an email and password let's try 
			
			//get article
			$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
			
			//mask the execluded parts with astrics
			$article = $this->replaceExecludes($article,$opt);
			 
	
			 
			//curl ini
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER,0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT,60);
			curl_setopt($ch, CURLOPT_REFERER, 'http://www.bing.com/');
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8');
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Good leeway for redirections.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Many login forms redirect at least once.
			 
			
			//curl post
			$curlurl="http://wordai.com/users/regular-api.php";
			$curlpost= "s=". urlencode($article) ."&quality=$pros_spinner_wordai_quality&email=$pros_spinner_wordai_email&pass=$pros_spinner_wordai_password&output=json&nonested=on";
			
			//sentence
			if(in_array('OPT_AUTO_SPIN_WORDAI_SENTENCE', $opt)){
				$curlpost = $curlpost.='&sentence=on';
			}
			
			//Paragraph
			if(in_array('OPT_AUTO_SPIN_WORDAI_PARAGRAPH', $opt)){
				$curlpost = $curlpost.='&paragraph=on';
			}
			
			 
			curl_setopt($ch, CURLOPT_URL, $curlurl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $curlpost); 
			$exec=curl_exec($ch);
			$x=curl_error($ch);
			
			//validate result 
			
			if(stristr($exec, '{')){

				//good it is json let's verify 
				$jsonReply = json_decode($exec);
				
				//verify status either success or failure 
				if( isset( $jsonReply->status) ){
					
					if($jsonReply->status  == 'Success'){
						
						//good the wordai spinned the content successfully 
						$this->article=$this->restoreExecludes( $jsonReply->text ) ;
						
						//report success
						pros_spinner_log_new('WordAI Success', 'WordAI returned content successfully pid:#'.$this->id) ;
						
						//now article contains the synonyms on the form {test|test2}
						return $this->update_post();
						
					}elseif ($jsonReply->status  == 'Failure' ){
						pros_spinner_log_new('WordAI Err', 'WordAI returned an error: '.$jsonReply->error  );
					}else{
						
						pros_spinner_log_new('WordAI Err', 'Unknown status '.$jsonReply->status );
					}
					
				}else{
					pros_spinner_log_new('WordAI Err', 'Can not find reply status with decoded json');
				}
				
				
			}else{
				pros_spinner_log_new('WordAI Err', 'We issued the request but the response does not contain expected json');
			}//response does not even contain json

			 
			
			
		}//no email or password saved
		
		
		//failed to use wordai
		pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
		
	}
	
	/**
	 * TheBestSPinner spinning function
	 *
	 */
	function spin_tbs(){
	
		pros_spinner_log_new('spinning', 'Trying to use TBS api');
	
		//TBS options
		$pros_spinner_tbs_email 	    = get_option('pros_spinner_tbs_email','');
		$pros_spinner_tbs_password 	= get_option('pros_spinner_tbs_password','');
		$pros_spinner_tbs_maxsyns 	= get_option('pros_spinner_tbs_maxsyns','');
		
		if(! is_numeric($pros_spinner_tbs_maxsyns) && $pros_spinner_tbs_maxsyns > 0 ){
			$pros_spinner_tbs_maxsyns = 3;
		}
		
		$pros_spinner_tbs_quality 	= get_option('pros_spinner_tbs_quality','');

		if($pros_spinner_tbs_quality != 1 && $pros_spinner_tbs_quality != 2 && $pros_spinner_tbs_quality != 3){
			$pros_spinner_tbs_quality = 3;
		}
		
		$tbs_protected  =   get_option('pros_spinner_execlude','');  
	
		$opt= get_option('pros_spin',array());
	
		//check if email and password is saved
		if(trim($pros_spinner_tbs_email) != '' && trim($pros_spinner_tbs_password) != ''){
	
			//good we now have an email and password let's try
				
			//get article
			$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
			$article = $this->replaceExecludes($article, $opt);
			
			//$article ='Here is an example.';
				
			//curl ini
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER,0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT,60);
			curl_setopt($ch, CURLOPT_REFERER, 'http://www.bing.com/');
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8');
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Good leeway for redirections.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Many login forms redirect at least once.
	
			
			$url = 'http://thebestspinner.com/api.php';
			
			// $testmethod = 'identifySynonyms';
			$testmethod = 'replaceEveryonesFavorites';
			
			// Build the data array for authenticating.
			
			$data = array ();
			$data ['action'] = 'authenticate';
			$data ['format'] = 'php'; // You can also specify 'xml' as the format.
			
			// The user credentials should change for each UAW user with a TBS account.
			 
			if(trim($tbs_protected) != ''){
				$tbs_protected = explode("\n", $tbs_protected);
				$tbs_protected = array_filter($tbs_protected);
				$tbs_protected = array_map('trim', $tbs_protected);
				$tbs_protected = implode(',', $tbs_protected);
			}
			
			//add , if not exists
			if( stristr($tbs_protected, ',')  ){
				$tbs_protected = $tbs_protected .',';
			}
			
			$data ['username'] = $pros_spinner_tbs_email;
			$data ['password'] = $pros_spinner_tbs_password;
			
			  
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			$exec=curl_exec($ch);
			$x=curl_error($ch);
				

			  
			
			if(stristr($exec, 'a:')){
	
				//good it is unsersialzed array verify
				$exec = unserialize($exec);
				
				 
				//verify status either success or failure
				if( isset( $exec['success']) ){
					
					if( $exec['success']  == true ){
						//good we got valid session to use
						$session = $exec ['session'];
							
						// Build the data array for the example.
						$data = array ();
						$data ['session'] = $session;
						$data ['format'] = 'php'; // You can also specify 'xml' as the format.
						$data ['protectedterms'] = $tbs_protected ;
						$data ['text'] =   ( $article );
						$data ['action'] = $testmethod;
						$data ['maxsyns'] = $pros_spinner_tbs_maxsyns; // The number of synonyms per term.
					
						
						if ($testmethod == 'replaceEveryonesFavorites') {
							// Add a quality score for this method.
							$data ['quality'] = $pros_spinner_tbs_quality;
						}
						
 
						curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_POST, true);
						curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
						$exec=curl_exec($ch);
						$x=curl_error($ch);
						
 						if(stristr($exec, 'a:')){
 							
 							$exec = unserialize($exec);
 							
 							//valid serialized reply array
 							if ($exec ['success'] == true) {

 								//good successfully spinned article here
 								$this->article=  $this->restoreExecludes($exec['output']);
 								
 								//report success
 								pros_spinner_log_new('TBS Success', 'TBS returned content successfully pid:#'.$this->id) ;
 								
 								//now article contains the synonyms on the form {test|test2}
 								return $this->update_post();
 								
 								
 							}else{
 								
 								if(isset($exec['error'])){
 									pros_spinner_log_new('TBS Err', 'login success but spin request returned an error:'.$exec['error']);
 								}else{
 									pros_spinner_log_new('TBS Err','niether success or error ');
 								}
 								
 							}
 							
 						}else{
 							
 							pros_spinner_log_new('TBS Err', 'login success but spin request did not return valid unserialized array');
 							
 						}							
						
						
					}elseif( isset($exec['error'])  ){
						pros_spinner_log_new('TBS Err', 'Login status is not success:'.$exec['error']);
					}else{
						pros_spinner_log_new('TBS Err', 'can not find success or error');
					}
							
				}else{
					pros_spinner_log_new('TBS Err', 'Can not find reply status with decoded Arr');
				}
	
	
			}else{
				pros_spinner_log_new('TBS Err', 'We issued the LOGIN request but the response does not contain expected serialized array');
			}//response does not even contain json
	
	
		
		
		}//no email or password saved
	
	
					//failed to use wordai
					pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
	
	}
	
	/* ContentProfessor spinning function
	*
	*/
	function spin_cp(){
	
		pros_spinner_log_new('spinning', 'Trying to use ContentProfessor api');
	
		//CP options 
		$pros_spinner_cp_email 	    = get_option('pros_spinner_cp_email','');
		$pros_spinner_cp_password 	    = get_option('pros_spinner_cp_password','');
		$pros_spinner_cp_language 	    = get_option('pros_spinner_cp_language','en');
		$pros_spinner_cp_limit 	    = get_option('pros_spinner_cp_limit','5');
		$pros_spinner_cp_quality 	    = get_option('pros_spinner_cp_quality','ideal');
		$pros_spinner_cp_synonym_set 	    = get_option('pros_spinner_cp_synonym_set','global');
		$pros_spinner_cp_min_words_count 	    = get_option('pros_spinner_cp_min_words_count','1');
		$pros_spinner_cp_max_words_count 	    = get_option('pros_spinner_cp_max_words_count','7');
		$pros_spinner_cp_type = get_option('pros_spinner_cp_type','free');
	
		 
 		$opt= get_option('pros_spin',array());
	

		//check if email and password is saved
		if(trim($pros_spinner_cp_email) != '' && trim($pros_spinner_cp_password) != ''){
	
			//good we now have an email and password let's try
	
			//get article
			$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
	
			 
			//curl ini
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER,0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT,60);
			curl_setopt($ch, CURLOPT_REFERER, 'http://www.bing.com/');
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8');
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Good leeway for redirections.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Many login forms redirect at least once.
	

			//build session url
			$url = 'http://www.contentprofessor.com/member_'.$pros_spinner_cp_type.'/api/get_session?format=json&login='.trim($pros_spinner_cp_email).'&password='.trim($pros_spinner_cp_password);

			//process request
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
 			$exec=curl_exec($ch);
			$x=curl_error($ch);
	
			 			
				
			if(stristr($exec, '{')){
	
				//good it is unsersialzed array verify
				$exec = json_decode($exec);

		 
				
				//verify status either success or failure
				if(  isset($exec->result)  ){
				 
					if( isset($exec->result->data->session) ){
						
						
						//good we got valid session to use
						$session = $exec->result->data->session;

						$article = $this->replaceExecludes($article, $opt);
						
						$url = "http://www.contentprofessor.com/member_".$pros_spinner_cp_type."/api/include_synonyms?format=json&session=".$session."&language=$pros_spinner_cp_language&text=".urlencode($article)."&limit=$pros_spinner_cp_limit&quality=$pros_spinner_cp_quality&synonym_set=$pros_spinner_cp_synonym_set&min_words_count=$pros_spinner_cp_min_words_count&max_words_count=$pros_spinner_cp_max_words_count";
						
						if(in_array('OPT_AUTO_SPIN_CP_REMOVAL', $opt)){
							$url = $url + '&removal_on=1' ;  
						}
						
						if(in_array('OPT_AUTO_SPIN_CP_EXECLUDE', $opt)){
							$url = $url + '&excludes_on=1' ;
						}
						
						if(in_array('OPT_AUTO_SPIN_CP_STOP', $opt)){
							$url = $url + '&exclude_stop_words=1' ;
						}
						
						
						
						curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_POST, true);
						curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
						$exec=curl_exec($ch);
						$x=curl_error($ch);
						
						 
						if(stristr($exec, '{')){
	
							$exec = json_decode($exec);
	
							//valid json decoded reply array
							if ( isset($exec->result->data->text) ) {
	
							 
								//good successfully spinned article here
								
								$article  = preg_replace('{<span class="word" id=".*?">(.*?)</span>}su', "$1", $exec->result->data->text);
								
								$this->article =  $this->restoreExecludes($article);
									
								//report success
								pros_spinner_log_new('CP Success', 'CP returned content successfully pid:#'.$this->id) ;
									
								//now article contains the synonyms on the form {test|test2}
								return $this->update_post();
									
									
							}else{
									
								if(isset( $exec->result->error->description )){
									pros_spinner_log_new('CP Err', 'login success but spin request returned an error:'.$exec->result->error->description);
								}else{
									pros_spinner_log_new('CP Err','niether success or error ');
								}
									
							}
	
						}else{
	
							pros_spinner_log_new('CP Err', 'We issued the Rewrite request but the response does not contain expected valid json');
	
						}
	
	
					}elseif( isset( $exec->result->error->description )  ){
						pros_spinner_log_new('CP Err', 'Login status is not success:'.$exec->result->error->description);
					}else{
						pros_spinner_log_new('CP Err', 'can not find success or error');
					}
						
				}else{
					pros_spinner_log_new('CP Err', 'Can not find reply result with decoded Json');
				}
	
	
			}else{
				pros_spinner_log_new('ContentProfessor Err', 'We issued the LOGIN request but the response does not contain expected valid json');
			}//response does not even contain json
	
	
	
	
		}//no email or password saved
	
	
		//failed to use wordai
		pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
	
	}
	
	/**
	 * Spinner chief spinning function
	 */
	function spin_sc(){

		pros_spinner_log_new('spinning', 'Trying to use SpinnerChief api');
		
		 
		//sc spinnerchief
		$pros_spinner_sc_replacetype = get_option('pros_spinner_sc_replacetype','0');
		$pros_spinner_sc_wordquality = get_option('pros_spinner_sc_wordquality','0');
		$pros_spinner_sc_thesaurus = get_option('pros_spinner_sc_thesaurus','English');
		$pros_spinner_sc_Wordscount = get_option('pros_spinner_sc_Wordscount','5');
		$pros_spinner_sc_spinfreq = get_option('pros_spinner_sc_spinfreq','4');
		$pros_spinner_sc_password = get_option('pros_spinner_sc_password','');
		$pros_spinner_sc_email = get_option('pros_spinner_sc_email','');
		
			
		$opt= get_option('pros_spin',array());
		
		
		//check if email and password is saved
		if(trim($pros_spinner_sc_email) != '' && trim($pros_spinner_sc_password) != ''){
		
			//good we now have an email and password let's try
		
			//get article
			$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
			$article = $this->replaceExecludes($article, $opt);
		
			//curl ini
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER,0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT,60);
			curl_setopt($ch, CURLOPT_REFERER, 'http://www.bing.com/');
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8');
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Good leeway for redirections.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Many login forms redirect at least once.
		
		
			//build session url
			$url = "http://api.spinnerchief.com:443/apikey=5adb5b9077d04b14b&username=".urlencode($pros_spinner_sc_email)."&password=".urlencode($pros_spinner_sc_password);
		
			//protectwords protect your words here
			
			//build parameters
			$url.="&spinfreq=".$pros_spinner_sc_spinfreq;
			$url.="&Wordscount=".$pros_spinner_sc_Wordscount;
			$url.="&wordquality=".$pros_spinner_sc_wordquality;
			$url.="&tagprotect=[]";
			$url.="&original=1";
			$url.="&thesaurus=".$pros_spinner_sc_thesaurus;
			$url.="&replacetype=".$pros_spinner_sc_replacetype;
			$url.="&chartype=1&convertbase=0";
			
			
			
			//pos
			if(in_array('OPT_AUTO_SPIN_SC_POS', $opt)){
				$url.="&pos=1";
			}
			
			//GAI
			if( in_array('OPT_AUTO_SPIN_SC_GAI', $opt) ){
				$url.="&UseGrammarAI=1";
			}
			
			//process request
			//curl post
			$curlurl="$url";
			$curlpost=  ( ( $article ) );
			curl_setopt($ch, CURLOPT_URL, $curlurl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $curlpost); 
 			
			$exec=curl_exec($ch);
			$x=curl_error($ch);
 			
			$exec = $this->restoreExecludes($exec);
			
			 
			if(stristr($exec, '911911')){
		 
							
				
				//good we have the article found
				$this->article=  utf8_encode ( $exec );
 				
				//report success
				pros_spinner_log_new('SpinnerChief Success', 'SpinnerChief returned content successfully pid:#'.$this->id) ;
					
				//now article contains the synonyms on the form {test|test2}
				return $this->update_post();

			}elseif(stristr($exec, 'error=')){
				
				//error found
				pros_spinner_log_new('SpinnerChief err', str_replace('error=', '', $exec));
				
			}else{
				
				pros_spinner_log_new('SpinnerChief err', 'neither success or error found in the response');
				
			}
		
		}//no email or password saved
		
		//failed to use wordai
		pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
		
		
		
	}
	
	/* 
	 * ChimpRewriter spinning function
	 *
	 */
	
	function spin_cr(){
	
		pros_spinner_log_new('spinning', 'Trying to use  ChimpRewriter api');
	
		//cr chimprewriter 
		$pros_spinner_cr_email = get_option('pros_spinner_cr_email','');
		$pros_spinner_cr_apikey = get_option('pros_spinner_cr_apikey','');
		$pros_spinner_cr_quality = get_option('pros_spinner_cr_quality','4');
		$pros_spinner_cr_phrasequality = get_option('pros_spinner_cr_phrasequality','3');
		$pros_spinner_cr_posmatch = get_option('pros_spinner_cr_posmatch','3');
			
		$opt= get_option('pros_spin',array());
	
		//check if email and password is saved
		if(trim($pros_spinner_cr_email) != '' && trim($pros_spinner_cr_apikey) != ''){
	
			//good we now have an email and password let's try
	
			//get article
			$article = stripslashes($this->title).' 911911 '. (stripslashes($this->post) );
			$article = $this->replaceExecludes($article, $opt);
	
			//curl ini
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER,0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT,60);
			curl_setopt($ch, CURLOPT_REFERER, 'http://www.bing.com/');
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.8) Gecko/2009032609 Firefox/3.0.8');
			curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // Good leeway for redirections.
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Many login forms redirect at least once.
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	
			
			 
			$curlurl="https://api.chimprewriter.com/ChimpRewrite";
			$curlpost="email=".trim($pros_spinner_cr_email)."&apikey=".trim($pros_spinner_cr_apikey)."&quality=".$pros_spinner_cr_quality."&text=".urlencode($article)."&aid=none&tagprotect=[|]";
			
			$curlpost = $curlpost. '&phrasequality='.$pros_spinner_cr_phrasequality;
			$curlpost = $curlpost. '&posmatch='.$pros_spinner_cr_posmatch;
 
			//sentense rewrite
			if(in_array('OPT_AUTO_SPIN_CR_SREWRITE', $opt)){
				$curlpost = $curlpost. '&sentencerewrite=1' ;
			}
			 
			if(in_array('OPT_AUTO_SPIN_CR_GCHECK', $opt)){
				$curlpost = $curlpost. '&grammarcheck=1' ;
			}
			
			if(in_array('OPT_AUTO_SPIN_CR_reorderparagraphs', $opt)){
				$curlpost = $curlpost. '&reorderparagraphs=1' ;
			}
			
			
			if(in_array('OPT_AUTO_SPIN_CR_replacephraseswithphrases', $opt)){
				$curlpost = $curlpost. '&replacephraseswithphrases=1' ;
			}
			
			if(in_array('OPT_AUTO_SPIN_CR_spintidy', $opt)){
				$curlpost = $curlpost. '&spintidy=0' ;
			}
			
			
	
			
			curl_setopt($ch, CURLOPT_URL, $curlurl);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $curlpost);
			
			$exec=curl_exec($ch);
			$x=curl_error($ch);
			
			 
	
			if(stristr($exec, '{')){
	
				//good it is unsersialzed array verify
				$exec = json_decode($exec);
	  
				//verify status either success or failure
				if(  isset($exec->status)  ){
						
					if( isset($exec->output) && trim($exec->status) == 'success'  ){
	
						
						
						//good successfully spinned article here
						$this->article=  $this->restoreExecludes( $exec->output);
							
						//report success
						pros_spinner_log_new('CR Success', 'CP returned content successfully pid:#'.$this->id) ;
							
						//now article contains the synonyms on the form {test|test2}
						return $this->update_post();
							
	
	
					}elseif( trim($exec->status) == 'failure'   ){
						pros_spinner_log_new('CR Err', 'Login status is not success:'.$exec->output);
					}else{
						pros_spinner_log_new('CR Err', 'can not find success or error');
					}
	
				}else{
					pros_spinner_log_new('CR Err', 'Can not find reply result with decoded Json');
				}
	
	
			}else{
				pros_spinner_log_new('ChimpRewriter Err', 'We issued the LOGIN request but the response does not contain expected valid json');
			}//response does not even contain json
	
	
	
	
		}//no email or password saved
	
	
		//failed to use wordai
		pros_spinner_log_new('SpinRewriter Skip', 'We will use the internal synonyms database instead');
		return $this->spin();
	
	}
	
	
/*
 * function pros_spin_spin : spins an article by replacing synonyms from database treasure.dat
 * @article: the article to be spinned
 * return : the spinned article spinned or false if error.
 */	
	function spin() {
 
		$opt= get_option('pros_spin',array());
		
		
		$article = stripslashes($this->title).'**9999**'.stripslashes($this->post);
		 
		//match links
		$htmlurls = array();
		
		if( ! in_array( 'OPT_AUTO_SPIN_LINKS' , $opt)){
			preg_match_all( "/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*?)<\/a>/s" ,$article,$matches,PREG_PATTERN_ORDER);
			$htmlurls=$matches[0];
		}

		//execlude urls pasted OPT_AUTO_SPIN_URL_EX
		$urls_txt = array();
		if(in_array('OPT_AUTO_SPIN_URL_EX', $opt)){
			//ececluding the capped words
			preg_match_all('/https?:\/\/[^<\s]+/', $article,$matches_urls_txt);
			$urls_txt = $matches_urls_txt[0];
		}
  
		 
		//html tags 
		preg_match_all("/<[^<>]+>/is",$article,$matches,PREG_PATTERN_ORDER);
		$htmlfounds=$matches[0];
		

		//no spin items
		preg_match_all('{\[nospin\].*?\[/nospin\]}s', $article,$matches_ns);
		$nospin = $matches_ns[0];
		
		 
		//extract all fucken shortcodes
		$pattern="\[.*?\]";
		preg_match_all("/".$pattern."/s",$article,$matches2,PREG_PATTERN_ORDER);
		$shortcodes=$matches2[0];
		
		//javascript
		preg_match_all("/<script.*?<\/script>/is",$article,$matches3,PREG_PATTERN_ORDER);
		$js=$matches3[0];
		
		
		//numbers \d*
		preg_match_all('/\d{2,}/s', $article,$matches_nums);
		$nospin_nums = $matches_nums[0];
		sort($nospin_nums);
		$nospin_nums = array_reverse($nospin_nums);
		
		 
		//extract all reserved words 
		$pros_spinner_execlude=get_option('pros_spinner_execlude','');
		$execlude=explode("\n",trim($pros_spinner_execlude));
		
		//execlude title words 
		$autospin=get_option('pros_spin',array());
		if(in_array('OPT_AUTO_SPIN_TITLE_EX',$autospin)){
			 $extitle=explode(' ', $this->title);
			 $execlude=array_merge($execlude ,$extitle );
		}
		
		//execlude capital letters
		$capped = array();
		if(in_array('OPT_AUTO_SPIN_CAP_EX', $opt)){
			//ececluding the capped words
			preg_match_all('{\b[A-Z][a-z]+\b}', $article,$matches_cap);
			$capped = $matches_cap[0];
			sort($capped);
			$capped=array_reverse($capped);
			 
			
		}
		 
		
		//execlude curly quotes
		$curly_quote = array();
		if(in_array('OPT_AUTO_SPIN_CURLY_EX', $opt)){

			//double smart qoute
			preg_match_all('{“.*?”}', $article, $matches_curly_txt);
			$curly_quote = $matches_curly_txt[0];
			
			//single smart quote 
			preg_match_all('{‘.*?’}', $article, $matches_curly_txt_s);
			$single_curly_quote = $matches_curly_txt_s[0];
			
			//&quot;
			preg_match_all('{&quot;.*?&quot;}', $article, $matches_curly_txt_s_and);
			$single_curly_quote_and = $matches_curly_txt_s_and[0];

			//&#8220; &#8221;
			preg_match_all('{&#8220;.*?&#8221}', $article, $matches_curly_txt_s_and_num);
			$single_curly_quote_and_num = $matches_curly_txt_s_and_num[0];

			//regular duouble quotes
			$curly_quote_regular = array();
			if(in_array('OPT_AUTO_SPIN_CURLY_EX_R',$opt )){
				preg_match_all('{".*?"}', $article, $matches_curly_txt_regular);
				$curly_quote_regular = $matches_curly_txt_regular[0];
			}	
		
			$curly_quote=array_merge($curly_quote , $single_curly_quote ,$single_curly_quote_and,$single_curly_quote_and_num,$curly_quote_regular);
			
			
		}
		
		 
		
		$exword_founds=array(); // ini
		
		foreach($execlude as $exword){
 
			if(preg_match('/\b'. preg_quote(trim($exword),'/') .'\b/u', $article)) {
				$exword_founds[]=trim($exword);
			}
		}
		
		
		// merge shortcodes to html which should be replaced
		$htmlfounds=array_merge(   $nospin, $js, $htmlurls, $curly_quote ,$htmlfounds , $urls_txt  ,$shortcodes ,$nospin_nums ,$capped  );
 		
		$htmlfounds = array_filter(array_unique($htmlfounds));
		  
	 	$i=1;
		foreach($htmlfounds as $htmlfound){
			$article=str_replace($htmlfound,'('.str_repeat('*', $i).')',$article);
			  
			$i++;
		}
		
	
		
	
	 	//echo $article;
		//replacing execluded words 
		foreach($exword_founds as $exword){
			if(trim($exword) != ''){
				$article = preg_replace('/\b'. preg_quote(trim($exword),'/').'\b/u', '('.str_repeat('*', $i).')' , $article);
				$i++;
			}
		}
		
		 
		
	
		//open the treasures db

		$pros_spinner_lang=get_option('pros_spinner_lang','en');

		//original synonyms	
		$file=file(dirname(__FILE__)  .'/treasures_'.$pros_spinner_lang.'.dat');
		
		//deleted synonyms update 
		$deleted= array_unique( get_option('pros_spinner_deleted_'.$pros_spinner_lang ,array() ) );
		foreach($deleted as $deleted_id){
			unset($file[$deleted_id]);
		}
		
		//updated synonyms update 
		$modified=get_option('pros_spinner_modified_'.$pros_spinner_lang ,array() );
		
		foreach($modified as $key=> $val){
			if(isset($file[$key])){
				$file[$key]=$val;
			}
		}
		
		//custom synonyms on top of synonyms
		$custom=get_option('pros_spinner_custom_'.$pros_spinner_lang ,array() );
		$file= array_merge($custom ,$file );
		//echo $article;
		
		//checking all words for existance
		foreach($file as $line){
			
			//echo 'line:'.$line;
			
			//each synonym word
			$synonyms=explode('|',$line);
			$synonyms=array_map('trim',$synonyms);
			
			
			if(in_array('OPT_AUTO_SPIN_ACTIVE_SHUFFLE', $autospin) ){
				$synonyms2=$synonyms;
			}else{
				$synonyms2=array($synonyms[0]);
			}
			
			
			
			foreach($synonyms2 as $word){
				//echo ' word:'. $word;
				 
				$word=str_replace('/','\/',$word);
				if(trim($word) != '' & ! in_array( strtolower($word), $execlude)){
					
					 //echo $word.' ';
				 
					//echo '..'.$word;
					if(preg_match('/\b'. $word .'\b/u', $article)) {
					 
					  //replace the word with it's hash str_replace(array("\n", "\r"), '',$line)and add it to the array for restoring to prevent duplicate
					   
						//restructure line to make the original word as the first word
						$restruct=array($word);
						$restruct=array_merge($restruct,$synonyms);
						$restruct=array_unique($restruct);
						//$restruct=array_reverse($restruct);
						$restruct=implode('|',$restruct);
						
						
						$founds[md5($word)]= str_replace(array("\n", "\r"), '',$restruct) ;
						
						$md = md5($word);
						$article=preg_replace('/\b'.$word.'\b/u',$md,$article);
						
						//fix hivens like one-way
						$article= str_replace('-'.$md, '-'.$word, $article);
						$article= str_replace($md.'-', $word.'-', $article);
						
				
					  
					}
					
					
					//replacing upper case words
					$uword=$this->pros_spinner_mb_ucfirst($word);
					
					//echo ' uword:'.$uword;
					
					if(preg_match('/\b'. $uword .'\b/u', $article)) {

						$restruct=array($word);
						$restruct=array_merge($restruct,$synonyms);
						$restruct=array_unique($restruct);
						//$restruct=array_reverse($restruct);
						$restruct=implode('|',$restruct);
							
						
						$founds[md5( $uword )]=  $this->pros_spinner_upper_case( str_replace(array("\n", "\r"), '',$restruct)) ;
					
						$article=preg_replace('/\b'.$uword.'\b/u',md5($uword),$article);
							
					}
					
					 
					
					
					
				}
			}
			
	 
			
		}//foreach line of the synonyms file
		
	 	
		//restore html tags
		$i=1;
		foreach($htmlfounds as $htmlfound){
			$article=str_replace( '('.str_repeat('*', $i).')',$htmlfound,$article);
			$i++;
		}
		
		
		//replacing execluded words
		foreach($exword_founds as $exword){
			if(trim($exword) != ''){
				$article=str_replace( '('.str_repeat('*', $i).')',$exword,$article);
				$i++;
			}
			
		}
		
		
		//replace hashes with synonyms
		if(count($founds) !=0){
			foreach ($founds as $key=>$val){
				$article=str_replace($key,'{'.$val.'}',$article);
			}
		}
		
	
		//deleting spin and nospin shortcodes
		$article = str_replace(array('[nospin]','[/nospin]'), '', $article);
		
		$this->article=$article;
		
	 
		//now article contains the synonyms on the form {test|test2}
		return $this->update_post();

		 
	}
	
	// spintax post , update data , return array of data
	function update_post(){
		
		$spinned =$this->article;
		 
		//synonyms
		if(stristr($spinned, '911911')){
			$spinned=str_replace('911911', '**9999**', $spinned);
		}
			
			
		$spinned_arr=explode('**9999**' , $spinned);
		
		
		$spinned_ttl=$spinned_arr[0];
		$spinned_cnt=$spinned_arr[1];
		
		 
		
		//spintaxed wrirretten instance	 
		require_once('class.spintax.php');
		$spintax=new pros_spinner_Spintax;
		$spintaxed =$spintax->spin($spinned);
		
		 
		
		$spintaxed2=$spintax->editor_form;
		
		$spintaxed_arr=explode('**9999**',$spintaxed);
		$spintaxed_arr2=explode('**9999**',$spintaxed2);
		$spintaxed_ttl=$spintaxed_arr[0];
		$spintaxed_cnt=$spintaxed_arr[1];
		$spintaxed_cnt2=$spintaxed_arr2[1];
		
		
		//update post meta
		$post_id=$this->id;
		update_post_meta($post_id, 'spinned_ttl', $spinned_ttl);
		update_post_meta($post_id, 'spinned_cnt', $spinned_cnt);
		update_post_meta($post_id, 'spintaxed_ttl', $spintaxed_ttl);
		update_post_meta($post_id, 'spintaxed_cnt', $spintaxed_cnt);
		update_post_meta($post_id, 'spintaxed_cnt2', $spintaxed_cnt2);
		update_post_meta($post_id, 'original_ttl', stripslashes($this->title));
		update_post_meta($post_id, 'original_cnt', stripslashes($this->post) );
		
		$return = array();
		$return['spinned_ttl'] =  $spinned_ttl;
		$return['spinned_cnt'] =  $spinned_cnt ;
		$return['spintaxed_ttl'] =  $spintaxed_ttl ;
		$return['spintaxed_cnt'] = $spintaxed_cnt;
		$return['spintaxed_cnt2'] = $spintaxed_cnt2;
		$return['original_ttl'] = $this->title;
		$return['original_cnt'] = $this->post;
		 
		return $return ;
		
	}
	
	// convert to upercase compatible with unicode chars
	function pros_spinner_mb_ucfirst($string)
	{
		
		
		if (function_exists('mb_strtoupper')){
			$encoding="utf8";
			$firstChar = mb_substr($string, 0, 1, $encoding);
			$then = mb_substr($string, 1, mb_strlen($string), $encoding);
			return mb_strtoupper($firstChar, $encoding) . $then;
		}else{
			return ucfirst($string);
		}
	}
	
	
	//check the first letter of the word and upercase words in the line
	function  pros_spinner_upper_case($line){
	
			$w_arr=explode('|',$line);
	
			for( $i=0;$i< count($w_arr);$i++ ){
				$w_arr[$i] =  $this->pros_spinner_mb_ucfirst($w_arr[$i]) ;
			}
	
			$line=implode('|',	$w_arr );
	
			return $line;
	
	
		 
	}
	
	/**
	 * function replaceExecludes
	 * 
	 */
	function replaceExecludes($article,$opt){
		
		//match links
		$htmlurls = array();
		
		if( ! in_array( 'OPT_AUTO_SPIN_LINKS' , $opt)){
			preg_match_all( "/<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*?)<\/a>/s" ,$article,$matches,PREG_PATTERN_ORDER);
			$htmlurls=$matches[0];
		}
		
		//execlude urls pasted OPT_AUTO_SPIN_URL_EX
		$urls_txt = array();
		if(in_array('OPT_AUTO_SPIN_URL_EX', $opt)){
			//ececluding the capped words
			preg_match_all('/https?:\/\/[^<\s]+/', $article,$matches_urls_txt);
			$urls_txt = $matches_urls_txt[0];
		}
		
			
		//html tags
		preg_match_all("/<[^<>]+>/is",$article,$matches,PREG_PATTERN_ORDER);
		$htmlfounds=$matches[0];
		
		
		//no spin items
		preg_match_all('{\[nospin\].*?\[/nospin\]}s', $article,$matches_ns);
		$nospin = $matches_ns[0];
		
			
		//extract all fucken shortcodes
		$pattern="\[.*?\]";
		preg_match_all("/".$pattern."/s",$article,$matches2,PREG_PATTERN_ORDER);
		$shortcodes=$matches2[0];
		
		//javascript
		preg_match_all("/<script.*?<\/script>/is",$article,$matches3,PREG_PATTERN_ORDER);
		$js=$matches3[0];
		
		
		//numbers \d*
		preg_match_all('/\d{2,}/s', $article,$matches_nums);
		$nospin_nums = $matches_nums[0];
		sort($nospin_nums);
		$nospin_nums = array_reverse($nospin_nums);
		
			
		//extract all reserved words
		$pros_spinner_execlude=get_option('pros_spinner_execlude','');
		$execlude=explode("\n",trim($pros_spinner_execlude));
		
		//execlude title words
		$autospin=get_option('pros_spin',array());
		if(in_array('OPT_AUTO_SPIN_TITLE_EX',$autospin)){
			$extitle=explode(' ', $this->title);
			$execlude=array_merge($execlude ,$extitle );
		}
		
		//execlude capital letters
		$capped = array();
		if(in_array('OPT_AUTO_SPIN_CAP_EX', $opt)){
			//ececluding the capped words
			preg_match_all('{\b[A-Z][a-z]+\b}', $article,$matches_cap);
			$capped = $matches_cap[0];
			sort($capped);
			$capped=array_reverse($capped);
		
				
		}
			
		
		//execlude curly quotes
		$curly_quote = array();
		if(in_array('OPT_AUTO_SPIN_CURLY_EX', $opt)){
		
			//double smart qoute
			preg_match_all('{“.*?”}', $article, $matches_curly_txt);
			$curly_quote = $matches_curly_txt[0];
				
			//single smart quote
			preg_match_all('{‘.*?’}', $article, $matches_curly_txt_s);
			$single_curly_quote = $matches_curly_txt_s[0];
				
			//&quot;
			preg_match_all('{&quot;.*?&quot;}', $article, $matches_curly_txt_s_and);
			$single_curly_quote_and = $matches_curly_txt_s_and[0];
		
			//&#8220; &#8221;
			preg_match_all('{&#8220;.*?&#8221}', $article, $matches_curly_txt_s_and_num);
			$single_curly_quote_and_num = $matches_curly_txt_s_and_num[0];
		
			//regular duouble quotes
			$curly_quote_regular = array();
			if(in_array('OPT_AUTO_SPIN_CURLY_EX_R',$opt )){
				preg_match_all('{".*?"}', $article, $matches_curly_txt_regular);
				$curly_quote_regular = $matches_curly_txt_regular[0];
			}
		
			$curly_quote=array_merge($curly_quote , $single_curly_quote ,$single_curly_quote_and,$single_curly_quote_and_num,$curly_quote_regular);
				
				
		}
		
			
		
		$exword_founds=array(); // ini
		
		foreach($execlude as $exword){
		
			if(preg_match('/\b'. preg_quote(trim($exword),'/') .'\b/u', $article)) {
				$exword_founds[]=trim($exword);
			}
		}
		
		
		// merge shortcodes to html which should be replaced
		$htmlfounds=array_merge(   $nospin, $js, $htmlurls, $curly_quote ,$htmlfounds , $urls_txt  ,$shortcodes ,$nospin_nums ,$capped  );
			
		$htmlfounds = array_filter(array_unique($htmlfounds));
		
		$i=1;
		foreach($htmlfounds as $htmlfound){
			$article=str_replace($htmlfound,'('.str_repeat('*', $i).')',$article);
				
			$i++;
		}
		
		
		//echo $article;
		//replacing execluded words
		foreach($exword_founds as $exword){
			if(trim($exword) != ''){
				$article = preg_replace('/\b'. preg_quote(trim($exword),'/').'\b/u', '('.str_repeat('*', $i).')' , $article);
				$i++;
			}
		}
		
		
		//save the exwords 
		$this->htmlfounds = $htmlfounds;
		$this->execludewords = $exword_founds;
		
		return $article;
		
	}
	
	/**
	 * function:restore execludes astrics to real content
	 */
	function restoreExecludes($article){
		
		$htmlfounds = $this->htmlfounds;
		$exword_founds = $this->execludewords;
		
		//restore html tags
		$i=1;
		foreach($htmlfounds as $htmlfound){
			$article=str_replace( '('.str_repeat('*', $i).')',$htmlfound,$article);
			$i++;
		}
		
		
		//replacing execluded words
		foreach($exword_founds as $exword){
			if(trim($exword) != ''){
				$article=str_replace( '('.str_repeat('*', $i).')',$exword,$article);
				$i++;
			}
				
		}
		
		
		//deleting spin and nospin shortcodes
		$article = str_replace(array('[nospin]','[/nospin]'), '', $article);
		
		return $article;
		
	}
	
	
}//end class 