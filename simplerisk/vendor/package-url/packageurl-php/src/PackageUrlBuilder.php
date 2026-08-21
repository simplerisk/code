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

/**
 * @internal this is not guaranteed to stay backwards compatible
 *
 * @author jkowalleck
 */
class PackageUrlBuilder
{
    use BuildParseTrait;

    // region build

    /**
     * @psalm-param string $type
     * @psalm-param string|null $namespace
     * @psalm-param string $name
     * @psalm-param string|null $version
     * @psalm-param mixed[]|null $qualifiers can handle "checksum" as string or list of strings.
     * @psalm-param string|null $subpath
     *
     * @throws \DomainException if type is empty
     * @throws \DomainException if name is empty
     *
     * @psalm-return non-empty-string
     */
    public function build(
        string $type,
        ?string $namespace,
        string $name,
        ?string $version,
        ?array $qualifiers,
        ?string $subpath
    ): string {
        if ('' === $type) {
            throw new \DomainException('Type must not be empty');
        }
        if ('' === $name) {
            throw new \DomainException('Name must not be empty');
        }

        $type = $this->normalizeType($type);
        $namespace = $this->normalizeNamespace($namespace, $type);
        $name = $this->normalizeName($name, $type);
        $version = $this->normalizeVersion($version);
        $qualifiers = $this->normalizeQualifiers($qualifiers);
        $subpath = $this->normalizeSubpath($subpath);

        return PackageUrl::SCHEME.
            ':'.$type.
            (null === $namespace ? '' : '/'.$namespace).
            '/'.$name.
            (null === $version ? '' : '@'.$version).
            (null === $qualifiers ? '' : '?'.$qualifiers).
            (null === $subpath ? '' : '#'.$subpath);
    }

    // endregion build

    // region normalize

    /**
     * @psalm-param non-empty-string $data
     *
     * @psalm-return non-empty-string
     */
    public function normalizeType(string $data): string
    {
        return strtolower($data);
    }

    /**
     * @psalm-param non-empty-string $type
     *
     * @psalm-return non-empty-string|null
     */
    public function normalizeNamespace(?string $data, string $type): ?string
    {
        if (null === $data) {
            return null;
        }

        $data = trim($data, '/');
        $segments = explode('/', $data);
        $segments = array_filter($segments, [$this, 'isNotEmpty']);
        $segments = array_map($this->getNormalizerForNamespace($type), $segments);
        $segments = array_map([$this, 'encode'], $segments);

        $namespace = implode('/', $segments);

        return '' === $namespace
            ? null
            : $namespace;
    }

    /**
     * @psalm-param string $data
     * @psalm-param non-empty-string $type
     *
     * @throws \DomainException if name is empty
     */
    public function normalizeName(string $data, string $type): string
    {
        $data = trim($data, '/');
        if ('' === $data) {
            throw new \DomainException('name must not be empty');
        }
        $data = $this->normalizeNameForType($data, $type);

        return $this->encode($data);
    }

    /**
     * @psalm-return non-empty-string|null
     */
    public function normalizeVersion(?string $data): ?string
    {
        if (null === $data) {
            return null;
        }

        return '' === $data
            ? null
            : $this->encode($data);
    }

    /**
     * Can handle "checksum" as string or list of strings.
     *
     * @psalm-param mixed[]|null $data
     *
     * @psalm-return non-empty-string|null
     */
    public function normalizeQualifiers(?array $data): ?string
    {
        if (null === $data) {
            return null;
        }

        $segments = [];

        $data = array_change_key_case($data, \CASE_LOWER);

        $checksum = $this->normalizeChecksum($data[PackageUrl::QUALIFIER_CHECKSUM] ?? null);
        unset($data[PackageUrl::QUALIFIER_CHECKSUM]);

        /** @var mixed $value */
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ('' === $key) {
                continue;
            }
            $value = (string) $value;
            if ('' === $value) {
                continue;
            }
            $segments[] = $key.'='.$this->encode($value);
        }

        if (null !== $checksum) {
            $segments[] = PackageUrl::QUALIFIER_CHECKSUM.'='.$checksum;
        }

        sort($segments, \SORT_STRING);
        $qualifiers = implode('&', $segments);

        return '' === $qualifiers
            ? null
            : $qualifiers;
    }

    /**
     * @psalm-param  mixed $data
     *
     * @psalm-return non-empty-string|null
     */
    private function normalizeChecksum($data): ?string
    {
        if (null === $data) {
            return null;
        }
        if (\is_string($data)) {
            $data = explode(',', $data);
        } elseif (!\is_array($data)) {
            return null;
        }

        $checksums = [];
        /** @var mixed $checksum */
        foreach ($data as $checksum) {
            $checksum = (string) $checksum;
            if ('' === $checksum) {
                continue;
            }
            $checksums[] = $this->encode($checksum);
        }
        $checksum = implode(',', $checksums);

        return '' === $checksum
            ? null
            : $checksum;
    }

    /**
     * @psalm-return non-empty-string|null
     */
    public function normalizeSubpath(?string $data): ?string
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data, '/');

        $segments = explode('/', $data);
        /** @see BuildParseTrait::isUsefulSubpathSegment() */
        $segments = array_filter($segments, [$this, 'isUsefulSubpathSegment']);
        $segments = array_map([$this, 'encode'], $segments);

        $subpath = implode('/', $segments);

        return '' === $subpath
            ? null
            : $subpath;
    }

    // endregion normalize

    // region encode

    /**
     * Revert special chars that must not be encoded.
     * See {@link https://github.com/package-url/purl-spec#character-encoding Character encoding}.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const RAWURLENCODE_REVERT = [
        '%3A' => ':',
        '%2F' => '/',
    ];

    /**
     * @psalm-param non-empty-string $data
     *
     * @psalm-return non-empty-string
     */
    private function encode(string $data): string
    {
        $encoded = strtr(
            rawurlencode($data),
            self::RAWURLENCODE_REVERT
        );
        \assert('' !== $encoded);

        return $encoded;
    }

    // endregion encode
}
