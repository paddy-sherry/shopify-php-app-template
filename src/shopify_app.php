<?php

//start a session
session_start(); 
require '../vendor/autoload.php';
use phpish\shopify;


class ShopifyApp {
   
    function __construct($config) {
      $this->host = $config['host'];
      $this->dbname = $config['dbname'];
      $this->dbuser = $config['username'];
      $this->dbpass = $config['password'];
    }

    /*
    * function connectToDb
    * This function handles the PDO connection to the db and logs an error if there was a problem.
    */
    public function connectToDb() {
        //try to connect to the db or send an error to the log file if there's a problem
        try {
            // $dbh is our PDO connection to the db
          $dbh = new PDO("mysql:host=$this->host;dbname=$this->dbname",$this->dbuser,$this->dbpass);
          //set the error
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
          //getMessage is the exact error reported.
            file_put_contents('logs/PDOErrors.txt', 'Connection failed: ' . $e->getMessage(), FILE_APPEND);
        }

        return $dbh;
    }

    //Fetch the app data from the db
    public function fetchAppData($dbh, $id) {
        $stmt = $dbh->prepare("SELECT * FROM tbl_appsettings WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        //execute the prepared statement
        $stmt->execute();
     
        $app_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($app_settings) < 1){
            die('forbidden');
        }

        return $app_settings;
    }

    //Fetch the store data from the db if its already installed
    public function fetchStoreData($dbh, $shop) {
        //select the store from the db & check if the store exists 
        $stmt = $dbh->prepare("SELECT store_name FROM tbl_usersettings WHERE store_name = :shop");
        $stmt->bindParam(':shop', $shop, PDO::PARAM_STR);
        //execute the prepared statement
        $stmt->execute();
     
        $select_store = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $select_store;
    }

    public function fetchAccessToken($dbh, $id) {
        $stmt = $dbh->prepare("SELECT access_token FROM tbl_usersettings WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
     
        $access_token = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $access_token;
    }

    public function showPermissionsPage($shop, $app_settings) {
   
        //convert the permissions to an array
        $permissions = explode(',', $app_settings[0]['permissions']);

        //get the permission url
        $permission_url = shopify\authorization_url(
            $shop, $app_settings[0]['api_key'], $permissions
        );

        $permission_url .= '&redirect_uri=' . $app_settings[0]['redirect_url'];
        
        //redirect to the permission url
        header('Location: ' . $permission_url); 
    }

    public function storeShopDetails($dbh, $shop, $app_settings, $code) {
        //get permanent access token
        $access_token = shopify\access_token(
            $shop, $app_settings[0]['api_key'], $app_settings[0]['shared_secret'], $code
        );

        // $dbh is our PDO connection to the db
        $dbh = $this->connectToDb();
        
        //SQL to insert into the db with variables for latitude and longitude
        $stmt = $dbh->prepare("INSERT INTO tbl_usersettings (access_token, store_name) VALUES (:access_token, :store_name)");

        //bind the lat and long params to the query above. This also validates them
        $stmt->bindParam(':access_token', $access_token, PDO::PARAM_STR);
        $stmt->bindParam(':store_name', $shop, PDO::PARAM_STR);
    
        //execute the prepared statement
        $stmt->execute();

        //if there was a problem inserting into the db then die.
        if ($stmt->rowCount() < 1) {
          die('forbidden'); 
        }
        
        //close the db connection.
        $dbh = null;
        

        //save the signature and shop name to the current session
        $_SESSION['shopify_signature'] = $signature;
        $_SESSION['shop'] = $shop;
     
        header('Location: https://' . $_SESSION['shop'] . '/admin/apps/' . $app_settings[0]['api_key']);
    }

    public function loadAdminPage($getParam, $app_settings, $signature, $shop) {
          if(shopify\is_valid_request($getParam, $app_settings[0]['shared_secret'])){ //check if its a valid request from Shopify        
              
              $_SESSION['shopify_signature'] = $signature;
              $_SESSION['shop'] = $shop;
              //redirect to the admin page
              header('Location: https://' . $_SESSION['shop'] . '/admin/apps/' . $app_settings[0]['api_key']);
          } 
    }

    public function runApp() {
        
        //filter our _GET params
        $shop = filter_var($_GET['shop'], FILTER_SANITIZE_STRING);
        $signature = filter_var($_GET['signature'], FILTER_SANITIZE_STRING);
        $code = filter_var($_GET['code'], FILTER_SANITIZE_STRING);
     
        //check if the shop name is passed in the URL before we do anything
        if(!empty($shop)){ 

          //$dbh is our PDO connection to the db
          $dbh = $this->connectToDb();

          //get the app data from the db
          $app_settings = $this->fetchAppData($dbh, 1); 

          //check if the store exists and fetch its data
          $select_store = $this->fetchStoreData($dbh, $shop);

          if (count($select_store) > 0){
              //if the store exists in the db, check if its a valid request, set the signature and shop and then redirect to the admin page 
              $this->loadAdminPage($_GET, $app_settings, $signature, $shop);           
          } else {  
              //if store does NOT exist in db, get the permission needed and give the user a popup asking them to accept and install   
              $this->showPermissionsPage($shop, $app_settings);    
          }
        }

        /*NOTE --- $code is generated the by the api the first time the user installs app after being shown the permissions page.
        When its set we go into the if below and store the shop details.
        its not passed any other time so storeShopDetails is only called once*/
        if(!empty($shop) && !empty($code)){
            //If store doesnt exist in db then we add it for future use
            $this->storeShopDetails($dbh, $shop, $app_settings, $code); 
        }

        //close the db connection
        $dbh = null;
    }
}


$config = new Config;
$config = $config->returnConfigs();


$shopifyApp = new ShopifyApp($config);
$shopifyApp->runApp();

