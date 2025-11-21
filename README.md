# libasync

`libasync` is a lightweight PHP asynchronous programming library that provides coroutines, deferred execution, traps,
timeouts, and thread-like tasks. It was originally developed for personal use on a private server and has now been
released publicly.

## Features

- **Coroutines:** Easily write asynchronous code using `async()` and `thread()`.
- **Deferred Execution:** Register deferred closures to be executed when a coroutine finishes (`defer()`).
- **Traps:** Add trap closures that trigger under specific conditions (`trap()`, `trap_online()`).
- **Timeouts:** Set timeouts for coroutine tasks (`timeout()`).
- **Coroutine Lifecycle Control:**
    - `joined()` ensures a coroutine runs to completion, even during shutdown.
    - `may_drop()` allows a coroutine to be dropped immediately if not critical.
- **Safe Closures:** Detect and prevent cyclic references in closures using `ClosureUtils::noCyclic()`.
