<?php

declare(strict_types=1);

/**
 * STB-02 / Roadmap_Beta4 `[ln]` Buffer Pool Object Recycling — BENCHMARK GATE.
 *
 * The Beta-4 roadmap gates PSR-7 / buffer object-pooling behind a profiling
 * requirement: pooling "only proceeds if the benchmark demonstrates material GC
 * pressure", and carries an explicit constraint — recycling mutable
 * Request/Response objects is shared state across requests, in direct tension
 * with the statelessness mandate and the Igor worker-safety audit, while PSR-7
 * immutability (`with*()` clones) erodes most of the win.
 *
 * This script profiles a representative request→response object cycle to answer
 * the gate question: does the PHP cycle-collector churn on PSR-7 objects, or
 * does plain refcounting reclaim them deterministically?
 *
 * Run (inside the dev container, against this component's autoloader):
 *   docker exec -it -w /waffle-commons/http waffle-dev php benchmarks/psr7_gc_churn.php
 *
 * Interpretation:
 *   - "GC cycles collected" ≈ 0  → PSR-7 objects form no reference cycles; they
 *     are freed by refcounting the instant they leave scope. The cycle-collector
 *     does not run hot. Pooling buys ~nothing and adds statelessness risk.
 *   - Flat peak memory across the run → no per-request leak; allocations are
 *     reclaimed each iteration.
 */

require __DIR__ . '/../vendor/autoload.php';

use Waffle\Commons\Http\Request;
use Waffle\Commons\Http\Response;
use Waffle\Commons\Http\Uri;

const ITERATIONS = 100_000;

/**
 * One representative request→response cycle. Mirrors what a handler touches:
 * a parsed URI, a Request, and a Response mutated through the PSR-7 `with*()`
 * clone chain (each clone is a fresh immutable instance).
 */
function requestCycle(int $i): int
{
    $uri = new Uri('https://example.com/users/' . $i . '?page=2&sort=name');
    $request = new Request('GET', $uri);

    $response = new Response(200, ['Content-Type' => 'application/json'])
        ->withHeader('X-Request-Id', (string) $i)
        ->withAddedHeader('X-Trace', 'span-' . $i)
        ->withStatus(201);

    $body = $response->getBody();
    $body->write('{"id":' . $i . ',"ok":true}');

    // Return something derived so the optimiser cannot elide the work.
    return $request->getMethod() === 'GET' ? $response->getStatusCode() : 0;
}

// Warm up the autoloader + opcode paths so they do not pollute the measurement.
for ($i = 0; $i < 1_000; $i++) {
    requestCycle($i);
}

gc_collect_cycles();
$gcBefore = gc_status();
$memStart = memory_get_usage();
$peakStart = memory_get_peak_usage();
$t0 = hrtime(true);

$checksum = 0;
for ($i = 0; $i < ITERATIONS; $i++) {
    $checksum += requestCycle($i);
}

$elapsed = (hrtime(true) - $t0) / 1e9;
$gcAfter = gc_status();
$memEnd = memory_get_usage();
$peakEnd = memory_get_peak_usage();

$collected = $gcAfter['collected'] - $gcBefore['collected'];
$runs = $gcAfter['runs'] - $gcBefore['runs'];

printf("PSR-7 GC-churn benchmark (STB-02 / [ln] buffer-pool gate)\n");
printf("--------------------------------------------------------\n");
printf("PHP version            : %s\n", PHP_VERSION);
printf("Iterations             : %s request cycles\n", number_format(ITERATIONS));
printf("Wall time              : %.3f s  (%s cycles/s)\n", $elapsed, number_format((int) (ITERATIONS / $elapsed)));
printf("Checksum (anti-elision): %d\n", $checksum);
printf("\n");
printf("GC cycle-collector runs    : %d\n", $runs);
printf("GC cycles collected        : %d  (%.4f per request)\n", $collected, $collected / ITERATIONS);
printf("Resident memory delta      : %+d bytes (start %d → end %d)\n", $memEnd - $memStart, $memStart, $memEnd);
printf("Peak memory delta          : %+d bytes (start %d → end %d)\n", $peakEnd - $peakStart, $peakStart, $peakEnd);
printf("\n");

$materialPressure = ($collected / ITERATIONS) > 0.5 || ($peakEnd - $peakStart) > 5_000_000;
printf(
    "GATE VERDICT: %s\n",
    $materialPressure
        ? 'MATERIAL GC PRESSURE — pooling MAY be justified (re-read the statelessness constraint first).'
        : 'NO MATERIAL GC PRESSURE — refcounting reclaims PSR-7 objects; DEFER pooling.',
);
