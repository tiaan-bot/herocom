<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\DataTransferObjects;

use App\Domain\Onboarding\Enums\DocumentType;
use Illuminate\Http\UploadedFile;

final readonly class DocumentUploadData
{
    public function __construct(
        public DocumentType $type,
        public UploadedFile $file,
    ) {}
}
