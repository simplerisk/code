<?php

declare(strict_types=1);

/*
 * This file is part of CycloneDX PHP Library.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * SPDX-License-Identifier: Apache-2.0
 * Copyright (c) OWASP Foundation. All Rights Reserved.
 */

namespace CycloneDX\Core\Validation\Errors;

use CycloneDX\Core\Validation\ValidationError;
use LibXMLError;

/**
 * @author jkowalleck
 */
class XmlValidationError extends ValidationError
{
    /**
     * keep for internal debug purposes.
     *
     * @var object|null
     */
    private $debugError;

    /**
     * @internal
     *
     * @return static
     */
    public static function fromLibXMLError(LibXMLError $error): self
    {
        $i = new static($error->message);
        $i->debugError = $error;

        return $i;
    }

    /**
     * Accessor for debug purposes.
     *
     * @internal as this method is not kept backwards-compatible
     *
     * @codeCoverageIgnore
     *
     * @SuppressWarnings(PHPMD)
     */
    final public function debug_getError(): ?object
    {
        return $this->debugError;
    }
}
