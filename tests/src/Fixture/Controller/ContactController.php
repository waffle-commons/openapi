<?php

declare(strict_types=1);

namespace WaffleTests\Commons\OpenApi\Fixture\Controller;

use WaffleTests\Commons\OpenApi\Fixture\Contact;
use WaffleTests\Commons\OpenApi\Fixture\Envelope;

final class ContactController
{
    public function save(Contact $contact): Contact
    {
        return $contact;
    }

    public function wrap(Envelope $envelope): Envelope
    {
        return $envelope;
    }
}
