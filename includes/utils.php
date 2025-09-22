<?php
declare(strict_types=1);

function sanitize_string(?string $value, int $maxLen = 255): string {
    $value = trim((string)($value ?? ''));
    if (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }
    return $value;
}

function sanitize_int(?string $value, int $min = 0, int $max = PHP_INT_MAX): int {
    $num = filter_var($value, FILTER_VALIDATE_INT);
    if ($num === false) { return 0; }
    if ($num < $min) { return $min; }
    if ($num > $max) { return $max; }
    return $num;
}

function is_valid_base64_image(string $dataUrl): bool {
    if (!preg_match('/^data:image\/(png|jpeg);base64,/', $dataUrl)) {
        return false;
    }
    $parts = explode(',', $dataUrl, 2);
    if (count($parts) !== 2) { return false; }
    return base64_decode($parts[1], true) !== false;
}

?>


