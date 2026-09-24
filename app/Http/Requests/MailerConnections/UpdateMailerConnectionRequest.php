<?php

namespace App\Http\Requests\MailerConnections;

/**
 * Updating a mailer connection accepts the same payload as creating one; a blank
 * password is intentionally omitted from {@see settings()} so the stored credential
 * is preserved by the update action.
 */
class UpdateMailerConnectionRequest extends StoreMailerConnectionRequest {}
