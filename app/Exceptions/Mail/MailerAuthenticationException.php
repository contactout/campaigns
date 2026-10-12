<?php

namespace App\Exceptions\Mail;

use RuntimeException;

/**
 * A mailer connection has no usable credentials, so it cannot send at all.
 */
class MailerAuthenticationException extends RuntimeException {}
