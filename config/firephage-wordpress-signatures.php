<?php

return [
    'version' => '2026.03.10.1',
    'high_confidence_patterns' => [
        '/eval\s*\(\s*base64_decode\s*\(/i' => 'eval(base64_decode())',
        '/gzinflate\s*\(\s*base64_decode\s*\(/i' => 'gzinflate(base64_decode())',
        '/preg_replace\s*\(.+\/e[\'"]?\s*,/i' => 'preg_replace /e modifier',
        '/\b(?:FilesMan|auth_pass)\b/i' => 'known webshell interface marker',
        '/(?:(?:include|include_once|require|require_once)\s+__DIR__\s*\.\s*[\'"][^\'"]+\.php[\'"]\s*;[\r\n\s]*){3,}/i' => 'staged loader include chain',
    ],
    'heuristic_patterns' => [],
];
