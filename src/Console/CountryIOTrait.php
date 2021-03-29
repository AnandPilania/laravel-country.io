<?php

namespace KSPEdu\CountryIO\Console;

use Illuminate\Support\Str;

trait CountryIOTrait
{
    protected $class = \App\Models\CountryIO::class;

    protected $url = 'http://country.io/';
    protected $names = 'names.json';

    protected $flags_path = 'http://country.io/static/flags/';

    protected $xPath_name = "//a[@class='cc-country-toplink']";
    protected $xPath_core = "//div[@class='col-sm-10 cc-home-block']/div/div/table/tr/td";
    protected $xPath_neighbours = "//div[@class='cc-neighbour']/p/span/a";
    protected $xPath_history = "//h2[@id='history']/following-sibling::p";
    protected $xPath_currency_population = "//div[@class='col-sm-10 cc-home-block']/div/div/table/tr/td";
    protected $xPath_geography = "//h2[@id='geography']/following-sibling::div/table/tr/td";
    protected $xPath_demographics = "//h2[@id='demographics']/following-sibling::div/table/tr/td";
    protected $xPath_transportation = "//h2[@id='transportation']/following-sibling::div/table/tr/td";
    protected $xPath_economy = "//h2[@id='economy']/following-sibling::div/table/tr/td";
    protected $xPath_coordinates = null;

    protected function generateMigration()
    {
        $contain = false;
        foreach (glob($this->laravel->databasePath() . self::DS . 'migrations/*') as $file) {
            if (Str::contains($file, '_create_' . $this->table . '_table')) {
                $contain = true;
                break;
            }
        }

        if (!$contain) {
            $this->callSilent('vendor:publish', ['--tag' => 'countryio-migration', '--force' => true]);
        }
    }

    protected function generateModel()
    {
        if ($this->model) {
            return true;
        }

        $modelPath = app_path('Models' . self::DS . 'CountryIO.php');

        if (!$this->files->exists($modelPath)) {
            $this->files->put($modelPath, $this->files->get(__DIR__ . '/../../stubs/countryio_model.stub'));
            return true;
        }

        return $this->confirm('CountryIO models already exists! Continue anyway?', false);
    }
}
