<?php

namespace App\Commands;

use App\DataDirectory;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class DownloadImages extends Command
{
    /**
     * @var \GuzzleHttp\Client
     */
    public $http;

    /**
     * @var \App\DataDirectory
     */
    private $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:download
                           {--category= : If set, only this category will be downloaded}
                           {--chunks=10 : The amount of chunks the downloads should be split into}
                           {--offset=0 : The offset where the downloads should start}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download images from bulbapedia archives for the dataset';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->files = app(DataDirectory::class);
        $this->http = new Client();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        collect(config('sources.sprite_categories'))
            ->reject(function ($category) {
                return $this->onlyRequestedCategories($category);
            })
            ->each(function ($category, $label) {
                $this->hasNecessaryMetadata($label);
            })
            ->each(function ($category, $label) {
                $this->downloadImages($category, $label);
            });
    }

    private function onlyRequestedCategories($category)
    {
        if ($this->option('category')) {
            return $category !== $this->option('category');
        }

        return false;
    }

    private function hasNecessaryMetadata($label): void
    {
        if (!$this->files->exists("lists/$label.json") || !$this->files->exists("uris/$label.json")) {
            throw new \Exception('You must run `php okido dataset:images:metadata` before continuing with the download');
        }
    }

    private function downloadImages(string $category, string $label)
    {
        $this->info('Downloading images for: ' . $category);

        $urls = $this->files->getCollection("uris/$label.json");

        $this->output->progressStart($urls->count());

        $urls->split($this->option('chunks'))
            ->each(function(Collection $chunk, $index) use ($label) {
                if($index < $this->option('offset')) {
                    $this->output->progressAdvance($chunk->count());
                    return;
                }

                $chunk->each(function($image) use ($label) {
                    $this->downloadImage($image['url'], $label);
                });

            });

        $this->output->progressFinish();
    }

    /**
     * @param string $url
     * @param string $label
     */
    private function downloadImage(string $url, string $label): void
    {
        $filename = $this->filename($url);

        if ($this->files->exists("images/_base/$label/$filename")) {
            $this->output->progressAdvance();
            return;
        }

        try {
            $response = $this->http->get($url);
            $this->files->put("images/_base/$label/$filename", $response->getBody()->getContents());
        } catch (\Exception $e) {
            // This simply means the link was a dud, so no need to do anything...
        } finally {
            $this->output->progressAdvance();
        }
    }

    /**
     * @param string $url
     * @return string
     */
    private function filename(string $url): string
    {
        $pathFragments = explode('/', parse_url($url, PHP_URL_PATH));

        return end($pathFragments);
    }
}
