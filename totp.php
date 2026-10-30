<?php
// RFC 6238-compatible 6-digit TOTP (SHA-1, 30-second period).
function secret(): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(random_bytes(20)) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 5) as $part) {
        $result .= $alphabet[bindec($part)];
    }
    return $result;
}
function otp(string $secret, int $timestamp): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper($secret)) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) return '';
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $key = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $key .= chr(bindec($byte));
    }
    $counter = intdiv($timestamp, 30);
    $message = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
    $hash = hash_hmac('sha1', $message, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}
function validotp(string $secret, string $code): bool {
    $code = preg_replace('/[\s-]+/', '', trim($code)) ?? '';
    if (!preg_match('/^[0-9]{6}$/', $code)) return false;
    for ($window = -1; $window <= 1; $window++) {
        if (hash_equals(otp($secret, time() + $window * 30), $code)) return true;
    }
    return false;
}
