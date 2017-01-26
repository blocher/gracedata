<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Scrapers\SermonScraper;
use App\Scrapers\SermonImporter;
use App\Scrapers\GracenotesScraper;
use App\Scrapers\GracenotesImporter;

class ScrapeGraceNotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:gracenotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrape Grace Notes';

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
        $scraper = new GraceNotesScraper();
        $sermons = $scraper->scrape();

        $importer = new GraceNotesImporter();
        $importer->import();
    }
}
