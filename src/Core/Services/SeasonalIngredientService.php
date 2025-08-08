<?php

declare(strict_types=1);

namespace PrepMeal\Core\Services;

class SeasonalIngredientService
{
    private array $seasonalIngredients = [];

    public function __construct()
    {
        $this->loadSeasonalIngredients();
    }

    private function loadSeasonalIngredients(): void
    {
        $this->seasonalIngredients = [
            // Printemps (mars, avril, mai)
            'printemps' => [
                'asperges' => ['fr' => 'Asperges', 'en' => 'Asparagus', 'es' => 'Espárragos', 'de' => 'Spargel'],
                'petits_pois' => ['fr' => 'Petits pois', 'en' => 'Peas', 'es' => 'Guisantes', 'de' => 'Erbsen'],
                'radis' => ['fr' => 'Radis', 'en' => 'Radish', 'es' => 'Rábano', 'de' => 'Rettich'],
                'salade' => ['fr' => 'Salade', 'en' => 'Lettuce', 'es' => 'Lechuga', 'de' => 'Salat'],
                'epinards' => ['fr' => 'Épinards', 'en' => 'Spinach', 'es' => 'Espinacas', 'de' => 'Spinat'],
                'fraise' => ['fr' => 'Fraise', 'en' => 'Strawberry', 'es' => 'Fresa', 'de' => 'Erdbeere'],
                'rhubarbe' => ['fr' => 'Rhubarbe', 'en' => 'Rhubarb', 'es' => 'Ruibarbo', 'de' => 'Rhabarber'],
                'cerise' => ['fr' => 'Cerise', 'en' => 'Cherry', 'es' => 'Cereza', 'de' => 'Kirsche']
            ],
            
            // Été (juin, juillet, août)
            'ete' => [
                'tomate' => ['fr' => 'Tomate', 'en' => 'Tomato', 'es' => 'Tomate', 'de' => 'Tomate'],
                'courgette' => ['fr' => 'Courgette', 'en' => 'Zucchini', 'es' => 'Calabacín', 'de' => 'Zucchini'],
                'aubergine' => ['fr' => 'Aubergine', 'en' => 'Eggplant', 'es' => 'Berenjena', 'de' => 'Aubergine'],
                'poivron' => ['fr' => 'Poivron', 'en' => 'Bell pepper', 'es' => 'Pimiento', 'de' => 'Paprika'],
                'concombre' => ['fr' => 'Concombre', 'en' => 'Cucumber', 'es' => 'Pepino', 'de' => 'Gurke'],
                'haricot_vert' => ['fr' => 'Haricot vert', 'en' => 'Green beans', 'es' => 'Judías verdes', 'de' => 'Grüne Bohnen'],
                'melon' => ['fr' => 'Melon', 'en' => 'Melon', 'es' => 'Melón', 'de' => 'Melone'],
                'peche' => ['fr' => 'Pêche', 'en' => 'Peach', 'es' => 'Melocotón', 'de' => 'Pfirsich'],
                'abricot' => ['fr' => 'Abricot', 'en' => 'Apricot', 'es' => 'Albaricoque', 'de' => 'Aprikose'],
                'framboise' => ['fr' => 'Framboise', 'en' => 'Raspberry', 'es' => 'Frambuesa', 'de' => 'Himbeere'],
                'myrtille' => ['fr' => 'Myrtille', 'en' => 'Blueberry', 'es' => 'Arándano', 'de' => 'Heidelbeere'],
                'figue' => ['fr' => 'Figue', 'en' => 'Fig', 'es' => 'Higo', 'de' => 'Feige']
            ],
            
            // Automne (septembre, octobre, novembre)
            'automne' => [
                'potiron' => ['fr' => 'Potiron', 'en' => 'Pumpkin', 'es' => 'Calabaza', 'de' => 'Kürbis'],
                'champignon' => ['fr' => 'Champignon', 'en' => 'Mushroom', 'es' => 'Champiñón', 'de' => 'Pilz'],
                'chou' => ['fr' => 'Chou', 'en' => 'Cabbage', 'es' => 'Repollo', 'de' => 'Kohl'],
                'brocoli' => ['fr' => 'Brocoli', 'en' => 'Broccoli', 'es' => 'Brócoli', 'de' => 'Brokkoli'],
                'chou_fleur' => ['fr' => 'Chou-fleur', 'en' => 'Cauliflower', 'es' => 'Coliflor', 'de' => 'Blumenkohl'],
                'carotte' => ['fr' => 'Carotte', 'en' => 'Carrot', 'es' => 'Zanahoria', 'de' => 'Karotte'],
                'pomme' => ['fr' => 'Pomme', 'en' => 'Apple', 'es' => 'Manzana', 'de' => 'Apfel'],
                'poire' => ['fr' => 'Poire', 'en' => 'Pear', 'es' => 'Pera', 'de' => 'Birne'],
                'raisin' => ['fr' => 'Raisin', 'en' => 'Grape', 'es' => 'Uva', 'de' => 'Traube'],
                'prune' => ['fr' => 'Prune', 'en' => 'Plum', 'es' => 'Ciruela', 'de' => 'Pflaume'],
                'noix' => ['fr' => 'Noix', 'en' => 'Walnut', 'es' => 'Nuez', 'de' => 'Walnuss'],
                'chataigne' => ['fr' => 'Châtaigne', 'en' => 'Chestnut', 'es' => 'Castaña', 'de' => 'Kastanie']
            ],
            
            // Hiver (décembre, janvier, février)
            'hiver' => [
                'endive' => ['fr' => 'Endive', 'en' => 'Endive', 'es' => 'Endibia', 'de' => 'Endivie'],
                'mache' => ['fr' => 'Mâche', 'en' => 'Lamb\'s lettuce', 'es' => 'Canónigo', 'de' => 'Feldsalat'],
                'cresson' => ['fr' => 'Cresson', 'en' => 'Watercress', 'es' => 'Berro', 'de' => 'Brunnenkresse'],
                'poireau' => ['fr' => 'Poireau', 'en' => 'Leek', 'es' => 'Puerro', 'de' => 'Lauch'],
                'celeri_rave' => ['fr' => 'Céleri-rave', 'en' => 'Celeriac', 'es' => 'Apio-nabo', 'de' => 'Sellerie'],
                'topinambour' => ['fr' => 'Topinambour', 'en' => 'Jerusalem artichoke', 'es' => 'Aguaturma', 'de' => 'Topinambur'],
                'citron' => ['fr' => 'Citron', 'en' => 'Lemon', 'es' => 'Limón', 'de' => 'Zitrone'],
                'orange' => ['fr' => 'Orange', 'en' => 'Orange', 'es' => 'Naranja', 'de' => 'Orange'],
                'clementine' => ['fr' => 'Clémentine', 'en' => 'Clementine', 'es' => 'Clementina', 'de' => 'Clementine'],
                'kiwi' => ['fr' => 'Kiwi', 'en' => 'Kiwi', 'es' => 'Kiwi', 'de' => 'Kiwi'],
                'pomelo' => ['fr' => 'Pomelo', 'en' => 'Grapefruit', 'es' => 'Pomelo', 'de' => 'Pampelmuse']
            ]
        ];
    }

    public function getSeasonalIngredients(string $season): array
    {
        return $this->seasonalIngredients[$season] ?? [];
    }

    public function getAllSeasonalIngredients(): array
    {
        return $this->seasonalIngredients;
    }

    public function getCurrentSeason(): string
    {
        $month = (int) date('n');
        
        if ($month >= 3 && $month <= 5) {
            return 'printemps';
        } elseif ($month >= 6 && $month <= 8) {
            return 'ete';
        } elseif ($month >= 9 && $month <= 11) {
            return 'automne';
        } else {
            return 'hiver';
        }
    }

    public function getCurrentSeasonalIngredients(): array
    {
        $currentSeason = $this->getCurrentSeason();
        return $this->getSeasonalIngredients($currentSeason);
    }

    public function isIngredientSeasonal(string $ingredientName, string $season): bool
    {
        $seasonalIngredients = $this->getSeasonalIngredients($season);
        
        foreach ($seasonalIngredients as $key => $names) {
            if (strtolower($ingredientName) === strtolower($key) || 
                in_array(strtolower($ingredientName), array_map('strtolower', $names))) {
                return true;
            }
        }
        
        return false;
    }

    public function getIngredientName(string $ingredientKey, string $locale = 'fr'): string
    {
        foreach ($this->seasonalIngredients as $season => $ingredients) {
            if (isset($ingredients[$ingredientKey])) {
                return $ingredients[$ingredientKey][$locale] ?? $ingredients[$ingredientKey]['fr'] ?? $ingredientKey;
            }
        }
        
        return $ingredientKey;
    }

    public function getSeasonsForIngredient(string $ingredientName): array
    {
        $seasons = [];
        
        foreach ($this->seasonalIngredients as $season => $ingredients) {
            foreach ($ingredients as $key => $names) {
                if (strtolower($ingredientName) === strtolower($key) || 
                    in_array(strtolower($ingredientName), array_map('strtolower', $names))) {
                    $seasons[] = $season;
                    break;
                }
            }
        }
        
        return $seasons;
    }

    public function getRecommendedIngredients(string $season, int $limit = 10): array
    {
        $seasonalIngredients = $this->getSeasonalIngredients($season);
        $recommended = [];
        
        foreach ($seasonalIngredients as $key => $names) {
            $recommended[] = [
                'key' => $key,
                'names' => $names,
                'season' => $season
            ];
            
            if (count($recommended) >= $limit) {
                break;
            }
        }
        
        return $recommended;
    }

    public function getNutritionalBenefits(string $ingredientKey): array
    {
        $benefits = [
            'asperges' => ['vitamines' => ['A', 'C', 'K'], 'mineraux' => ['folate', 'fer']],
            'tomate' => ['vitamines' => ['A', 'C', 'K'], 'antioxydants' => ['lycopene']],
            'brocoli' => ['vitamines' => ['A', 'C', 'K'], 'fibres' => true],
            'pomme' => ['vitamines' => ['C'], 'fibres' => true, 'antioxydants' => ['quercetine']],
            'citron' => ['vitamines' => ['C'], 'antioxydants' => ['flavonoides']],
            'carotte' => ['vitamines' => ['A'], 'antioxydants' => ['beta-carotene']],
            'epinards' => ['vitamines' => ['A', 'C', 'K'], 'mineraux' => ['fer', 'calcium']],
            'fraise' => ['vitamines' => ['C'], 'antioxydants' => ['anthocyanes']],
            'potiron' => ['vitamines' => ['A', 'C'], 'fibres' => true],
            'chou' => ['vitamines' => ['C', 'K'], 'fibres' => true]
        ];
        
        return $benefits[$ingredientKey] ?? [];
    }

    public function getSeasonalRecipeSuggestions(string $season): array
    {
        $suggestions = [
            'printemps' => [
                'salade_asperges' => 'Salade d\'asperges vinaigrette',
                'soupe_pois' => 'Soupe de petits pois à la menthe',
                'tarte_fraises' => 'Tarte aux fraises'
            ],
            'ete' => [
                'ratatouille' => 'Ratatouille provençale',
                'salade_tomates' => 'Salade de tomates mozzarella',
                'tarte_peches' => 'Tarte aux pêches'
            ],
            'automne' => [
                'soupe_potiron' => 'Soupe de potiron',
                'gratin_chou' => 'Gratin de chou-fleur',
                'tarte_pommes' => 'Tarte aux pommes'
            ],
            'hiver' => [
                'soupe_poireaux' => 'Soupe de poireaux',
                'salade_endives' => 'Salade d\'endives aux noix',
                'tarte_citrons' => 'Tarte au citron'
            ]
        ];
        
        return $suggestions[$season] ?? [];
    }
}
