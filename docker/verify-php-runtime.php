<?php

// Check the actual runtime, including FrankenPHP's embedded PHP.
$patched = (PHP_VERSION_ID >= 80427 && PHP_VERSION_ID < 80500)
    || (PHP_VERSION_ID >= 80512 && PHP_VERSION_ID < 80600);

if ($patched === false || filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOL)) {
    fwrite(STDERR, 'Unsafe PHP runtime or session upload progress setting: '.PHP_VERSION."\n");
    exit(1);
}

fwrite(STDOUT, 'Verified PHP '.PHP_VERSION.' ('.PHP_SAPI.")\n");
