<?php
/* Minimal WP set up -- we are called directly, not through the normal WP
 * process.  We won't be displaying full fledged WP pages either.
 */
$wp_root = dirname(__FILE__) .'/../../../';
if(file_exists($wp_root . 'wp-load.php')) {
      require_once($wp_root . "wp-load.php");
} else if(file_exists($wp_root . 'wp-config.php')) {
      require_once($wp_root . "wp-config.php");
} else {
      exit;
}

@error_reporting(0);
  
global $wp_db_version;
if ($wp_db_version < 8201) {
	// Pre 2.6 compatibility (BY Stephen Rider)
	if ( ! defined( 'WP_CONTENT_URL' ) ) {
		if ( defined( 'WP_SITEURL' ) ) define( 'WP_CONTENT_URL', WP_SITEURL . '/wp-content' );
		else define( 'WP_CONTENT_URL', get_option( 'url' ) . '/wp-content' );
	}
	if ( ! defined( 'WP_CONTENT_DIR' ) ) define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ( ! defined( 'WP_PLUGIN_URL' ) ) define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

require_once(ABSPATH.'wp-admin/admin.php');

/* Make sure we are first and only program */
if (headers_sent()) {
  @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
  wp_die(__('The headers have been sent by another plugin - there may be a plugin conflict.','web-librarian'));
}

define('WEBLIB_FILE', basename(__FILE__));
define('WEBLIB_DIR' , dirname(__FILE__));
define('WEBLIB_INCLUDES', WEBLIB_DIR . '/includes');
require_once(WEBLIB_INCLUDES . '/database_code.php');
require_once(WEBLIB_INCLUDES . '/admin_page_classes.php');

$barcode = $_REQUEST['barcode'];
$xml_response = '<?xml version="1.0" ?>';

if (current_user_can('manage_circulation') && isset($_REQUEST['patronid'])) {
  $patronid = $_REQUEST['patronid'];
} else {
  $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
}
//file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (before if) xml_response = $xml_response\n");

if (!WEBLIB_ItemInCollection::IsItemInCollection($barcode)) {
  $xml_response .= '<message>'.sprintf(__('No such item: %s!','web-librarian'),$barcode).'</message>';
} else if ($patronid == '' || !WEBLIB_Patron::ValidPatronID($patronid)) {
  $xml_response .= '<message>'.sprintf(__('No such patron id: %d!','web-librarian'),$patronid).'</message>';
} else if (WEBLIB_HoldItem::PatronAlreadyHolds($patronid,$barcode)) {
  $xml_response .= '<message>'.__('Patron already has a hold on this item!','web-librarian').'</message>';
} else {
  $item = new WEBLIB_ItemInCollection($barcode);
  if ($item->type() == '' && !WEBLIB_Type::KnownType($item->type())) {
    $xml_response .= '<message>'.sprintf(__('Item has invalid type: %s!','web-librarian'),$item->type()).'</message>';
  } else {
    $type = new WEBLIB_Type($item->type());
    $expiredate = date('Y-m-d',time()+($type->loanperiod()*24*60*60));
    $transaction = $item->hold($patronid, 'Local', $expiredate);
    if ($transaction > 0) {
      $newhold = new WEBLIB_HoldItem($transaction);
      $patronid = $newhold->patronid();
      $telephone = WEBLIB_Patrons_Admin::addtelephonedashes(
                                WEBLIB_Patron::TelephoneFromId($patronid)
                                );
      $userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
      $email = get_userdata( $userid )->user_email;
      $patronname = WEBLIB_Patron::NameFromID($patronid);
      $expires = mysql2date('F j, Y',$newhold ->dateexpire());
      $xml_response .= '<result><barcode>'.$barcode.'</barcode><holdcount>'.
      WEBLIB_HoldItem::HoldCountsOfBarcode($barcode).
      '</holdcount><name>'.$patronname.'</name><email>'.$email.
      '</email><telephone>'.$telephone.'</telephone><expires>'.$expires.
      '</expires></result>';
      //file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (after transaction) xml_response = $xml_response\n");
    } else {
      $xml_response .= '<message>Hold failed!</message>';
    }
  }
}

//file_put_contents("php://stderr","*** PlaceHoldOnItem.php: (after if) xml_response = $xml_response\n");

/* http Headers */
@header('Content-Type: text/xml');
@header('Content-Length: '.strlen($xml_response));
@header("Pragma: no-cache");
@header("Expires: 0");
@header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
@header("Robots: none");
echo "";	/* End of headers */
echo $xml_response;


  

