<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamScore extends Model
{
    protected $fillable = [
        'applicant_id',
        'exam_schedule_id',
        'score',
        'total_items',
        'remarks',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'score'       => 'float',
        'total_items' => 'float',
        'recorded_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class);
    }

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    // Percentage score (0–100)
    public function getPercentageAttribute(): ?float
    {
        if ($this->score === null || !$this->total_items) {
            return null;
        }
        return round(($this->score / $this->total_items) * 100, 2);
    }

    // Passing threshold: 75%
    public function getPassedAttribute(): ?bool
    {
        if ($this->percentage === null) {
            return null;
        }
        return $this->percentage >= 75;
    }
}
