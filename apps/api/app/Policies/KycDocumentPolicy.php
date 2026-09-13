<?php

namespace App\Policies;

use App\Models\KycDocument;
use App\Models\User;

class KycDocumentPolicy
{
    public function view(User $user, KycDocument $document): bool
    {
        return $user->can('compliance.manage') || $document->uploaded_by_user_id === $user->id;
    }
}
