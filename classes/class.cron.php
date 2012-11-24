<?php
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
		//register_activation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'create_scheduler'));
		//register_deactivation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'clear_scheduler'));
		//add_action(self::hook, array(get_class(), 'schedule_posts_to_product'));
		//add_action('wp_insert_post', array(get_class(), 'wp_insert_post'), 100, 2);
	}
	
	static function wp_insert_post($post_id, $post){
		return update_post_meta($post_id, 'wpcommerce_status', '0');
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
		
		//var_dump($posts);
		//print_r($posts);
		//die();
		
	
		if(!empty($posts)) :
			global $wpdb;	
			
			self::handle_categories();
			
			foreach($posts as $post){
				
				$post_data = array('post_title'=>$post->post_title, 'post_type'=>'product', 'post_status'=>'publish', 'post_content'=>'', 'post_excerpt'=>'');
			
				//$wpdb->insert($wpdb->posts, array('post_title'=>$post->post_title, 'post_type'=>'product', 'post_status'=>'publish', 'post_name'=>sanitize_title($post->post_name), 'post_content'=>'', 'post_excerpt'=>'', 'post_date'=>$post->post_date, 'post_date_gmt'=> $post->post_date_gmt), array('%s', '%s', '%s', '%s', '%s', '%s', '%s'));
				
				$ID = wp_insert_post($post_data);
							
				
				if($ID) :
					
					$current_time = current_time('timestamp');
					
					update_post_meta($post->ID, 'woocommerce_id', $ID);
					update_post_meta($ID, 'post_id', $post->ID);
					
					$wpdb->insert($wpdb->postmeta, array('post_id'=>$ID, 'meta_key'=>'update_time', 'meta_value'=>current_time('timestamp')), array('%d', '%s', '%s'));
									
					update_post_meta($ID, 'update_time', $current_time);
					
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
		
		
		 
		 /*
		
		$sql = "SELECT $wpdb->posts.ID, $wpdb->posts.post_title FROM $wpdb->posts 
			INNER JOIN $wpdb->postmeta
			ON $wpdb->posts.ID = $wpdb->postmeta.post_id
			WHERE $wpdb->postmeta.meta_key = 'ReviewAZON_LowestNewPrice'
			AND $wpdb->postmeta.meta_value = '267'
			AND $wpdb->posts.post_type = 'post'
			AND (($wpdb->posts.post_status = 'publish') OR ($wpdb->posts.post_status = 'draft') OR ($wpdb->posts.post_status = 'future'))
			ORDER BY $wpdb->posts.post_date ASC
			LIMIT 100
		";
		* */
	
		$sql = "select ID, post_title from wp_posts where ID in (
						select c.post_id from(
							SELECT count(*) as num, post_id  FROM `wp_postmeta` WHERE `meta_key` LIKE 'ReviewAZON_LowestNewPrice' or meta_key like 'woocommerce_id'  group by post_id 
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
		
}
