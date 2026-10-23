<?php

declare(strict_types=1);

// This checks upstream CLI releases only. Verify the HTTP worker separately,
// and check vendor advisories for builds that carry backported fixes.
$fixedVersions = [
    '8.3' => '8.3.36',
    '8.4' => '8.4.27',
    '8.5' => '8.5.12',
];

$branch = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
$fixedVersion = $fixedVersions[$branch] ?? null;

if ($fixedVersion === null || version_compare(PHP_VERSION, $fixedVersion, '<')) {
    fwrite(STDERR, "PHP ".PHP_VERSION." is not an upstream fixed release for CVE-2026-109618 and CVE-2026-109619.\n");
    fwrite(STDERR, "Use PHP 8.3.36+, 8.4.27+, or 8.5.12+ on the respective branch. Verify the HTTP worker separately.\n");
    exit(1);
}

fwrite(STDOUT, "PHP ".PHP_VERSION." meets the upstream PHP2Shell fixed-version threshold (CLI only).\n");
