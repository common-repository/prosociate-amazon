jQuery(document).ready(function($) {
    $(".nav-tab").click(function(e){
        e.preventDefault();
        $(".nav-tab").removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tabs-div').css('display', 'none');
        var subsMenu = '#' + $(this).attr('id') + '-div';
        $(subsMenu).css('display', 'inline');
    });

    $(".dmShowAdvanceSearchFilter").click(function(e){
        e.preventDefault();
        $(".dmShowAdvanceSearchFilter").toggle();
        $("#dmPros_advanceSearch").toggle();
    });

    // Tooltips
    $(".tips, .help_tip").tipTip({
        'attribute' : 'data-tip',
        'fadeIn' : 50,
        'fadeOut' : 50,
        'delay' : 200
    });

    // Initialize the dialog
    $('#cattree_container').dialog({
        autoOpen: false,
        height: 600,
        position: { my: "left top", at: "left top+10", of: "#category" },
        open: function(event, ui) {
            if(document.getElementById('dmCatManual') != null ) {
                $('#dmCatManual').blur();
            }
        }
    });

    // open the category tree on click of the textbox
    $('#category').click(function() {
        $("#cattree_container").dialog("open");
    });

    $('body').bind('click', function(e){
        if( !$(e.target).is('#category') && !$(e.target).closest('.ui-dialog').length ) {
            if( $("#cattree_container").dialog("isOpen") ) {
                $("#cattree_container").dialog("close");
                if(document.getElementById('dmCatManual') != null ) {
                    if(document.getElementById('dmCatManual').checked) {
                        if(document.getElementById('dmBrowseNodeText').value != '') {
                            // Disable the search button
                            // TODO add handler for fail and error
                            var dmE = $("#pros_search_button");
                            dmE[0].disabled = true;
                            document.getElementById('category').value = document.getElementById('dmBrowseNodeText').value;
                            var dmData = {
                                action: 'prossociate_manual_browsenode',
                                node: document.getElementById('category').value
                            };
                            $.post(ajaxurl, dmData, function(response){
                                document.getElementById("searchindex").value = response;
                                dmE[0].disabled = false;
                                load_browsenode_sortvalues(response);
                            });
                        }
                    }
                }
            }
        }
    });

    // If category option change
    $(".dmChoiceNode").change(function(e) {
        if($(this).val() === 'manual') {
            $("#dmBrowseNodeText").removeAttr('disabled');
            $('#jstree_upper_container').css('display', 'none');
        } else {
            $('#dmBrowseNodeText').attr('disabled', 'disabled');
            // Display the tree
            $('#jstree_upper_container').css('display', 'inline');
        }
    })

    // tree
    var category_tree = $("#jstree_container").jstree({
        html_data: {
            ajax: {
                url: ajaxurl + '?action=prossociate_search_node',
                data: function(n) {
                    return {
                        id: (n.attr ? n.attr("id") : '-2000'),
                        nodes: (n.attr ? n.attr("nodes") : ''),
                        root: (n.attr ? n.attr("root") : '')
                    }; // yuri - add node tree path
                }
            }
        },
        "plugins": ["themes", "html_data"]

        // yuri - load category path for selected node
    }).bind("loaded.jstree", function(event, data) {
            var initCat = $("#category").attr( 'class' );
            if( initCat )
            {
                //console.log( initCat );
                //$( "#" + initCat + ' a').css( 'background-color', '#a3b9ff' );
                $( "#" + initCat).css( 'background-color', 'rgb(240, 232, 232)' );
            }
        }).bind("loaded.jstree", function(event, data) {
            if ($("#nodepath").val() != '') {
                $("#tmp_nodepath").val($("#nodepath").val());
            }
            open_browse_node_path();
        }).bind("after_open.jstree", function(event, data) {
            open_browse_node_path();
        });


    // yuri - open selected category tree path
    function open_browse_node_path() {
        var nodepath = $("#tmp_nodepath").val();
        if (nodepath != '') {
            var nodeids = nodepath.split(',');
            if (nodeids.length > 0) {
                var nodeid = nodeids[0];
                $("#" + nodeid + " > ins").click();
                if (nodeids.length == 1) {
                    nodepath = '';
                } else {
                    nodepath = nodepath.substring(nodeid.length + 1, nodepath.length);
                }
                $("#tmp_nodepath").val(nodepath);
            }

        }
    }
});

// The previous selected node
var pastNodeId;

// yuri - set browse node value into serach index box
function prossociate_select_browsenodes(nodeid, nodename, root) {
    jQuery("#category").val(nodename);
    jQuery("#browsenode").val(nodeid);
    jQuery("#nodepath").val(jQuery("#" + nodeid).attr('nodes'));
    var searchindex = jQuery("#searchindex").val();
    jQuery("#searchindex").val(root);
    load_browsenode_sortvalues(root);

    // Initial category
    var initNode = jQuery("#category").attr('class');

    if( initNode == null || initNode == undefined || initNode == '' )
    {
        initNode = nodeid;
    }

    // To clean the init cat
    if (initNode !== nodeid)
    {
        // Clean the previous selected node
        jQuery("#" + initNode).css('background-color', '#ffffff');
    }

    // Check if the selected node isn't the past selected node
    if (pastNodeId !== nodeid)
    {
        // Clean the previous selected node
        jQuery("#" + pastNodeId).css('background-color', '#ffffff');
    }

    // Mark the new selected node
    clicknode(nodeid);

    // Set the pastNodeId as the current selected nodeid
    pastNodeId = nodeid;

    // Cloe the dialog
    jQuery( '#cattree_container' ).dialog( "close" );
}

// DM
// Highlight the selected node
function clicknode(nodeid) {
    //jQuery("#" + nodeid + ' a:first').css('background-color', '#a3b9ff');
    jQuery("#" + nodeid).css('background-color', 'rgb(240, 232, 232)');
}

// yuri - load sort values for a browse node
function load_browsenode_sortvalues(nodename, sortval) {
    jQuery("#sort").load(
        ajaxurl + '?action=prossociate_sort_values&searchindex=' + nodename,
        function() {
            jQuery("#sort").on(
                'change',
                function(event) {
                    var sortby = jQuery(this).val();
                    jQuery("#sort").children('option').each(function() {
                        if (this.value == sortby) {
                            jQuery("#sortby").val(sortby);
                            this.selected = true;
                        } else {
                            this.selected = false;
                        }
                    });
                }
            );

            if (sortval != null && sortval != '') {
                jQuery("#sortby").val(sortval);
                jQuery("#sort").val(sortval);
            }
        }
    );
}