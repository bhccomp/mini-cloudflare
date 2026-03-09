<?php

return [
    'version' => '2026.03.09.1',
    'high_confidence_patterns' => [
        '/eval\s*\(\s*base64_decode\s*\(/i' => 'eval(base64_decode())',
        '/gzinflate\s*\(\s*base64_decode\s*\(/i' => 'gzinflate(base64_decode())',
        '/preg_replace\s*\(.+\/e[\'"]?\s*,/i' => 'preg_replace /e modifier',
        '/(?:base64_decode|gzinflate|str_rot13)\s*\([^;]{0,120}(?:eval|assert)\s*\(/i' => 'encoded execution chain',
        '/\$_(?:POST|GET|REQUEST|COOKIE)\s*\[[^\]]+\]\s*\([^)]*\)\s*;/i' => 'user input invoked as code',
        '/\bnew\s+Function\s*\(/i' => 'dynamic JavaScript compilation',
        '/\b(?:system|shell_exec|passthru|proc_open|popen)\s*\(/i' => 'system command execution',
        '/php:\/\/input/i' => 'raw request body execution path',
        '/\b(?:FilesMan|auth_pass)\b/i' => 'known webshell interface marker',
        '/(?:include|include_once|require|require_once)\s+__DIR__\s*\.\s*[\'"][^\'"]+\.php[\'"]\s*;/i' => 'staged loader include chain',
    ],
    'heuristic_patterns' => [
        '/(?:shell_exec|passthru|proc_open|popen|system|exec)\s*\(/i' => ['label' => 'system command execution', 'score' => 3],
        '/\bassert\s*\(/i' => ['label' => 'dynamic code execution', 'score' => 2],
        '/(?:document\.write|String\.fromCharCode)\s*\(/i' => ['label' => 'obfuscated javascript output', 'score' => 1],
        '/(?:base64_decode|gzinflate|str_rot13|rawurldecode)\s*\(/i' => ['label' => 'encoded payload helper', 'score' => 1],
        '/[A-Za-z0-9+\/]{280,}={0,2}/' => ['label' => 'large embedded base64 blob', 'score' => 1],
        '/(?:error_reporting\s*\(\s*0\s*\)|ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]0[\'"]\s*\))/i' => ['label' => 'runtime concealment', 'score' => 1],
        '/\batob\s*\(/i' => ['label' => 'base64 decode in JavaScript', 'score' => 2],
        '/(?:fetch|XMLHttpRequest)\s*\(/i' => ['label' => 'runtime network fetch', 'score' => 1],
        '/https?:\/\/(?:cdn\.jsdelivr|unpkg|cdnjs)\.com/i' => ['label' => 'remote third-party script source', 'score' => 2],
        '/multipart\/form-data/i' => ['label' => 'file upload form handling', 'score' => 2],
        '/(?:move_uploaded_file|fwrite|file_put_contents|chmod|rename|touch)\s*\(/i' => ['label' => 'file manager behavior', 'score' => 2],
        '/\b(?:fsockopen|stream_socket_client)\s*\(/i' => ['label' => 'outbound socket usage', 'score' => 2],
        '/\b(?:phpinfo|uname\s+-a|/etc\/passwd|/bin\/sh)\b/i' => ['label' => 'host reconnaissance or shell marker', 'score' => 2],
        '/(?:<form[^>]+method\s*=\s*[\'"]post[\'"]|type\s*=\s*[\'"]hidden[\'"])/i' => ['label' => 'interactive control panel markup', 'score' => 1],
    ],
];
