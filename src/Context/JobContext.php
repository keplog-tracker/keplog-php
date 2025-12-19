<?php

namespace Keplog\Context;

/**
 * Captures Laravel queue job context
 */
class JobContext
{
    /**
     * Capture queue job context
     *
     * @param mixed $job Laravel job instance
     * @param \Throwable|null $exception Optional exception that caused the failure
     * @return array<string, mixed>
     */
    public static function capture($job, ?\Throwable $exception = null): array
    {
        if ($job === null) {
            return [];
        }

        $context = [];

        // Job class name
        $context['job_class'] = get_class($job);

        // Queue name
        if (property_exists($job, 'queue') && $job->queue !== null) {
            $context['queue'] = $job->queue;
        } elseif (method_exists($job, 'getQueue')) {
            $context['queue'] = $job->getQueue();
        }

        // Connection name
        if (property_exists($job, 'connection') && $job->connection !== null) {
            $context['connection'] = $job->connection;
        } elseif (method_exists($job, 'getConnectionName')) {
            $context['connection'] = $job->getConnectionName();
        }

        // Attempt count
        if (method_exists($job, 'attempts')) {
            $context['attempts'] = $job->attempts();
        }

        // Max tries
        if (property_exists($job, 'tries') && $job->tries !== null) {
            $context['max_tries'] = $job->tries;
        }

        // Timeout
        if (property_exists($job, 'timeout') && $job->timeout !== null) {
            $context['timeout'] = $job->timeout;
        }

        // Job ID
        if (method_exists($job, 'getJobId')) {
            $context['job_id'] = $job->getJobId();
        }

        // Delay
        if (property_exists($job, 'delay') && $job->delay !== null) {
            $context['delay'] = $job->delay;
        }

        // Exception information if provided
        if ($exception !== null) {
            $context['exception_message'] = $exception->getMessage();
            $context['exception_class'] = get_class($exception);
        }

        return $context;
    }

    /**
     * Capture context from Laravel JobFailed event
     *
     * @param mixed $event Laravel JobFailed event instance
     * @return array<string, mixed>
     */
    public static function captureFromFailedEvent($event): array
    {
        if ($event === null) {
            return [];
        }

        $context = [];

        // Connection name
        if (isset($event->connectionName)) {
            $context['connection'] = $event->connectionName;
        }

        // Job information
        if (isset($event->job)) {
            $jobContext = self::captureFromJobInstance($event->job);
            $context = array_merge($context, $jobContext);
        }

        // Exception
        if (isset($event->exception)) {
            $context['exception_message'] = $event->exception->getMessage();
            $context['exception_class'] = get_class($event->exception);
        }

        return $context;
    }

    /**
     * Capture context from raw job instance
     *
     * @param mixed $job Raw job instance
     * @return array<string, mixed>
     */
    private static function captureFromJobInstance($job): array
    {
        if ($job === null) {
            return [];
        }

        $context = [];

        // Queue name
        if (method_exists($job, 'getQueue')) {
            $context['queue'] = $job->getQueue();
        }

        // Job name/class
        if (method_exists($job, 'getName')) {
            $context['job_name'] = $job->getName();
        }

        // Attempt count
        if (method_exists($job, 'attempts')) {
            $context['attempts'] = $job->attempts();
        }

        // Payload (be careful with sensitive data)
        if (method_exists($job, 'payload')) {
            $payload = $job->payload();
            if (is_array($payload)) {
                // Only include safe metadata
                if (isset($payload['displayName'])) {
                    $context['job_class'] = $payload['displayName'];
                }
                if (isset($payload['maxTries'])) {
                    $context['max_tries'] = $payload['maxTries'];
                }
                if (isset($payload['timeout'])) {
                    $context['timeout'] = $payload['timeout'];
                }
            }
        }

        return $context;
    }
}
