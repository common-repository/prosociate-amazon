<?php
// returns ASINs, Titles, and some other product data for a search query

class ProssociateSearch {

    // yuri - add sortby, browsenode parameter
    var $keywords, $searchindex, $browsenode, $sortby, $title;
    // yuri - add advanced search options
    var $minprice, $maxprice, $availability, $condition, $manufacturer, $brand, $merchantid, $minpercentageoff, $dmAsinLists;
    var $pure_results, $results, $totalpages, $totalresults, $page;

    var $response;

    // yuri - add sortby, browsenode parameter
    public function __construct($keywords, $searchindex, $browsenode = null, $sortby = null, $page = 1, $dmCategory = null) {
        if($browsenode == 1) {
            $browsenode = null;
        }
        $this->keywords = $keywords;
        $this->searchindex = $searchindex;
        $this->browsenode = $browsenode;
        $this->sortby = $sortby;
        $this->page = $page;

        add_action('wp_ajax_prossociate_search_node', array($this, 'ajax_search_node'));
        // yuri - return sort values
        add_action('wp_ajax_prossociate_sort_values', array($this, 'ajax_sort_values'));

        add_action('wp_ajax_prossociate_manual_browsenode', array($this, 'ajax_manual_browsenode'));
    }

    /**
     * Manually specify browsenodes
     */
    public function ajax_manual_browsenode() {
        // Make sure we only have valid node id
        if(!is_numeric($_REQUEST['node'])) {
            echo 'Please enter valid node';
            die();
        }

        if(AWS_COUNTRY == 'com') {
            $this->manualBrowseNodeByMain($_REQUEST['node']);
        } else {
            $this->manualBrowseNodeByAmazon($_REQUEST['node']);
        }

        die();
    }

    /**
     * Get searchindex of specific browsenode id from amazon
     * @param $nodeId
     */
    private function manualBrowseNodeByAmazon($nodeId) {
        // We can only use 'All' for now
        // Todo amazon.in doesn't support All search index
        $searchIndex = 'All';

        echo $searchIndex;
    }

    /**
     * Get searchindex of specific browsenode id from prosociate.com
     * @param $nodeId
     */
    private function manualBrowseNodeByMain($nodeId) {
        $requestUrl = 'http://prosociate.com/?dmpros=1';
        $data = array(
            'dmpros' => '1',
            'node' => $nodeId
        );
        $url = add_query_arg($data, $requestUrl);
        $request = wp_remote_get($url);
        // Check if error
        if(!is_wp_error($request)) {
            $response = wp_remote_retrieve_body($request);
            $unserializedRespons = unserialize($response);

            // if no searchindex retrieved
            if(empty($unserializedRespons) || $unserializedRespons === 'ERROR')
                $this->manualBrowseNodeByAmazon($nodeId);
            else
                echo $unserializedRespons;

        } else {
            // If error use the manualBrowsenode by amazon
            $this->manualBrowseNodeByAmazon($nodeId);
        }
    }

    /**
     * Set the asin lists
     * @param string $dmAsinLists
     */
    public function setAsinLists($dmAsinLists = '') {
        $this->dmAsinLists = $dmAsinLists;
    }

    // yuri - set advanced search options
    public function set_advanced_options($minprice = '', $maxprice = '', $availability = '', $condition = '', $manufacturer = '', $brand = '', $merchantid = '', $minpercentageoff = '', $title='') {

        if(!is_numeric($minprice))
            $minprice = '';

        if(!is_numeric($maxprice))
            $maxprice = '';

        $this->minprice = $minprice;
        $this->maxprice = $maxprice;
        $this->availability = $availability;
        $this->merchantid = $merchantid;
        $this->condition = $condition;
        $this->manufacturer = $manufacturer;
        $this->brand = $brand;
        $this->minpercentageoff = $minpercentageoff;
        $this->title = $title;
    }

    public function execute($responsegroup = 'Small,OfferSummary,Images,Variations,VariationOffers,Offers,OfferFull', $isAsinLookUp = false) {

        $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

        $amazonEcs->category($this->searchindex);
        $amazonEcs->responseGroup($responsegroup);
        $amazonEcs->title($this->title);
        //$amazonEcs->optionalParameters(array('MerchantId' => 'All', 'Condition' => 'All'));
        // yuri - add sortby parameter
        if (!empty($this->sortby)) {
            $amazonEcs->sortby($this->sortby);
        }
        if ($this->page) {
            $amazonEcs->page($this->page);
        }

        if(!is_numeric($this->minprice))
            $this->minprice = '';

        if(!is_numeric($this->maxprice))
            $this->maxprice = '';

        // yuri - add advanced search options
        if (!empty($this->minprice))
            $amazonEcs->minprice($this->makePrice($this->minprice));
        if (!empty($this->maxprice))
            $amazonEcs->maxprice($this->makePrice($this->maxprice));
        if (!empty($this->availability))
            $amazonEcs->availability($this->availability);
        if (!empty($this->merchantid))
            $amazonEcs->merchantid($this->merchantid);
        if (!empty($this->condition))
            $amazonEcs->condition($this->condition);
        if (!empty($this->manufacturer))
            $amazonEcs->manufacturer($this->manufacturer);
        if (!empty($this->brand))
            $amazonEcs->brand($this->brand);
        if (!empty($this->minpercentageoff))
            $amazonEcs->minpercentageoff($this->minpercentageoff);

        $amazonEcs->category($this->searchindex);
        $amazonEcs->responseGroup($responsegroup);

        //$amazonEcs->merchantid('Amazon');

        if(!$isAsinLookUp) {
            if (!empty($this->keywords)) {
                $response = $amazonEcs->search($this->keywords, $this->browsenode);
            } else {
                $response = $amazonEcs->search('*', $this->browsenode);
            }
        } else {
            $asinString = str_replace(array("\r\n", "\r", "\n"), ",", $this->dmAsinLists);
            // Take only 10 asins
            $asins10 = array(); // Container
            $arrayLists = explode(',', $asinString);
            $ctrr = 0; // Counter
            foreach($arrayLists as $arrayList) {
                // Only get 10
                if($ctrr > 9)
                    break;
                $asins10[] = $arrayList;
                $ctrr++;
            }

            $response = $amazonEcs->lookup($asins10);
        }

        if ($response != '') {
            if ($response->Items->Request->IsValid != 'True') {
                print_r($response);
                throw new Exception('Invalid Request');
            }
        }

        $this->response = $response;

        // yuri - null exception
        $results = array();

        if (count($response->Items->Item) == 1) {

            $item = $response->Items->Item;

            $ASIN = $item->ASIN;
            //var_dump($item->ParentASIN);
            if($item->ParentASIN){
              $ASIN = $item->ParentASIN;
            }
            $DetailPageURL = $item->DetailPageURL;
            $Title = $item->ItemAttributes->Title;

            $results[] = array("ASIN" => $ASIN, "Title" => $Title);
            $items[] = $item;
        } else {

            if (isset($response->Items->Item)) {

                foreach ($response->Items->Item as $item) {
                    $ASIN = $item->ASIN;
                    //var_dump($item->ParentASIN);
                    if($item->ParentASIN){
                      $ASIN = $item->ParentASIN;
                    }
                    $DetailPageURL = $item->DetailPageURL;
                    $Title = $item->ItemAttributes->Title;


                    $results[] = array("ASIN" => $ASIN, "Title" => $Title);
                    $items[] = $item;
                }
            }
        }

        $this->results = $results;
        $this->results_pure = $items;

        // Total pages and results logic for asin look up
        if($isAsinLookUp) {
            $this->totalpages = 1; // Because we have 10 asin lookup limit
            $this->totalresults = count($this->results_pure); // Because no total results are given
        } else {
            $this->totalpages = 1;
            $this->totalresults = 10;
        }

        // TODO
        //print_r( $response );

        return $results;
    }

    // yuri - load sort values for selected search index
    public function get_sortvalue_array($searchindex_sel) {
      echo PROSSOCIATE_ROOT_DIR . '/data/sortvalues-' . AWS_COUNTRY . '.csv';
        $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/sortvalues-' . AWS_COUNTRY . '.csv', 'r');

        if (!$handle) {
            throw new Exception('SortValue data unreadable or inaccessible.');
        }

        $sortvalues = array();

        $searchindex_cur = 'All';
        while (($data = fgetcsv($handle, 0, "\t")) !== false) {
            $count = count($data);
            if ($count == 1) {
                if (strpos($data[0], 'SearchIndex:') !== false) {
                    $searchindex_line = explode(':', $data[0]);
                    $searchindex = trim($searchindex_line[1]);
                    if (!empty($searchindex) && $searchindex != $searchindex_cur) {
                        $searchindex_cur = $searchindex;
                    }
                } else {
                    continue;
                }
            } else if ($count > 1) {
                if ($data[0] == 'Value') {
                    continue;
                } else {
                    $sortvalues[$searchindex_cur][] = array('val' => $data[0], 'txt' => $data[1]);
                }
            } else {
                continue;
            }
        }

        fclose($handle);

        return $sortvalues[$searchindex_sel];
    }

    public function get_browsenode_array() {
        // Make the process different
        if(AWS_COUNTRY === 'com') {
            $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/browsenodes-com.csv', 'r');
        } else {
            $handle = fopen(PROSSOCIATE_ROOT_DIR . '/data/browsenodes.csv', 'r');
        }

        if (!$handle) {
            throw new Exception('BrowseNode data unreadable or inaccessible.');
        }

        $firstrow = fgetcsv($handle);

        // Make the process different
        if(AWS_COUNTRY === 'com') {
            $browsenodes = array();
            while (($data = fgetcsv($handle)) !== false) {
                $browsenodes[] = array(
                    'name' => $data[0],
                    'nodeId' => $data[1],
                    'searchIndex' => $data[2]
                );
            }
        } else {
            $key = 0;
            while (($data = fgetcsv($handle)) !== false) {
                $count = count($data);

                for ($i = 0; $i < $count; $i++) {
                    // yuri - convert county initial to the AES code
                    $nn = ProssociateSearch::get_country_code_from_initial($firstrow[$i]);
                    $browsenodes[$data[0]][$nn] = $data[$i];
                }

                $key++;
            }
        }

        fclose($handle);

        return $browsenodes;
    }

    public function get_country_code_from_initial($initial) {

        switch ($initial) {
            case 'IN':
                return 'in';
            case 'CA':
                return 'ca';
            case 'CN':
                return 'cn';
            case 'DE':
                return 'de';
            case 'ES':
                return 'es';
            case 'FR':
                return 'fr';
            case 'IT':
                return 'it';
            case 'JP':
                return 'co.jp';
            case 'UK':
                return 'co.uk';
            case 'US':
                return 'com';
            default:
                return 'com';
        }
    }

    public function makePrice($price) {
        if(AWS_COUNTRY == 'de' || AWS_COUNTRY == 'fr' || AWS_COUNTRY == 'es' || AWS_COUNTRY == 'it') {
            $cleanPrice = str_replace(',','', $price);
        } else {
            $cleanPrice = str_replace('.','', $price);
        }

        // Clean price for eng
        if(!is_numeric($cleanPrice))
            return '';

        return $cleanPrice;
    }

    /**
     * Get browsnode data on amazon
     * @param $nodeid
     * @param $nodes
     * @param $root
     * @throws Exception
     */
    private function browseNodeByAmazon($nodeid, $nodes, $root) {
        // Check if on top level nodes
        if($nodeid == '-2000') {
            $nodeid = '';
        }

        if (!$nodeid) { ?>
            <ul>
            <?php
                $browsenodes = ProssociateSearch::get_browsenode_array();
                if(AWS_COUNTRY === 'com') {
                    // For united states
                    foreach($browsenodes as $node) {
                        echo '
                            <li class="jstree-closed" id="' . $node['nodeId'] . '" nodes="" root="' . $node['searchIndex'] . '">
                                <a href="javascript:prossociate_select_browsenodes(\'' . $node['nodeId'] . '\', \'' . $node['name'] . '\', \'' . $node['searchIndex'] . '\');">' . $node['name'] . '</a>
                            </li>';
                    }
                } else {
                    foreach ($browsenodes as $nodename => $nodevalues) {
                        // yuri - load selected country's data
                        if ($nodevalues[AWS_COUNTRY]) {
                            // yuri - set browse node value into serach index box
                            echo '
                            <li class="jstree-closed" id="' . $nodevalues[AWS_COUNTRY] . '" nodes="" root="' . $nodename . '">
                                <a href="javascript:prossociate_select_browsenodes(\'' . $nodevalues[AWS_COUNTRY] . '\', \'' . $nodename . '\', \'' . $nodename . '\');">' . $nodename . '</a>
                            </li>';
                        }
                    }
                }
            ?>
            </ul>
        <?php } else {

            $amazonEcs = new AmazonECS(AWS_API_KEY, AWS_API_SECRET_KEY, AWS_COUNTRY, AWS_ASSOCIATE_TAG);

            $amazonEcs->responseGroup('BrowseNodeInfo');

            $try = 0;

            $this->searchChildBrowsenode($amazonEcs, $nodeid, $try, $root);

        }
    }

    private function searchChildBrowsenode($amazonEcs, $nodeid, $try, $root) {

        $response = $amazonEcs->browseNodeLookup($nodeid);

        if ($response->BrowseNodes->Request->IsValid != 'True') {
            print_r($response);
            throw new Exception("Invalid Request");
        }

        if($nodeid == 1 ) {
            die();
        }

        // yuri - check for invalid id exception
        if (!isset($response->BrowseNodes->Request->Errors)) {
            // Check if there are child node
            if (isset($response->BrowseNodes->BrowseNode->Children)) {
                foreach ($response->BrowseNodes->BrowseNode->Children->BrowseNode as $browsenode) {
                    if ($browsenode->BrowseNodeId) {
                        //$amazonEcs->responseGroup('BrowseNodeInfo');
                        //$response2 = $amazonEcs->browseNodeLookup($browsenode->BrowseNodeId);
                        // yuri - track node tree path
                        if (empty($nodes)) {
                            $node_ids = array();
                        } else {
                            $node_ids = split(',', $nodes);
                        }
                        $node_ids[] = $nodeid;
                        $node_path = implode(',', $node_ids);

                        echo '<li class="jstree-closed" id="' . $browsenode->BrowseNodeId . '" nodes="' . $node_path . '" root="' . $root . '"><a href="javascript:prossociate_select_browsenodes(' . $browsenode->BrowseNodeId . ', \'' . addslashes($browsenode->Name) . '\', \'' . $root . '\');">' . $browsenode->Name . '</a></li>';
                    }
                }
            } else {
                echo '<li class="jstree-leaf dm-no-child">No child categories</li>';
            }
        } else {
            // Hard fix for categories
            // Apparel
            /*
            if($nodeid == 1036592) {
                $forcedBrowsenodes = array(
                    '1294227011' => 'Brands',
                    '1036682' => 'Clothing',
                    '51569011' => 'Featured Categories'
                );

                foreach($forcedBrowsenodes as $k => $v) {
                    echo '<li class="jstree-closed" id="' . $k . '" nodes="1036592" root="Apparel"><a href="javascript:prossociate_select_browsenodes(' . $k . ', \'' . $v . '\', \'Apparel\');">' . $v . '</a></li>';
                }
            }
            */
            var_dump($response);
        }
    }

    private function manualSearchNode($nodeId, $rootNodes, $root) {
        // Checker
        //$useMainSite = false;

        // Only when on
        /*
        if(AWS_COUNTRY == 'com') {
            // Try to get the nodes data from main site
            $response = wp_remote_get('http://prosociate.com/?dmprosi=1&node=' . $nodeId); // TODO replace url
            $responseBody = wp_remote_retrieve_body($response);

            // If fail to connect to the main
            if(is_wp_error($response) || $response == '' || $responseBody == '') {
                $useMainSite = false;
            } else {
                // if connected to prosociate.com
                $useMainSite = true;
            }
        }
        */

        $this->browseNodeByAmazon($nodeId, $rootNodes, $root);

        die();
    }

    public function ajax_search_node() {

        $nodeid = $_REQUEST['id'];
        $nodes = $_REQUEST['nodes']; // yuri - get node tree path
        $root = $_REQUEST['root']; // yuri - get node tree path


        //if(AWS_COUNTRY == 'com') {
            $this->manualSearchNode($nodeid, $nodes, $root);
            die();
        //}



    }

        // yuri - return  sort values for selected search index
        public function ajax_sort_values() {
            global $proso_sort_order;

            $searchindex_sel = $_REQUEST['searchindex'];

            if (empty($searchindex_sel)) {
                echo '<option value="" selected="selected">Default</option>';
                die();
            }

            $sortvalues = ProssociateSearch::get_sortvalue_array($searchindex_sel);

            echo '<option value="" selected="selected">Default</option>';
            if (count($sortvalues)) {
                foreach ($sortvalues as $sortvalue) {
                    $value = $sortvalue['val'];
                    // Check if value is in the clean sort values array
                    if (array_key_exists($value, $proso_sort_order)) {
                        // Use the clean values
                        $optionLabel = $proso_sort_order[$value];
                    } else {
                        $optionLabel = $value;
                    }
                    $text = substr($sortvalue['txt'], 0, 20);
                    echo '<option value="' . $value . '">' . $optionLabel . '</option>';
                }
            }

            die();
        }
        // parse power search data for books
        public function parse_power_search_data($operator='or', $books_options, $ajax=true){
          //$operator='or';
          $power_options='';
          if($ajax){
            $countera = 0;
            foreach($books_options as $key => $value){
              $book_tag_arr = explode('_',$key);
              if(!empty($value)){
                if($countera == 0 ){
                  $power_options = $book_tag_arr[1].':'.$value;
                }
                else{
                  $power_options = $power_options.' '.$operator.' '.$book_tag_arr[1].':'.$value;
                }
                $countera++;
              }

            }
          }else{
            $books_options_arr = explode("|", $books_options);
            //$books_options = json_decode($books_options);
            //var_dump($books_options_arr);
            $counterb = 0;
            foreach($books_options_arr as $book_option){
              $book_tag_arr = explode('_',$book_option);
              if(!empty($book_tag_arr[2])){
                if($counterb == 0 ){
                  $power_options = $book_tag_arr[1].':'.$book_tag_arr[2];
                }
                else{
                  $power_options = $power_options.' '.$operator.' '.$book_tag_arr[1].':'.$book_tag_arr[2];
                }
                $counterb++;
              }
            }
          }
          $power_options = urlencode($power_options);
          return $power_options;
        }
    }
