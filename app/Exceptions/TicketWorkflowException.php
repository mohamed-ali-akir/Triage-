<?php

namespace App\Exceptions;

use DomainException;

/**
 * Base class of the business errors raised by TicketWorkflow.
 * They are rendered as a message for the user (see bootstrap/app.php).
 */
abstract class TicketWorkflowException extends DomainException {}
