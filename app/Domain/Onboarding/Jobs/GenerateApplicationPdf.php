<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Jobs;

use App\Domain\Onboarding\Enums\AccountType;
use App\Domain\Onboarding\Enums\ApplicationPdfStatus;
use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Onboarding\Models\OnboardingApplication;
use App\Notifications\ApplicationReceivedNotification;
use Barryvdh\DomPDF\PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Renders the submitted application as a PDF replicating the relevant paper form,
 * stores it on the private onboarding disk, records it as an `application_form`
 * document, and emails a copy to the applicant attached to their confirmation.
 *
 * Idempotent: re-running (e.g. the Filament "Regenerate" action) replaces any
 * existing application-form PDF for the application.
 */
final class GenerateApplicationPdf implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly OnboardingApplication $application) {}

    public function handle(PDF $pdf, FilesystemFactory $filesystem): void
    {
        $application = $this->application->loadMissing(['company', 'principals', 'tradeReferences']);

        $diskName = (string) config('onboarding.documents.disk');
        $disk = $filesystem->disk($diskName);

        $view = $application->account_type_requested === AccountType::Credit
            ? 'pdf.application-credit'
            : 'pdf.application-cod';

        $contents = $pdf->loadView($view, [
            'application' => $application,
            'wordmark' => $this->wordmark(),
            'signature' => $this->signature($filesystem, $application),
        ])->output();

        // Replace any prior application-form PDF so regeneration never duplicates.
        $application->documents()
            ->where('type', DocumentType::ApplicationForm)
            ->get()
            ->each(function ($document) use ($filesystem): void {
                $filesystem->disk($document->disk)->delete($document->path);
                $document->delete();
            });

        $path = "onboarding/{$application->uuid}/application-form.pdf";
        $disk->put($path, $contents);

        $application->documents()->create([
            'type' => DocumentType::ApplicationForm,
            'disk' => $diskName,
            'path' => $path,
            'original_filename' => 'application-form.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            // System-generated artifact — not part of the manual document review.
            'verification_status' => VerificationStatus::Verified,
        ]);

        $application->forceFill(['application_pdf_status' => ApplicationPdfStatus::Generated])->save();

        $this->notifyApplicant($application, $diskName, $path);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);

        $application = $this->application->loadMissing('company');
        $application->forceFill(['application_pdf_status' => ApplicationPdfStatus::Failed])->save();

        // Still confirm receipt to the applicant, just without the PDF attached.
        $this->notifyApplicant($application, null, null);
    }

    private function notifyApplicant(OnboardingApplication $application, ?string $disk, ?string $path): void
    {
        (new AnonymousNotifiable)
            ->route('mail', $application->contact_email)
            ->notify(new ApplicationReceivedNotification(
                $application->contact_name,
                $application->company->legal_name,
                $disk,
                $path,
            ));
    }

    private function wordmark(): ?string
    {
        $file = public_path('images/brand/wordmark-full-color.png');

        if (! is_file($file)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($file));
    }

    private function signature(FilesystemFactory $filesystem, OnboardingApplication $application): ?string
    {
        if ($application->signature_path === null) {
            return null;
        }

        $disk = $filesystem->disk((string) config('onboarding.documents.disk'));

        if (! $disk->exists($application->signature_path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) $disk->get($application->signature_path));
    }
}
