<?php
//api key 6efceb8935e61ab01f2a1f216b023d4b
//client id f70880b91dd8c7dd73d599cad4a5a639
//list id de1fa82595ce3b2eb99a6f4a69bbe452

require_once("li_campaign_monitor.php");

$cm = new LI_Campaign_Monitor("6efceb8935e61ab01f2a1f216b023d4b");

//test POST request
//adding a new subscriber with email name@test.com
$r = $cm->call("clients/f70880b91dd8c7dd73d599cad4a5a639/lists", "GET");
var_dump($r);
/*
//test GET request
//get details for subscriber with email name@test.com
$r = $cm->call("subscribers/de1fa82595ce3b2eb99a6f4a69bbe452", "GET", array(), array("email" => "name@test.com"));
var_dump($r);

//test PUT request
//change email of subscriber with email name@test.com to othername@test.com
$r = $cm->call("subscribers/de1fa82595ce3b2eb99a6f4a69bbe452", "PUT", array("EmailAddress" => "othername@test.com"), array("email" => "name@test.com"));
var_dump($r);

//test PUT request again
//change email of subscriber with email othername@test.com to name@test.com
$r = $cm->call("subscribers/de1fa82595ce3b2eb99a6f4a69bbe452", "PUT", array("EmailAddress" => "name@test.com"), array("email" => "othername@test.com"));
var_dump($r);

//test DELETE request
//delete subscriber with email name@test.com
$r = $cm->call("subscribers/de1fa82595ce3b2eb99a6f4a69bbe452", "DELETE", array(), array("email" => "name@test.com"));
var_dump($r);
*/
?>