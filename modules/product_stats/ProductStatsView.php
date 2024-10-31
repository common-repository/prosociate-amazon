<?php
/**
 * The display for manage campaign page (wp-admin/admin.php?page=prossociate_manage_campaigns)
 *
 * ProssociateCampaignController::manage_campaigns()
 *
 * $currentPage - (int) Current page number
 * $numberOfProducts - (int) Number of all the campaigns
 * $numberOfPages - (int) Number of pages
 * $campaigns - (object) Contains the campaigns to be displayed
 *
 */


// define the columns to display, the syntax is 'internal name' => 'display name'
$linkUrl = 'pros-products-stats';
$numberOfProducts = wp_count_posts('product')->publish;
global $wpdb;
$count_query = "SELECT COUNT(*) FROM {$wpdb->posts} , {$wpdb->postmeta} WHERE {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = '_pros_ASIN' AND {$wpdb->posts}.post_status = 'publish' AND {$wpdb->posts}.post_type = 'product'";
$numberOfProducts = $wpdb->get_var($count_query);
$post_per_page = 20;
$numberOfPages  = ceil($numberOfProducts/$post_per_page);
$currentPage = (isset($_GET['pagi'])) ? $_GET['pagi'] : 1;
$order = (isset($_GET['order'])) ? $_GET['order'] : 'ASC';
$orderBy = (isset($_GET['order_by'])) ? $_GET['order_by'] : 'title';
$columns = array(
            'ID' => 'ID',
            'asin' => 'ASIN',
            'image' => 'Image',
            'title' => 'Title',
            'hits' => 'Hits',
            'carted' => 'Added to cart',
            'redirected' => 'Redirected to Amazon',
            'date' => 'Date Added',
            'modified' => 'Updated',
            );

$args = array(
			'post_type' => 'product',
			'posts_per_page' => $post_per_page,
			'orderby' => $orderBy,
			'order' => $order,
			'paged' => $currentPage,
			'meta_key' => '_pros_ASIN',
		) ;

$products = query_posts( $args );
//var_dump($products);

// The url of the manage campaign page. (current url)
$manageUrl = "admin.php?page=" . $linkUrl;

// Conver the $currentPage to int to perform math operations
$currentPage = (int)$currentPage;

// The first page link
$firstPagiLink = admin_url( $manageUrl );

// The last page link
$lastPagiLink = admin_url( $manageUrl . '&pagi=' . $numberOfPages );

// Get the prev page number
$prevPageNumber = $currentPage - 1;

// The url for the prev page
$prevPagiLink = admin_url( $manageUrl . '&pagi=' . $prevPageNumber );

// Check if we need to disable the prev page and first page link.
// Make the $prevPagi link as the same as the $firstPagiLink
$prevPagiClass = '';
if( $currentPage === 1)
{
    $prevPagiClass = ' disabled';
    $prevPagiLink = $firstPagiLink;
}

// Get the next page number
$nextPageNumber = $currentPage + 1;

// The url for the prev page
$nextPagiLink = admin_url( $manageUrl . '&pagi=' . $nextPageNumber );

// Check if we need to disable the next page and last page link
// Make the $nextPagiLink link as the same as the $lastPagiLink
$nextPagiClass = '';
if( $currentPage == $numberOfPages )
{
    $nextPagiClass = ' disabled';
    $nextPagiLink = $lastPagiLink;
}


?>

<div class="wrap">

<h2>
	Prosociate: Product Stats
</h2>

<?php // We'll be using the built-in wordpress pagination display so we won't need to add styles for the pagination ?>
<div class="tablenav top">
    <div class="tablenav-pages">
        <span class="displaying-num"><?php echo $numberOfProducts . ' Products'; ?></span>
        <?php
        if( $numberOfProducts > $post_per_page )
        { ?>
        <span class="pagination_links">
            <a class="first-page<?php echo $prevPagiClass; ?>" title="Go to the first page" href="<?php echo $firstPagiLink; ?>">«</a>
            <a class="prev-page<?php echo $prevPagiClass; ?>" title="Go to the previous page" href="<?php echo $prevPagiLink; ?>">‹</a>

            <span class="paging-input">
                <input class="current-page" title="Current page" type="text" name="pagi" value="<?php echo $currentPage; ?>" size="1">
                of
                <span class="total-pages"><?php echo $numberOfPages; ?></span>
            </span>

            <a class="next-page<?php echo $nextPagiClass; ?>" title="Go to the next page" href="<?php echo $nextPagiLink; ?>">›</a>
            <a class="last-page<?php echo $nextPagiClass; ?>" title="Go to the last page" href="<?php echo $lastPagiLink; ?>">»</a>
        </span>
        <?php
        } // End if
        ?>
    </div>
</div>

<?php
/*
    if( $msg )
    {
        // TODO not currently working
    	echo '<div id="message" class="modified"><p>' . $msg . '</p></div>';
    }
 *
 */
?>
	<div class="clear"></div>

	<table class="statlist">

		<thead>
		<tr>


			<?php
			$col_html = '';
			foreach($columns as $column_id => $column_display_name)
			{
                $column_link = "<a href='";
				$order2 = 'ASC';

                // TODO Question: where is $order_by declared?
				if( $orderBy == $column_id )
                {
                    $order2 = ($order == 'DESC') ? 'ASC' : 'DESC';
                }

                // TODO Question: where is $this->baseUrl declared?
                if($column_id=='title' || $column_id=='ID' || $column_id=='date'  || $column_id=='modified'){
					$column_link .= esc_url( add_query_arg( array( 'order' => $order2, 'order_by' => $column_id ),$manageUrl ) );
				}
				else
				{
					$column_link .= esc_url( remove_query_arg( array( 'order', 'order_by', ),$manageUrl ) );
				}
				$column_link .= "'>{$column_display_name}</a>";
				$col_html .= '<th scope="col" class="column-' . $column_id . ' ' . ($orderBy == $column_id ? $order : '') . '">' . $column_link . '</th>';
			}
			echo $col_html;
			?>
		</tr>
		</thead>

		<tbody id="" class="">
		<?php
		// Check if there's no campaign.
		if( count( $products ) < 1 ): ?>
			<tr>
				<td colspan="<?php echo count($columns) + 1 ?>">&nbsp;</td>
			</tr>
		<?php
		else: // There are existing campaigns

            $class = ''; // Class container

            // Loop through all the campaign
            foreach ($products as $product):
                // Check if we're on even campaign. For the display variation
                //var_dump($product);

                // TODO delete line below
                //$class = ('alternate' == $class) ? '' : 'alternate';


$children = get_children(array(
            'post_parent' => $product->ID,
            'post_type' => 'product_variation',
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
		array(
			'key'     => '_pros_ASIN',
		),
	),
        ));

	$class='';
	if($children){
		$class = 'header';
	}

                ?>

				<tr class="<?php echo $class; ?> main" valign="middle">


								<th valign="top" align="left" scope="row">
									<?php echo $product->ID; ?> <?php
									if($children){
										?>
										<button>+</button>
										<?php
									}
									?>
								</th>

								<th valign="top" scope="row">
									<?php echo get_post_meta($product->ID, '_pros_ASIN', true);
									?>
								</th>

								<td class='post-title page-title column-title'>
									<?php
									$thumb_id  = get_post_thumbnail_id ( $product->ID);
									$url = wp_get_attachment_thumb_url( $thumb_id );
					?>
							<img src="<?php echo $url ?>" width='50'/>
								</td>

								<td class='post-title page-title column-title'>
									<a href="<?php echo get_edit_post_link( $product->ID ); ?> "><?php echo $product->post_title; ?></a>
								</td>

								<td class='post-title page-title column-title' align="center">
									<?php echo get_post_meta($product->ID, '_pros_hits', true); ?>
								</td>

								<td class='post-title page-title column-title' align="center">
									<?php
										echo get_post_meta($product->ID, '_pros_addtocart', true);
									?>
								</td>
								<td align="center">
									<?php
										echo get_post_meta($product->ID, '_pros_redirected', true);
									?>
								</td>

								<td align="center">
									<?php echo $product->post_date; ?>
								</td>
								<td align="center">
									<?php echo $product->post_modified; ?>
								</td>


				</tr>



				<?php

        		//echo '<pre>';
        		//var_dump($children);
        		if($children):
        			foreach ($children as $child):
				?>
				<tr class="childrow" valign="middle">


								<th valign="top" scope="row">
									<?php echo $child->ID; ?>
								</th>

								<th valign="top" scope="row">
									<?php echo get_post_meta($child->ID, '_pros_ASIN', true);
									?>
								</th>

								<td class='post-title page-title column-title'>
									<?php
									$thumb_id  = get_post_thumbnail_id ( $child->ID);
									$url = wp_get_attachment_thumb_url( $thumb_id );
									?>
							<img src="<?php echo $url ?>" width='50'/>
								</td>

								<td class='post-title page-title column-title'>
									<a href="<?php echo get_edit_post_link( $child->ID ); ?> "><?php echo $child->post_title; ?></a>
								</td>

								<td class='post-title page-title column-title' align="center">
									<?php echo get_post_meta($child->ID, '_pros_hits', true); ?>
								</td>

								<td class='post-title page-title column-title' align="center">
									<?php
										echo get_post_meta($child->ID, '_pros_addtocart', true);
									?>
								</td>
								<td align="center">
									<?php
										echo get_post_meta($child->ID, '_pros_redirected', true);
									?>
								</td>

								<td align="center">
									<?php echo $child->post_date; ?>
								</td>
								<td align="center">
									<?php echo $child->post_modified; ?>
								</td>


				</tr>
				<?php
				endforeach;
			endif;?>
			<?php endforeach; ?>
		<?php endif ?>
		</tbody>
	</table>
	<div class="clear"></div>
</div>
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery(function() {
		jQuery('.header').click(function(){
			//alert('test');
		   jQuery(this).find('button').text(function(_, value){return value=='-'?'+':'-'});
		    jQuery(this).nextUntil('tr.main').slideToggle(100, function(){
		    });
	 	});
	});
	jQuery(function() {

		jQuery('#product_cat-adder').hide();
		jQuery('a[href^="#product_cat-all"]').click(function(event){
			event.preventDefault();
			jQuery('#product_cat-all').show();
			jQuery('a[href^="#product_cat-all"]').parent().addClass('tabs');
			jQuery('#product_cat-pop').hide();
			jQuery('a[href^="#product_cat-pop"]').parent().removeClass('tabs');

		});
		jQuery('a[href^="#product_cat-pop"]').click(function(event){
			event.preventDefault();
			jQuery('#product_cat-pop').show();
			jQuery('a[href^="#product_cat-pop"]').parent().addClass('tabs');
			jQuery('#product_cat-all').hide();
			jQuery('a[href^="#product_cat-all"]').parent().removeClass('tabs');
		});

	});
});
</script>
