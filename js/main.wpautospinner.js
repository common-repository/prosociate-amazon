//update the menu function
var updateMenu = function(){

	jQuery("#spinner-editor_ifr").parent().append('<ul style="display:none;position: absolute;" id="spinner_dev"><li>test</li><li>test</li></ul>');

	var mouseX;
	var mouseY;

	jQuery(jQuery("#spinner-editor_ifr").contents()[0], window).bind("mousemove", function(e) {
		mouseX = e.pageX;
		mouseY = e.pageY;
	});

	var currentSynonym ;

	jQuery("#spinner-editor_ifr").contents().find('.synonym').mouseover(function(){

	currentSynonym=this;

	//empty list
	jQuery('#spinner_dev   li').remove();

	 var synonyms= jQuery(this).attr('synonyms')    ;
	  synonyms_arr=synonyms.split('|') ;
	  jQuery.each(synonyms_arr, function (index, value) {
	        if (value != '') {
			jQuery('#spinner_dev').append('<li>' + value + '</li>');
	        }
	    });

	  jQuery('#spinner_dev').css({'top':mouseY - 13 +45 - jQuery("#spinner-editor_ifr").contents().find('body').scrollTop(),'left':mouseX -5}).fadeIn('slow');
	  jQuery('#spinner_dev').focus();

	  jQuery('#spinner_dev li').click(function(){

	    jQuery(currentSynonym).html(jQuery(this).html());
	    jQuery('#spinner_dev').hide();
	  });

	});

	jQuery('#spinner_dev').mouseleave(function(){
	  jQuery('#spinner_dev').hide();
	});


	jQuery('#spinner_dev   li').click(function(event){
	    console.log(jQuery(this).html());
	     event.stopImmediatePropagation();
	    return false;
	});

};

jQuery(document).ready(function() {

	/*
	 * tabs js
	 */
	jQuery('.tab_head').click(function(){
		jQuery(this).parent().find('li').removeClass('tabs');
		jQuery(this).addClass('tabs');
		jQuery(this).parent().parent().find('div.tabs-panel').hide();
		jQuery(this).parent().parent().find('div.tabs-panel').eq(jQuery(this).index()).show();
		console.log(jQuery(this).index());
		return false;
	});


	jQuery('#wp-auto-spinner-post-rewrite').click(function(){
		/*
		 * post rewrite button: takes tinymce text and rewrite the post
		 */

		//triger tinymce save to get instant changes
		jQuery('#content-tmce').trigger('click');

		jQuery.ajax({
			url : jQuery('#wp_spinner_rewrite_source').val(),
			type : 'POST',
			data : {post: tinyMCE.get('content').getContent(),title:jQuery('#title').val(),post_id:jQuery('#post_ID').val()},

			success : function(data) {
				//remove ajax icon
				jQuery('#wp-autospin-ajax-loading1').addClass('ajax-loading');

				var res={'status':'error','status':'fail','msg':'invalid json'};

				if(data.substr(0,1) == '{'){
				//is json
					var res = jQuery.parseJSON(data);
				}


				if (res['status'] == 'success') {
					//execute call back
					jQuery('#field-wp-auto-spin-rewritten-title').val(res['spintaxed_ttl']);

					tinyMCE.get('spinner-editor').setContent(res['spintaxed_cnt2']);
					updateMenu();


					jQuery('#field-wp-auto-spin-rewritten-titlesyn').val(res['spinned_ttl']);
					jQuery('#field-wp-auto-spin-rewritten-postsyn').val(res['spinned_cnt']);

					jQuery('#field-wp-auto-spin-rewritten-titleori').val(jQuery('#title').val());
					jQuery('#field-wp-auto-spin-rewritten-postori').val(tinymce.get('content').getContent());


					jQuery('.wp-auto-spinner-meta').slideDown('slow');

				} else if (res['status'] == 'fail') {
					console.log(res['msg']);

				}

				//posting message

			},

			beforeSend : function() {
				jQuery('#content-tmce').trigger('click');
				jQuery('.wp-auto-spinner-meta').slideUp('slow');
				jQuery('#wp-autospin-ajax-loading1').removeClass('ajax-loading');

			}
		});

		return false;
	});

	/*
	 * rewrite button : sends synonyms for title and post content and return a spintaxed instance
	 */

	jQuery('#wordpress-auto-spinner-regenerate').click(function(){

		jQuery.ajax({
			url : jQuery(this).attr('href'),
			type : 'POST',
			data : {post: jQuery('#field-wp-auto-spin-rewritten-postsyn').val(),title:jQuery('#field-wp-auto-spin-rewritten-titlesyn').val(),post_id:jQuery('#post_ID').val()},

			success : function(data) {
				//remove ajax icon
				jQuery('#wp-autospin-ajax-loading2').addClass('ajax-loading');


				var res={'status':'error','status':'fail','msg':'invalid json'};

				if(data.substr(0,1) == '{'){
				//is json
					var res = jQuery.parseJSON(data);
				}


				if (res['status'] == 'success') {
					//execute call back
					jQuery('#wp-auto-spin-rewritten-head').trigger('click');
					jQuery('#field-wp-auto-spin-rewritten-title').val(res['spintaxed_ttl']);
					tinyMCE.get('spinner-editor').setContent(res['spintaxed_cnt2']);
					updateMenu();


				} else if (res['status'] == 'fail') {
					console.log(res['msg']);

				}

				//posting message

			},

			beforeSend : function() {

				jQuery('#wp-autospin-ajax-loading2').removeClass('ajax-loading');

			}
		});

		return false;
	});


	/*
	 * rewrite button 2 : sends synonyms for title and post content and return a spintaxed instance
	 */

	jQuery('#wordpress-auto-spinner-regenerate-re').click(function(){

		jQuery.ajax({
			url : jQuery(this).attr('href'),
			type : 'POST',
			data : {post: jQuery('#field-wp-auto-spin-rewritten-postsyn').val(),title:jQuery('#field-wp-auto-spin-rewritten-titlesyn').val(),post_id:jQuery('#post_ID').val()},

			success : function(data) {
				//remove ajax icon
				jQuery('#wp-autospin-ajax-loading3').addClass('ajax-loading');


				var res={'status':'error','status':'fail','msg':'invalid json'};

				if(data.substr(0,1) == '{'){
				//is json
					var res = jQuery.parseJSON(data);
				}


				if (res['status'] == 'success') {
					//execute call back
					jQuery('#wp-auto-spin-rewritten-head').trigger('click');
					jQuery('#field-wp-auto-spin-rewritten-title').val(res['spintaxed_ttl']);
					tinyMCE.get('spinner-editor').setContent(res['spintaxed_cnt2']);
					updateMenu();


				} else if (res['status'] == 'fail') {
					console.log(res['msg']);

				}

				//posting message

			},

			beforeSend : function() {

				jQuery('#wp-autospin-ajax-loading3').removeClass('ajax-loading');

			}
		});

		return false;
	});



	/*
	 * send to editor button
	 */

	jQuery('#wp-auto-spinner-stoeditor').click(function(){

		if(spinTitle){
			jQuery('#title').val(jQuery('#field-wp-auto-spin-rewritten-title').val());
		}

		tinyMCE.get('content').setContent( tinyMCE.get('spinner-editor').getContent() );
		jQuery("#content_ifr").contents().find('.synonym').each(function(){
		    (jQuery(this).replaceWith(jQuery(this).html()));
		});
		jQuery('html, body').animate({
            scrollTop: 0
        });
		return false;
	});

	jQuery('#wp-auto-spin-restore').click(function(){
		jQuery('#title').val(jQuery('#field-wp-auto-spin-rewritten-titleori').val());
		tinyMCE.get('content').setContent(jQuery('#field-wp-auto-spin-rewritten-postori').val());
		jQuery('html, body').animate({
            scrollTop: 0
        });
		return false;
	});

	jQuery('#wp-auto-spin-restore-az').click(function(){
		jQuery('#title').val(jQuery('#field-wp-auto-spin-rewritten-titleori-az').val());
		//jQuery('#title').val(jQuery('#field-wp-auto-spin-rewritten-titleori-az').val());
		tinyMCE.get('content').setContent(jQuery('#field-wp-auto-spin-rewritten-postori-az').val());
		jQuery('html, body').animate({
            scrollTop: 0
        });
		return false;
	});







});//docready

//synonyms hover list

jQuery('#spinner-editor_ifr').load(function(){

	alert('load');

});
