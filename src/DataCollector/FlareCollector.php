<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\DataCollector;

use Composer\InstalledVersions;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AutoconfigureTag('data_collector', ['id' => 'flare.collector'])]
final class FlareCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'version' => InstalledVersions::getVersion('heimrichhannot/contao-flare-bundle'),
        ];
    }

    public function getName(): string
    {
        return 'flare.collector';
    }

    public static function getTemplate(): ?string
    {
        return '@HeimrichHannotFlare/data_collector/flare.html.twig';
    }

    public function getVersion(): string
    {
        return $this->data['version'];
    }

    public function getSemVersion(): string
    {
        $parts = \explode('-', $this->data['version']);
        $parts = \explode('.', $parts[0]);
        $parts = \array_slice($parts, 0, 3);
        return \implode('.', $parts);
    }

    public function getVersionSuffix(): string
    {
        if (!\preg_match('/^(\d+\.)+\d+-[\w.]+$/', $this->data['version'])) {
            return '';
        }

        $dashed = \explode('-', $this->data['version'], 2);
        $numVer = \explode('.', $dashed[0]);
        $numVer = \array_slice($numVer, 3);
        $suffix = $numVer ? ('.' . \implode('.', $numVer)) : '';
        $suffix .= ($dashed[1] ?? null) ? ('-' . $dashed[1]) : '';
        return $suffix;
    }
}