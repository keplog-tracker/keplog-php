<?php

namespace Keplog;

/**
 * Manages global context and scope for error events
 */
class Scope
{
    /** @var array<string, mixed> */
    private array $context = [];

    /** @var array<string, string> */
    private array $tags = [];

    private ?array $user = null;

    /**
     * Set a context value
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * Get all context data
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set a single tag
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function setTag(string $key, string $value): void
    {
        $this->tags[$key] = $value;
    }

    /**
     * Set multiple tags at once
     *
     * @param array<string, string> $tags
     * @return void
     */
    public function setTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }

    /**
     * Get all tags
     *
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Set user information
     *
     * @param array $user
     * @return void
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * Get user information
     *
     * @return array|null
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * Clear all scope data
     *
     * @return void
     */
    public function clear(): void
    {
        $this->context = [];
        $this->tags = [];
        $this->user = null;
    }

    /**
     * Merge global scope with local context
     *
     * @param array $localContext
     * @return array<string, mixed>
     */
    public function merge(array $localContext = []): array
    {
        $merged = array_merge($this->context, $localContext);

        // Add tags if they exist
        if (!empty($this->tags) || !empty($localContext['tags'] ?? [])) {
            $merged['tags'] = array_merge(
                $this->tags,
                $localContext['tags'] ?? []
            );
        }

        // Add user if exists
        if ($this->user !== null || !empty($localContext['user'] ?? null)) {
            $merged['user'] = array_merge(
                $this->user ?? [],
                $localContext['user'] ?? []
            );
        }

        return $merged;
    }
}
