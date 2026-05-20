<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    protected $fillable = [
        'title',
        'exam_date',
        'start_time',
        'end_time',
        'venue',
        'batch',
        'slots',
        'instructions',
        'status',
        'exam_type',
    ];

    protected $casts = [
        'exam_date'  => 'date',
        'slots'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get start_time as a Carbon instance for formatting
     */
    public function getStartTimeAttribute($value)
    {
        if (!$value) return null;
        return \Carbon\Carbon::createFromTimeString($value);
    }

    /**
     * Get end_time as a Carbon instance for formatting
     */
    public function getEndTimeAttribute($value)
    {
        if (!$value) return null;
        return \Carbon\Carbon::createFromTimeString($value);
    }

    // Applicants assigned to this schedule
    public function applicants()
    {
        return $this->hasMany(Applicant::class);
    }

    // Scores recorded for this schedule
    public function scores()
    {
        return $this->hasMany(ExamScore::class);
    }

    public function questions()
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function activeQuestions()
    {
        return $this->hasMany(ExamQuestion::class)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attempts()
    {
        return $this->hasMany(ExamAttempt::class);
    }

    // How many slots are still available
    public function availableSlots(): int
    {
        return max(0, $this->slots - $this->applicants()->count());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Human-readable time range
    public function getTimeRangeAttribute(): string
    {
        if (!$this->start_time || !$this->end_time) {
            return 'TBD';
        }
        return $this->start_time->format('g:i A') . ' – ' . $this->end_time->format('g:i A');
    }

    // Check if exam requires manual score entry
    public function requiresManualScoring(): bool
    {
        return $this->exam_type === 'onsite';
    }

    // Check if exam is auto-graded
    public function isAutoGraded(): bool
    {
        return $this->exam_type === 'online';
    }
}
