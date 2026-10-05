<?php
/**
 * ONE VISION COMMUNITY — CONFIGURATION DU QUESTIONNAIRE D'ACCUEIL
 * 
 * Ce fichier centralise toutes les étapes et les questions du parcours d'onboarding.
 * Modifiable facilement sans casser les réponses existantes en base de données.
 */

if (!defined('ONE_VISION_APP')) {
    define('ONE_VISION_APP', true);
}

/**
 * Retourne la structure complète des étapes et des questions.
 */
function get_questionnaire_definition(): array {
    return [
        'welcome' => [
            'title' => "Bienvenue chez One Vision Community",
            'subtitle' => "Ici, chacun avance mieux entouré. Répondez à quelques questions rapides, juste des cases à cocher (environ 2 minutes), pour que nous puissions vous accueillir comme il se doit et vous connecter aux bonnes personnes.",
            'button_text' => "Commencer",
            'photo' => "img/welcome-photo.jpg" // Chemin de la photo d'accueil (remplaçable directement par votre propre photo)
        ],
        'steps' => [
            1 => [
                'number' => 1,
                'total' => 5,
                'title' => "Faisons connaissance",
                'description' => "Quelques informations pour mieux vous situer dans la communauté."
            ],
            2 => [
                'number' => 2,
                'total' => 5,
                'title' => "Ce qui vous anime",
                'description' => "Comprendre vos motivations profondes pour vous proposer le meilleur accompagnement."
            ],
            3 => [
                'number' => 3,
                'total' => 5,
                'title' => "Vos ambitions",
                'description' => "Vos objectifs à court et moyen terme pour grandir ensemble."
            ],
            4 => [
                'number' => 4,
                'total' => 5,
                'title' => "Votre place dans la communauté",
                'description' => "Comment vous souhaitez participer et enrichir le collectif."
            ],
            5 => [
                'number' => 5,
                'total' => 5,
                'title' => "Pour bien vous accueillir",
                'description' => "Derniers détails pour personnaliser vos lives, masterminds et alertes."
            ]
        ],
        'questions' => [
            // ÉTAPE 1 : FAISONS CONNAISSANCE
            'q1' => [
                'id' => 'q1',
                'step' => 1,
                'index' => 1,
                'type' => 'unique',
                'question' => "Quelle est votre tranche d'âge ?",
                'helper' => "Une seule réponse possible",
                'options' => [
                    "18-24 ans",
                    "25-34 ans",
                    "35-44 ans",
                    "45-54 ans",
                    "55 ans et plus",
                    "Moins de 18 ans"
                ],
                'is_minor_trigger' => "Moins de 18 ans"
            ],
            'q2' => [
                'id' => 'q2',
                'step' => 1,
                'index' => 2,
                'type' => 'unique',
                'question' => "Où en êtes-vous aujourd'hui ?",
                'helper' => "Une seule réponse possible",
                'options' => [
                    "J'ai une idée et je cherche à la concrétiser",
                    "Je me lance en ce moment",
                    "J'ai déjà une activité et je veux la faire grandir",
                    "Je veux développer mes compétences et ma carrière",
                    "Je cherche ma voie"
                ]
            ],
            'q3' => [
                'id' => 'q3',
                'step' => 1,
                'index' => 3,
                'type' => 'multiple',
                'question' => "Dans quels domaines évoluez-vous ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Commerce et vente",
                    "Conseil, coaching, services",
                    "Digital et technologie",
                    "Agriculture et agro-alimentaire",
                    "Éducation et formation",
                    "Créativité et communication",
                    "Autre"
                ]
            ],

            // ÉTAPE 2 : CE QUI VOUS ANIME
            'q4' => [
                'id' => 'q4',
                'step' => 2,
                'index' => 4,
                'type' => 'multiple',
                'question' => "Qu'est-ce qui vous a poussé à rejoindre une communauté comme la nôtre ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Apprendre auprès de personnes qui ont de l'expérience",
                    "Rencontrer des gens qui me ressemblent et me comprennent",
                    "Ne plus avancer seul",
                    "Faire connaître mes compétences",
                    "Trouver des partenaires pour mes projets",
                    "Rester motivé et régulier"
                ]
            ],
            'q5' => [
                'id' => 'q5',
                'step' => 2,
                'index' => 5,
                'type' => 'unique',
                'question' => "Qu'aimeriez-vous ressentir en faisant partie de One Vision ?",
                'helper' => "Une seule réponse possible",
                'options' => [
                    "Entouré et soutenu",
                    "Inspiré chaque jour",
                    "Fier de mes progrès",
                    "Utile aux autres"
                ]
            ],

            // ÉTAPE 3 : VOS AMBITIONS
            'q6' => [
                'id' => 'q6',
                'step' => 3,
                'index' => 6,
                'type' => 'multiple',
                'question' => "Où voulez-vous être dans 6 mois ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Avoir lancé mon projet",
                    "Avoir de nouveaux clients",
                    "Avoir un réseau solide autour de moi",
                    "Avoir gagné en confiance et en visibilité",
                    "Maîtriser de nouvelles compétences",
                    "Être reconnu pour mon expertise"
                ]
            ],
            'q7' => [
                'id' => 'q7',
                'step' => 3,
                'index' => 7,
                'type' => 'multiple',
                'question' => "Qu'est-ce qui pourrait le mieux vous aider à y arriver ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Des conseils d'experts",
                    "Des échanges avec d'autres entrepreneurs",
                    "Un groupe qui me motive",
                    "Des retours sur mes idées",
                    "Des occasions de me faire connaître"
                ]
            ],

            // ÉTAPE 4 : VOTRE PLACE DANS LA COMMUNAUTÉ
            'q8' => [
                'id' => 'q8',
                'step' => 4,
                'index' => 8,
                'type' => 'multiple',
                'question' => "Comment aimeriez-vous participer ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Apprendre en suivant les lives des experts",
                    "Échanger et poser mes questions au quotidien",
                    "Rejoindre des masterminds pour avancer en groupe",
                    "Me connecter à d'autres membres pour collaborer",
                    "Partager mon expérience avec les autres",
                    "Animer mes propres lives ou masterminds et mettre mon expertise en avant"
                ],
                'animateur_trigger' => "Animer mes propres lives ou masterminds et mettre mon expertise en avant"
            ],
            'q9' => [
                'id' => 'q9',
                'step' => 4,
                'index' => 9,
                'type' => 'multiple',
                'question' => "Qu'aimeriez-vous partager avec la communauté un jour ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Mon parcours",
                    "Mes compétences",
                    "Mes réussites et mes erreurs",
                    "Mes conseils",
                    "Je veux d'abord apprendre, je verrai ensuite"
                ]
            ],

            // ÉTAPE 5 : POUR BIEN VOUS ACCUEILLIR
            'q10' => [
                'id' => 'q10',
                'step' => 5,
                'index' => 10,
                'type' => 'multiple',
                'question' => "Quels sujets souhaitez-vous voir dans nos lives et masterminds ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Lancer et structurer son projet",
                    "Trouver des clients et vendre",
                    "Gérer ses finances",
                    "Développer son mindset",
                    "Construire son réseau",
                    "Utiliser les outils digitaux",
                    "Leadership et prise de parole"
                ]
            ],
            'q11' => [
                'id' => 'q11',
                'step' => 5,
                'index' => 11,
                'type' => 'multiple',
                'question' => "Comment préférez-vous apprendre ?",
                'helper' => "Plusieurs réponses possibles",
                'options' => [
                    "Lives en direct",
                    "Replays à mon rythme",
                    "Échanges écrits",
                    "Petits groupes (masterminds)",
                    "Parcours avec certification"
                ]
            ],
            'q12' => [
                'id' => 'q12',
                'step' => 5,
                'index' => 12,
                'type' => 'unique',
                'question' => "Comment avez-vous connu One Vision ?",
                'helper' => "Une seule réponse possible",
                'options' => [
                    "Réseaux sociaux",
                    "Recommandation d'un proche",
                    "Un live ou un événement",
                    "Recherche sur internet",
                    "Autre"
                ]
            ],
            'q13' => [
                'id' => 'q13',
                'step' => 5,
                'index' => 13,
                'type' => 'unique',
                'question' => "Voulez-vous être prévenu des prochains lives ?",
                'helper' => "Une seule réponse possible",
                'options' => [
                    "Oui, par email",
                    "Oui, par WhatsApp",
                    "Pas pour l'instant"
                ],
                'consent_section' => [
                    'label' => "J'accepte de recevoir par email des informations et des offres de One Vision. Je peux me désabonner à tout moment.",
                    'notice' => "Vos réponses servent uniquement à personnaliser votre expérience au sein de One Vision."
                ]
            ]
        ],
        'completion_screen' => [
            'title' => "Merci, votre place vous attend !",
            'message' => "Grâce à vos réponses, nous savons mieux ce que vous cherchez. Il ne reste qu'une étape pour rejoindre la communauté et commencer à avancer avec nous.",
            'button_text' => "Choisir mon abonnement",
            'button_url' => "choisir-abonnement.php"
        ],
        'minor_screen' => [
            'title' => "Merci de votre intérêt pour One Vision",
            'message' => "One Vision Community est une communauté d'entraide et d'entrepreneuriat réservée aux personnes majeures (+18 ans). Nous vous remercions chaleureusement pour votre démarche et vous souhaitons plein de succès dans vos études et vos futurs projets. Nous serons ravis de vous accueillir dès votre majorité !",
            'button_text' => "Retourner à l'accueil",
            'button_url' => "index.php"
        ]
    ];
}

/**
 * Récupère une question par son identifiant (ex: 'q1').
 */
function get_question_by_id(string $qId): ?array {
    $def = get_questionnaire_definition();
    return $def['questions'][$qId] ?? null;
}

/**
 * Récupère une question par son index numérique (1 à 13).
 */
function get_question_by_index(int $index): ?array {
    $def = get_questionnaire_definition();
    foreach ($def['questions'] as $q) {
        if ((int)$q['index'] === $index) {
            return $q;
        }
    }
    return null;
}

/**
 * Récupère le nombre total de questions.
 */
function get_total_questions_count(): int {
    $def = get_questionnaire_definition();
    return count($def['questions']);
}

/**
 * Valide les réponses soumises pour une question donnée.
 * Retourne ['valid' => bool, 'error' => ?string, 'sanitized' => array].
 */
function validate_question_submission(string $qId, $rawAnswers): array {
    $question = get_question_by_id($qId);
    if (!$question) {
        return ['valid' => false, 'error' => "Question inconnue.", 'sanitized' => []];
    }

    $answers = is_array($rawAnswers) ? $rawAnswers : [$rawAnswers];
    $sanitized = [];

    foreach ($answers as $a) {
        $trimmed = trim((string)$a);
        if ($trimmed !== '') {
            $sanitized[] = $trimmed;
        }
    }

    if (empty($sanitized)) {
        return ['valid' => false, 'error' => "Veuillez sélectionner au moins une réponse.", 'sanitized' => []];
    }

    // Question à réponse unique
    if ($question['type'] === 'unique') {
        if (count($sanitized) > 1) {
            return ['valid' => false, 'error' => "Une seule réponse est autorisée pour cette question.", 'sanitized' => []];
        }
        $val = $sanitized[0];
        if (!in_array($val, $question['options'], true)) {
            return ['valid' => false, 'error' => "L'option sélectionnée n'est pas valide.", 'sanitized' => []];
        }
        return ['valid' => true, 'error' => null, 'sanitized' => [$val]];
    }

    // Question à réponses multiples
    if ($question['type'] === 'multiple') {
        $validList = [];
        foreach ($sanitized as $val) {
            if (in_array($val, $question['options'], true)) {
                $validList[] = $val;
            }
        }
        $validList = array_unique($validList);
        if (empty($validList)) {
            return ['valid' => false, 'error' => "Veuillez sélectionner une option valide.", 'sanitized' => []];
        }
        return ['valid' => true, 'error' => null, 'sanitized' => array_values($validList)];
    }

    return ['valid' => true, 'error' => null, 'sanitized' => $sanitized];
}
