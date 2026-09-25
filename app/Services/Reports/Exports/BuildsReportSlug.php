<?php

namespace App\Services\Reports\Exports;

trait BuildsReportSlug
{
    private function slug(string $value): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($value))) ?: 'export';
    }
}
