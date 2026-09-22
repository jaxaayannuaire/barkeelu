<?php

namespace App\Policies;

use App\Models\KycDocument;
use App\Models\User;

class KycDocumentPolicy
{
    public function view(User $user, KycDocument $document): bool
    {
        return app(KycProfilePolicy::class)->view($user, $document->profile);
    }
}
