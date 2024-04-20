/*
This code was written to solve the problem of digital product delivery.
You want to automatically email a customer a link, but not with an eternally lasting download link.
This code accepts a POST from stripe from the payment_intent.succeeded event.
It parses the POST for the customer email.
It uses amazon s3 to generate a presigned url which lasts for 24 hours.
It emails the customer the download link using amazon SES.
*/

<?php 
require 'vendor/autoload.php';

use Aws\Ses\SesClient;
use Aws\S3\S3Client; 
use Aws\Exception\AwsException;

// Set your secret key. Remember to switch to your live secret key in production!
// See your keys here: https://dashboard.stripe.com/account/apikeys
\Stripe\Stripe::setApiKey('YOUR_KEY_HERE');

$payload = @file_get_contents('php://input');
$event = null;

try {
    $event = \Stripe\Event::constructFrom(
        json_decode($payload, true)
    );
} catch(\UnexpectedValueException $e) {
    // Invalid payload
    http_response_code(400);
    exit();
}

// Handle the event

switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object; // contains a StripePaymentIntent
        // code when payment succeeds 
		// Pass the provider to the client
		$s3Client = new Aws\S3\S3Client([
			'region' => 'SEET_YOU_REGION_HERE',  //SET YOUR OWN REGION!
			'version' => 'latest',
		]);
		//Creating a presigned URL
		$cmd = $s3Client->getCommand('GetObject', [
			'Bucket' => 'YOUR_OWN_BUCKET',  // SET YOUR REGION!
			'Key' => 'YOUR_OWN_OBJECT'      // SET YOUR ITEM!
		]);
		$request = $s3Client->createPresignedRequest($cmd, '+86400 seconds');
		// Get the actual presigned-url
		$presignedUrl = (string)$request->getUri();		
		echo($presignedUrl);
		
		// Get the email of the buyer
		echo($event->data->object['receipt_email']);
		http_response_code(200);

		// Send email
		// Create an SesClient. Change the value of the region parameter  
		// Change the value of the profile parameter if you want to use a profile in your credentials file
		// other than the default.
		$SesClient = new SesClient([
			'version' => 'latest',
			'region'  => 'YOUR_REGION'
		]);

		// Replace sender@example.com with your "From" address.
		// This address must be verified with Amazon SES.
		$sender_email = 'YOUR_EMAIL_HERE';

		// Replace these sample addresses with the addresses of your recipients. If
		// your account is still in the sandbox, these addresses must be verified.
		$recipient_emails = [$event->data->object['receipt_email']];

		// Email content here

		$subject = 'YOUR SUBJECT HERE';
		$plaintext_body = $presignedUrl ;
		$html_body =  '<h1>YOUR HEADING</h1>'.
					  '<p>Download your ITEM at <a href="' . $presignedUrl . '">'.
					  'This link</a>.<br /<br />Thank you for your purchase!</p>';
		$char_set = 'UTF-8';

		// Send mail

		try {
			$result = $SesClient->sendEmail([
				'Destination' => [
					'ToAddresses' => $recipient_emails,
				],
				'ReplyToAddresses' => [$sender_email],
				'Source' => $sender_email,
				'Message' => [
				  'Body' => [
					  'Html' => [
						  'Charset' => $char_set,
						  'Data' => $html_body,
					  ],
					  'Text' => [
						  'Charset' => $char_set,
						  'Data' => $plaintext_body,
					  ],
				  ],
				  'Subject' => [
					  'Charset' => $char_set,
					  'Data' => $subject,
				  ],
				],
				// If you aren't using a configuration set, comment or delete the
				// following line
				//'ConfigurationSetName' => $configuration_set,
			]);
			$messageId = $result['MessageId'];
			echo("Email sent! Message ID: $messageId"."\n");
		} catch (AwsException $e) {
			// output error message if fails
			echo $e->getMessage();
			echo("The email was not sent. Error message: ".$e->getAwsErrorMessage()."\n");
			echo "\n";
		}
		// End Mail code
		
        break;
    default:
        echo 'Received unknown event type ' . $event->type;
}




?>
