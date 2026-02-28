<?php

namespace App\Services\Edge;

use App\Models\Site;

interface EdgeProviderInterface
{
    public function key(): string;

    public function requiresCertificateValidation(): bool;

    public function requestCertificate(Site $site): array;

    public function checkCertificateValidation(Site $site): array;

    public function provision(Site $site): array;

    public function checkDns(Site $site): array;

    public function checkSsl(Site $site): array;

    public function purgeCache(Site $site, array $paths = ['/*']): array;

    public function setUnderAttackMode(Site $site, bool $enabled): array;

    public function setDevelopmentMode(Site $site, bool $enabled): array;

    public function deleteDeployment(Site $site): array;
}
