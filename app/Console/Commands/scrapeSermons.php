<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Scrapers\SermonScraper;
use App\Scrapers\SermonImporter;

class ScrapeSermons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:sermons';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Sermons';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $scraper = new SermonScraper();
        $sermons = $scraper->scrape();
        echo 'Beginning import' . PHP_EOL;
        $importer = new SermonImporter();
        $importer->import();
    }
}
