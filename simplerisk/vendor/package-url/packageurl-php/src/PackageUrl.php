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
 * A purl is a package URL as defined at
 * {@link https://github.com/package-url/purl-spec}.
 *
 * @psalm-type TType = non-empty-string
 * @psalm-type TNamespace = non-empty-string|null
 * @psalm-type TName = non-empty-string
 * @psalm-type TVersion = non-empty-string|null
 * @psalm-type TQualifiers = null|non-empty-array<non-empty-string, non-empty-string>
 * @psalm-type TChecksums = null|non-empty-list<string>
 * @psalm-type TSubpath = non-empty-string|null
 *
 * @author jkowalleck
 */
class PackageUrl
{
    public const SCHEME = 'pkg';

    // region known qualifiers
    /*
     * see https://github.com/package-url/purl-spec/blob/master/PURL-SPECIFICATION.rst#known-qualifiers-keyvalue-pairs
     *
     * Note: Do not abuse qualifiers: it can be tempting to use many qualifier keys but their usage should be limited to the bare minimum for proper package identification to ensure that a purl stays compact and readable in most cases.
     *
     * Additional, separate external attributes stored outside of a purl are the preferred mechanism to convey extra long and optional information such as a download URL, vcs URL or checksums in an API, database or web form.
     *
     * With this warning, the known key and value defined here are valid for use in all package types:
    */

    /**
     * is an extra URL for an alternative, non-default package repository or registry.
     * When a package does not come from the default public package repository for its type a purl may be qualified with this extra URL.
     * The default repository or registry of a type is documented in the {@link https://github.com/package-url/purl-spec/blob/master/PURL-TYPES.rst "Known purl types" section}.
     */
    public const QUALIFIER_REPOSITORY_URL = 'repository_url';

    /**
     * is an extra URL for a direct package web download URL to optionally qualify a purl.
     */
    public const QUALIFIER_DOWNLOAD_URL = 'download_url';

    /**
     * is an extra URL for a package version control system URL to optionally qualify a purl.
     * The syntax for this URL should be as defined in Python pip or the SPDX specification.
     * See {@link https://github.com/spdx/spdx-spec/blob/cfa1b9d08903/chapters/3-package-information.md#37-package-download-location}.
     */
    public const QUALIFIER_VCS_URL = 'vcs_url';

    /**
     * is an extra file name of a package archive.
     */
    public const QUALIFIER_FILE_NAME = 'file_name';

    /**
     * is a qualifier for one or more checksums stored as a comma-separated list.
     * Each item in the value is in form of lowercase_algorithm:hex_encoded_lowercase_value.
     */
    public const QUALIFIER_CHECKSUM = 'checksum';

    /**
     * @see QUALIFIER_CHECKSUM
     * @deprecated use {@see QUALIFIER_CHECKSUM} instead
     */
    public const CHECKSUM_QUALIFIER = self::QUALIFIER_CHECKSUM;

    // endregion known qualifiers

    /**
     * @psalm-var TType
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $type;

    /**
     * @psalm-var TNamespace
     */
    private $namespace;

    /**
     * @psalm-var TName
     *
     * @psalm-suppress PropertyNotSetInConstructor
     */
    private $name;

    /**
     * @psalm-var TVersion
     */
    private $version;

    /**
     * @psalm-var TQualifiers
     */
    private $qualifiers;

    /**
     * @var TChecksums
     */
    private $checksums;

    /**
     * @psalm-var TSubpath
     */
    private $subpath;

    // region getters/setters

    /**
     * @psalm-return TType
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @throws \DomainException if value is empty
     *
     * @psalm-return  $this
     */
    public function setType(string $type): self
    {
        if ('' === $type) {
            throw new \DomainException('Type must not be empty');
        }
        $this->type = $type;

        return $this;
    }

    /**
     * @psalm-return TNamespace
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * @psalm-return $this
     */
    public function setNamespace(?string $namespace): self
    {
        $this->namespace = '' === $namespace ? null : $namespace;

        return $this;
    }

    /**
     * @psalm-return TName
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws \DomainException if value is empty
     *
     * @psalm-return $this
     */
    public function setName(string $name): self
    {
        if ('' === $name) {
            throw new \DomainException('Name must not be empty');
        }
        $this->name = $name;

        return $this;
    }

    /**
     * @psalm-return TVersion
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @psalm-return $this
     */
    public function setVersion(?string $version): self
    {
        $this->version = '' === $version ? null : $version;

        return $this;
    }

    /**
     * @psalm-return TQualifiers
     */
    public function getQualifiers(): ?array
    {
        return $this->qualifiers;
    }

    /**
     * @psalm-param TQualifiers $qualifiers
     *
     * @throws \DomainException if checksums are part of the qualifiers. Use setChecksums() to set these.
     *
     * @psalm-return $this
     */
    public function setQualifiers(?array $qualifiers): self
    {
        if ($qualifiers && \array_key_exists(self::QUALIFIER_CHECKSUM, $qualifiers)) {
            throw new \DomainException('Checksums must not be part of the qualifiers. Use setChecksums().');
        }
        $this->qualifiers = $qualifiers;

        return $this;
    }

    /**
     * @psalm-return TChecksums
     */
    public function getChecksums(): ?array
    {
        return $this->checksums;
    }

    /**
     * @psalm-param TChecksums $checksums
     *
     * @psalm-return $this
     */
    public function setChecksums(?array $checksums): self
    {
        $this->checksums = null === $checksums ? null : array_values($checksums);

        return $this;
    }

    /**
     * @psalm-return TSubpath
     */
    public function getSubpath(): ?string
    {
        return $this->subpath;
    }

    /**
     * @psalm-return $this
     */
    public function setSubpath(?string $subpath): self
    {
        $this->subpath = '' === $subpath ? null : $subpath;

        return $this;
    }

    // endregion getters/setters

    /**
     * @throws \DomainException if a value was invalid
     *
     * @see settype()
     * @see setName()
     */
    final public function __construct(string $type, string $name)
    {
        $this->setType($type);
        $this->setName($name);
    }

    /**
     * implementation is not yet completely conform to
     * {@link https://github.com/package-url/purl-spec/blob/master/README.rst#a-purl-is-a-url}.
     *
     * @psalm-return non-empty-string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @psalm-return non-empty-string
     *
     * @psalm-suppress MissingThrowsDocblock since DomainExceptions are impossible due to internal assertions.
     */
    public function toString(?PackageUrlBuilder $builder = null): string
    {
        $builder = $builder ?? new PackageUrlBuilder();

        $qualifiers = $this->qualifiers ?? [];
        if ($this->checksums) {
            $qualifiers[self::QUALIFIER_CHECKSUM] = $this->checksums;
        }

        return $builder->build(
            $this->type,
            $this->namespace,
            $this->name,
            $this->version,
            $qualifiers,
            $this->subpath
        );
    }

    /**
     * @throws \DomainException if the data is invalid according to the specification
     *
     * @psalm-return static|null null when empty string is passed
     */
    public static function fromString(string $data, ?PackageUrlParser $parser = null): ?self
    {
        if ('' === $data) {
            return null;
        }

        $parser = $parser ?? new PackageUrlParser();

        [
            'scheme' => $scheme,
            'type' => $type,
            'name' => $name,
            'namespace' => $namespace,
            'version' => $version,
            'qualifiers' => $qualifiers,
            'subpath' => $subpath,
        ] = $parser->parse($data);

        if (self::SCHEME !== $parser->normalizeScheme((string) $scheme)) {
            throw new \DomainException("Mismatching scheme '{$scheme}'");
        }

        $type = $parser->normalizeType($type);
        if (null === $type) {
            throw new \DomainException('Type must not be empty');
        }

        $name = $parser->normalizeName($name, $type);
        if (null === $name) {
            throw new \DomainException('Name must not be empty');
        }

        [$qualifiers, $checksums] = $parser->normalizeQualifiers($qualifiers);

        return (new static($type, $name))
            ->setNamespace($parser->normalizeNamespace($namespace, $type))
            ->setVersion($parser->normalizeVersion($version))
            ->setQualifiers($qualifiers)
            ->setChecksums($checksums)
            ->setSubpath($parser->normalizeSubpath($subpath));
    }
}
