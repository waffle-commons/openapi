<?php

declare(strict_types=1);

namespace Waffle\Commons\OpenApi\Exception;

use InvalidArgumentException;

/**
 * An `#[OA\*]` attribute the document cannot carry: a status that is not an HTTP
 * status, or a body type that is not a `#[Dto]` class. Raised at build time by
 * `openapi:generate`, never on a request.
 */
final class InvalidAttributeException extends InvalidArgumentException {}
