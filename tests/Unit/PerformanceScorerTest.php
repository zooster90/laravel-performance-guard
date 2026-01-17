<?php

declare(strict_types=1);

use Zufarmarwah\PerformanceGuard\Analyzers\PerformanceScorer;

beforeEach(function () {
    $this->scorer = new PerformanceScorer;
});

it('grades A for fast requests', function () {
    $grading = ['A' => 200, 'B' => 500, 'C' => 1000, 'D' => 3000];

    expect($this->scorer->grade(100, $grading))->toBe('A');
    expect($this->scorer->grade(200, $grading))->toBe('A');
});

it('grades B for moderate requests', function () {
    $grading = ['A' => 200, 'B' => 500, 'C' => 1000, 'D' => 3000];

    expect($this->scorer->grade(300, $grading))->toBe('B');
    expect($this->scorer->grade(500, $grading))->toBe('B');
});

it('grades C for slow requests', function () {
    $grading = ['A' => 200, 'B' => 500, 'C' => 1000, 'D' => 3000];

    expect($this->scorer->grade(750, $grading))->toBe('C');
    expect($this->scorer->grade(1000, $grading))->toBe('C');
});

it('grades D for very slow requests', function () {
    $grading = ['A' => 200, 'B' => 500, 'C' => 1000, 'D' => 3000];

    expect($this->scorer->grade(2000, $grading))->toBe('D');
    expect($this->scorer->grade(3000, $grading))->toBe('D');
});

it('grades F for extremely slow requests', function () {
    $grading = ['A' => 200, 'B' => 500, 'C' => 1000, 'D' => 3000];

    expect($this->scorer->grade(5000, $grading))->toBe('F');
    expect($this->scorer->grade(10000, $grading))->toBe('F');
});
