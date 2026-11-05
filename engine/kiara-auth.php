<?php
// Kiara VK bridge, protocol kiara-vk-1. Requires PHP 7.4+, cURL and writable private storage.
// Included by engine/auth.php. No VK client secret is sent to or stored in the Android app.

function kiara_json($status, $payload, $http = 200)
{
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    echo json_encode(array('status' => $status, 'payload' => $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function kiara_post_string($name)
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
}

function kiara_storage_dir()
{
    // storage/.htaccess already denies HTTP access. Prefer a path OUTSIDE the web root.
    return defined('KIARA_OAUTH_STORAGE_DIR')
        ? KIARA_OAUTH_STORAGE_DIR : dirname(__DIR__).'/storage/kiara-oauth';
}

function kiara_parse_browser_state($state)
{
    if (!is_string($state) ||
        !preg_match('/\Akiara1\.([a-f0-9]{32})\.([a-f0-9]{64})\z/D', $state, $matches)) {
        return false;
    }
    return array('state' => $matches[1], 'challenge' => $matches[2]);
}

function kiara_callback_url($state, $ticket = null)
{
    $params = array('state' => $state);
    if ($ticket === null) {
        $params['error'] = 'authorization_failed';
    } else {
        $params['ticket'] = $ticket;
    }
    // Fixed destination: never use a redirect URI supplied by the browser/client.
    return 'kiara-vk://auth?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function kiara_create_ticket($authToken, $challenge)
{
    if (!is_string($authToken) || !preg_match('/\A[a-f0-9]{32}\z/D', $authToken)) {
        throw new RuntimeException('Invalid internal token');
    }
    $dir = kiara_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create handoff storage');
    }
    $ticket = bin2hex(random_bytes(32));
    $path = $dir.'/'.hash('sha256', $ticket).'.json';
    $oldMask = umask(0077);
    $file = fopen($path, 'x+b');
    umask($oldMask);
    if (!$file) throw new RuntimeException('Cannot create handoff');
    $json = json_encode(array(
        'expires_at' => time() + 120,
        'challenge' => $challenge,
        'auth_token' => $authToken
    ));
    $written = fwrite($file, $json);
    fclose($file);
    if ($written !== strlen($json)) {
        @unlink($path);
        throw new RuntimeException('Cannot save handoff');
    }
    // Opportunistic expiry cleanup. No credential-bearing filename or output.
    foreach (glob($dir.'/*.json') ?: array() as $candidate) {
        if (filemtime($candidate) < time() - 600) @unlink($candidate);
    }
    return $ticket;
}

function kiara_redeem_ticket($ticket, $verifier)
{
    if (!is_string($ticket) || !preg_match('/\A[a-f0-9]{64}\z/D', $ticket) ||
        !is_string($verifier) || !preg_match('/\A[a-f0-9]{64}\z/D', $verifier)) {
        return false;
    }
    $path = kiara_storage_dir().'/'.hash('sha256', $ticket).'.json';
    $file = @fopen($path, 'r+b');
    if (!$file) return false;
    if (!flock($file, LOCK_EX)) {
        fclose($file);
        return false;
    }
    $record = json_decode(stream_get_contents($file, 4096), true);
    $valid = is_array($record) && isset($record['expires_at'], $record['challenge'], $record['auth_token']) &&
        $record['expires_at'] >= time() &&
        hash_equals($record['challenge'], hash('sha256', $verifier));
    $token = $valid ? $record['auth_token'] : false;
    if ($valid || (is_array($record) && ($record['expires_at'] ?? 0) < time())) {
        // A second concurrent redeemer sees an empty record even if it opened the file before unlink.
        ftruncate($file, 0);
        fflush($file);
        @unlink($path);
    }
    flock($file, LOCK_UN);
    fclose($file);
    return $token;
}

function kiara_vk_user_id($accessToken)
{
    if (!is_string($accessToken) || strlen($accessToken) < 16 || strlen($accessToken) > 8192) {
        return false;
    }
    $curl = curl_init('https://api.vk.com/method/users.get');
    if (!$curl) return false;
    curl_setopt_array($curl, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(array(
            'access_token' => $accessToken, 'v' => '5.199'
        ), '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));
    $response = curl_exec($curl);
    $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === false || $http !== 200) return false;
    $json = json_decode($response, true);
    // With no user_ids parameter, VK returns the owner of the USER token.
    // Never trust an Android-provided user_id, email, name or a decoded-but-unverified token.
    if (!is_array($json) || isset($json['error']) || !isset($json['response']) ||
        !is_array($json['response']) || count($json['response']) !== 1) return false;
    $id = $json['response'][0]['id'] ?? null;
    return filter_var($id, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
}

function kiara_handle_external_request()
{
    global $external;
    if (!$external) return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = kiara_post_string('action');
        if ($action === 'capabilities') {
            kiara_json(0, array('protocol' => 'kiara-vk-1'));
        }
        if ($action === 'redeem') {
            $token = kiara_redeem_ticket(kiara_post_string('ticket'), kiara_post_string('verifier'));
            if ($token === false) kiara_json(2, 'Invalid or expired handoff', 400);
            kiara_json(0, $token);
        }
        if ($action === 'native') {
            $id = kiara_vk_user_id(kiara_post_string('access_token'));
            if (!$id) kiara_json(2, 'VK token validation failed', 401);
            // The existing account mapping/token issuance is shared with browser OAuth.
            processIncomingLogin('vk_'.$id);
            return;
        }
        kiara_json(1, 'Unsupported authentication request', 400);
    }
    // Existing web and Telegram flows remain unchanged unless this exact state prefix is present.
    $context = kiara_parse_browser_state($_GET['state'] ?? null);
    if ($context !== false) $GLOBALS['kiara_browser_context'] = $context;
}

function kiara_finish($status, $payload)
{
    $context = $GLOBALS['kiara_browser_context'] ?? null;
    if ($context === null) return;
    $ticket = null;
    if ($status === 0) {
        try {
            $ticket = kiara_create_ticket($payload, $context['challenge']);
        } catch (Throwable $error) {
            // Do not log VK codes, access tokens, private verifiers or internal chat tokens.
        }
    }
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('Location: '.kiara_callback_url($context['state'], $ticket), true, 303);
    exit;
}
