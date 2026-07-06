<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_id', 'company_id', 'assigned_to', 'created_by',
        'po_number', 'customer', 'manufacturer', 'product_name',
        'inspection_date', 'location', 'status',
        'sampling_method', 'inspection_level', 'custom_sample_pct',
        'notes', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'inspection_date' => 'date',
            'submitted_at'    => 'datetime',
            'completed_at'    => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('item_no');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class)->orderBy('step_order');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'inspection_order_id');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isDraft(): bool       { return $this->status === 'draft'; }
    public function isInProgress(): bool  { return $this->status === 'in_progress'; }
    public function isSubmitted(): bool   { return $this->status === 'submitted'; }
    public function isInReview(): bool    { return $this->status === 'in_review'; }
    public function isApproved(): bool    { return $this->status === 'approved'; }
    public function isRejected(): bool    { return $this->status === 'rejected'; }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'in_progress']);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited();
    }

    public function currentApprovalStep(): ?ApprovalWorkflow
    {
        return $this->approvals()->where('status', 'pending')->first();
    }

    // ─── Sampling helpers ─────────────────────────────────────────────────────

    /**
     * Resolve effective sampling method — order overrides template.
     */
    public function effectiveSamplingMethod(): string
    {
        return $this->sampling_method ?? $this->template->sampling_method;
    }

    public function effectiveInspectionLevel(): string
    {
        return $this->inspection_level ?? $this->template->inspection_level ?? 'II';
    }
}