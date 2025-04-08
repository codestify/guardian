<?php

namespace Shah\Guardian\Detection;

class DetectionResult
{
    /**
     * The detection score (0-100).
     *
     * @var int
     */
    public $score;

    /**
     * The detection signals.
     *
     * @var array
     */
    public $signals;

    /**
     * Confidence level classifications.
     *
     * @var array
     */
    private const CONFIDENCE_LEVELS = [
        'low' => [0, 39],
        'medium' => [40, 59],
        'high' => [60, 79],
        'very_high' => [80, 100],
    ];

    /**
     * Create a new detection result instance.
     *
     * @return void
     */
    public function __construct(int $score, array $signals = [])
    {
        $this->score = min(100, max(0, $score)); // Ensure score is between 0-100
        $this->signals = $signals;
    }

    /**
     * Determine if the request was detected as an AI crawler.
     */
    public function isDetected(): bool
    {
        $threshold = config('guardian.detection.threshold', 60);

        return $this->score >= $threshold;
    }

    /**
     * Get the confidence level classification.
     */
    public function confidenceLevel(): string
    {
        foreach (self::CONFIDENCE_LEVELS as $level => [$min, $max]) {
            if ($this->score >= $min && $this->score <= $max) {
                return $level;
            }
        }

        return 'unknown';
    }

    /**
     * Add a signal to the detection result.
     *
     * @param  int|string|bool  $value
     * @return $this
     */
    public function addSignal(string $signal, $value = true, int $weight = 1): self
    {
        $this->signals[$signal] = $value;
        $this->adjustScore($weight);

        return $this;
    }

    /**
     * Adjust the score by a given weight.
     *
     * @return $this
     */
    public function adjustScore(int $weight): self
    {
        $this->score = min(100, $this->score + $weight);

        return $this;
    }

    /**
     * Increase the score by a given amount.
     *
     * @param  int  $amount  The amount to increase the score by
     * @return $this
     */
    public function increaseScore(int $amount): self
    {
        return $this->adjustScore($amount);
    }

    /**
     * Get the most significant signals.
     */
    public function topSignals(int $limit = 5): array
    {
        $signalWeights = [];

        // Extract weights or assign default weight of 1
        foreach ($this->signals as $signal => $value) {
            if (is_numeric($value)) {
                $signalWeights[$signal] = $value;
            } else {
                $signalWeights[$signal] = 1;
            }
        }

        // Sort by weight (descending)
        arsort($signalWeights);

        // Return top signals
        return array_slice($signalWeights, 0, $limit, true);
    }

    /**
     * Merge with another detection result.
     *
     * @return $this
     */
    public function merge(DetectionResult $result): self
    {
        $this->score = min(100, $this->score + $result->score);
        $this->signals = array_merge($this->signals, $result->signals);

        return $this;
    }

    /**
     * Convert the detection result to an array.
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'signals' => $this->signals,
            'detected' => $this->isDetected(),
            'confidence' => $this->confidenceLevel(),
        ];
    }

    /**
     * Convert the detection result to JSON.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
