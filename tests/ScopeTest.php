<?php

namespace Keplog\Tests;

use PHPUnit\Framework\TestCase;
use Keplog\Scope;

class ScopeTest extends TestCase
{
    private Scope $scope;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scope = new Scope();
    }

    public function testSetAndGetContext(): void
    {
        $this->scope->setContext('key1', 'value1');
        $this->scope->setContext('key2', ['nested' => 'data']);

        $context = $this->scope->getContext();

        $this->assertEquals('value1', $context['key1']);
        $this->assertEquals(['nested' => 'data'], $context['key2']);
    }

    public function testSetAndGetTag(): void
    {
        $this->scope->setTag('env', 'production');

        $tags = $this->scope->getTags();

        $this->assertEquals('production', $tags['env']);
    }

    public function testSetTags(): void
    {
        $this->scope->setTag('existing', 'value');
        $this->scope->setTags([
            'env' => 'staging',
            'version' => '1.2.3',
        ]);

        $tags = $this->scope->getTags();

        $this->assertEquals('value', $tags['existing']);
        $this->assertEquals('staging', $tags['env']);
        $this->assertEquals('1.2.3', $tags['version']);
    }

    public function testSetAndGetUser(): void
    {
        $user = [
            'id' => 123,
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];

        $this->scope->setUser($user);

        $this->assertEquals($user, $this->scope->getUser());
    }

    public function testClearScope(): void
    {
        $this->scope->setContext('key', 'value');
        $this->scope->setTag('env', 'production');
        $this->scope->setUser(['id' => 123]);

        $this->scope->clear();

        $this->assertEmpty($this->scope->getContext());
        $this->assertEmpty($this->scope->getTags());
        $this->assertNull($this->scope->getUser());
    }

    public function testMergeWithEmptyLocalContext(): void
    {
        $this->scope->setContext('global_key', 'global_value');
        $this->scope->setTag('global_tag', 'tag_value');
        $this->scope->setUser(['id' => 123]);

        $merged = $this->scope->merge();

        $this->assertEquals('global_value', $merged['global_key']);
        $this->assertEquals(['global_tag' => 'tag_value'], $merged['tags']);
        $this->assertEquals(['id' => 123], $merged['user']);
    }

    public function testMergeWithLocalContext(): void
    {
        $this->scope->setContext('global_key', 'global_value');

        $localContext = [
            'local_key' => 'local_value',
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals('global_value', $merged['global_key']);
        $this->assertEquals('local_value', $merged['local_key']);
    }

    public function testMergeLocalContextOverridesGlobal(): void
    {
        $this->scope->setContext('key', 'global_value');

        $localContext = [
            'key' => 'local_value',
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals('local_value', $merged['key']);
    }

    public function testMergeTagsWithLocalTags(): void
    {
        $this->scope->setTag('global_tag', 'global_value');

        $localContext = [
            'tags' => [
                'local_tag' => 'local_value',
            ],
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals('global_value', $merged['tags']['global_tag']);
        $this->assertEquals('local_value', $merged['tags']['local_tag']);
    }

    public function testMergeLocalTagsOverrideGlobalTags(): void
    {
        $this->scope->setTag('env', 'production');

        $localContext = [
            'tags' => [
                'env' => 'development',
            ],
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals('development', $merged['tags']['env']);
    }

    public function testMergeUserWithLocalUser(): void
    {
        $this->scope->setUser(['id' => 123, 'email' => 'global@example.com']);

        $localContext = [
            'user' => [
                'name' => 'Local User',
            ],
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals(123, $merged['user']['id']);
        $this->assertEquals('global@example.com', $merged['user']['email']);
        $this->assertEquals('Local User', $merged['user']['name']);
    }

    public function testMergeLocalUserOverridesGlobalUser(): void
    {
        $this->scope->setUser(['id' => 123, 'email' => 'global@example.com']);

        $localContext = [
            'user' => [
                'id' => 456,
                'email' => 'local@example.com',
            ],
        ];

        $merged = $this->scope->merge($localContext);

        $this->assertEquals(456, $merged['user']['id']);
        $this->assertEquals('local@example.com', $merged['user']['email']);
    }

    public function testMergeDoesNotIncludeTagsWhenEmpty(): void
    {
        $this->scope->setContext('key', 'value');

        $merged = $this->scope->merge();

        $this->assertArrayNotHasKey('tags', $merged);
    }

    public function testMergeDoesNotIncludeUserWhenNull(): void
    {
        $this->scope->setContext('key', 'value');

        $merged = $this->scope->merge();

        $this->assertArrayNotHasKey('user', $merged);
    }
}
