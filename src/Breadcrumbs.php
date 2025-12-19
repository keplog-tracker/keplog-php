<?php

namespace Keplog;

/**
 * Manages breadcrumbs - a trail of events before an error
 */
class Breadcrumbs
{
    /** @var array<array> */
    private array $breadcrumbs = [];

    private int $maxBreadcrumbs;

    public function __construct(int $maxBreadcrumbs = 100)
    {
        $this->maxBreadcrumbs = $maxBreadcrumbs;
    }

    /**
     * Add a breadcrumb
     *
     * @param array $breadcrumb
     * @return void
     */
    public function add(array $breadcrumb): void
    {
        // Ensure timestamp is set
        if (!isset($breadcrumb['timestamp'])) {
            $breadcrumb['timestamp'] = time();
        }

        $this->breadcrumbs[] = $breadcrumb;

        // Maintain max limit (FIFO - remove oldest)
        if (count($this->breadcrumbs) > $this->maxBreadcrumbs) {
            array_shift($this->breadcrumbs);
        }
    }

    /**
     * Get all breadcrumbs
     *
     * @return array<array>
     */
    public function getAll(): array
    {
        return $this->breadcrumbs;
    }

    /**
     * Clear all breadcrumbs
     *
     * @return void
     */
    public function clear(): void
    {
        $this->breadcrumbs = [];
    }

    /**
     * Get count of breadcrumbs
     *
     * @return int
     */
    public function getCount(): int
    {
        return count($this->breadcrumbs);
    }
}
