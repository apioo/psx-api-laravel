<?php
/*
 * PSX is an open source PHP framework to develop RESTful APIs.
 * For the current version and information visit <https://phpsx.org>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\ApiLaravel\Http;

use PSX\DateTime\LocalDate;
use PSX\DateTime\LocalDateTime;
use PSX\DateTime\LocalTime;
use PSX\Http\Exception\BadRequestException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * ParameterReader
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://phpsx.org
 */
class ParameterReader
{
    public function parse(mixed $value, ReflectionParameter $parameter, string $name, string $type): mixed
    {
        if (!$parameter->allowsNull() && $value === null) {
            throw new BadRequestException('Missing ' . $type . ' parameter "' . $name . '"');
        }

        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType) {
            $type = $type->getName();
        }

        if ($type === null || $value === null) {
            return null;
        }

        return match ($type) {
            LocalDate::class => LocalDate::parse($value),
            LocalDateTime::class => LocalDateTime::parse($value),
            LocalTime::class => LocalTime::parse($value),
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => $value,
        };
    }
}
