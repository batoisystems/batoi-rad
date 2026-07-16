# Performance Budgets

The v1 release gates deliberately use broad, reproducible budgets. They catch
large regressions without pretending that localhost timings predict production.

| Boundary | v1 budget |
| --- | ---: |
| Public UIF tree | 4 MiB |
| RAD Admin asset tree | 28 MiB |
| Bundled AIF tree | 3 MiB |
| Release ZIP | 12 MiB |
| Release-boundary PHP peak memory | 32 MiB |
| Local authenticated dashboard navigation | 4 seconds |
| Authenticated dashboard database work | no more than 200 queries |
| Authenticated dashboard duplicate work | no more than 50 repeated query fingerprints |
| Authenticated dashboard PHP peak memory | 64 MiB |
| Dashboard stylesheets and scripts | fewer than 50 |

`PerformanceBudgetTest.php`, `RuntimePerformanceLogTest.php`, the browser smoke
test, and the release verifier enforce these budgets. Access logs record
request-level execution time, total/unique/duplicate query counts, peak memory,
and a one-way session fingerprint; they never record SQL text, parameters, or a
raw session identifier. New work should not increase a route baseline without a
documented reason.
