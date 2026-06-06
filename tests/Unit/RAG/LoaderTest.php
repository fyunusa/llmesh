<?php

declare(strict_types=1);

namespace LLMesh\Core\Tests\Unit\RAG;

use LLMesh\Core\RAG\Loaders\ArrayLoader;
use LLMesh\Core\RAG\Loaders\DirectoryLoader;
use LLMesh\Core\RAG\Loaders\TextLoader;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LLMesh\Core\RAG\Loaders\ArrayLoader
 * @covers \LLMesh\Core\RAG\Loaders\TextLoader
 * @covers \LLMesh\Core\RAG\Loaders\DirectoryLoader
 */
final class LoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = __DIR__ . '/fixtures_loader_test';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testArrayLoaderLoadsRawTexts(): void
    {
        $texts = [
            'Hello world',
            'PHP is nice',
        ];
        $metadata = [
            ['author' => 'Alice'],
            ['author' => 'Bob', 'topic' => 'programming'],
        ];

        $loader = new ArrayLoader($texts, $metadata);
        $docs = $loader->load();

        $this->assertCount(2, $docs);
        $this->assertSame('Hello world', $docs[0]->content);
        $this->assertSame('Alice', $docs[0]->metadata['author']);

        $this->assertSame('PHP is nice', $docs[1]->content);
        $this->assertSame('Bob', $docs[1]->metadata['author']);
        $this->assertSame('programming', $docs[1]->metadata['topic']);
    }

    public function testTextLoaderLoadsSingleFile(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'Single file content.');

        $loader = new TextLoader($filePath);
        $docs = $loader->load();

        $this->assertCount(1, $docs);
        $this->assertSame('Single file content.', $docs[0]->content);
        $this->assertSame(realpath($filePath), $docs[0]->metadata['source']);
        $this->assertSame('test.txt', $docs[0]->metadata['filename']);
        $this->assertSame('txt', $docs[0]->metadata['extension']);
    }

    public function testTextLoaderThrowsExceptionIfFileNotFound(): void
    {
        $loader = new TextLoader($this->tempDir . '/does_not_exist.txt');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file:');
        $loader->load();
    }

    public function testDirectoryLoaderLoadsMatchingFilesNonRecursive(): void
    {
        file_put_contents($this->tempDir . '/a.txt', 'Content A');
        file_put_contents($this->tempDir . '/b.md', 'Content B');
        file_put_contents($this->tempDir . '/c.json', 'Content C'); // Not matching txt/md

        $loader = new DirectoryLoader($this->tempDir, ['txt', 'md']);
        $docs = $loader->load();

        $this->assertCount(2, $docs);
        $this->assertSame('Content A', $docs[0]->content);
        $this->assertSame('Content B', $docs[1]->content);
    }

    public function testDirectoryLoaderLoadsMatchingFilesRecursive(): void
    {
        $subDir = $this->tempDir . '/sub';
        mkdir($subDir);

        file_put_contents($this->tempDir . '/a.txt', 'Content A');
        file_put_contents($subDir . '/b.md', 'Content B');

        // Non-recursive should only find A
        $nonRecursiveLoader = new DirectoryLoader($this->tempDir, ['txt', 'md'], recursive: false);
        $this->assertCount(1, $nonRecursiveLoader->load());

        // Recursive should find both A and B
        $recursiveLoader = new DirectoryLoader($this->tempDir, ['txt', 'md'], recursive: true);
        $docs = $recursiveLoader->load();

        $this->assertCount(2, $docs);
        $this->assertSame('Content A', $docs[0]->content);
        $this->assertSame('Content B', $docs[1]->content);
    }

    public function testDirectoryLoaderThrowsExceptionIfDirNotFound(): void
    {
        $loader = new DirectoryLoader($this->tempDir . '/non_existing_dir');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Directory not found or not readable:');
        $loader->load();
    }
}
