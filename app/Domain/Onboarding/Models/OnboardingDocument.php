<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Models;

use App\Domain\Onboarding\Enums\DocumentType;
use App\Domain\Onboarding\Enums\VerificationStatus;
use App\Domain\Shared\Concerns\HasUuid;
use App\Models\User;
use Database\Factories\Onboarding\OnboardingDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property string $uuid
 * @property DocumentType $type
 * @property VerificationStatus $verification_status
 */
class OnboardingDocument extends Model
{
    /** @use HasFactory<OnboardingDocumentFactory> */
    use HasFactory;

    use HasUuid;
    use LogsActivity;

    protected $fillable = [
        'onboarding_application_id',
        'type',
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'verification_status' => VerificationStatus::class,
            'size_bytes' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OnboardingApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class, 'onboarding_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('onboarding_document')
            ->logOnly([
                'type',
                'verification_status',
                'verified_by',
                'uploaded_by',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function newFactory(): OnboardingDocumentFactory
    {
        return OnboardingDocumentFactory::new();
    }
}
