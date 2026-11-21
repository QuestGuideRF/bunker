<?php
class CardGenerator
{
    private string $dataPath;
    private array $professions = [];
    private array $health = [];
    private array $phobias = [];
    private array $baggage = [];
    private array $superpowers = [];
    private array $specials = [];
    private array $extras = [];
    public function __construct(string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? (__DIR__ . '/../data');
        $this->load();
    }
    private function load(): void
    {
        $files = ['professions', 'health', 'phobias', 'baggage', 'superpowers', 'specials'];
        foreach ($files as $f) {
            $path = $this->dataPath . '/' . $f . '.json';
            if (is_file($path)) {
                $json = json_decode(file_get_contents($path), true);
                $this->{$f} = is_array($json) ? $json : [];
            }
        }
        $path = $this->dataPath . '/extras.json';
        if (is_file($path)) {
            $this->extras = json_decode(file_get_contents($path), true) ?: [];
        }
    }
    private function pick(array $arr): string
    {
        if (empty($arr)) return '';
        return $arr[array_rand($arr)];
    }
    private function bio(): array
    {
        $genders = ['мужской', 'женский'];
        $gender = $genders[array_rand($genders)];
        $age = rand(18, 72);
        $fertility = $gender === 'женский'
            ? (['фертильна', 'нефертильна', 'менопауза'][array_rand(['фертильна', 'нефертильна', 'менопауза'])])
            : (rand(0, 100) < 15 ? 'бесплоден' : 'фертилен');
        $children = rand(0, 4);
        return [
            'age' => $age,
            'gender' => $gender,
            'fertility' => $fertility,
            'children' => $children,
        ];
    }
    private function extra(): array
    {
        $out = [];
        foreach (['skills', 'traits', 'hobbies', 'experiences'] as $key) {
            $arr = $this->extras[$key] ?? [];
            if (!empty($arr)) {
                $out[$key] = $this->pick($arr);
            }
        }
        return $out;
    }
    public function generateOne(bool $superpowersOn): array
    {
        $profession = $this->pick($this->professions);
        $health = $this->pick($this->health);
        $phobia = $this->pick($this->phobias);
        $baggage = $this->pick($this->baggage);
        $bio = $this->bio();
        $extra = $this->extra();
        $superpower = null;
        if ($superpowersOn && !empty($this->superpowers) && rand(1, 100) <= 25) {
            $superpower = $this->pick($this->superpowers);
        }
        $special = null;
        if (!empty($this->specials) && rand(1, 100) <= 30) {
            $special = $this->pick($this->specials);
        }
        return [
            'profession' => $profession,
            'health' => $health,
            'phobia' => $phobia,
            'bio' => $bio,
            'extra' => $extra,
            'baggage' => $baggage,
            'superpower' => $superpower,
            'special_condition' => $special,
        ];
    }
}