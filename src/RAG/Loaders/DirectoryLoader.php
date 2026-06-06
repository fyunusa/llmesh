<?php

declare(strict_types=1);

namespace LLMesh\Core\RAG\Loaders;

use LLMesh\Core\RAG\Document;

/**
 * Loads all files with matching extensions from a directory (non-recursive by default).
 *
 * @example
 *   $loader = new DirectoryLoader('/docs', ['txt', 'md']);
 *   $docs   = $loader->load(); // one Document per matching file
 */
final class DirectoryLoader implements LoaderInterface
{
    /**
     * @param string   $path       Path to the directory to scan
     * @param string[] $extensions File extensions to include (without the leading dot)
     * @param bool     $recursive  When true, sub-directories are also scanned
     */
    public function __construct(
        private readonly string $path,
        private readonly array $extensions = ['txt', 'md'],
        private readonly bool $recursive = false,
    ) {
    }

    /** {@inheritDoc} */
    public function load(): array
    {
        $realPath = realpath($this->path);

        if ($realPath === false || !is_dir($realPath)) {
            throw new \RuntimeException("Directory not found or not readable: {$this->path}");
        }

        $files     = $this->collectFiles($realPath);
        $documents = [];

        foreach ($files as $file) {
            $documents[] = Document::fromFile($file);
        }

        return $documents;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Collect file paths matching the configured extensions.
     *
     * @param  string $directory Canonical directory path
     * @return string[]
     */
    private function collectFiles(string $directory): array
    {
        $extensionSet = array_map('strtolower', $this->extensions);
        $files        = [];

        $flags = $this->recursive
            ? \FilesystemIterator::SKIP_DOTS
            : \FilesystemIterator::SKIP_DOTS;

        $iterator = $this->recursive
            ? new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, $flags),
            )
            : new \FilesystemIterator($directory, $flags);

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower($fileInfo->getExtension());
            if (in_array($ext, $extensionSet, strict: true)) {
                $files[] = $fileInfo->getRealPath();
            }
        }

        // Sort for deterministic ordering across filesystems
        sort($files);

        return $files;
    }
}
