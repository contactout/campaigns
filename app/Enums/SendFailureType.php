<?php

namespace App\Enums;

/**
 * Why a campaign email could not be sent, which decides what happens next.
 */
enum SendFailureType: string
{
    /**
     * The provider rejected this recipient; only this email is affected.
     */
    case Recipient = 'recipient';

    /**
     * A temporary problem (throttling, timeouts, provider outages); retry later.
     */
    case Transient = 'transient';

    /**
     * The connection's credentials or configuration are broken.
     */
    case Connection = 'connection';
}
