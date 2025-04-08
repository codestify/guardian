<?php

namespace Shah\Guardian\Tests\Unit;

use Shah\Guardian\Detection\DetectionResult;

it('creates a valid detection result', function () {
    $result = new DetectionResult(50, ['test_signal' => true]);

    expect($result->score)->toBe(50)
        ->and($result->signals)->toBe(['test_signal' => true]);
});

it('ensures score stays within valid range', function () {
    $lowResult = new DetectionResult(-10, []);
    $highResult = new DetectionResult(120, []);

    expect($lowResult->score)->toBe(0)
        ->and($highResult->score)->toBe(100);
});

it('correctly determines if detection threshold is met', function () {
    // Set config threshold to 60 for this test
    config(['guardian.detection.threshold' => 60]);

    $belowThreshold = new DetectionResult(59, []);
    $atThreshold = new DetectionResult(60, []);
    $aboveThreshold = new DetectionResult(70, []);

    expect($belowThreshold->isDetected())->toBeFalse()
        ->and($atThreshold->isDetected())->toBeTrue()
        ->and($aboveThreshold->isDetected())->toBeTrue();
});

it('provides correct confidence level', function () {
    $lowConfidence = new DetectionResult(30, []);
    $mediumConfidence = new DetectionResult(45, []);
    $highConfidence = new DetectionResult(65, []);
    $veryHighConfidence = new DetectionResult(85, []);

    expect($lowConfidence->confidenceLevel())->toBe('low')
        ->and($mediumConfidence->confidenceLevel())->toBe('medium')
        ->and($highConfidence->confidenceLevel())->toBe('high')
        ->and($veryHighConfidence->confidenceLevel())->toBe('very_high');
});

it('adds signals and adjusts score correctly', function () {
    $result = new DetectionResult(0, []);

    $result->addSignal('signal1', true, 20);
    expect($result->score)->toBe(20)
        ->and($result->signals)->toHaveKey('signal1');

    $result->addSignal('signal2', 'value2', 30);
    expect($result->score)->toBe(50)
        ->and($result->signals)->toHaveKey('signal2')
        ->and($result->signals['signal2'])->toBe('value2');

    // Score should cap at 100
    $result->addSignal('signal3', true, 60);
    expect($result->score)->toBe(100);
});

it('retrieves top signals correctly', function () {
    $result = new DetectionResult(0, [
        'low_signal' => 1,
        'medium_signal' => 5,
        'high_signal' => 10,
        'critical_signal' => 20,
        'boolean_signal' => true,
    ]);

    $topSignals = $result->topSignals(3);

    // Should return highest values first
    expect($topSignals)->toHaveCount(3)
        ->and(array_keys($topSignals)[0])->toBe('critical_signal')
        ->and(array_keys($topSignals)[1])->toBe('high_signal')
        ->and(array_keys($topSignals)[2])->toBe('medium_signal');
});

it('merges multiple detection results correctly', function () {
    $result1 = new DetectionResult(30, ['signal1' => true]);
    $result2 = new DetectionResult(40, ['signal2' => 'value2']);

    $result1->merge($result2);

    expect($result1->score)->toBe(70)
        ->and($result1->signals)->toHaveKey('signal1')
        ->and($result1->signals)->toHaveKey('signal2')
        ->and($result1->signals['signal2'])->toBe('value2');

    // Test cap at 100
    $result3 = new DetectionResult(50, ['signal3' => true]);
    $result1->merge($result3);

    expect($result1->score)->toBe(100)
        ->and($result1->signals)->toHaveKey('signal3');
});

it('handles keys collisions when merging', function () {
    $result1 = new DetectionResult(30, ['signal1' => 'original']);
    $result2 = new DetectionResult(40, ['signal1' => 'updated']);

    $result1->merge($result2);

    // The second value should overwrite the first
    expect($result1->signals['signal1'])->toBe('updated');
});

it('converts to array with necessary information', function () {
    $result = new DetectionResult(75, ['signal1' => true]);
    $array = $result->toArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveKeys(['score', 'signals', 'detected', 'confidence'])
        ->and($array['score'])->toBe(75)
        ->and($array['signals'])->toHaveKey('signal1')
        ->and($array['detected'])->toBeTrue()
        ->and($array['confidence'])->toBe('high');
});

it('converts to JSON correctly', function () {
    $result = new DetectionResult(75, ['signal1' => true]);
    $json = $result->toJson();

    expect($json)->toBeString()
        ->and(json_decode($json, true))->toBeArray()
        ->and(json_decode($json, true)['score'])->toBe(75);
});
