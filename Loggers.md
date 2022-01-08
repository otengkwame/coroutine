# logger

An simple, fast, asynchronous PSR-3 compatible logger.

This `Logger` library is modeling itself to have similar functionality as in Python's Async [aiologger](https://github.com/async-worker/aiologger) Package.

## Table of Contents

* [Introduction/Usage](#introduction/usage)
* [Functions](#functions)
* [Installation](#installation)
* [Usage/Historical](#usage/historical)
* [Contributing](#contributing)
* [License](#license)

## Introduction/Usage

**logger** can be used to create log entries in different formats using multiple backend *writers*.
Basic usage of **logger** requires both a *writer* and a *logger* instance. A *writer* stores the **log** *entry/message* into a backend, and the *logger* instance consumes the *writer* to perform logging operations.

The **log** *entry/message* contains the information to be logged. The message can consist of a format string and arguments (given as two separate parameters), a string or a report of the arguments, a map of key-value list array. These arguments can be accompanied by a report callback an `processor`, using `addProcessor()` method,.

The report callback is a convenience function that an *writer* __formatter__ can use to convert the report to a format string and arguments, or directly to a string. The `defaultFormatter()` method can also use its own conversion function, if no callback is provided, or if a customized formatting is desired.

## Functions

This **`logger`** package is intended to be used ___asynchronous___, which require the *underlining* package used in, to operate as such. All the following functions should be call within an routine that was launched from `coroutine_run()` function out of our [Coroutine](https://github.com/symplely/coroutine) library.

```php
/**
 * Will create or return an logger instance by tag name.
 * If no name supplied, will use calling class name
 */
 \logger_create($name);

/**
 * Return an logger instance by name.
 * If no name supplied, will use calling class name
 */
 \logger_instance($name);

/**
 * Close and perform any cleanup actions by name.
 * Optionally, clear arrayWriter logs.
 * - This function needs to be prefixed with `yield`
 */
yield \logger_close($name, $clearLogs);

/**
 * Shutdown Logger.
 * Commit, close, and clears out ALL logger instances.
 * - This function needs to be prefixed with `yield`
 */
yield \logger_shutdown();

/**
 * Wait for logs to commit and remove finished logs from logging tasks list by name
 * - This function needs to be prefixed with `yield`
 */
yield \logger_commit($name)

/**
 * Returns the array of `arrayWriter()` Logs.
 * - This function needs to be prefixed with `yield`
 */
yield \logger_arrayLogs($name)

/**
 * Printout the `arrayWriter()` Logs.
 * - This function needs to be prefixed with `yield`
 */
yield \logger_printLogs($name)
```

```php
/**
 * Will setup an custom backend `writer`, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \logger_writer($writer, $levels, $interval, $formatter, $name);

/**
 * Will setup the O.S. syslog as backend `writer`, optionally by name
 */
\logger_syslog($logOpts, $facility, $levels, $formatter, $name);

/**
 * Will setup the O.S. error_log as backend `writer`, optionally by name
 */
\logger_errorlog($type, $levels, $formatter, $name);

/**
 * Will setup an memory array as backend `writer`, optionally by name
 */
\logger_array($levels, $interval, $formatter, $name);

/**
 * Will setup an file/stream as backend `writer`, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \logger_stream($stream, $levels, $interval, $formatter, $name);

/**
 * Will setup to send email as backend `writer`, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \logger_mail($to, $subject, $headers, $levels, $interval, $formatter, $name);
```

```php
/**
 * Will setup and add custom `processor` report callback, for on named `{key}` placeholder,
 * for the global instance by name
 */
\logger_processor($key, $processor, $name);

/**
 * Will add prefix to unique id to the message to be logged, on `{unique_id}` placeholder,
 * for the global instance by name
 */
\logger_uniqueId($prefix, $name);

/**
 * Will add an PHP process ID to the message to be logged, on `{pid}` placeholder,
 * for the global instance by name
 */
\logger_pid($name);

/**
 * Will add timestamp by either microsecond to the message to be logged, on `{timestamp}` placeholder,
 * for the global instance by name
 */
\logger_timestamp($micro, $name);

/**
 * Will add type of PHP interface to the message to be logged, on `{php_sapi}` placeholder,
 * for the global instance by name
 */
\logger_phpSapi($name);

/**
 * Will add PHP version to the message to be logged, on `{php_version}` placeholder,
 * for the global instance by name
 */
\logger_phpVersion($name);

/**
 * Will add memory usage by size[GB|MB|KB] to the message to be logged, on `{memory_usage}` placeholder,
 * for the global instance by name
 * - This function needs to be prefixed with `yield`
 */
yield \logger_memoryUsage($format, $real, $peak, $name);
```

```php
/**
 * Will create new background task to Log an EMERGENCY message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_emergency($message, $context, $name);

/**
 * Will create new background task to Log an ALERT message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_alert($message, $context, $name);

/**
 * Will create new background task to Log an CRITICAL message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_critical($message, $context, $name);

/**
 * Will create new background task to Log an ERROR message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_error($message, $context, $name);

/**
 * Will create new background task to Log an WARNING message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_warning($message, $context, $name);

/**
 * Will create new background task to Log an NOTICE message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_notice($message, $context, $name);

/**
 * Will create new background task to Log an INFO message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_info($message, $context, $name);

/**
 * Will create new background task to Log an DEBUG message, optionally by name
 * - This function needs to be prefixed with `yield`
 */
yield \log_debug($message, $context, $name);
```

## Installation

```text
composer require symplely/logger
```

## Usage/Historical

```php
use Async\Logger\Logger;

$logger = Logger::getLogger('php-app');
// or
$logger = new Logger('php-app');
```

Now you can set a backend *writer* to process log messages:

```php
yield $logger->setWriter('print_r');

// Or print_r every 10 records formatted with default formatter
yield $logger->setWriter('print_r', Logger::ALL, 10);
```

Here are some preset backend *writers*:

```php
yield $logger->streamWriter($stream = 'php://stdout',
    $levels = Logger::ALL, $interval = 1, callable $formatter = null);

yield $logger->mailWriter($to, $subject = null, array $headers = [],
    $levels = Logger::ALL, $interval = 1, callable $formatter = null);

$logger->errorLogWriter($errorType = 0,
    $levels = Logger::ALL, callable $formatter = null);

$logger->syslogWriter($logOpts = \LOG_PID | \LOG_ODELAY | \LOG_CONS,
    $facility = \LOG_USER, $levels = Logger::ALL, callable $formatter = null);

$logger->arrayWriter($levels = Logger::ALL, $interval = 1, callable $formatter = null);
```

You can change default formatting:

```php
$logger->defaultFormatter(function ($level, $message, array $context) {
    return sprintf("[%s] (%s): %-10s '%s'", date(DATE_RFC822), $logger->getName(), strtoupper($level), $message);
});
```

Or pass custom formatter with writer:

```php
yield $logger->setWriter('print_r', Logger::ALL, 10, function ($level, $message, array $context) {
    //
});
```

It is possible to disable some levels for handling:

```php
$logger->disable(Logger::DEBUG | Logger::INFO);
```

A processor is executed before the log data are passed to the writer. The input of a processor is a log event, an array containing all of the information to log; the output is also a log event, but can contain modified or additional values. A processor modifies the log event to prior to sending it to the writer.

The ```addProcessor``` method allows add some extra data to context, which represent [PSR-3 message placeholders](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#12-message).

Placeholder names correspond to keys in the "context" array passed when logging a message:

`$logger->info('User with email {email} registered', ['email' => 'user@example.org']);`

```php
$logger->addProcessor('some_key', function (array $context) {
    return 'some_value';
});
```

Here are some preset concrete context processors:

```php
$logger->addUniqueId($prefix);
$logger->addPid();
$logger->addTimestamp($micro);
$logger->addMemoryUsage($format, $real, $peak;
$logger->addPhpSapi();
$logger->addPhpVersion();
```

Log an information message

```php
// will be executed in an new background task, will not pause current task
yield $logger->info('Informational message');

// will pause current task, do other tasks until logger completes the log entry
yield $logger->log(LogLevel::INFO, 'We have a PSR-compatible logger');
```
