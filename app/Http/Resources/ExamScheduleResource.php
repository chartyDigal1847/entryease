<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'exam_date'       => $this->exam_date?->toDateString(),
            'start_time'      => $this->start_time?->format('H:i'),
            'end_time'        => $this->end_time?->format('H:i'),
            'time_range'      => $this->time_range,
            'venue'           => $this->venue,
            'batch'           => $this->batch,
            'slots'           => $this->slots,
            'available_slots' => $this->availableSlots(),
            'exam_type'       => $this->exam_type,
            'status'          => $this->status,
            'instructions'    => $this->instructions,
            'applicants_count'=> $this->whenCounted('applicants'),
            'questions_count' => $this->whenCounted('questions'),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
