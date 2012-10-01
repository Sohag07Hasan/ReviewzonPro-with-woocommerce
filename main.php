<?php
/*
 * plugin name: ReviewZon With Wocommerce
 * Description: Reviewzon plugin stores data in database and Wocommerce sells these products
 * author: Sohag
 * */

define("ReviewzonWocommerce_DIR", dirname(__FILE__));
define("ReviewzonWocommerce_FILE", __FILE__);
define("ReviewzonWocommerce_URL", plugins_url('/', __FILE__));

include ReviewzonWocommerce_DIR . '/classes/class.cron.php';