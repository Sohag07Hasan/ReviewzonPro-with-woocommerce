<?php
set_time_limit(0);
/*
 * Handles the cron jobs
 * */

class ReviewzonWocommerceCron{
	
	const interval = "hourly";
	const hook = 'schedule_posts_to_product';
	static $meta_keys = array(
		'ReviewAZON_ListPrice' => '_regular_price',
		'ReviewAZON_LowestNewPrice' => '_price',		
	);
	
	static $meta_keys_to_parse = array('ReviewAZON_ASIN', 'ReviewAZON_ProductFeatures', 'ReviewAZON_Description', 'ReviewAZON_SmallImage', 'ReviewAZON_MediumImage', 'ReviewAZON_LargeImage', 'ReviewAZON_Brand', 'ReviewAZON_ListPrice', 'ReviewAZON_LowestNewPrice', 'ReviewAZON_Manufacturer', 'ReviewAZON_Model', 'ReviewAZON_OfferListingId');
	
	//contains all the hooks
	static function init(){
		register_activation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'create_scheduler'));
		register_deactivation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'clear_scheduler'));
		add_action(self::hook, array(get_class(), 'schedule_posts_to_product'));
		//add_action('wp_insert_post', array(get_class(), 'wp_insert_post'), 100, 2);
		
		add_action('after_delete_post', array(get_class(), 'delete_associated_product'));
		
		add_action('admin_menu', array(get_class(), 'admin_menu'));
	}
	
	
	static function admin_menu(){
		add_options_page('woocommerce site with amazon pro', 'Wocommerce Corn', 'manage_options', 'woocommerce-with-amazon', array(get_class(), 'content'));
	}
	
	static function content(){
		?>
			
			<div class="wrap">
				<h2> Cron Jobs </h2>
				
			<p>	Basically posts are converted to products with wordpess default cron. </p>
				
				<p> Run the corn script to upate the existing products form respective posts (2/3 times daily is recommened) </p>
				
				<table class="form-table">
					<tr>
						<td>Cron script directory</td>
						<td colspan="2"> <input size="85" type="text" readonly value="<?php echo ReviewzonWocommerce_DIR . '/cron/cron.php'; ?>"> </td>
					</tr>
					<tr>
						<td>Cron script URL</td>
						<td colspan="2"> <input size="85" type="text" readonly value="<?php echo ReviewzonWocommerce_URL . '/cron/cron.php'; ?>"> </td>
					</tr>
					
				</table>
				
			</div>
			
		<?php
	}
	
	
	/*
		if a post is deleted, it deletes associated post
	*/
	static function delete_associated_product($post_id){
		global $wpdb;
		$product_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'post_id' AND meta_value = '$post_id'");
		
		wp_delete_post($product_id, true);
	}
	
	
	/*
	 * handle scheduler
	 * */
	static function create_scheduler(){
		
		if(!wp_next_scheduled('schedule_posts_to_product')) {
			wp_schedule_event( current_time( 'timestamp' ), self::interval, self::hook);
		}
	}
	
	
	/*
	 * clear the scheduler
	 * */
	static function clear_scheduler(){
		wp_clear_scheduled_hook(self::hook);
	}
	
	
	/*
	 * do everything
	 * */
	static function schedule_posts_to_product(){		
		
		$posts = self::get_100_posts();
				
		
		if(!empty($posts)) :
			global $wpdb;	
			
			self::handle_categories();
			
			$current_time = current_time('timestamp');
			
			foreach($posts as $post){
				
				$post_data = array('post_title'=>$post->post_title, 'post_type'=>'product', 'post_status'=>'publish', 'post_content'=>'', 'post_excerpt'=>'');
			
				//$wpdb->insert($wpdb->posts, array('post_title'=>$post->post_title, 'post_type'=>'product', 'post_status'=>'publish', 'post_name'=>sanitize_title($post->post_name), 'post_content'=>'', 'post_excerpt'=>'', 'post_date'=>$post->post_date, 'post_date_gmt'=> $post->post_date_gmt), array('%s', '%s', '%s', '%s', '%s', '%s', '%s'));
				
				$ID = wp_insert_post($post_data);
							
				
				if($ID) :					
					
					update_post_meta($post->ID, 'woocommerce_id', $ID);
					update_post_meta($ID, 'post_id', $post->ID);
								
									
					update_post_meta($ID, 'update_time', $current_time);
					update_post_meta($post->ID, 'parse_time', $current_time);
					
					$categories = wp_get_object_terms($post->ID, 'category', array('fields' => 'names'));					
					wp_set_object_terms($ID, $categories, 'product_cat');
					
					
					
					$tags = wp_get_object_terms($post->ID, 'post_tag', array('fields' => 'names'));
					wp_set_object_terms($ID, $tags, 'product_tag');
					
					
					
					$meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = $post->ID");
					
					if($meta_infos) :
					
						$wpdb->insert($wpdb->postmeta, array('post_id'=>$ID, 'meta_key'=>'_visibility', 'meta_value'=>'visible'), array('%d', '%s', '%s'));
						
						update_post_meta($ID, '_visibility', 'visible');
								
						foreach($meta_infos as $meta_info){
							if(in_array($meta_info->meta_key, self::$meta_keys_to_parse)){
								switch($meta_info->meta_key){
									case 'ReviewAZON_ListPrice' :										
										update_post_meta($ID, '_regular_price', self::sanitize_price($meta_info->meta_value));
										break;
										
									case 'ReviewAZON_LowestNewPrice' :
										$price = self::sanitize_price($meta_info->meta_value);
										update_post_meta($ID, '_price', $price);
										update_post_meta($ID, '_sale_price', $price);
										break;
										
									default :										
										update_post_meta($ID, $meta_info->meta_key, $meta_info->meta_value);
										break;
								}
								
							}						
						}	
					
					endif;				
					
					
					
					
				
				endif;		
			}
		endif;
			
	}
	
	
	
	/*
	 * return 100posts
	 * */
	static function get_100_posts(){
		global $wpdb;
		
			
			
		$sql = "select ID, post_title from $wpdb->posts where ID in (
						select c.post_id from(
							SELECT count(*) as num, post_id  FROM `$wpdb->postmeta` WHERE `meta_key` LIKE 'ReviewAZON_LowestNewPrice' or meta_key like 'woocommerce_id'  group by post_id 
						) c where  c.num=1
		)" ;	
		
		$posts = $wpdb->get_results($sql);		
		return $posts;
	}
	
	/*
	 * sanitize price
	 * */
	static function sanitize_price($price = 0){
		return preg_replace('/[^0-9.]/', '', $price);
	}
	
	
	/*
	 * handle eveything about taxonomy
	 * */
	static function handle_categories(){
		$category_ids = self::get_top_level_categories();
		//var_dump($category_ids);
		if($category_ids){
			foreach($category_ids as $cat_id){
				self::recursively_insert_terms($cat_id);
			}
		}		
	}
	
	
	static function recursively_insert_terms($cat_id, $parent = 0){
		$category = get_term($cat_id, 'category');
	//	var_dump($category);
		wp_insert_term($category->name, 'product_cat', array('parent' => $parent));
		$sub_category_ids = self::get_top_level_categories($cat_id);
		if($sub_category_ids){
			foreach($sub_category_ids as $sub_cat_id){
				self::recursively_insert_terms($sub_cat_id, $cat_id);
			}
		}
	}
	
	/*
	 * function to get the parent categories
	 * */
	function get_top_level_categories($parent = 0){
		global $wpdb;		
		$sql = "SELECT $wpdb->terms.term_id FROM $wpdb->terms
				INNER JOIN $wpdb->term_taxonomy
				ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
				WHERE $wpdb->term_taxonomy.taxonomy = 'category'
				AND $wpdb->term_taxonomy.parent = $parent
				AND $wpdb->term_taxonomy.count > 0
				ORDER BY $wpdb->terms.name ASC
		";
			//
		return $wpdb->get_col($sql);
	}
	
	
	
	/*
	 * product_update shceduler
	 * */
	static function schedule_product_update(){
		
		
		$posts = self::get_products();
		if($posts) :
			$time = current_time('timestamp');
			global $wpdb;
			foreach($posts as $key => $post){
				$regular_price = self::sanitize_price($post['ReviewAZON_ListPrice']);
				$sale_price = self::sanitize_price($post['ReviewAZON_LowestNewPrice']);
				
				update_post_meta($post['woocommerce_id'], '_sale_price', $sale_price);
				update_post_meta($post['woocommerce_id'], '_price', $sale_price);
				update_post_meta($post['woocommerce_id'], '_regular_price', $regular_price);
				update_post_meta($post['woocommerce_id'], 'update_time', $time);
				update_post_meta($key, 'parse_time', $time);
				
				/*
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$list_price), array('post_id'=>(int)$post['woocommerce_id'], 'meta_key'=>'_regular_price'));
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$sale_price), array('post_id'=>(int)$post['woocommerce_id'], 'meta_key'=>'_price'));
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$sale_price), array('post_id'=>(int)$post['woocommerce_id'], 'meta_key'=>'sale_price'));
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$sale_price), array('post_id'=>(int)$post['woocommerce_id'], 'meta_key'=>'sale_price'));
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$time), array('post_id'=>(int)$post['woocommerce_id'], 'meta_key'=>'update_time'));
				$wpdb->update($wpdb->postmeta, array('meta_value'=>$time), array('post_id'=>(int)$key, 'meta_key'=>'parse_time'));
				*/ 
			}
			
		endif;
	}
	
	
	/*
	 * get products to update
	 * */
	static function get_products(){
		global $wpdb;
		$time = current_time('timestamp') - 24 * 60 * 60 ;
			
		$sql = " SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE post_id IN
			( SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'parse_time' AND meta_value < $time )
			
			AND (meta_key LIKE 'woocommerce_id' OR meta_key LIKE 'ReviewAZON_LowestNewPrice' OR meta_key LIKE 'ReviewAZON_ListPrice')
			
			";
		
		
		$posts_metas = $wpdb->get_results($sql);
		$posts = array();
		
		if($posts_metas){			
			foreach($posts_metas as $post){
				$posts[$post->post_id][$post->meta_key] = $post->meta_value;				
			}		
		}
		
		return $posts;
		
	}
	
	
		
}
