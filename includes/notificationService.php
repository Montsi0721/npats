<?php 
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__. '/../');
$dotenv->load();

function sendApplicationEmail($to, $appNumber, $stage, $status) {
    if (empty($to)) {
        error_log("Email skipped: recipient is empty");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        error_log("Email: Initializing SMTP...");
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port = $_ENV['SMTP_PORT'];

        error_log("Email: SMTP configured (Host: {$_ENV['SMTP_HOST']}, Port: {$_ENV['SMTP_PORT']})");

        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_NAME']);
        $mail->addAddress($to);

        error_log("Email: Sending to $to");

        $mail->Subject = "Application Update: $appNumber";
        $mail->isHTML(true);

        // Define the email content (HTML formatted message)
        $mail->Body = "
            <div style='font-family: Arial; padding: 20px; border: 1px solid #ddd; border-radius: 8px;'>
                <h2 style='color: #2c3e50;'>Application Update</h2>
                <p>Hello,</p>
                <p>Your application <strong>$appNumber</strong> has been updated.</p>
                <p>
                    <span style='color: #555;'>Stage:</span>
                    <strong>" . htmlspecialchars($stage) . "</strong><br>
                    <span style='color: #555;'>Status:</span>
                    <strong>" . htmlspecialchars($status) . "</strong>
                </p>
                <p style='margin-top: 20px; font-size: 12px; color: #888;'>
                    Please log in to view details.
                </p>
            </div>
            ";

        // Send the email using SMTP settings above
        $mail->send();

        error_log("Email SUCCESS: Sent to $to for app $appNumber");

    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
    }
}

function sendApplicationSMS($phone, $appNumber, $stage, $status) {

    if (empty($phone)) {
        error_log("SMS skipped: empty phone number");
        return false;
    }

    // Ensure number is in international format (basic safeguard)
    $phone = preg_replace('/\D+/', '', $phone);

    $message = "App {$appNumber}: {$stage} is now {$status}";

    // SMSPortal bulk format (correct structure)
    $payload = [
        "Messages" => [
            [
                "Destination" => $phone,
                "Body" => $message
            ]
        ]
    ];

    $url = "https://rest.smsportal.com/v1/bulkmessages";

    $clientId = $_ENV['SMS_CLIENT_ID'];
    $apiSecret = $_ENV['SMS_API_SECRET'];

    
    if (!$clientId || !$apiSecret) {
        error_log("SMS credentials missing... $clientId, $apiSecret");
        return false;
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode($clientId . ":" . $apiSecret),
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        error_log("SMS CURL ERROR: " . $error);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("SMS FAILED [$httpCode]: " . $response);
        return false;
    }

    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("SMS INVALID JSON RESPONSE: " . $response);
        return false;
    }

    error_log("SMS SENT SUCCESS: {$phone} | {$appNumber}");

    return $decoded;
}
?>