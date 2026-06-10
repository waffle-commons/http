# STB-02 / `[ln]` Buffer Pool Object Recycling — Benchmark Gate Result

**Status:** ❌ Gate **not** passed → **PSR-7 object pooling is DEFERRED** (Beta-4).
**Date profiled:** 2026-06-10 · **PHP:** 8.5.6 · **Benchmark:** [`psr7_gc_churn.php`](./psr7_gc_churn.php)

## The gate

`Roadmap_Beta4` gates PSR-7 / buffer object-pooling behind profiling: the work
"only proceeds if the benchmark demonstrates **material GC pressure**." It also
records a hard constraint — recycling mutable `Request`/`Response` objects is
shared state across requests, in direct tension with the **statelessness
mandate** and the **Igor** worker-safety audit, and PSR-7 immutability
(`with*()` clones) erodes most of the theoretical win.

## What was measured

A representative request→response cycle (parse a `Uri`, build a `Request`, build
a `Response` and mutate it through the `with*()` clone chain, write a JSON body),
run 100,000 times after warm-up, measuring the PHP cycle-collector and memory.

| Metric | Result | Reading |
|---|---|---|
| GC cycle-collector **runs** | **0** | the collector never ran |
| GC **cycles collected** | **0** (0.0000 / request) | PSR-7 objects form no reference cycles |
| Resident memory delta | **+1,152 bytes** total | flat — no per-request accumulation |
| Peak memory delta | **+0 bytes** | allocations fully reclaimed each iteration |
| Throughput | ~4,734 cycles/s | dominated by the body stream's `php://temp` `fopen`/`fclose`, **not** GC/allocation |

## Decision & rationale

**Defer PSR-7 message-object pooling.** The cycle-collector does no work on these
objects: they are acyclic and reclaimed by **refcounting** the instant they
leave scope, so there is no GC churn for a pool to remove. Pooling would:

1. buy ~nothing against the measured (zero) GC pressure;
2. reintroduce **cross-request mutable state** — the exact failure mode the
   FrankenPHP statelessness mandate and `wfl igor` exist to prevent;
3. fight PSR-7 immutability, since `with*()` already allocates fresh instances.

The real per-cycle cost is **I/O** (the response body stream's `php://temp`
open/close), not object allocation — which is the buffer/stream angle the
roadmap already carves out (`restrict recycling to internal byte
buffers/streams, never PSR-7 message objects`), and is a separate, still
statelessness-bound optimisation.

## When to revisit

Re-run the benchmark and reopen the gate only if **representative worker-mode
load** profiling (not this micro-benchmark) shows the cycle-collector running
hot or peak memory climbing per request. If it ever passes, scope recycling to
internal byte buffers/streams only — never the PSR-7 message objects.
