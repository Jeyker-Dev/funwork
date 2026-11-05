<?php

namespace App\Services\Google;

use RuntimeException;

/**
 * The user has no usable Google connection (never connected, or Google
 * rejected the stored tokens) and has to connect again.
 */
class GoogleAuthorizationException extends RuntimeException {}
