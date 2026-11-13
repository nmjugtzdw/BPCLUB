<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Raised when a read page needs the database but none is configured or the
 * connection failed. Controllers translate it into a 503 page instead of an
 * empty list, so "no content" never stands in for "no server".
 */
final class ReaderUnavailable extends RuntimeException
{
}
