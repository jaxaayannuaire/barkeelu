<?php

namespace App\Support;

final class HomeDemoData
{
    /**
     * @return list<array{title: string, summary: string, organizer: string, image: string, collected: int, goal: int, contributions: int, verificationStatus: string, cause: string, demoLabel: string}>
     */
    public static function campaigns(): array
    {
        return [
            [
                'title' => 'Soutien pédiatrique pour le petit bambara',
                'summary' => 'Exemple fictif d’une initiative locale pour améliorer l’accueil des enfants.',
                'organizer' => 'Collectif fictif Ndar',
                'image' => 'images/placeholders/campaign-health.svg',
                'collected' => 2450000,
                'goal' => 3000000,
                'contributions' => 124,
                'verificationStatus' => 'unverified',
                'cause' => 'Santé et soins',
                'demoLabel' => 'Exemple fictif',
            ],
            [
                'title' => 'Bibliothèque de l’école de Ndande',
                'summary' => 'Exemple fictif de rénovation d’un espace de lecture pour une communauté scolaire.',
                'organizer' => 'Association fictive Jàng',
                'image' => 'images/placeholders/campaign-education.svg',
                'collected' => 1800000,
                'goal' => 2000000,
                'contributions' => 89,
                'verificationStatus' => 'pending',
                'cause' => 'Éducation',
                'demoLabel' => 'Exemple fictif',
            ],
            [
                'title' => 'Équipement en pompage solaire',
                'summary' => 'Exemple fictif d’un projet communautaire d’accès à l’eau pour le maraîchage.',
                'organizer' => 'Groupement fictif Jappo',
                'image' => 'images/placeholders/campaign-community.svg',
                'collected' => 4200000,
                'goal' => 6000000,
                'contributions' => 205,
                'verificationStatus' => 'incomplete',
                'cause' => 'Solidarité familiale',
                'demoLabel' => 'Exemple fictif',
            ],
        ];
    }
}
