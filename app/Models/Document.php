<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['storage_path', 'sha256'];

    public const KINDS = [
        'agreement_of_sale' => 'Agreement of Sale',
        'national_id' => 'National ID',
        'passport' => 'Passport',
        'proof_of_residence' => 'Proof of residence',
        'council_clearance' => 'Council clearance certificate', // no longer asked for; kept to label older uploads
        'bank_statement' => 'Bank statement or payslip',
        'receipt' => 'Receipt',
        'other' => 'Other',
    ];

    /** What residents can choose when uploading. */
    public const UPLOAD_KINDS = ['agreement_of_sale', 'national_id', 'passport', 'proof_of_residence', 'bank_statement', 'receipt', 'other'];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
