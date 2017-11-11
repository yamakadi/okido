<?php

namespace App\Commands;

use App\DataDirectory;
use Illuminate\Console\Scheduling\Schedule;
use Intervention\Image\ImageManager;
use LaravelZero\Framework\Commands\Command;

class NormalizeImages extends Command
{
    /**
     * @var \App\DataDirectory
     */
    private $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:normalize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepared images for the dataset';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->files = app(DataDirectory::class);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $manager = new ImageManager(['driver' => 'gd']);

        $this->comment('Normalizing images according to dataset types.');

        collect(config('sources.sprite_categories'))
            ->each(function ($category, $label) use ($manager) {

                $this->info(PHP_EOL . "Normalizing images in $category" . PHP_EOL);

                $this->makeDirectories($label);

                $files = $this->files->files("images/_base/$label");
                $this->output->progressStart(count($files) * 3);

                foreach ($files as $file) {
                    $img = $manager->make($this->files->path("images/_base/{$label}/{$this->filename($file)}.png"));

                    $img = $img->encode('jpg', 100);

                    $img->resizeCanvas(256, 256);
                    $img->save($this->files->path("images/_normalized/{$label}/{$this->filename($file)}.jpg"), 100);

                    $img->resize(128, 128);
                    $img->save($this->files->path("images/_resized/{$label}/{$this->filename($file)}.jpg"), 100);

                    $img->greyscale();
                    $img->save($this->files->path("images/_greyscale/{$label}/{$this->filename($file)}.jpg"), 100);

                    $this->output->progressAdvance(3);
                }

            });

        $this->output->progressFinish();
        $this->comment(PHP_EOL . 'Completed!' . PHP_EOL);
    }

    /**
     * @param string $path
     * @return string
     */
    private function filename(string $path): string
    {
        $pathFragments = explode('/', parse_url($path, PHP_URL_PATH));
        $filename = explode('.', end($pathFragments));

        return array_first($filename);
    }

    /**
     * @param null|string $category
     */
    private function makeDirectories(?string $category = null): void
    {
        $directories = [
            'normalized',
            'resized',
            'greyscale',
        ];

        foreach ($directories as $directory) {
            $this->files->makeDirectory("images/_{$directory}");

            if ($category) {
                $this->files->makeDirectory("images/_{$directory}/{$category}");
            }
        }
    }
}
