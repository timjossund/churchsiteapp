<?php

namespace App\Exceptions;

use RuntimeException;

class DomainProvisioningFailed extends RuntimeException
{
    public function __construct(public readonly string $category)
    {
        // Never retain a provider response, Authorization header, or chained HTTP exception.
        parent::__construct('Domain reconciliation failed: '.$category);
    }
}
