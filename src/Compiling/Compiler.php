<?php

declare(strict_types=1);

namespace PCP\Compiling;

use PCP\Parsing\Parser;
use PCP\Parsing\Tokenizer;
use PCP\PCPConfig;
use RuntimeException;
use Throwable;

final readonly class Compiler
{
    public function __construct(
        private PCPConfig $config,
    ) {
    }

    public function compileIfNeeded(string $class, string $sourcePath): string
    {
        $compiledPath = $this->compiledPathFor($class, $sourcePath);

        $this->ensureCacheDirectoryExists();

        if (!$this->needsCompilation($sourcePath, $compiledPath)) {
            return $compiledPath;
        }

        $source = file_get_contents($sourcePath);

        if ($source === false) {
            throw new RuntimeException(sprintf(
                'Unable to read PCP component source file "%s".',
                $sourcePath,
            ));
        }

        $compiled = $this->compileSource($source, $class, $sourcePath);

        if (file_put_contents($compiledPath, $compiled, LOCK_EX) === false) {
            throw new RuntimeException(sprintf(
                'Unable to write compiled PCP component "%s".',
                $compiledPath,
            ));
        }

        return $compiledPath;
    }

    private function compileSource(string $source, string $class, string $sourcePath): string
    {
        $normalizedSource = $this->normalizeSource($source, $sourcePath);
        $transformedSource = $this->transformPcpReturns($normalizedSource, $class, $sourcePath);

        return <<<PHP
        <?php
        
        declare(strict_types=1);
        
        /**
         * --------------------------------------------------------------------------
         * PCP generated this file.
         * --------------------------------------------------------------------------
         * Source: {$sourcePath}
         * Class: {$class}
         */
        
        {$transformedSource}
        PHP;
    }

    private function normalizeSource(string $source, string $sourcePath): string
    {
        $source = str_replace(array("\r\n", "\r"), "\n", $source);
        $source = ltrim($source);

        if ($source === '') {
            throw new RuntimeException(sprintf(
                'PCP component source "%s" cannot be empty.',
                $sourcePath,
            ));
        }

        $isPcpFile = $this->isPcpFile($sourcePath);
        $isPhpFile = $this->isPhpFile($sourcePath);

        if (!$isPcpFile && !$isPhpFile) {
            throw new RuntimeException(sprintf(
                'Unsupported PCP component extension in "%s".',
                $sourcePath,
            ));
        }

        if (str_starts_with($source, '<?pcp')) {
            $source = substr($source, 5);
            $source = ltrim($source);

            if ($source === '') {
                throw new RuntimeException(sprintf(
                    'PCP component source "%s" is empty after removing the "<?pcp" directive.',
                    $sourcePath,
                ));
            }

            return $source;
        }

        if ($isPcpFile) {
            return $source;
        }

        throw new RuntimeException(sprintf(
            'PHP component "%s" must start with "<?pcp".',
            $sourcePath,
        ));
    }

    private function transformPcpReturns(string $source, string $class, string $sourcePath): string
    {
        $offset = 0;
        $result = '';

        while (true) {
            $returnPos = strpos($source, 'return', $offset);

            if ($returnPos === false) {
                $result .= substr($source, $offset);
                break;
            }

            $result .= substr($source, $offset, $returnPos - $offset);

            if (!$this->isStandaloneKeyword($source, $returnPos, 'return')) {
                $result .= 'return';
                $offset = $returnPos + 6;
                continue;
            }

            $cursor = $returnPos + 6;
            $this->skipWhitespace($source, $cursor);

            if (($source[$cursor] ?? null) !== '(') {
                $result .= 'return';
                $offset = $returnPos + 6;
                continue;
            }

            $markup = $this->extractBalancedParenthesizedContent($source, $cursor);
            $afterParen = $cursor;
            $this->skipWhitespace($source, $afterParen);

            if (($source[$afterParen] ?? null) !== ';') {
                throw new RuntimeException(sprintf(
                    'Expected ";" after PCP return block in "%s" near offset %d.',
                    $sourcePath,
                    $afterParen,
                ));
            }

            if (!$this->looksLikePcpMarkup($markup)) {
                $result .= 'return (' . $markup . ')';
                $offset = $afterParen;
                continue;
            }

            $generatedPhp = $this->compileMarkupToPhpExpression($markup, $class, $sourcePath);
            $result .= 'return ' . $generatedPhp;

            $offset = $afterParen;
        }

        return $result;
    }

    private function compileMarkupToPhpExpression(string $markup, string $class, string $sourcePath): string
    {
        $tokenizer = new Tokenizer();
        $tokens = $tokenizer->tokenize($markup);

        $parser = new Parser();
        $ast = $parser->parse($tokens);

        $classArr = explode('\\', $class);
        $namespace = implode('\\', array_slice($classArr, 0, -1));
        $generator = new PhpAstGenerator();
        $generator->setNamespace($namespace);

        try {
            return $generator->generate($ast);
        } catch (Throwable $e) {
            throw new RuntimeException(sprintf(
                'Failed to generate PHP for PCP markup in "%s" (%s): %s',
                $sourcePath,
                $class,
                $e->getMessage(),
            ), 0, $e);
        }
    }

    private function looksLikePcpMarkup(string $content): bool
    {
        $trimmed = ltrim($content);

        if ($trimmed === '') {
            return false;
        }

        return str_starts_with($trimmed, '<')
            || str_starts_with($trimmed, '@');
    }

    private function extractBalancedParenthesizedContent(string $source, int &$cursor): string
    {
        if (($source[$cursor] ?? null) !== '(') {
            throw new RuntimeException(sprintf(
                'Expected "(" at offset %d.',
                $cursor,
            ));
        }

        $cursor++; // skip opening (
        $depth = 1;
        $buffer = '';

        while (isset($source[$cursor])) {
            $char = $source[$cursor];
            $cursor++;

            if ($char === '"' || $char === "'") {
                $buffer .= $char;
                $buffer .= $this->readPhpStringContents($source, $cursor, $char);
                continue;
            }

            if ($char === '/' && ($source[$cursor] ?? null) === '/') {
                $buffer .= $char;
                $buffer .= $this->readLineComment($source, $cursor);
                continue;
            }

            if ($char === '/' && ($source[$cursor] ?? null) === '*') {
                $buffer .= $char;
                $buffer .= $this->readBlockComment($source, $cursor);
                continue;
            }

            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $buffer;
                }

                $buffer .= $char;
                continue;
            }

            $buffer .= $char;
        }

        throw new RuntimeException('Unterminated parenthesized PCP return block.');
    }

    private function readPhpStringContents(string $source, int &$cursor, string $quote): string
    {
        $buffer = '';

        while (isset($source[$cursor])) {
            $char = $source[$cursor];
            $cursor++;

            $buffer .= $char;
            if ($char === '\\') {

                if (isset($source[$cursor])) {
                    $buffer .= $source[$cursor];
                    $cursor++;
                }

                continue;
            }

            if ($char === $quote) {
                return $buffer;
            }
        }

        throw new RuntimeException('Unterminated string literal while parsing PCP source.');
    }

    private function readLineComment(string $source, int &$cursor): string
    {
        $buffer = '/';
        $buffer .= $source[$cursor] ?? '';
        $cursor++;

        while (isset($source[$cursor])) {
            $char = $source[$cursor];
            $buffer .= $char;
            $cursor++;

            if ($char === "\n") {
                return $buffer;
            }
        }

        return $buffer;
    }

    private function readBlockComment(string $source, int &$cursor): string
    {
        $buffer = '*';
        $cursor++; // skip *

        while (isset($source[$cursor])) {
            $char = $source[$cursor];
            $buffer .= $char;
            $cursor++;

            if ($char === '*' && ($source[$cursor] ?? null) === '/') {
                $buffer .= '/';
                $cursor++;

                return $buffer;
            }
        }

        throw new RuntimeException('Unterminated block comment while parsing PCP source.');
    }

    private function isStandaloneKeyword(string $source, int $position, string $keyword): bool
    {
        $before = $position > 0 ? $source[$position - 1] : null;
        $after = $source[$position + strlen($keyword)] ?? null;

        $beforeOk = $before === null || !preg_match('/\w/', $before);
        $afterOk = $after === null || !preg_match('/\w/', $after);

        return $beforeOk && $afterOk;
    }

    private function skipWhitespace(string $source, int &$cursor): void
    {
        while (isset($source[$cursor]) && preg_match('/\s/', $source[$cursor]) === 1) {
            $cursor++;
        }
    }

    private function needsCompilation(string $sourcePath, string $compiledPath): bool
    {
        if (!is_file($compiledPath)) {
            return true;
        }

        if (!$this->config->env->isDev()) {
            return false;
        }

        $sourceMTime = filemtime($sourcePath);
        $compiledMTime = filemtime($compiledPath);

        if ($sourceMTime === false || $compiledMTime === false) {
            return true;
        }

        return $sourceMTime > $compiledMTime;
    }

    private function compiledPathFor(string $class, string $sourcePath): string
    {
        $hash = sha1($class . '|' . $sourcePath);

        return $this->config->cacheDir
            . DIRECTORY_SEPARATOR
            . str_replace('\\', '_', trim($class, '\\'))
            . '.'
            . $hash
            . '.php';
    }

    private function ensureCacheDirectoryExists(): void
    {
        if (is_dir($this->config->cacheDir)) {
            return;
        }

        if (!mkdir($this->config->cacheDir, 0775, true) && !is_dir($this->config->cacheDir)) {
            throw new RuntimeException(sprintf(
                'Unable to create PCP cache directory "%s".',
                $this->config->cacheDir,
            ));
        }
    }

    private function isPcpFile(string $path): bool
    {
        return str_ends_with(strtolower($path), '.pcp');
    }

    private function isPhpFile(string $path): bool
    {
        return str_ends_with(strtolower($path), '.php');
    }
}