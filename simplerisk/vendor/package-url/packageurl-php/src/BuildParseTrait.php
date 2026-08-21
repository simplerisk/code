<?php

declare(strict_types=1);

/*
 * MIT License
 *
 * Copyright (c) 2021 package-url
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */

namespace PackageUrl;

use Closure;

/**
 * @internal
 *
 * @author jkowaleck
 */
trait BuildParseTrait
{
    /**
     * @psalm-assert-if-true non-empty-string $data
     */
    private function isNotEmpty(string $data): bool
    {
        return '' !== $data;
    }

    /**
     * @psalm-assert-if-true non-empty-string $segment
     */
    private function isUsefulSubpathSegment(string $segment): bool
    {
        return false === \in_array($segment, ['', '.', '..'], true);
    }

    /**
     * @psalm-return Closure(non-empty-string):non-empty-string
     */
    private function getNormalizerForNamespace(?string $type): \Closure
    {
        if (null !== $type) {
            $type = strtolower($type);
        }
        if (\in_array($type, ['bitbucket', 'deb', 'github', 'golang', 'hex', 'rpm'], true)) {
            return static function (string $data): string {
                return strtolower($data);
            };
        }

        return static function (string $data): string {
            return $data;
        };
    }

    /**
     * @psalm-param  non-empty-string $name
     *
     * @return non-empty-string
     */
    private function normalizeNameForType(string $name, ?string $type): string
    {
        if (null !== $type) {
            $type = strtolower($type);
        }
        if ('pypi' === $type) {
            /**
             * note for psalm that the length did not change.
             *
             * @psalm-var non-empty-string $name
             */
            $name = str_replace('_', '-', $name);
        }

        if (\in_array($type, ['bitbucket', 'deb', 'github', 'golang', 'hex', 'npm', 'pypi'], true)) {
            $name = strtolower($name);
        }

        return $name;
    }
}
