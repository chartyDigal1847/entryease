<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Applicant extends Model
{
    use HasFactory;

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
}
