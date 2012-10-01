<?php

/*
 * This script runs by cron
 */

set_time_limit(0);

include '../../../../wp-load.php';
ReviewzonWocommerceCron::schedule_posts_to_product();

?>
