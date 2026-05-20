<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamQuestion extends Model
{
    protected $fillable = [
        'exam_schedule_id',
        'question_text',
        'choices',
        'correct_answer',
        'points',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'choices' => 'array',
        'points' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function attemptAnswers()
    {
        return $this->hasMany(ExamAttemptAnswer::class);
    }
}
