<?php
/*
Plugin Name: Twilio Verify Phone number
Description: Replace WordPress/Woocommerce's default user registration confirmation with Twilio Verify sms
Version: 1.0.0
*/
require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Twilio\Rest\Client;

function getFormattedPhoneNumber($twilio, $phoneNo) {
    $phone_number = $twilio->lookups->v1->phoneNumbers($phoneNo)
        ->fetch([
                "countryCode" => "AU",
                "type" => ["phoneNumber"]
            ]
        );
    
    return $phone_number->phoneNumber ?? '';
}

function send_verification_sms($request) {
    $user_phone = $_REQUEST['phoneno'] ?? '';

    $sid    = $_ENV["TWILIO_ACCOUNT_SID"];
    $token  = $_ENV["TWILIO_AUTH_TOKEN"];
    $verifySid = $_ENV["TWILIO_VERIFY_SID"];
    $twilio = new Client($sid, $token);

    if($user_phone) {
        $formattedNo = getFormattedPhoneNumber($twilio, $user_phone);

        if ($formattedNo) {
            $verification = $twilio->verify->v2->services($verifySid)
                ->verifications
                ->create($formattedNo, "sms");

            // Create the response object
            $response = new WP_REST_Response('Your code has been sent.');
            // Add a custom status code
            $response->set_status( 201 );

            return $response;
        } else {
            return new WP_Error(500, 'Your phone number is invalid.');
        }
    } else {
        return new WP_Error(500, 'Your phone number is missing.');
    }
}

function verify_sms($request) {
    $user_phone = $_REQUEST['phoneno'] ?? '';
    $user_code = $_REQUEST['phonecode'] ?? '';

    $sid    = $_ENV["TWILIO_ACCOUNT_SID"];
    $token  = $_ENV["TWILIO_AUTH_TOKEN"];
    $verifySid = $_ENV["TWILIO_VERIFY_SID"];
    $twilio = new Client($sid, $token);

    if($user_phone) {
        $formattedNo = getFormattedPhoneNumber($twilio, $user_phone);

        if ($formattedNo && $user_code) {
            $verification_check = $twilio->verify->v2->services($verifySid)
                ->verificationChecks
                ->create($user_code, // code
                    ["to" => $formattedNo]
                );

            $verify_status = $verification_check->status;
            
            if($verify_status === 'approved') {
                // Create the response object
                $response = new WP_REST_Response('Your code is successful.');
                // Add a custom status code
                $response->set_status( 201 );

                return $response;
            } else {
                return new WP_Error(500, 'Your code number is invalid.');
            }
        } else {
            return new WP_Error(500, 'Your phone number is invalid.');
        }
    } else {
        return new WP_Error(500, 'Your phone number is missing.');
    }    
}

add_action('rest_api_init', function () {
	register_rest_route( 'hostress/v1', '/send-sms/', array(
		'methods'  => 'GET',
		'callback' => 'send_verification_sms'
    ));
    
    register_rest_route( 'hostress/v1', '/verify-sms/', array(
		'methods'  => 'GET',
		'callback' => 'verify_sms'
	));
});
?>