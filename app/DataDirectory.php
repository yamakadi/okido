<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DataDirectory
{
    /** @var null|string */
    public $path = '';

    /**
     * Create a new DataDirectory Instance
     *
     * @param string|null $path
     */
    public function __construct(?string $path = null)
    {
        if($path) {
            Storage::makeDirectory($path);
            $this->path = $path;
        }
    }

    /**
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return config('filesystems.disks.dataset.root') . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Determine if a file exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists($path)
    {
        return Storage::disk('dataset')->exists($this->path . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function get($path)
    {
        return Storage::disk('dataset')->get($this->path . DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Get the contents of a JSON file and returns it as a Collection.
     *
     * @param  string  $path
     * @return \Illuminate\Support\Collection
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function getCollection($path)
    {
        $contents = $this->get($path);

        return collect(json_decode($contents, true));
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  string|resource  $contents
     * @param  mixed  $options
     * @return bool
     */
    public function put($path, $contents, $options = [])
    {
        return Storage::disk('dataset')->put($this->path . DIRECTORY_SEPARATOR . $path, $contents, $options);
    }

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  \Illuminate\Support\Collection  $contents
     * @param  mixed  $options
     * @return bool
     */
    public function putCollection(string $path, Collection $contents, array $options = [])
    {
        return $this->put($path, $contents->toJson(), $options);
    }

    public function __toString()
    {
        return $this->path();
    }

    public function __call($name, $arguments)
    {
        return Storage::disk('dataset')->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        (new static)->{$name}(...$arguments);
    }

}