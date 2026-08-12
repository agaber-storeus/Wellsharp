<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case Issued = 'issued';
    case Revoked = 'revoked';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
