<?php	
	session_start();

	require '../vendor/autoload.php';
	use phpish\shopify;

	$config = new Config();
	$config = $config->returnConfigs();

	$shopifyApp = new ShopifyApp($config);

	$dbh = $shopifyApp->connectToDb();

	//get the app data from the db
    $app_settings = $shopifyApp->fetchAppData($dbh, 1); 

 	//get the access token from the db
    $access_token = $shopifyApp->fetchAccessToken($dbh, 1);


 	// print_r('shop = ' . $_SESSION['shop'] . "<br/>");
	// print_r('api key = ' . $app_settings[0]['api_key'] . "<br/>");
	// print_r('access token = ' . $access_token->access_token . "<br/>");

	$shopify = shopify\client (
	  $_SESSION['shop'], $app_settings[0]['api_key'], $access_token[0]['access_token'], $app_settings[0]['shared_secret']
	);


try
{
	# Making an API request can throw an exception
	$products = $shopify('GET /admin/products.json', array('published_status'=>'published'));
	echo json_encode($products);
}
catch (shopify\ApiException $e)
{
	# HTTP status code was >= 400 or response contained the key 'errors'
	echo $e;
	print_r($e->getRequest());
	print_r($e->getResponse());
}
catch (shopify\CurlException $e)
{
	# cURL error
	echo $e;
	print_r($e->getRequest());
	print_r($e->getResponse());
}


