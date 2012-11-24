<?php
/*
 * plugin name: ReviewZon With Wocommerce
 * Description: This is the cron script to convert post data into product to sell with woocommerce
 * author: Sohag
 * */

define("ReviewzonWocommerce_DIR", dirname(__FILE__));
define("ReviewzonWocommerce_FILE", __FILE__);
define("ReviewzonWocommerce_URL", plugins_url('/', __FILE__));

include ReviewzonWocommerce_DIR . '/classes/class.cron.php';
ReviewzonWocommerceCron::init();
