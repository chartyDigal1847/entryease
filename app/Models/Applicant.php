<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

    const STATUS_PENDING      = 'Pending';
    const STATUS_UNDER_REVIEW = 'Under Review';
    const STATUS_APPROVED     = 'Approved';
    const STATUS_REJECTED     = 'Rejected';

    const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'deoris_user_id',
        'portal_student_email',
        'portal_student_name',
        'grade_level',
        'additional_info',
        'status',
        'admission_status',
        'admin_notes',
        'reviewed_by',
        'exam_schedule_id',
        'exam_seat_number',
        'exam_room',
        'photo_2x2',
        'psa_birth_cert',
        'documents_updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'documents_updated_at' => 'datetime',
    ];

    public function getStudentEmailAttribute(): ?string
    {
        return $this->portal_student_email;
    }

    public function getStudentNameAttribute(): ?string
    {
        return $this->portal_student_name;
    }

    /** Legacy view accessor: $applicant->student->email */
    public function getStudentAttribute(): object
    {
        return (object) [
            'email' => $this->portal_student_email,
            'name' => $this->portal_student_name,
        ];
    }

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function examScore()
    {
        return $this->hasOne(ExamScore::class);
    }

    public function examAttempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function latestExamAttempt()
    {
        return $this->hasOne(ExamAttempt::class)->latestOfMany();
    }

    public function scopePending($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'Pending')
              ->orWhere('admission_status', 'pending');
        });
    }

    public function scopeUnderReview($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'Under Review')
              ->orWhere('admission_status', 'under_review');
        });
    }

    public function scopeApproved($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'Approved')
              ->orWhere('admission_status', 'approved');
        });
    }

    public function scopeRejected($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'Rejected')
              ->orWhere('admission_status', 'rejected');
        });
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status) {
            return $this->status;
        }

        return match ($this->admission_status) {
            'pending' => 'Pending',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', $this->admission_status ?? 'Pending')),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->effective_status) {
            'Pending' => 'pending',
            'Under Review' => 'review',
            'Approved' => 'approved',
            'Rejected' => 'rejected',
            default => 'pending',
        };
    }

    public static function admissionStatusFor(string $status): string
    {
        return match ($status) {
            self::STATUS_APPROVED     => 'approved',
            self::STATUS_REJECTED     => 'rejected',
            self::STATUS_UNDER_REVIEW => 'under_review',
            default                   => 'pending',
        };
    }

    public function hasPassingExamScore(): bool
    {
        $this->loadMissing('examScore');

        return $this->examScore !== null && $this->examScore->passed === true;
    }

    public function canTransitionTo(string $newStatus): bool
    {
        if ($newStatus === $this->status) {
            return true;
        }

        return in_array($newStatus, $this->nextStatuses(), true);
    }

    /**
     * @return list<string>
     */
    public function nextStatuses(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => [
                self::STATUS_UNDER_REVIEW,
                self::STATUS_REJECTED,
            ],
            self::STATUS_UNDER_REVIEW => array_values(array_filter([
                $this->hasPassingExamScore() ? self::STATUS_APPROVED : null,
                self::STATUS_REJECTED,
            ])),
            default => [],
        };
    }

    /**
     * Human-readable exam workflow stage for UI.
     */
    public function getExamStageLabelAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return '';
        }

        $this->loadMissing('examScore');

        if ($this->examScore) {
            return $this->examScore->passed ? 'Exam passed' : 'Exam failed';
        }

        if ($this->exam_schedule_id) {
            return 'Exam scheduled';
        }

        return 'No exam scheduled';
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function transitionTo(string $newStatus, ?string $reviewedBy = null): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            if ($newStatus === self::STATUS_APPROVED && $this->status === self::STATUS_UNDER_REVIEW) {
                throw new \InvalidArgumentException(
                    'Approval requires a passing exam score (75% or higher).'
                );
            }

            throw new \InvalidArgumentException(
                "Cannot transition application from '{$this->status}' to '{$newStatus}'."
            );
        }

        if ($newStatus === $this->status) {
            return;
        }

        $data = [
            'status'           => $newStatus,
            'admission_status' => self::admissionStatusFor($newStatus),
        ];

        if ($reviewedBy !== null) {
            $data['reviewed_by'] = $reviewedBy;
        }

        $this->update($data);
    }
}
