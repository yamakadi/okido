<?php

namespace App\Commands;

use App\DataDirectory;
use App\Pokedex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class PrepareDataset extends Command
{
    /**
     * @var \App\DataDirectory
     */
    private $files;

    /**
     * @var \App\Pokedex
     */
    private $pokedex;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataset:prepare';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepares imagelist (.lst) files with labels and paths for use by MxNet';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->files = app(DataDirectory::class);
        $this->pokedex = app(Pokedex::class);

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $this->comment('Getting a list of existing images for all dataset types.' . PHP_EOL);

        $images = collect(config('sources.datasets'))
            ->mapWithKeys(function ($type) {
                $images = collect($this->files->directories("images/_$type"))
                    ->map(function ($directory) {
                        return $this->files->files($directory);
                    })->flatten(1);

                return [$type => $images];
            });

        $this->comment('Building label indexes to use in the dataset.' . PHP_EOL);
        $labelIndexes = $this->buildLabelIndexes();

        $this->comment('Mapping images and labels for each dataset type.' . PHP_EOL);
        $this->output->progressStart($images->flatten()->count());

        $list = $images->map(function (Collection $images, $type) use ($labelIndexes) {
            $this->output->progressAdvance();

            return $images->map(function ($path, $index) use ($labelIndexes) {
                $pokedexNo = (int)$this->pokedexNo($path);
                $pokemon = $this->pokedex->get($pokedexNo);

                if (!$pokemon) {
                    return null;
                }

                $this->output->progressAdvance();

                return [
                    'index' => $index,
                    'name' => (int)$labelIndexes->get('name')->get($pokemon->get('name')),
                    'number' => (int)$pokemon->get('pokedex_number'),
                    'generation' => (int)$pokemon->get('generation'),
                    'type_one' => (int)$labelIndexes->get('type')->get($pokemon->get('type1', 'missing')),
                    'type_two' => (int)$labelIndexes->get('type')->get($pokemon->get('type2', 'missing')),
                    'classification' => (int)$labelIndexes->get('classification')
                        ->get($pokemon->get('classification', 'missing')),
                    'hp' => (int)$pokemon->get('hp', -1),
                    'height' => (float)$pokemon->get('height_m', -1),
                    'weight' => (float)$pokemon->get('weight_kg', -1),
                    'legendary' => (int)$pokemon->get('is_legendary'),
                    'path' => $path,
                ];
            });
        });

        $this->output->progressFinish();

        $this->comment('Creating datasets.' . PHP_EOL);

        $list->map(function (Collection $images, $type) {
            return $images->map(function ($data) {
                return implode('	', $data);
            })->implode(PHP_EOL);
        })->each(function ($images, $type) {
            $this->files->put("dataset_{$type}.lst", $images);
        });

        $this->comment('Completed!');
    }

    private function pokedexNo(string $path): ?string
    {
        $matches = [];

        preg_match('/(\d{3})/i', $path, $matches);

        if (count($matches) === 0) {
            return '';
        }

        return $matches[0];
    }

    private function buildLabelIndexes()
    {
        return collect(config('sources.label_indexes'))->map(function ($fields) {
            return collect($fields)->map(function ($field) {

                return $this->pokedex->pluck($field);

            })->flatten()->unique()->sort()->map(function ($value) {
                if (!$value) {
                    return 'missing';
                }

                return $value;
            })->values()->mapWithKeys(function ($value, $index) {
                return [$value => (string)$index];
            });
        })->each(function (Collection $values, $label) {
            $this->files->putCollection("label_indexes/{$label}_index.json", $values);
        });
    }
}
