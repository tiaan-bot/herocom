<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Enums;

enum DocumentType: string
{
    case CipcRegistration = 'cipc_registration';
    case VatCertificate = 'vat_certificate';
    case IdDocument = 'id_document';
    case SignedApplicationForm = 'signed_application_form';
    case BankConfirmation = 'bank_confirmation';
    case ProofOfAddress = 'proof_of_address';
    case DeedOfSurety = 'deed_of_surety';
}
