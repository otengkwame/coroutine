# Coroutine

[![Linux](https://github.com/symplely/coroutine/workflows/Linux/badge.svg)](https://github.com/symplely/coroutine/actions?query=workflow%3ALinux)[![Windows](https://github.com/symplely/coroutine/workflows/Windows/badge.svg)](https://github.com/symplely/coroutine/actions?query=workflow%3AWindows)[![macOS](https://github.com/symplely/coroutine/workflows/macOS/badge.svg)](https://github.com/symplely/coroutine/actions?query=workflow%3AmacOS)[![codecov](https://codecov.io/gh/symplely/coroutine/branch/master/graph/badge.svg)](https://codecov.io/gh/symplely/coroutine)[![Codacy Badge](https://app.codacy.com/project/badge/Grade/09861ff18c34465198b93d9d5672dc3e)](https://www.codacy.com/gh/symplely/coroutine/dashboard?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=symplely/coroutine&amp;utm_campaign=Badge_Grade)[![Maintainability](https://api.codeclimate.com/v1/badges/1bfc3497fde67b111a04/maintainability)](https://codeclimate.com/github/symplely/coroutine/maintainability)

This is version **2x**, it breaks version [1x](https://github.com/symplely/coroutine/tree/1x), by __namespace__ of *global* functions, moving all required  __CONSTANTS__ to the dependence package. This version includes the new **Fiber** implementation, with no need for *PHP* [ext-fibers](https://wiki.php.net/rfc/fibers) extension, slated for **PHP 8.1**. All `ext-fibers` [tests](https://github.com/amphp/ext-fiber/tree/master/tests), and [examples](https://github.com/amphp/ext-fiber/tree/master/examples) as they have them, has been implemented here in [examples/fiber](https://github.com/symplely/coroutine/tree/master/examples/fiber/), [tests/fiber](https://github.com/symplely/coroutine/tree/master/tests/fiber/) and [tests/FiberTest.php](https://github.com/symplely/coroutine/blob/master/tests/FiberTest.php).

For maximum performance it's advisable to install the cross-platform [libuv](https://github.com/libuv/libuv) library, the PHP extension [ext-uv](https://github.com/amphp/ext-uv). See the online [book](https://nikhilm.github.io/uvbook/index.html) for a full tutorial overview of it's usage.

> All **libuv** `socket/stream/udp/tcp` like features will need to reimplemented for *Windows*, the previous assumption they where broken, that is not the case, issue with PHP `ext-uv` version of libuv being used, and the assumption how the feature should work. As such current implementation using native `stream_select` for *Windows*, will be rework/refactored.

> This package next version after 2x release, will require [uv-ffi](https://github.com/symplely/uv-ffi) an **FFI** version of `ext-uv` of **libuv**, as such no additional binary library package will need to be downloaded/installed, and some current dependencies will no longer be necessary.

For a fundamental conceptional overview read ["Concurrency and PHP in relation to modern programming languages, Python, Go, NodeJS, Rust, Etc"](https://github.com/symplely/coroutine/blob/master/wiki/dev.io/concurrency_and_php.md), currently in **draft** form, will be posted on [dev.io](ttps://dev.to) when completed.

This version also implements **PHP** [ext-parallel](https://www.php.net/manual/en/book.parallel.php) extension in a way that uses the child [process](http://docs.libuv.org/en/v1.x/guide/processes.html) features of **libuv**. For a quick performance study between a *thread* and *process* see [Launching Linux threads and processes with clone](https://eli.thegreenplace.net/2018/launching-linux-threads-and-processes-with-clone/).

The implement here follows [parallel\Runtime](https://www.php.net/manual/en/class.parallel-runtime.php),[parallel\Future](https://www.php.net/manual/en/class.parallel-future.php), [parallel\Channel](https://www.php.net/manual/en/class.parallel-channel.php), and [Functional](https://www.php.net/manual/en/functional.parallel.php) **API** *specs* as defined, but without the many [limitations](https://www.php.net/manual/en/intro.parallel.php).

The limitations are overcome by using [opis/closure](https://opis.io/closure) package. All **ext-parallel** extension [tests](https://github.com/krakjoe/parallel/tree/develop/tests) and examples, have been mainly modified here in [examples/parallel](https://github.com/symplely/coroutine/tree/master/examples/parallel/), [tests/parallel](https://github.com/symplely/coroutine/tree/master/tests/parallel/) to include this package library instead and remove single array parameter requirement to variadic array. The **tests** with a somewhat major difference have `.1` added to filename, some test are skipped that might require `yield` for proper usage.

The **ext-parallel** `events` and `sync` API are not implemented, don't see a use case and is already internally part of this **coroutine** package.

---

## Table of Contents

* [Introduction](#introduction)
* [Functions](#functions)
* [Installation](#installation)
* [Usage](#usage)
* [Development](#development)
* [Todo](#todo)
* [Credits/References](#credits/references)
* [Contributing](#contributing)
* [License](#license)

## Introduction

__What is Async?__
Simply avoiding the blocking of the very next instruction. The mechanism of *capturing* and *running later*.

__What issues the current PHP implementations this package address?__
*Ease of use*. [Amp](#[Amp]) and [ReactPHP](#[ReactPHP]) both do and get you asynchronous results. But both require more setup by the *__user/developer__* to get a simple line of code to run. There is also **Swoole** and **Hhvm**, but neither are standard PHP installations.

When using **Amp** or **ReactPHP** and some of the packages based upon them, you must not only manage the `Callbacks` you provided, but also the returned `Promise` object, and the `Event Loop` object. These libraries modeling themselves around old school **Javascript**, where `Javascript` nowadays moving towards simple `async/await` syntax. Which brings up this question.

__What does `ease of use` mean?__
We can start by reviewing other programming languages implementations. And the fact you already have a working application, that you want various parts to run more responsive, which translates to do more.

[Go](https://golang.org/doc/effective_go.html#goroutines) has it's **Goroutine** keyword `go` then your function and statements.

These links of [Python](https://docs.python.org/3/library/asyncio-task.html), [Ruby](https://ruby-concurrency.github.io/concurrent-ruby/1.1.4/Concurrent/Async.html), [Rust](https://rust-lang.github.io/async-book/01_getting_started/04_async_await_primer.html#asyncawait-primer), [Nim](https://nim-lang.org/docs/asyncnet.html), [C#](https://docs.microsoft.com/en-us/dotnet/csharp/programming-guide/concepts/async/), [Elixir](https://hexdocs.pm/elixir/Task.html#module-async-and-await), [Java](https://github.com/electronicarts/ea-async), and [C++](https://lewissbaker.github.io/2017/11/17/understanding-operator-co-await) details keywords `async/await` combined with a function and statements.

When using they return the actual results you want, no meddling with any objects, no callbacks, and upon calling everything to up that point continues to run.

The thing about each, is the history leading to the feature, the actual behavior, the underlying concepts are the same, and a simple calling structure statement. They all reference in various ways the use of `yield` or `iterators`, which this package relies upon and makes the following true.

The only thing you will do to **make** your code `asynchronous`, is **placing** `yield` _within_, and **prefix** _calling_ your **code** with `yield` to **get** _actual_ `results` you want.

With this package you will have a **PHP** version of `async/await`, by just using **`yield`**.

There are a few helper functions available to tie everything together. Mainly, `away()` that's similar to Python's [create_task()](https://docs.python.org/3/library/asyncio-task.html#id4), that behaves like Google's [go()](https://golang.org/doc/effective_go.html#goroutines) keyword, which is included here as a alias function `go()`.

By using, it immediately returns a number, that can be used with `gather()`, another Python like [function](https://docs.python.org/3/library/asyncio-task.html#asyncio.gather), which also behaves like Google's [WaitGroup](https://golang.org/pkg/sync/#WaitGroup). This will wait and return the result of a code distant/**detached** for running in the `background`.

This package follows a new paradigm [Behavioral Programming](http://www.wisdom.weizmann.ac.il/~bprogram/) with the concept of [B-threads](https://medium.com/@lmatteis/b-threads-programming-in-a-way-that-allows-for-easier-changes-5d95b9fb6928), _functional generators_.

The base overall usage of [Swoole Coroutine](https://www.swoole.co.uk/coroutine), and [FaceBook's Hhvm](https://docs.hhvm.com/hack/asynchronous-operations/introduction) **PHP** follows the same outline implementations as others and put forth here.

To illustrate further take this comparison between **NodeJS** and **Python** from [Intro to Async Concurrency in Python vs. Node.js](https://medium.com/@interfacer/intro-to-async-concurrency-in-python-and-node-js-69315b1e3e36).

```js
// async_scrape.js (tested with node 11.3)
const sleep = ts => new Promise(resolve => setTimeout(resolve, ts * 1000));

async function fetchUrl(url) {
    console.log(`~ executing fetchUrl(${url})`);
    console.time(`fetchUrl(${url})`);
    await sleep(1 + Math.random() * 4);
    console.timeEnd(`fetchUrl(${url})`);
    return `<em>fake</em> page html for ${url}`;
}

async function analyzeSentiment(html) {
    console.log(`~ analyzeSentiment("${html}")`);
    console.time(`analyzeSentiment("${html}")`);
    await sleep(1 + Math.random() * 4);
    const r = {
        positive: Math.random()
    }
    console.timeEnd(`analyzeSentiment("${html}")`);
    return r;
}

const urls = [
    "https://www.ietf.org/rfc/rfc2616.txt",
    "https://en.wikipedia.org/wiki/Asynchronous_I/O",
]
const extractedData = {}

async function handleUrl(url) {
    const html = await fetchUrl(url);
    extractedData[url] = await analyzeSentiment(html);
}

async function main() {
    console.time('elapsed');
    await Promise.all(urls.map(handleUrl));
    console.timeEnd('elapsed');
}

main()
```

```py
# async_scrape.py (requires Python 3.7+)
import asyncio, random, time

async def fetch_url(url):
    print(f"~ executing fetch_url({url})")
    t = time.perf_counter()
    await asyncio.sleep(random.randint(1, 5))
    print(f"time of fetch_url({url}): {time.perf_counter() - t:.2f}s")
    return f"<em>fake</em> page html for {url}"

async def analyze_sentiment(html):
    print(f"~ executing analyze_sentiment('{html}')")
    t = time.perf_counter()
    await asyncio.sleep(random.randint(1, 5))
    r = {"positive": random.uniform(0, 1)}
    print(f"time of analyze_sentiment('{html}'): {time.perf_counter() - t:.2f}s")
    return r

urls = [
    "https://www.ietf.org/rfc/rfc2616.txt",
    "https://en.wikipedia.org/wiki/Asynchronous_I/O",
]
extracted_data = {}

async def handle_url(url):
    html = await fetch_url(url)
    extracted_data[url] = await analyze_sentiment(html)

async def main():
    t = time.perf_counter()
    await asyncio.gather(*(handle_url(url) for url in urls))
    print("> extracted data:", extracted_data)
    print(f"time elapsed: {time.perf_counter() - t:.2f}s")

asyncio.run(main())
```

**Using this package as setout, it's the same simplicity:**

```php
// This is in the examples folder as "async_scrape.php"
include 'vendor/autoload.php';

function fetch_url($url)
{
  print("~ executing fetch_url($url)" . \EOL);
  \timer_for($url);
  yield \sleep_for(\random_uniform(1, 5));
  print("time of fetch_url($url): " . \timer_for($url) . 's' . \EOL);
  return "<em>fake</em> page html for $url";
};

function analyze_sentiment($html)
{
  print("~ executing analyze_sentiment('$html')" . \EOL);
  \timer_for($html . '.url');
  yield \sleep_for(\random_uniform(1, 5));
  $r = "positive: " . \random_uniform(0, 1);
  print("time of analyze_sentiment('$html'): " . \timer_for($html . '.url') . 's' . \EOL);
  return $r;
};

function handle_url($url)
{
  yield;
  $extracted_data = [];
  $html = yield fetch_url($url);
  $extracted_data[$url] = yield analyze_sentiment($html);
  return yield $extracted_data;
};

function main()
{
  $urls = [
    "https://www.ietf.org/rfc/rfc2616.txt",
    "https://en.wikipedia.org/wiki/Asynchronous_I/O"
  ];
  $urlID = [];

  \timer_for();
  foreach ($urls as $url)
    $urlID[] = yield \away(handle_url($url));

  $result_data = yield \gather($urlID);
  foreach ($result_data as $id => $extracted_data) {
    echo "> extracted data:";
    \print_r($extracted_data);
  }

  print("time elapsed: " . \timer_for() . 's');
}

\coroutine_run(main());
```

Try recreating this with the other pure *PHP* async implementations, they would need an rewrite first to come close.

-------

A [**Coroutine**](https://en.wikipedia.org/wiki/Coroutine) here are specially crafted functions that are based on __generators__, with the use of `yield` and `yield from`. When used, they **control context**, meaning `capture/release` an application's execution flow.

When `yield` is placed within an block of code, it indicates to the calling function, that an object will be returned instead, the code is not immediately executed.

This package represent that calling function, an __scheduler__, similar to an **event loop**. A coroutine needs to be scheduled to run, and once scheduled coroutines are wrapped in an `Task`, which are a type of **Promise**.

A `task` is an object that represents some work to be done, potentially with a result at the end of it. These tasks are _registered_ with a scheduler that is responsible for running them.

Due to the __single-threaded__ nature of PHP (without extensions anyway), we cannot think of a `task` as doing a single __long-running__ calculation - this will __block__ the single thread until the task is finished.

Instead, `tasks` must perform work in small chunks/iterations __('ticks')__ where possible, passing control back to the scheduler at appropriate points. This is known as [__cooperative multi-tasking__](https://en.wikipedia.org/wiki/Cooperative_multitasking) (so called because the tasks must cooperate by yielding control voluntarily).

The scheduler is responsible for 'ticking' the scheduled tasks, with each scheduled task being repeatedly 'ticked' until it is complete. It is up to the scheduler implementation how to do this in a way that allows all scheduled tasks to run.

A `task` can become complete in one of three ways:

```text
The task reaches successful completion, and optionally produces a result
The task encounters an error and fails
The task is cancelled by calling cancel()
```

When using this package, and the code you are working on contain `yield` points, these define points is where a *context switch* can happen if other tasks are pending, but will not if no other task is pending. This can also be seen as **breakpoints/traps**, like when using an debugger, when triggered, the debugger steps in, an you can view state and step thought the remainder of your code.

> A *context switch* represents the __scheduler__ yielding the flow of control from one *coroutine* to the next.

> A *coroutine* here is define as an function/method containing the `yield` keyword, in which will return *generator* object.

The **generator** object that's immediately returned, gives us access to few methods, that allow itself to progress.

So here we have a very special case with `Generators` in that it being part of the PHP language, and when looked at through the lens of how Promise's work, and that's to not block, just execute line and return. The main idea of being asynchronous.

Promises returns an object, that's placed into an event loop queue. The event loop does the actual executing the callback attached to the object. This is really a manual process, with much code state/overhead to manage. This is called an [Reactor pattern](https://en.wikipedia.org/wiki/Reactor_pattern) of execution, dispatches callbacks synchronously.

The **mechanics** of an event loop is already present when an a *generator* is put in motion. I see this as an [Proactor pattern](https://en.wikipedia.org/wiki/Proactor_pattern). Since the action of `yield`ing is the initiator, begins the process of checking resource availability, performing operations/actions at that moment, and handling/returning completion events, all asynchronously.

Take a read of this post, [What are coroutines in C++20?](https://stackoverflow.com/questions/43503656/what-are-coroutines-in-c20)

```text
There are two kinds of coroutines; stackful and stackless.

A stackless coroutine only stores local variables in its state and its location of execution.

A stackful coroutine stores an entire stack (like a thread).

Stackless coroutines can be extremely light weight. The last proposal I read involved basically rewriting your function into something a bit like a lambda; all local variables go into the state of an object, and labels are used to jump to/from the location where the coroutine "produces" intermediate results.

The process of producing a value is called "yield", as coroutines are bit like cooperative multithreading; you are yielding the point of execution back to the caller.
```

This package performs cooperative scheduling, the basics for multitasking, asynchronous programming.

The steps, that's taking place when an `yield` is introduced.

1. The *function* is now an `coroutine`.
2. The *object* returned is captured by the `scheduler`.
3. The *scheduler*, wraps this captured `generator` object around an `task` object.
4. The *task* object has additional methods and features, it could be seen as `promise` like.
5. The *task* is now place into an `task queue` controlled by the `scheduler`.
6. You **`run`** your `function`, putting everything in motion. *Here you are not starting any **event loop***. What could be seen as an event loop, is the work being done *before* or *after* the `task` is place into **action** by the `scheduler`.
7. Where will this `task` land/return to? *Answer*: The same location that called it, there are **no callbacks**.

> Step **1**, is implemented in other languages with an specific keyword, `async`.

> Steps **2** to **6**, is preformed in other languages with an specific keyword, `await`.

The terminology/naming used here is more in line with [Python's Asyncio](https://www.python.org/dev/peps/pep-0492/) and [Curio](https://curio.readthedocs.io/en/latest/index.html#) usage. In fact, most of the source code method calls has been change to match theres.

This package should be seen/used as an **user-land** extension, it's usage of `yield` has been envisioned from [RFC](https://wiki.php.net/rfc/generator-delegation) creators.

## Functions

Only the functions located here and in the [Core.php](https://github.com/symplely/coroutine/blob/master/Core.php) file should be used. Direct access to object class libraries is discouraged, the names might change, or altogether drop if not listed here. Third party library package [development](#Development) is the exception.

The functions for **Network** related in [Stream.php](https://github.com/symplely/coroutine/blob/master/Stream.php), **File System** in [Path.php](https://github.com/symplely/coroutine/blob/master/Path.php), and **Processes** in [Worker.php](https://github.com/symplely/coroutine/blob/master/Worker.php), all have been namespaced, so use as follows:

```php
use function Async\Path\{ , };
use function Async\Worker\{ , };
use function Async\Stream\{ , };
```

```php
/**
 * Returns a random float between two numbers.
 *
 * Works similar to Python's `random.uniform()`
 * @see https://docs.python.org/3/library/random.html#random.uniform
 */
\random_uniform($min, $max);

/**
 * Return the value (in fractional seconds) of a performance counter.
 * Using either `hrtime` or system's `microtime`.
 *
 * The $tag is:
 * - A reference point used to set, to get the difference between the results of consecutive calls.
 * - Will be cleared/unset on the next consecutive call.
 *
 * returns float|void
 *
 * @see https://docs.python.org/3/library/time.html#time.perf_counter
 * @see https://nodejs.org/docs/latest-v11.x/api/console.html#console_console_time_label
 */
\timer_for(string $tag = 'perf_counter');

/**
 * Makes an resolvable function from label name that's callable with `away`
 */
\async(string $labelFunction, $asyncFunction);

/**
 * Wrap the value with `yield`, when placed within this insure that
 * any *function/method* will be `awaitable` and the actual return
 * value is picked up properly by `gather()`.
 */
return \value($value)

/**
 * Add/schedule an `yield`-ing `function/callable/task` for background execution.
 * Will immediately return an `int`, and continue to the next instruction.
 * Returns an task Id
 * - This function needs to be prefixed with `yield`
 *
 * @see https://docs.python.org/3.7/library/asyncio-task.html#asyncio.create_task
 */
yield \away($awaitedFunction, ...$args) ;

/**
 * Performs a clean application exit and shutdown.
 *
 * Provide $skipTask incase called by an Signal Handler. Defaults to the main parent task.
 * - Use `get_task()` to retrieve caller's task id.
 *
 * - This function needs to be prefixed with `yield`
 */
yield \shutdown($skipTask)

/**
 * Wrap the callable with `yield`, this insure the first attempt to execute will behave
 * like a generator function, will switch at least once without actually executing, return object instead.
 * This function is used by `away` not really called directly.
 *
 * @see https://docs.python.org/3.7/library/asyncio-task.html#awaitables
 */
\awaitAble($awaitableFunction, ...$args);

/**
 * Run awaitable objects in the tasks set concurrently and block until the condition specified by race.
 *
 * Controls how the `gather()` function operates.
 * `gather_wait` will behave like **Promise** functions `All`, `Some`, `Any` in JavaScript.
 *
 * - This function needs to be prefixed with `yield`
 *
 * @see https://docs.python.org/3.7/library/asyncio-task.html#waiting-primitives
 */
yield \gather_wait(array $tasks, int $race = 0, bool $exception = true, bool $clear = true)

/**
 * Run awaitable objects in the taskId sequence concurrently.
 * If any awaitable in taskId is a coroutine, it is automatically scheduled as a Task.
 *
 * If all awaitables are completed successfully, the result is an aggregate list of returned values.
 * The order of result values corresponds to the order of awaitables in taskId.
 *
 * The first raised exception is immediately propagated to the task that awaits on gather().
 * Other awaitables in the sequence won't be cancelled and will continue to run.
 * - This function needs to be prefixed with `yield`
 *
 * @see https://docs.python.org/3.7/library/asyncio-task.html#asyncio.gather
 */
yield \gather(...$taskId);

/**
 * Block/sleep for delay seconds.
 * Suspends the calling task, allowing other tasks to run.
 * A result is returned If provided back to the caller
 * - This function needs to be prefixed with `yield`
 */
yield \sleep_for($delay, $result);

/**
 * Creates an communications Channel between coroutines, returns an object
 * Similar to Google Go language - basic, still needs additional functions
 * - This function needs to be prefixed with `yield`
 */
yield \make();

/**
 * Send message to an Channel
 * - This function needs to be prefixed with `yield`
 */
yield \sender($channel, $message, $taskId);

/**
 * Set task as Channel receiver, and wait to receive Channel message
 * Will continue other tasks until so.
 * - This function needs to be prefixed with `yield`
 */
yield \receiver($channel);

/**
 * A goroutine is a function that is capable of running concurrently with other functions.
 * To create a goroutine we use the keyword `go` followed by a function invocation
 * @see https://www.golang-book.com/books/intro/10#section1
 */
yield \go($goFunction, ...$args);

/**
 * Modeled as in `Go` Language.
 *
 * The behavior of defer statements is straightforward and predictable.
 * There are three simple rules:
 * 1. *A deferred function's arguments are evaluated when the defer statement is evaluated.*
 * 2. *Deferred function calls are executed in Last In First Out order after the* surrounding function returns.
 * 3. *Deferred functions can`t modify return values when is type, but can modify content of reference to
 *
 * @see https://golang.org/doc/effective_go.html#defer
 */
\defer(&$previous, $callback)

/**
 * Modeled as in `Go` Language.
 *
 * Regains control of a panicking `task`.
 *
 * Recover is only useful inside `defer()` functions. During normal execution, a call to recover will return nil
 * and have no other effect. If the current `task` is panicking, a call to recover will capture the value given
 * to panic and resume normal execution.
 */
\recover(&$previous, $callback);

/**
 * Modeled as in `Go` Language.
 *
 * An general purpose function for throwing an Coroutine `Exception`,
 * or some abnormal condition needing to keep an `task` stack trace.
 */
\panic($message, $code, $previous);

/**
 * Return the task ID
 * - This function needs to be prefixed with `yield`
 */
yield \get_task();

/**
 * kill/remove an task using task id
 * - This function needs to be prefixed with `yield`
 */
yield \cancel_task($tid);

/**
 * Wait for the callable/task to complete with a timeout.
 * Will continue other tasks until so.
 * - This function needs to be prefixed with `yield`
 */
yield \wait_for($callable, $timeout);

/**
 * Wait on read stream/socket to be ready read from.
 * Will continue other tasks until so.
 * - This function needs to be prefixed with `yield`
 */
yield \read_wait($stream);

/**
 * Wait on write stream/socket to be ready to be written to.
 * Will continue other tasks until so.
 * - This function needs to be prefixed with `yield`
 */
yield \write_wait($stream);

/**
 * Wait on keyboard input.
 * Will continue other tasks until so.
 * Will not block other task on `Linux`, will continue other tasks until `enter` key is pressed,
 * Will block on Windows, once an key is typed/pressed, will continue other tasks `ONLY` if no key is pressed.
 * - This function needs to be prefixed with `yield`
 */
yield \input_wait($size);

/**
 * An PHP Functional Programming Primitive.
 * Return a curryied version of the given function. You can decide if you also
 * want to curry optional parameters or not.
 *
 * @see https://github.com/lstrojny/functional-php/blob/master/docs/functional-php.md#currying
 */
\curry($function, $required);

\coroutine_instance();

\coroutine_clear();

\coroutine_create($coroutine);

/**
 * This function runs the passed coroutine, taking care of managing the scheduler and
 * finalizing asynchronous generators. It should be used as a main entry point for programs, and
 * should ideally only be called once.
 *
 * @see https://docs.python.org/3.7/library/asyncio-task.html#asyncio.run
 */
\coroutine_run($coroutine);
```

```php
use function Async\Worker\{ add_process, spawn_task, spawn_await };

/**
 * Add/execute a blocking `subprocess` task that runs in parallel.
 * This function will return `int` immediately, use `gather()` to get the result.
 * - This function needs to be prefixed with `yield`
 */
yield \spawn_task($command, $timeout);

/**
 * Add and wait for result of an blocking `I/O` subprocess that runs in parallel.
 * - This function needs to be prefixed with `yield`
 *
 * @see https://docs.python.org/3.7/library/asyncio-subprocess.html#subprocesses
 * @see https://docs.python.org/3.7/library/asyncio-dev.html#running-blocking-code
 */
 yield \spawn_await($callable, $timeout, $display, $channel, $channelTask, $signal, $signalTask);
/**
 * Add and wait for result of an blocking `I/O` subprocess that runs in parallel.
 * This function turns the calling function internal __state/type__ used by `gather()`
 * to **process/paralleled** which is handled differently.
 * - This function needs to be prefixed with `yield`
 *
 * @see https://docs.python.org/3.7/library/asyncio-subprocess.html#subprocesses
 * @see https://docs.python.org/3.7/library/asyncio-dev.html#running-blocking-code
 */
yield \add_process($command, $timeout);
```

```php
use function Async\Path\file_***Any File System Command***;

/**
 * Executes a blocking system call asynchronously either natively thru `libuv`, `threaded`, or it's `uv_spawn`
 * feature, or in a **child/subprocess** by `proc_open`, if `libuv` is not installed.
 * - This function needs to be prefixed with `yield`
 */
yield \file_***Any File System Command***( ...$arguments);
```

## Installation

```cmd
composer require symplely/coroutine
```

This version will use **libuv** features if available. Do one of the following to install.

For **Debian** like distributions, Ubuntu...

```bash
apt-get install libuv1-dev php-pear php-dev -y
```

For **RedHat** like distributions, CentOS...

```bash
yum install libuv-devel php-pear php-dev -y
```

Now have **Pecl** auto compile, install, and setup.

```bash
pecl channel-update pecl.php.net
pecl install uv-beta
```

For **Windows** there is good news, native *async* thru `libuv` has arrived.

Windows builds for stable PHP versions are available [from PECL](https://pecl.php.net/package/uv).

Directly download latest from https://windows.php.net/downloads/pecl/releases/uv/0.2.4/

Extract `libuv.dll` to sample directory as `PHP` binary executable, and extract `php_uv.dll` to `ext\` directory.

Enable extension `php_sockets.dll` and `php_uv.dll` in php.ini

```powershell
cd C:\Php
Invoke-WebRequest "https://windows.php.net/downloads/pecl/releases/uv/0.2.4/php_uv-0.2.4-7.2-nts-vc15-x64.zip" -OutFile "php_uv-0.2.4.zip"
#Invoke-WebRequest "https://windows.php.net/downloads/pecl/releases/uv/0.2.4/php_uv-0.2.4-7.3-nts-vc15-x64.zip" -OutFile "php_uv-0.2.4.zip"
#Invoke-WebRequest "https://windows.php.net/downloads/pecl/releases/uv/0.2.4/php_uv-0.2.4-7.4-ts-vc15-x64.zip" -OutFile "php_uv-0.2.4.zip"
7z x -y php_uv-0.2.4.zip libuv.dll php_uv.dll
copy php_uv.dll ext\php_uv.dll
del php_uv.dll
del php_uv-0.2.4.zip
echo extension=php_sockets.dll >> php.ini
echo extension=php_uv.dll >> php.ini
```

> Note: Seems there are issues with __PHP ZTS__ on both *Windows* and *Linux* when using `uv_spawn`.

## Usage

In general, any method/function having the `yield` keyword, will operate as an interruption point, suspend current routine, do something else, then return/resume.

```php
function main() {
    // Your initialization/startup code will need to be enclosed inside an function.
    // This is required for proper operations to start.
}

\coroutine_run(\main());
```

There after, review as below, the scripts in the [examples](https://github.com/symplely/coroutine/tree/master/examples) folder.

```php
/**
 * @see https://docs.python.org/3/library/asyncio-task.html#timeouts
 */
include 'vendor/autoload.php';

function eternity() {
    // Sleep for one hour
    print("\nAll good!\n");
    yield \sleep_for(3600);
    print(' yay!');
}

function keyboard() {
    // will begin outputs of `needName` in 1 second
    print("What's your name: ");
    // Note: I have three Windows systems
    // - Windows 10 using PHP 7.2.18 (cli) (built: Apr 30 2019 23:32:39) ( ZTS MSVC15 (Visual C++ 2017) x64 )
    // - Windows 10 using PHP 7.1.19 (cli) (built: Jun 20 2018 23:37:54) ( NTS MSVC14 (Visual C++ 2015) x86 )
    // - Windows 7 using PHP 7.1.16 (cli) (built: Mar 28 2018 21:15:31) ( ZTS MSVC14 (Visual C++ 2015) x64 )
    // Windows 10 blocks STDIN from the beginning with no key press.
    // Windows 7 does non-blocking STDIN, if no input attempted. only after typing something it blocks.
    return yield \input_wait();
}

function needName() {
    $i = 1;
    yield \sleep_for(1);
    while(true) {
        echo $i;
        yield \sleep_for(0.05);
        $i++;
        if ($i == 15) {
            print(\EOL.'hey! try again: ');
        }
        if ($i == 100) {
            print(\EOL.'hey! try again, one more time: ');
            break;
        }
    }
}

function main() {
    yield \away(\needName());
    echo \EOL.'You typed: '.(yield \keyboard()).\EOL;

    try {
        // Wait for at most 0.5 second
        yield \wait_for(\eternity(), 0.5);
    } catch (\RuntimeException $e) {
        print("\ntimeout!");
        // this script should have exited automatically, since
        // there are no streams open, nor tasks running, this exception killed `eternity` task
        // currently, will continue to run
        // task id 2 is `ioWaiting` task, the scheduler added for listening
        // for stream socket connections
        yield \cancel_task(2);
        // This might just be because `main` is task 1,
        // and still running by the exception throw, need more testing
    }
}

\coroutine_run(\main());
```

```php
/**
 * @see https://golangbot.com/goroutines/
 * @see https://play.golang.org/p/oltn5nw0w3
 */
include 'vendor/autoload.php';

function numbers() {
    for ($i = 1; $i <= 5; $i++) {
        yield \sleep_for(250 * \MS);
        print(' '.$i);
    }
}

function alphabets() {
    for ($i = 'a'; $i <= 'e'; $i++) {
        yield \sleep_for(400 * \MS);
        print(' '.$i);
    }
}

function main() {
    yield \go(\numbers());
    yield \go(\alphabets());
    yield \sleep_for(3000 * \MS);
    print(" main terminated");
}

\coroutine_run(\main());
```

## Development

```php
/**
 * Template for developing an library package for access
 */
public static function someName($whatever, ...$args)
{
    return new Kernel(
        function(TaskInterface $task, Coroutine $coroutine) use ($whatever, $args){
            // Use/Execute/call some $whatever with ...$args;
            //
            if ($done) {
                // will return $someValue back to the caller
                $task->sendValue($someValue);
                // will return back to the caller, the callback
                $coroutine->schedule($task);
            }
        }
    );
}

// Setup to call
function some_name($whatever, ...$args) {
    return Kernel::someName($whatever, ...$args);
}

// To use
yield \some_name($whatever, ...$args);
```

## Todo

* Add `WebSocket` support, or really convert/rewrite some package.
* Add `Database` support, base off my maintenance of [ezsql](http://ezsql.github.io/ezsql).
* Add more standard examples from other languages, converted over.
* Update docs in reference to similar sections of functionally in Python, Go or any other languages.

## Credits/References

 **Nikita Popov** [Cooperative multitasking using coroutines (in PHP!)](https://nikic.github.io/2012/12/22/Cooperative-multitasking-using-coroutines-in-PHP.html). Which this package **forks** [Ditaio](https://github.com/nikic/ditaio), restructuring/rewriting.

**Parallel** class is a restructured/rewrite of [spatie/async](https://github.com/spatie/async). The **Parallel** class rely upon [symplely/spawn](https://github.com/symplely/spawn) as a dependency, used for **subprocess** management/execution, it uses **`uv_spawn`** of **libuv** for launching processes. The **Spawn** package has [opis/closure](https://github.com/opis/closure) as an dependency, used to overcome **PHP** serialization limitations, and [symfony/process](https://github.com/symfony/process) as a fallback to **`proc_open`** for launching processes, in case **libuv** the _PHP_-**UV** extension is not installed.

## Contributing

Contributions are encouraged and welcome; I am always happy to get feedback or pull requests on Github :) Create [Github Issues](https://github.com/symplely/coroutine/issues) for bugs and new features and comment on the ones you are interested in.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
