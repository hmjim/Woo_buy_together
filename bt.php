<?php
/*
Plugin Name: Woocommerce Frequently Bought Together
Plugin URI:
Description: display frequently bought products
Author: hmjim 
Version: 1.0
Author URI:
*/

add_action( 'init', 'bought_together_init' );
function bought_together_init() {
	add_filter( 'template_include', 'bought_together_include' );
}

function bought_together_include( $template ) {
	bought_together_to_cart();

	return $template;
}

function bought_together_to_cart() {
	global $woocommerce;
	if ( $_GET['add-to-cart-multiple'] ) {
		$pids    = $_GET['add-to-cart-multiple'];
		$pid_arr = explode( ',', $pids );
		if ( $pid_arr ) {
			for ( $i = 0; $i < count( $pid_arr ); $i ++ ) {
				$product_id = $pid_arr[ $i ];
				$quantity   = 1;
				// Add the product to the cart
				if ( WC()->cart->add_to_cart( $product_id, $quantity ) ) {
					wc_add_to_cart_message( $product_id );
					$was_added_to_cart = true;
					$added_to_cart[]   = $product_id;
				}
			}
		}
		wp_redirect( $woocommerce->cart->get_cart_url() );
		exit;
	}
}

function get_bought_together_products( $pids, $exclude_pids = 0 ) {
	$all_products = array();
	$pids_count   = count( $pids );
	$pid          = implode( ',', $pids );
	global $wpdb, $table_prefix;
	if ( $pids_count > 1 || ( $pids_count == 1 && ! $all_products = wp_cache_get( 'bought_together_' . $pid, 'ah_bought_together' ) ) ) {
		$subsql     = "SELECT oim.order_item_id FROM " . $table_prefix . "woocommerce_order_itemmeta oim where oim.meta_key='_product_id' and oim.meta_value in ($pid)";
		$sql        = "SELECT oi.order_id from  " . $table_prefix . "woocommerce_order_items oi where oi.order_item_id in ($subsql) limit 100";
		$all_orders = $wpdb->get_col( $sql );
		if ( $all_orders ) {
			$all_orders_str = implode( ',', $all_orders );
			$subsql2        = "select oi.order_item_id FROM " . $table_prefix . "woocommerce_order_items oi where oi.order_id in ($all_orders_str) and oi.order_item_type='line_item'";
			if ( $exclude_pids ) {
				$sub_exsql2 = " and oim.meta_value not in ($pid)";
			}
			$sql2         = "select oim.meta_value as product_id,count(oim.meta_value) as total_count from " . $table_prefix . "woocommerce_order_itemmeta oim where oim.meta_key='_product_id' $sub_exsql2 and oim.order_item_id in ($subsql2) group by oim.meta_value order by total_count desc limit 15";
			$all_products = $wpdb->get_col( $sql2 );

			if ( $pids_count == 1 ) {
				wp_cache_add( 'bought_together_' . $pid, $all_products, 'ah_bought_together' );
			}
		} else {
			return false;
		}
	}

	return $all_products;
}

add_shortcode( 'with_shortcode', 'bought_together_cart_display' );

function bought_together_product_detail_display() {
	$pid      = get_the_id();
	$products = get_bought_together_products( array( $pid ) );

	bought_together_related_products( $products );
}

function bought_together_cart_display() {

	global $woocommerce;
	$cart_contents_count = $woocommerce->cart->cart_contents_count;
	$product_arr         = array();
	if ( ! $woocommerce->cart->cart_contents ) {
		return;
	}
	foreach ( $woocommerce->cart->cart_contents as $key => $cart_content ) {
		$product_arr[] = $cart_content['product_id'];
	}

	if ( $product_arr ) {
		$products = get_bought_together_products( $product_arr, 1 );
		$title    = __( 'Customers Who Bought Items in Your Cart Also Bought', 'bt' );
		bought_together_related_products( $products, $title );
	}
}

function bought_together_related_products( $products, $title = '' ) {

	$woocommerce_loop['columns'] = $columns;
	if ( $products == false ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'meta_value_num',
			'meta_query'     => array(
				array(
					'key'     => 'total_sales',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'numeric'
				),
			),
		);
	} else {
		$args = array(
			'post_type'           => 'product',
			'ignore_sticky_posts' => 1,
			'no_found_rows'       => 1,
			'posts_per_page'      => 5,
			'post__in'            => $products
		);
	}
	$products_list = new WP_Query( $args );

	if ( $products == false ) {
		$title = __( 'Top Selling', 'bt' );
	} else {
		if ( ! $title ) {
			$title = __( 'Customers Who Bought This Item Also Bought', 'bt' );
		}
	}
	if ( $products_list->have_posts() ) : ?>

        <div class="related products">

            <h2><?php echo $title; ?></h2>

			<?php woocommerce_product_loop_start(); ?>

			<?php while ( $products_list->have_posts() ) : $products_list->the_post(); ?>

				<?php wc_get_template_part( 'content', 'product' ); ?>

			<?php endwhile; // end of the loop. ?>

			<?php woocommerce_product_loop_end(); ?>

        </div>

	<?php endif;

	wp_reset_postdata();
}

/*Function to display products with add to cart button*/
function bought_together_addto_cart( $products ) {

	$args = array(
		'post_type'           => 'product',
		'ignore_sticky_posts' => 1,
		'no_found_rows'       => 1,
		'posts_per_page'      => 5,
		'post__in'            => $products
	);

	$products_buy = new WP_Query( $args );
	if ( $products_buy ) {
		$add_to_cart_pid_arr = array();
		$add_to_cart_arr     = array();
		$total_price         = 0;
		if ( $products_buy->have_posts() ) {
			while ( $products_buy->have_posts() ) {
				$products_buy->the_post();
				$size                  = 'shop_thumbnail';
				$pid                   = get_the_id();
				$add_to_cart_pid_arr[] = $pid;
				echo $products_buy->add_to_cart_url();
				if ( has_post_thumbnail() ) {
					$image = get_the_post_thumbnail( $pid, $size );
				} elseif ( wc_placeholder_img_src() ) {
					$image = wc_placeholder_img( $size );
				}
				global $product;
				$prd_price         = $product->get_display_price();
				$prd_link          = get_permalink();
				$total_price       += $prd_price;
				$cart_content      = '';
				$cart_content      .= '<div class="bought_prd" price="' . $prd_price . '">';
				$cart_content      .= '<a href="' . $prd_link . '">' . $image . '</a>';
				$cart_content      .= '<div class="bought_title"><input type="checkbox" name="bought_pid[]" value="' . $pid . '" checked > <a href="' . $prd_link . '">' . get_the_title( $pid ) . '</a></div>';
				$cart_content      .= '<div class="bought_price">' . $product->get_price_html() . '</div>';
				$cart_content      .= '</div>';
				$add_to_cart_arr[] = $cart_content;
			}
		}

		if ( $add_to_cart_arr ) {
			echo '<h4>' . __( 'Frequently Bought Together', 'bt' ) . '</h4>';
			echo '<div id="bought_together_frm">';
			echo '<div class="bought_together_prds">';
			echo implode( ' <div class="bought_plus">+</div> ', $add_to_cart_arr );
			$pids = implode( ',', $add_to_cart_pid_arr );
			echo '</div>';
			echo '<div class="boubht_add_to_cart"><div class="bought_price_total">' . get_woocommerce_currency_symbol() . $total_price . '</div><a class="single_add_to_cart_button button also_bought_css_button" href="#">' . __( 'Add 3 Items To Cart', 'bt' ) . '</a></div>';
			echo '<input type="hidden" name="bought_selected_prdid" value="' . $pids . '" id="bought_selected_prdid" >';
			echo '</div>';
			echo '<script>
				jQuery("#bought_together_frm input:checkbox").click(function() {
					var total_price = 0;
					var priceval = [];
					var counter = 0;
					var currency = "' . get_woocommerce_currency_symbol() . '";
					jQuery("#bought_together_frm :checkbox:checked").each(function(i){
					  pid = jQuery(this).val();
					  priceval[i] = pid;
					  price = jQuery(this).closest(".bought_prd").attr("price");
					  total_price = parseInt(total_price) + parseInt(price);
					  counter = i+1;
					});
					jQuery(".bought_price_total").html(currency+total_price);
					jQuery("#bought_selected_prdid").val(priceval);
					if(counter==3){
						var button_text = "' . __( 'Add 3 Items To Cart', 'bt' ) . '";
					}else if(counter==2){
						var button_text = "' . __( 'Add Both Items To Cart', 'bt' ) . '";
					}else if(counter==1){
						var button_text = "' . __( 'Add To Cart', 'bt' ) . '";
					}else if(counter==0){
						var button_text = "' . __( 'Select Atleast One Item', 'bt' ) . '";
					}
					jQuery(".single_add_to_cart_button").html(button_text);
					
				});
				
				jQuery(".single_add_to_cart_button").click(function() {
					var addtocarturl = "' . site_url( '?add-to-cart-multiple=' ) . '";
					var priceval = jQuery("#bought_selected_prdid").val();
					if(priceval){
						addtocarturl = addtocarturl+priceval;
						jQuery("a.single_add_to_cart_button").attr("href",addtocarturl);
					}else{
						return false;
					}
				});
				
			</script>';
		}
	}
}
