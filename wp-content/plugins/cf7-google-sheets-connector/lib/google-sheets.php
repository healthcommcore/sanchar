<?php

if (!defined('ABSPATH'))
   exit;

include_once ( plugin_dir_path(__FILE__) . 'vendor/autoload.php' );

class cf7gsc_googlesheet {

   private $token;
   private $spreadsheet;
   private $worksheet;

   const clientId = '1075324102277-drjc21uouvq2d0l7hlgv3bmm67er90mc.apps.googleusercontent.com';
   const clientSecret = 'RFM9hElCqJMsXyc8YNjhf9Zs';
   const redirect = 'urn:ietf:wg:oauth:2.0:oob';

   private static $instance;

   public function __construct() {
      
   }

   public static function setInstance(Google_Client $instance = null) {
      self::$instance = $instance;
   }

   public static function getInstance() {
      if (is_null(self::$instance)) {
         throw new LogicException("Invalid Client");
      }

      return self::$instance;
   }

   //constructed on call
   public static function preauth($access_code) {
      $client = new Google_Client();
      $client->setClientId(cf7gsc_googlesheet::clientId);
      $client->setClientSecret(cf7gsc_googlesheet::clientSecret);
      $client->setRedirectUri(cf7gsc_googlesheet::redirect);
      $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
      $client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
      $client->setAccessType('offline');
      $client->fetchAccessTokenWithAuthCode($access_code);
      $tokenData = $client->getAccessToken();

      cf7gsc_googlesheet::updateToken($tokenData);
   }

   public static function updateToken($tokenData) {
      $tokenData['expire'] = time() + intval($tokenData['expires_in']);
      try {
         $tokenJson = json_encode($tokenData);
         update_option('gs_token', $tokenJson);
      } catch (Exception $e) {
         Gs_Connector_Utility::gs_debug_log("Token write fail! - " . $e->getMessage());
      }
   }

   public function auth() {
      $tokenData = json_decode(get_option('gs_token'), true);
      if (!isset($tokenData['refresh_token']) || empty($tokenData['refresh_token'])) {
         throw new LogicException("Auth, Invalid OAuth2 access token");
         exit();
      }

      try {
         $client = new Google_Client();
         $client->setClientId(cf7gsc_googlesheet::clientId);
         $client->setClientSecret(cf7gsc_googlesheet::clientSecret);
         $client->setScopes(Google_Service_Sheets::SPREADSHEETS);
         $client->setScopes(Google_Service_Drive::DRIVE_METADATA_READONLY);
         $client->refreshToken($tokenData['refresh_token']);
         $client->setAccessType('offline');
         cf7gsc_googlesheet::updateToken($tokenData);

         self::setInstance($client);
      } catch (Exception $e) {
         throw new LogicException("Auth, Error fetching OAuth2 access token, message: " . $e->getMessage());
         exit();
      }
   }

   public function get_user_data() {
      $client = self::getInstance();

      $results = $this->get_spreadsheets();

      echo '<pre>';
      print_r($results);
      echo '</pre>';
      $spreadsheets = $this->get_worktabs('1mRuDMnZveDFQrmzHM9s5YkPA4F_dZkHJ1Gh81BvYB2k');
      echo '<pre>';
      print_r($spreadsheets);
      echo '</pre>';
      $this->setSpreadsheetId('1mRuDMnZveDFQrmzHM9s5YkPA4F_dZkHJ1Gh81BvYB2k');
      $this->setWorkTabId('Foglio1');
      $worksheetTab = $this->list_rows();
      echo '<pre>';
      print_r($worksheetTab);
      echo '</pre>';
   }

   //preg_match is a key of error handle in this case
   public function setSpreadsheetId($id) {
      $this->spreadsheet = $id;
   }

   public function getSpreadsheetId() {

      return $this->spreadsheet;
   }

   public function setWorkTabId($id) {
      $this->worksheet = $id;
   }

   public function getWorkTabId() {
      return $this->worksheet;
   }

   public function add_row( $data ) {
      try {         
         $client = self::getInstance();
         $service = new Google_Service_Sheets($client);
         $spreadsheetId = $this->getSpreadsheetId(); 
         $work_sheets = $service->spreadsheets->get($spreadsheetId);

         if ( ! empty( $work_sheets ) && ! empty( $data ) ) {
            foreach ($work_sheets as $sheet) {
               $properties = $sheet->getProperties();

               $sheet_id = $properties->getSheetId();

               $worksheet_id = $this->getWorkTabId();

               if ($sheet_id == $worksheet_id) {
                  $worksheet_id = $properties->getTitle();


                  $worksheetCell = $service->spreadsheets_values->get($spreadsheetId, $worksheet_id . "!1:1");
                  $insert_data = array();
                  if (isset($worksheetCell->values[0])) {
                     $insert_data_index = 0;

                     foreach ($worksheetCell->values[0] as $k => $name) {

                        if ($insert_data_index == 0) {
                           if (isset($data[$name]) && $data[$name] != '') {

                        $insert_data[] = $data[$name];
                           } else {
                              $insert_data[] = '';
                           }
                        } else {
                           if (isset($data[$name]) && $data[$name] != '') {
                              $insert_data[] = $data[$name];
                           } else {
                              $insert_data[] = '';
                           }
                        }
                        $insert_data_index++;
                     }
                  }
                  $range_new = $worksheet_id;

                  // Create the value range Object
                  $valueRange = new Google_Service_Sheets_ValueRange();

                  // set values of inserted data
                  $valueRange->setValues(["values" => $insert_data]);
                  
                  // Add two values
                  // Then you need to add configuration
                  $conf = ["valueInputOption" => "RAW", "insertDataOption" => "INSERT_ROWS"];

                  // append the spreadsheet(add new row in the sheet)
                  $result = $service->spreadsheets_values->append($spreadsheetId, $range_new, $valueRange, $conf);
               }
            }
         }
      } catch (Exception $e) {
         return null;
         exit();
      }
   }
   //get all the spreadsheets
   public function get_spreadsheets() {
      $all_sheets = array();
      try {
         $client = self::getInstance();

         $service = new Google_Service_Drive($client);

         $optParams = array(
            'q' => "mimeType='application/vnd.google-apps.spreadsheet'"
         );
         $results = $service->files->listFiles($optParams);
        foreach ($results->files as $spreadsheet) {
            if (isset($spreadsheet['kind']) && $spreadsheet['kind'] == 'drive#file') {
               $all_sheets[] = array(
                  'id' => $spreadsheet['id'],
                  'title' => $spreadsheet['name'],
               );
            }
         }
      } catch (Exception $e) {
         return null;
         exit();
      }
      return $all_sheets;
   }
   
   //get worksheets title
   public function get_worktabs($spreadsheet_id) {


      $work_tabs_list = array();
      try {
         $client = self::getInstance();
         $service = new Google_Service_Sheets($client);
         $work_sheets = $service->spreadsheets->get($spreadsheet_id);


         foreach ($work_sheets as $sheet) {
            $properties = $sheet->getProperties();
            $work_tabs_list[] = array(
               'id' => $properties->getSheetId(),
               'title' => $properties->getTitle(),
            );
         }
      } catch (Exception $e) {
         return null;
         exit();
      }

      return $work_tabs_list;
   }
}
