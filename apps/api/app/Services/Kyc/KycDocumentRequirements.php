<?php

namespace App\Services\Kyc;

use App\Enums\BeneficiaryType;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycWorkflowErrorCode;
use App\Exceptions\KycWorkflowException;
use App\Models\KycProfile;

class KycDocumentRequirements
{
    public function assertMinimum(KycProfile $profile): void
    {
        $this->assertDocuments($profile, [KycDocumentStatus::UPLOADED, KycDocumentStatus::ACCEPTED]);
    }

    public function assertAccepted(KycProfile $profile): void
    {
        $this->assertDocuments($profile, KycDocumentStatus::ACCEPTED);
    }

    /** @param list<KycDocumentStatus>|KycDocumentStatus $statuses */
    private function assertDocuments(KycProfile $profile, array|KycDocumentStatus $statuses): void
    {
        $requiredGroups = $this->requiredGroups($profile);
        $statuses = is_array($statuses) ? $statuses : [$statuses];

        foreach ($requiredGroups as $group) {
            $types = array_map(static fn (KycDocumentType $type): string => $type->value, $group);
            $statusValues = array_map(static fn (KycDocumentStatus $status): string => $status->value, $statuses);
            $documents = $profile->documents()->whereIn('type', $types);

            if (! $documents->clone()->whereIn('status', $statusValues)->exists()) {
                throw new KycWorkflowException(
                    $statuses === [KycDocumentStatus::ACCEPTED]
                        ? KycWorkflowErrorCode::REQUIRED_DOCUMENT_NOT_ACCEPTED
                        : KycWorkflowErrorCode::REQUIRED_DOCUMENT_MISSING,
                    'Document KYC requis absent ou dans un état non conforme.',
                );
            }

            if (! $documents->clone()
                ->whereIn('status', $statusValues)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists()) {
                throw new KycWorkflowException(KycWorkflowErrorCode::REQUIRED_DOCUMENT_EXPIRED, 'Document KYC requis expiré.');
            }
        }
    }

    /** @return list<list<KycDocumentType>> */
    private function requiredGroups(KycProfile $profile): array
    {
        if ($profile->user_id !== null) {
            return [[KycDocumentType::IDENTITY_DOCUMENT, KycDocumentType::PASSPORT]];
        }

        if ($profile->organization_id !== null) {
            return [[KycDocumentType::REGISTRATION_DOCUMENT]];
        }

        $beneficiaryType = $profile->beneficiary?->type;

        return match ($beneficiaryType) {
            BeneficiaryType::INDIVIDUAL => [[KycDocumentType::IDENTITY_DOCUMENT, KycDocumentType::PASSPORT]],
            BeneficiaryType::ORGANIZATION => [[KycDocumentType::REGISTRATION_DOCUMENT]],
            BeneficiaryType::COMMUNITY, BeneficiaryType::OTHER, null => throw new KycWorkflowException(
                KycWorkflowErrorCode::SUBMISSION_REQUIREMENTS_UNDEFINED,
                'Dossier KYC non défini pour ce sujet.',
            ),
        };
    }
}
