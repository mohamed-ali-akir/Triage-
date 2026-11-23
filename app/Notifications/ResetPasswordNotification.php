<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The e-mail that carries the link to choose a new password.
 *
 * It is sent by a queue worker, not during the request: talking to the mail
 * server takes time, and that delay would tell a stranger that the address
 * has an account. The job is encrypted, because it carries the reset token.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
