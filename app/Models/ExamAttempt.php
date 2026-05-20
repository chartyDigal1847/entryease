<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = [
        'applicant_id',
        'exam_schedule_id',
        'status',
        'score',
        'total_items',
        'started_at',
        'submitted_at',
    ];

    protected $casts = [
        'score' => 'float',
        'total_items' => 'float',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function answers()
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }

    public function getPercentageAttribute(): ?float
    {
        if (!$this->total_items) {
            return null;
        }

        return round(($this->score / $this->total_items) * 100, 2);
    }
}
