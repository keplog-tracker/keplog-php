<?php

namespace Keplog\Tests;

use PHPUnit\Framework\TestCase;
use Keplog\Breadcrumbs;

class BreadcrumbsTest extends TestCase
{
    private Breadcrumbs $breadcrumbs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breadcrumbs = new Breadcrumbs(5);
    }

    public function testAddBreadcrumb(): void
    {
        $breadcrumb = [
            'message' => 'User clicked button',
            'category' => 'ui',
        ];

        $this->breadcrumbs->add($breadcrumb);
        $all = $this->breadcrumbs->getAll();

        $this->assertCount(1, $all);
        $this->assertEquals('User clicked button', $all[0]['message']);
        $this->assertEquals('ui', $all[0]['category']);
    }

    public function testAddBreadcrumbAutoAddsTimestamp(): void
    {
        $breadcrumb = ['message' => 'Test'];

        $this->breadcrumbs->add($breadcrumb);
        $all = $this->breadcrumbs->getAll();

        $this->assertArrayHasKey('timestamp', $all[0]);
        $this->assertIsInt($all[0]['timestamp']);
    }

    public function testAddBreadcrumbPreservesExistingTimestamp(): void
    {
        $timestamp = 1234567890;
        $breadcrumb = [
            'message' => 'Test',
            'timestamp' => $timestamp,
        ];

        $this->breadcrumbs->add($breadcrumb);
        $all = $this->breadcrumbs->getAll();

        $this->assertEquals($timestamp, $all[0]['timestamp']);
    }

    public function testMaxBreadcrumbsLimit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->breadcrumbs->add(['message' => "Breadcrumb $i"]);
        }

        $all = $this->breadcrumbs->getAll();

        $this->assertCount(5, $all);
        // Should keep the last 5 (FIFO - oldest removed)
        $this->assertEquals('Breadcrumb 5', $all[0]['message']);
        $this->assertEquals('Breadcrumb 9', $all[4]['message']);
    }

    public function testClearBreadcrumbs(): void
    {
        $this->breadcrumbs->add(['message' => 'Test 1']);
        $this->breadcrumbs->add(['message' => 'Test 2']);

        $this->breadcrumbs->clear();

        $this->assertCount(0, $this->breadcrumbs->getAll());
    }

    public function testGetCount(): void
    {
        $this->assertEquals(0, $this->breadcrumbs->getCount());

        $this->breadcrumbs->add(['message' => 'Test 1']);
        $this->assertEquals(1, $this->breadcrumbs->getCount());

        $this->breadcrumbs->add(['message' => 'Test 2']);
        $this->assertEquals(2, $this->breadcrumbs->getCount());
    }

    public function testCustomMaxBreadcrumbs(): void
    {
        $breadcrumbs = new Breadcrumbs(3);

        for ($i = 0; $i < 5; $i++) {
            $breadcrumbs->add(['message' => "Item $i"]);
        }

        $this->assertCount(3, $breadcrumbs->getAll());
    }

    public function testBreadcrumbsAreFIFO(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->breadcrumbs->add(['message' => "Item $i"]);
        }

        $all = $this->breadcrumbs->getAll();

        // Should have items 2, 3, 4, 5, 6 (oldest 0 and 1 removed)
        $this->assertEquals('Item 2', $all[0]['message']);
        $this->assertEquals('Item 6', $all[4]['message']);
    }
}
