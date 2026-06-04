<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Actions;

use App\Domain\Onboarding\Models\OnboardingDocument;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

final class GenerateOnboardingDocumentUrlAction
{
    public function __construct(
        private readonly Gate $gate,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * Generate a short-lived signed URL for a document, logging the access.
     * The stored path is never exposed directly.
     */
    public function execute(OnboardingDocument $document, User $actor): string
    {
        $this->gate->forUser($actor)->authorize('view', $document->application);

        activity('onboarding')
            ->causedBy($actor)
            ->performedOn($document)
            ->event('document_accessed')
            ->log('Generated signed URL for onboarding document');

        $ttl = (int) config('onboarding.documents.url_ttl_minutes', 5);

        return $this->filesystem
            ->disk($document->disk)
            ->temporaryUrl($document->path, CarbonImmutable::now()->addMinutes($ttl));
    }
}
