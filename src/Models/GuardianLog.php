<?php

namespace Shah\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class GuardianLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'guardian_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the signals as an array.
     */
    public function getSignalsArrayAttribute(): array
    {
        if (empty($this->signals)) {
            return [];
        }

        return json_decode($this->signals, true) ?: [];
    }

    /**
     * Scope a query to only include high confidence detections.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('score', '>=', 80);
    }

    /**
     * Scope a query to only include server-side detections.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeServerDetections($query)
    {
        return $query->where('detection_type', 'server');
    }

    /**
     * Scope a query to only include client-side detections.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeClientDetections($query)
    {
        return $query->where('detection_type', 'client');
    }

    /**
     * Get the detection confidence level.
     */
    public function getConfidenceLevelAttribute(): string
    {
        if ($this->score >= 80) {
            return 'very_high';
        } elseif ($this->score >= 60) {
            return 'high';
        } elseif ($this->score >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
