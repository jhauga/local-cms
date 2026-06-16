<?php
declare(strict_types=1);

namespace Cms\Port;

use RuntimeException;

/**
 * Raised when a source theme cannot be ported into a Local CMS-compatible theme.
 *
 * The message is the human-readable reason the port was refused (no style.css,
 * missing "Theme Name" header, no base template, ...). port-cms surfaces it
 * verbatim so the operator learns exactly why the port stopped.
 */
final class PortCompatibilityException extends RuntimeException
{
}
