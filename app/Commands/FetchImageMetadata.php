<?php

namespace App\Commands;

use App\DataDirectory;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class FetchImageMetadata extends Command
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
    protected $signature = 'images:metadata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get image metadata and urls from bulbapedia archives for the dataset';

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
            ->map(function ($category, $label) {
                return $this->listOfImages($category, $label);
            })
            ->map(function (Collection $list, string $label) {
                return $this->imageUrls($list, $label);
            });

        $this->info(PHP_EOL);
        $this->info('Completed collecting metadata for each image in all categories.');
    }

    /**
     * @param string $category
     * @param string $label
     * @return \Illuminate\Support\Collection
     */
    private function listOfImages(string $category, string $label): Collection
    {
        $this->info('Getting list of images for: ' . $category);

        if ($this->files->exists("lists/$label.json")) {
            return $this->files->getCollection("lists/$label.json");
        }

        $list = $this->getPartialListOfImages($category, collect())
            ->mapWithKeys(function ($data) {
                return [$data['pageid'] => $data];
            });

        $this->files->putCollection("lists/$label.json", $list);

        return $list;
    }

    /**
     * @param string                         $category
     * @param \Illuminate\Support\Collection $list
     * @param null|string                    $continue
     * @return \Illuminate\Support\Collection
     */
    private function getPartialListOfImages(string $category, Collection $list, ?string $continue = ''): Collection
    {
        $response = $this->http->get("https://archives.bulbagarden.net/w/api.php?action=query&list=categorymembers&cmtitle={$category}&format=json&cmlimit=500&cmcontinue={$continue}");
        $data = json_decode($response->getBody()->getContents(), true);

        if (array_key_exists('query', $data) && array_key_exists('categorymembers', $data['query'])) {
            $list = collect($data['query']['categorymembers'])->merge($list);
        }

        if ($this->canContinue($data)) {
            return $this->getPartialListOfImages($category, $list, $data['continue']['cmcontinue']);
        }

        return $list;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function canContinue(array $data): bool
    {
        return array_key_exists('continue', $data)
            && array_key_exists('cmcontinue', $data['continue'])
            && $data['continue']['cmcontinue'] !== '';
    }

    /**
     * @param \Illuminate\Support\Collection $list
     * @param string                         $label
     * @return \Illuminate\Support\Collection
     */
    private function imageUrls(Collection $list, string $label): Collection
    {
        if ($this->files->exists("uris/$label.json")) {
            return $this->files->getCollection("uris/$label.json");
        }

        $urls = $list->chunk(50)->map(function (Collection $chunk, $index) use ($label) {
            return $this->fetchImageMetadata($chunk, $index, $label);
        })->flatten(1)->map(function ($metadata) {
            return $this->fetchImageUri($metadata);
        })->filter();

        $this->files->putCollection("uris/$label.json", $urls);

        return $urls;
    }

    /**
     * @param \Illuminate\Support\Collection $chunk
     * @param                                $index
     * @param string                         $label
     * @return array
     */
    private function fetchImageMetadata(Collection $chunk, $index, string $label): array
    {
        if ($this->files->exists("uris/chunks/$label-$index.json")) {
            return $this->files->getCollection("uris/chunks/$label-$index.json");
        }

        $imageTitles = $chunk
            ->map(function ($data) {
                return $data['title'];
            })
            ->flatten(1)
            ->implode('|');

        $response = $this->http->get("https://archives.bulbagarden.net/w/api.php?action=query&titles={$imageTitles}&prop=imageinfo&&iiprop=url&format=json");

        $data = json_decode($response->getBody()->getContents(), true);

        if (array_key_exists('query', $data) && array_key_exists('pages', $data['query'])) {
            $this->files->put("uris/chunks/$label-$index.json", json_encode($data['query']['pages'], JSON_PRETTY_PRINT));
            return $data['query']['pages'];
        }

        return [];
    }

    /**
     * @param array $metadata
     * @return array|null
     */
    private function fetchImageUri(array $metadata): ?array
    {
        if (array_key_exists('imageinfo', $metadata) && array_key_exists('url', $metadata['imageinfo'][0])) {
            return [
                'title' => $metadata['title'],
                'url' => $metadata['imageinfo'][0]['url'],
            ];
        } else {
            return null;
        }
    }
}
