<?php
/*
 * Handles the cron jobs
 * */

class ReviewzonWocommerceCron{
	
	const interval = "daily";
	const hook = 'schedule_posts_to_product';
	static $meta_keys = array(
		'ReviewAZON_ListPrice' => '_regular_price',
		'ReviewAZON_LowestNewPrice' => '_price',		
	);
	
	//contains all the hooks
	static function init(){
		//register_activation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'create_scheduler'));
		//register_deactivation_hook(ReviewzonWocommerce_FILE, array(get_class(), 'clear_scheduler'));
		//add_action(self::hook, array(get_class(), 'schedule_posts_to_product'));
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
	//	var_dump($posts);
		if(!empty($posts)) :
			global $wpdb;
			
			foreach($posts as $post){
				$wpdb->update($wpdb->posts, array('post_type'=>'product', 'post_status'=>'publish', 'post_name'=>sanitize_title($post['post_name'])), array('ID'=>$post['ID']), array('%s', '%s', '%s'), array('%d'));
				$ID = $post['ID'];
				//updating term taxonomy table
				$sql = "UPDATE $wpdb->term_taxonomy AS tt
					INNER JOIN $wpdb->term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
					SET tt.taxonomy = 'product_cat'
					WHERE tr.object_id = '$ID'
					AND tt.taxonomy = 'category'
				";
				
				$wpdb->query($sql);
				
				$wpdb->insert($wpdb->postmeta, array('post_id'=>$post['ID'], 'meta_key'=>'_price', 'meta_value'=>$post['_price']), array('%d', '%s', '%s'));
				$wpdb->insert($wpdb->postmeta, array('post_id'=>$post['ID'], 'meta_key'=>'_regular_price', 'meta_value'=>$post['_regular_price']), array('%d', '%s', '%s'));
				$wpdb->insert($wpdb->postmeta, array('post_id'=>$post['ID'], 'meta_key'=>'_sale_price', 'meta_value'=>$post['_price']), array('%d', '%s', '%s'));
				$wpdb->insert($wpdb->postmeta, array('post_id'=>$post['ID'], 'meta_key'=>'_visibility', 'meta_value'=>'visible'), array('%d', '%s', '%s'));
								
			}
		endif;
			
	}
	
	
	
	/*
	 * return 100posts
	 * */
	static function get_100_posts(){
		global $wpdb;
		$sql = "SELECT ID, post_title FROM $wpdb->posts 
			INNER JOIN $wpdb->postmeta
			ON $wpdb->posts.ID = $wpdb->postmeta.post_id
			WHERE $wpdb->postmeta.meta_key = 'ReviewAZON_LowestNewPrice'
			AND $wpdb->posts.post_type = 'post'
			AND $wpdb->posts.post_status = 'draft'
			ORDER BY $wpdb->posts.post_date ASC
			LIMIT 200
		";
		
		$post_ids = $wpdb->get_results($sql);		
		
		$posts = array();
		if($post_ids){
			foreach($post_ids as $post_id){
				
				$posts[] = array(
					'ID' => $post_id->ID,
					'post_name' => $post_id->post_title,
					'_price' => self::sanitize_price(get_post_meta($post_id->ID, 'ReviewAZON_LowestNewPrice', true)),
					'_regular_price' => self::sanitize_price(get_post_meta($post_id->ID, 'ReviewAZON_ListPrice', true))
				);
			}
			
						
		}
						
		return $posts;
	}
	
	/*
	 * sanitize price
	 * */
	static function sanitize_price($price = 0){
		return preg_replace('/[^0-9.]/', '', $price);
	}
	
	
}