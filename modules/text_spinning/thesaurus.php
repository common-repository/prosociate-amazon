<?php function pros_spinner_thesaurus_f(){?>

<?php 
 
		//INI
		$pros_spinner_lang=get_option('pros_spinner_lang','en');
	
		$file=get_option('pros_spinner_custom_'.$pros_spinner_lang,array());
		
		 
?>


<div class="wrap">
    
    <div style="margin-left:8px" class="icon32 icon32-posts-page" id="icon-edit-pages">
        <br>
    </div>
    
    <h2>Prosociate Spinner Custom Thesaurus</h2>
 
     <div id="synonyms_choice">
	    <label for="field-treasures">
	    	Thesaurus :
	    </label>
	    <select  disabled="disabled" name="treasures" id="treasures">
	    		<option  value="en"  <?php opt_selected('en',$pros_spinner_lang) ?> >English</option> 
				<option  value="du"  <?php opt_selected('du',$pros_spinner_lang) ?> >Dutch</option>
				<option  value="ge"  <?php opt_selected('ge',$pros_spinner_lang) ?> >German</option>
				<option  value="fr"  <?php opt_selected('fr',$pros_spinner_lang) ?> >French</option>
				<option  value="it"  <?php opt_selected('it',$pros_spinner_lang) ?> >Italian</option>
				<option  value="sp"  <?php opt_selected('sp',$pros_spinner_lang) ?> >Spanish</option>
				<option  value="po"  <?php opt_selected('po',$pros_spinner_lang) ?> >Portuguese</option>
				<option  value="ro"  <?php opt_selected('ro',$pros_spinner_lang) ?> >Romanian</option>
				<option  value="tr"  <?php opt_selected('tr',$pros_spinner_lang) ?> >Turkish</option>
				 
	    </select>
    </div>

    <div id="dashboard-widgets-wrap">

    <div class="metabox-holder columns-2" id="dashboard-widgets">
        <div class="postbox-container" id="postbox-container-1">
            <div class="meta-box-sortables ui-sortable" id="normal-sortables">
                <div class="postbox">
                    <h3 class="hndle"><span>Thesaurus synonyms</span></h3>
                    <div class="inside">
                        <!--  insider start -->





                        <div id="synonyms_wraper">
                            <div id="synonyms">
                                <p>All Words</p>
                                <select name="sometext" size="30" id="word_select">
                               
                                </select>

                            </div>

                            <div id="synonyms_words">
                                <p>Current Word</p>
                                <input  disabled="disabled"  type="text" id="synonym_word" />
                                <p>Word synonyms</p>
                                <textarea disabled="disabled" rows="10" cols="20" name="name" id="word_synonyms"></textarea>
                                <p>
                                    
                                    <input name="wp_spinner_rewrite_source" id="wp_spinner_rewrite_source" type="hidden" value="<?php echo site_url('/?pros_spinner=ajax'); ?>">
                                    
                                    <button  id="edit_synonyms" title="Edit Synonym">
                                        <img src="<?php echo plugins_url('images/edit.png',__FILE__) ?> " /><br>Edit
                                    </button>
                                    
                                    <button  id="save_synonyms"  disabled="disabled"  title="Save Synonym">
                                        <img src="<?php echo plugins_url('images/save.png',__FILE__) ?> " /><br>Save
                                    </button>
                                    
                                    <button  id="cancel_synonyms"  disabled="disabled"  title="Cancel">
                                        <img src="<?php echo plugins_url('images/delete.png',__FILE__) ?> " /><br>Cancel
                                    </button>
                                    
                                    <button  id="delete_synonyms"   title="Delete Synonym">
                                        <img src="<?php echo plugins_url('images/trash.png',__FILE__) ?> " /><br>Trash
                                    </button>
                                    
                                    
                                </p>
                            </div>
                            <!-- synonyms words -->

                            <div class="clear"></div>

                        </div>
                        <!--synonyms wraper-->

                        <!-- /insider 3 -->
                    </div>
                </div>

            </div>
        </div><!-- postbox container 1  -->

         <div class="postbox-container" id="postbox-container-2">
            <div class="meta-box-sortables ui-sortable" id="normal-sortables">
                <div class="postbox">
                    <h3 class="hndle"><span>Add New Synonyms</span></h3>
                    <div class="inside">
                        <!--  insider start -->
							<p>one word per line</p>
							<textarea  style="width:100%" cols="15" rows="3" class="mceEditor" id="content" name="content"></textarea>
							<button id="add_synonyms" class="button">Add synonyms</button>
                        <!-- /insider 3 -->
                    </div>
                </div>

            </div>
        </div><!-- postbox container 2  -->
        

    </div>


</div>
</div>
<!-- wrap -->

<script type="text/javascript">
	var synonyms_arr = <?php echo json_encode($file)?>;
	var lastKey=0; 
		 
	
	   jQuery.each(synonyms_arr, function( index, value ) {
		 
			   jQuery('#word_select').append( '<option value="'+ index +'" >'+ value.split('|')[0] +'</option>') ;
 				lastKey=index;
	    });

	   jQuery('#word_synonyms').focus();
		
	
</script>

<?php }//end function ?>