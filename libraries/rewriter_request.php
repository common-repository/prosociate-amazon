<?php

// CONTENT PROFESSOR

class PROS_Spintax_Replace {
	public function process($text) {
		return preg_replace_callback(
		'/\{(((?>[^\{\}]+)|(?R))*)\}/x',
		array($this, 'replace'),
		$text
		);

	}
	 
	public function replace($text) {
		$text = $this->process($text[1]);
		$parts = explode('|', $text);
		return $parts[array_rand($parts)];
	}
}

function prosociate_cprof_rewrite($article) {


	$cp_username = get_option('prossociate_settings-dm-spin-cp-username');
	$cp_password = get_option('prossociate_settings-dm-spin-cp-password');
	$cp_quality = get_option('prossociate_settings-dm-spin-cp-quality');
	$cp_account = get_option('prossociate_settings-dm-spin-cp-account');
	$cp_lang = get_option('prossociate_settings-dm-spin-cp-lang');
	$cp_syn = get_option('prossociate_settings-dm-spin-cp-syn');
	/*$options = get_option("allrewriters_settings");
	$protected_words = $options["general"]["options"]["protected_words"]["value"];
	$options = $options["contentprofessor"]["options"];*/

	$login = $cp_username;
	$pw = $cp_password;
	$quality = $cp_quality;		
	$cprof_language = $cp_lang;		
	$cprof_syn_limit = $cp_syn;		
	$cprof_acc_type = $cp_account;		

	if(isset($article) && isset($quality) && isset($login) && isset($pw)) {

		$article = urlencode($article);

		if($cprof_acc_type == "paid") {
			$cpurl = 'http://www.contentprofessor.com/member_pro/api/get_session?format=xml&login='.$login.'&password='.$pw.'';
		} else {
			$cpurl = 'http://www.contentprofessor.com/member_free/api/get_session?format=xml&login='.$login.'&password='.$pw.'';
		}

		$req = curl_init();
		curl_setopt($req, CURLOPT_URL, $cpurl);
		curl_setopt($req, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($req, CURLOPT_POST, true);
		curl_setopt($req, CURLOPT_POSTFIELDS, $article);
		$result = trim(curl_exec($req));
		curl_close($req);
		
		$pxml = simplexml_load_string($result);
		$session = $pxml->data->session;

		if(!empty($pxml->error->description)) {
			$return["error"] = "Contenprofessor Error: ". $pxml->error->description;	
			return $return;				
		} elseif(!empty($session)) {
		
			if($cprof_acc_type == "paid") {
				$cpurl = 'http://www.contentprofessor.com/member_pro/api/include_synonyms?format=xml&session='.$session.'&quality='.$quality.'&limit='.$cprof_syn_limit.'&language='.$cprof_language.'&text='.$article.'';
			} else {
				$cpurl = 'http://www.contentprofessor.com/member_free/api/include_synonyms?format=xml&session='.$session.'&quality='.$quality.'&limit='.$cprof_syn_limit.'&language='.$cprof_language.'&text='.$article.'';
			}				
			
			$req = curl_init();
			curl_setopt($req, CURLOPT_URL, $cpurl);
			curl_setopt($req, CURLOPT_RETURNTRANSFER,1);
			curl_setopt($req, CURLOPT_POST, true);
			curl_setopt($req, CURLOPT_POSTFIELDS, $article);
			$result = trim(curl_exec($req));
			curl_close($req);
			
			$pxml = simplexml_load_string($result);		

			if(!empty($pxml->error->description)) {
				$return["error"] = "Contenprofessor Error: ". $pxml->error->description;	
				return $return;				
			} elseif(!empty($pxml->data->text)) {

				//STRIP TAFS
				$spintax = new PROS_Spintax_Replace;
				$txt = $pxml->data->text;
				//$txt = (string) $pxml->data->text;	
				$txt = (string) strip_tags($pxml->data->text);	
				$txt = $spintax->process($txt);	
				return $txt;
			} else {
				$return["error"] = "Contenprofessor Error: Empty response.";	
				return $return;				
			}
		} else {
			$return["error"] = "Contenprofessor Session could not be loaded.";	
			return $return;			
		}
	} else {
		$return["error"] = "API Information missing.";	
		return $return;	
	}
}

// SPIN REWRITER

function prosociate_sr_rewrite($article) {

	$sr_email = get_option('prossociate_settings-dm-spin-sr-email');
	$sr_api = get_option('prossociate_settings-dm-spin-sr-api');
	$sr_quality = get_option('prossociate_settings-dm-spin-sr-quality');
	$sr_enable = get_option('prossociate_settings-dm-spin-sr-enable');

	/*$options = get_option("allrewriters_settings");
	$protected_words = $options["general"]["options"]["protected_words"]["value"];
	$options = $options["spinrewriter"]["options"];	*/

	$data = array();	
	$data['email_address'] = $sr_email;			// your Spin Rewriter email address goes here
	$data['api_key'] = $sr_api;	// your unique Spin Rewriter API key goes here
	$data['action'] = "unique_variation";		
	// possible values: 'api_quota', 'text_with_spintax', 'unique_variation', 'unique_variation_from_spintax'
	$data['text'] = $article;
	
	$protected = explode(",", $protected_words);
	$prot = "";
	
	foreach($protected as $pt) {$prot .= trim($pt)."\n";}
	$data['protected_terms'] = $prot;		// protected terms: John, Douglas Adams, then
	$data['confidence_level'] = $sr_quality;							// possible values: 'low', 'medium' (default value), 'high'
	$data['nested_spintax'] = "true";							// possible values: 'false' (default value), 'true'	
	$data_raw = "";
	foreach ($data as $key => $value){
		$data_raw = $data_raw . $key . "=" . urlencode($value) . "&";
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "http://www.spinrewriter.com/action/api");
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data_raw);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$api_response = trim(curl_exec($ch));
	curl_close($ch);	
	$resp = json_decode($api_response, true);
	
	if($resp["status"] == "OK") {
		return $resp["response"];	
	} elseif($resp["status"] == "ERROR") {
		$return["error"] = $resp["response"];	
		return $return;			
	} else {
		$return["error"] = "No response received";	
		return $return;				
	}
}


// TheBestSpinner

function prosociate_tbs_request($url, $data, &$info){

	$fdata = "";
	foreach($data as $key => $val){
		$fdata .= "$key=" . urlencode($val) . "&";
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fdata);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_REFERER, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 80);	
	$html = trim(curl_exec($ch));
	curl_close($ch);
	return $html;
}

function prosociate_tbs_rewrite($text) {

	$bs_username = get_option('prossociate_settings-dm-spin-bs-username');
	$bs_password = get_option('prossociate_settings-dm-spin-bs-password');
	$bs_quality = get_option('prossociate_settings-dm-spin-bs-quality');
	$bs_enable = get_option('prossociate_settings-dm-spin-bs-enable');

	/*$options = get_option("allrewriters_settings");
	$protected_words = $options["general"]["options"]["protected_words"]["value"];
	$options = $options["thebestspinner"]["options"];*/

	$data = array();
	$data['action'] = 'authenticate';
	$data['apikey'] = 'wprobot4b8ff4a5ef0d3';	
	$data['format'] = 'php';
	$data['username'] = $bs_username;
	$data['password'] = $bs_password;
	
	$output = unserialize(prosociate_tbs_request('http://thebestspinner.com/api.php', $data, $info));
	if($output['success']=='true'){

		$session = $output['session'];

		$data = array();
		$data['session'] = $session;
		$data['apikey'] = 'wprobot4b8ff4a5ef0d3';
		$data['format'] = 'php';
		$data['text'] = $text;
		$data['action'] = 'replaceEveryonesFavorites';
		$data['maxsyns'] = '3';
		$data['quality'] = $bs_quality;
		$data['protectedterms'] = urlencode($protected_words);
		
		$output = prosociate_tbs_request('http://thebestspinner.com/api.php', $data, $info);
		$output = unserialize($output);

		if($output['success']=='true'){
			if($spinsave == "Yes") {		
				return stripslashes(str_replace("\r", "<br>", $output['output']));			
			} else {
				
				$newtext = stripslashes(str_replace("\r", "<br>", $output['output']));

				$data = array();
				$data['session'] = $session;
				$data['apikey'] = 'wprobot4b8ff4a5ef0d3';
				$data['format'] = 'php';
				$data['text'] = $newtext;
				$data['action'] = 'randomSpin';
				
				$output = prosociate_tbs_request('http://thebestspinner.com/api.php', $data, $info);
				$output = unserialize($output);

				if($output['success']=='true'){
					return stripslashes(str_replace("\r", "<br>", $output['output']));
				} else {
					if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}
					$return["error"] = __("Rewrite Error: ","allrewriters").$output["error"];
					return $return;				
				}
			}
		} else {
			if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}
			$return["error"] = __("Rewrite Error: ","allrewriters").$output["error"];
			return $return;
		}
	} else {
		if(empty($output["error"])) {$output["error"] = "TBS request has timed out, no response received.";}
		$return["error"] = __("Rewrite Error: ","allrewriters").$output["error"];
		return $return;
	}
}