<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SourceEncodingTest extends TestCase
{
    /**
     * Falla si algún archivo quedó con texto UTF-8 releído como Windows-1252
     * (por ejemplo, la "í" de "jardín" convertida en dos caracteres raros).
     */
    public function test_source_files_have_no_mojibake(): void
    {
        $root = dirname(__DIR__, 2);
        // Se arman desde los caracteres buenos para que este archivo no se detecte a sí mismo.
        $patterns = array_map(
            fn (string $char): string => mb_convert_encoding($char, 'UTF-8', 'Windows-1252'),
            ["\xC3", "\xE2\x80"],
        );
        $offenders = [];

        foreach (['app', 'config', 'database', 'resources', 'routes', 'tests'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! preg_match('/\.(php|js|json|css)$/', $file->getFilename())) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                foreach ($patterns as $pattern) {
                    if (str_contains($contents, $pattern)) {
                        $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Archivos con mojibake: '.implode(', ', $offenders));
    }
}
