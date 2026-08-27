<?php
// includes/mailer.php — centralized transactional email via Resend's REST API.
//
// Deliberately no SMTP library and no Composer dependency: just PHP's built-in
// curl extension (confirmed available on the vercel-php runtime this app
// deploys to). One HTTP POST per email, no vendor/ directory to bundle and
// hope survives deployment.
//
// Credentials come from environment variables only (set in the Vercel
// dashboard) — there is no safe hardcoded fallback for an API key, unlike
// config/db.php's dev-convenience DB fallback, so a missing key fails
// gracefully (logs and returns false) rather than sending nothing silently
// or crashing the page that tried to send it.

/**
 * Send a transactional HTML email via Resend.
 *
 * @param string $to       Recipient email address.
 * @param string $subject  Email subject line.
 * @param string $bodyHtml Email body as HTML.
 * @return bool True if Resend accepted the email, false otherwise (check
 *              the PHP error log for the reason — callers should treat a
 *              false return as "log it and move on", not fatal the request).
 */
function sendEmail(string $to, string $subject, string $bodyHtml): bool {
    $apiKey    = getenv('RESEND_API_KEY');
    $fromEmail = getenv('FROM_EMAIL') ?: 'onboarding@resend.dev';
    $fromName  = getenv('FROM_NAME') ?: 'Elysian Success';

    if (empty($apiKey)) {
        error_log('sendEmail: RESEND_API_KEY is not set — email to ' . $to . ' was not sent.');
        return false;
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('sendEmail: invalid recipient address, email not sent: ' . var_export($to, true));
        return false;
    }

    $payload = json_encode([
        'from'    => "{$fromName} <{$fromEmail}>",
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $bodyHtml,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("sendEmail: cURL error sending to {$to}: {$curlError}");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    error_log("sendEmail: Resend API returned HTTP {$httpCode} for {$to}: {$response}");
    return false;
}
