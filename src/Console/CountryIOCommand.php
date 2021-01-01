<?php

namespace KSPEdu\CountryIO\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CountryIOCommand extends Command
{
    use CountryIOTrait;

    const DS = DIRECTORY_SEPARATOR;

    protected $signature = 'kspedu:countryio
							{--T|to=file : Fetch and Save to file|db.}
							{--O|offline : Save for future use.}
							{--fresh : Fresh install.}
							{--clean : Clean after complete.}
							{--silent : Work silently.}';

    protected $description = 'Fetch Countries';

    protected $files;
    protected $cols;
    protected $cols_type;
    protected $has_slug;

    protected $response;

    protected $table;
    protected $model;

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;

        $this->cols_type = config('countryio.cols_type', 'json');
        $this->cols = array_merge(config('countryio.cols', []), config('countryio.cols_' . $this->cols_type, []));
        $this->has_slug = config('countryio.has_slug', null);

        $this->table = config('countryio.table_name', 'countryio');

        if($model = config('countryio.model', null) ?? (class_exists($this->class) ? $this->class : null)) {
            $this->model = new $model;
        }
    }

    public function handle()
    {
        if ($this->isDB()) {

            if (!$this->generateModel()) {
                exit();
            }

            $this->generateMigration();
            $this->call('migrate');

            if (!$this->model) {
                return throw new \Exception('CountryIO: Set MODEL in countryio.model config OR publish default model!');
            }

            if (!$this->model instanceof \Illuminate\Database\Eloquent\Model) {
                return throw new \Exception('CountryIO: ' . $this->model . ' sould be instance of Eloquent Model!');
            }

            if ($this->option('fresh')) {
                \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
                $this->model->truncate();
                \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            }
        }

        $this->getCountriesList();

        if ($this->option('clean') && is_dir($path = storage_path('app' . self::DS . 'country'))) {
            Storage::deleteDirectory($path, true);
            sleep(1);
            Storage::deleteDirectory($path);
        }
    }

    protected function generateMigration()
    {
        $contain = false;
        foreach (glob($this->laravel->databasePath() . self::DS . 'migrations/*') as $file) {
            if (Str::contains($file, '_create_'.$this->table.'_table')) {
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
            $this->files->put($modelPath, $this->files->get(__DIR__ . '/../../stubs/countryio_model_' . $this->cols_type . '.stub'));
            return true;
        }

        return $this->confirm('CountryIO models already exists! Continue anyway?', false);
    }

    protected function getCountriesList()
    {
        $link = $this->url . $this->names;

        if (!Storage::exists('country' . self::DS . 'countryio.json')) {
            $countries = file_get_contents($link);

            if ($this->isOffline()) {
                Storage::put('country' . self::DS . 'countryio.json', $countries);
            }
        } else {
            $countries = Storage::get('country' . self::DS . 'countryio.json');
        }

        foreach (json_decode($countries) as $code => $name) {
            //$this->getFlags($code);
            $this->getCountry($name);
        }

        if (!$this->isDB()) {
            $file = config('countryio.file', storage_path('app' . self::DS . 'countryio.json'));
            if ($this->files->exists($file)) {
                $this->files->delete($file);
            }

            $this->files->put($file, json_encode($this->response, true));
        }

        $this->info('Country.io done');
    }

    protected function getFlags($code)
    {
        if ($this->isOffline() && !$this->option('no-flags')) {
            $png = Str::lower($code) . '.png';

            if (!$this->option('flag-24') && !$this->option('flag-48')) {
                $sFile = 'country/flag/24/' . $png;
                $xFile = 'country/flag/48/' . $png;

                if (!Storage::exists($sFile)) {
                    Storage::put($sFile, file_get_contents($this->flags_path . '24/' . $png));
                }

                if (!Storage::exists($xFile)) {
                    Storage::put($xFile, file_get_contents($this->flags_path . '48/' . $png));
                }

            }
        }
    }

    protected function getCountry($name)
    {
        $country = Str::slug($name);
        $link = $this->url . "{$country}/";
        $_name = $this->convertToHtml($link);

        if (!Storage::exists('country' . self::DS . $_name)) {
            $contents = file_get_contents($link);

            if ($this->isOffline()) {
                Storage::put('country' . self::DS . $_name, $contents);
            }
        } else {
            $contents = Storage::get('country' . self::DS . $_name);
        }

        $response = [];

        $xpath = $this->getXPath($this->isOffline() ? storage_path('app' . self::DS . 'country' . self::DS . $country . '.html') : $contents);

        $name = $xpath->query($this->xPath_name);

        foreach ($this->cols as $col => $enabled) {
            if ($enabled) {
                if ('name' === $col) {
                    $response['name'] = $name[0]->textContent;
                }

                if ($this->has_slug) {
                    $response['slug'] = Str::slug($name[0]->textContent);
                }

                $items = $xpath->query($this->xPath_core);
                if ('code' === $col) {
                    $response['code'] = $items[1]->textContent;
                }
                if ('iso2' === $col) {
                    $response['iso2'] = $items[4]->textContent;
                }
                if ('iso3' === $col) {
                    $response['iso3'] = $items[7]->textContent;
                }
                if ('capital' === $col) {
                    $response['capital'] = $items[10]->textContent;
                }
                if ('lang' === $col) {
                    $response['lang'] = $items[13]->textContent;
                }

                if ('neighbours' === $col) {
                    $response['neighbours'] = null;
                    $neighbours = $xpath->query($this->xPath_neighbours);
                    foreach ($neighbours as $index => $item) {
                        $response['neighbours'][] = Str::slug($item->textContent);
                    }
                }

                if ('history' === $col) {
                    $history = $xpath->query($this->xPath_history);
                    $response['history'] = str_ireplace(' Read more on Wikipedia', '', rtrim($history[0]->textContent));
                }
            }
        }

        foreach ($this->cols as $col => $enabled) {
            if ($enabled) {
                $_response = array_merge($response, $this->{$this->cols_type}($xpath, $col));
                $response = $_response;
            }
        }

        if ($this->isDB()) {
            $field = $this->has_slug ? 'slug' : 'name';
            $field_val = $this->has_slug ? Str::slug($name[0]->textContent) : $name[0]->textContent;

            if ($model = $this->model->where($field, $field_val)->first()) {
                $model->update($response);
            } else {
                $this->model->create($response);
            }

//            $model = new $this->model;
//            $this->model = $model;
        } else {
            $this->response[] = $response;
        }

        $response = [];

        if (!$this->option('silent')) {
            echo $country . ' done' . PHP_EOL;
        }
    }

    private function json($xpath, $col)
    {
        $response = [];

        $items = $xpath->query($this->xPath_currency_population);
        if ('currency' === $col) {
            $currency = explode(' ', $items[16]->textContent);
            if (count($currency) > 0) {
                $response['currency'] = ['code' => str_ireplace('(', '', str_ireplace(')', '', $currency[1])), 'name' => $currency[0]];
            } else {
                $response['currency'] = [$items[16]->textContent, null];
            }
        }
        if ('population' === $col) {
            $response['population'] = ['total' => $items[22]->textContent, 'position' => $items[23]->textContent];
        }

        if ('geography' === $col) {
            $geography = $xpath->query($this->xPath_geography);
            $response['geography'][Str::slug($geography[0]->textContent, '_')] = $geography[1]->textContent;
            $response['geography'][Str::slug($geography[3]->textContent, '_')] = $geography[4]->textContent;
            $response['geography'][Str::slug($geography[6]->textContent, '_')] = ['total' => $geography[7]->textContent, 'position' => $geography[8]->textContent];
            $response['geography'][Str::slug($geography[9]->textContent, '_')] = $geography[10]->textContent;
            $response['geography'][Str::slug($geography[12]->textContent, '_')] = $geography[13]->textContent;
            $response['geography'][Str::slug($geography[15]->textContent, '_')] = $geography[16]->textContent;
            if (isset($geography[18])) {
                $response['geography'][Str::slug($geography[18]->textContent, '_')] = $geography[19]->textContent;
            }
        }

        if ('demographics' === $col) {
            $demographics = $xpath->query($this->xPath_demographics);
            $response['demographics'][Str::slug($demographics[0]->textContent, '_')] = ['total' => $demographics[1]->textContent, 'position' => $demographics[2]->textContent];
            $response['demographics'][Str::slug($demographics[3]->textContent, '_')] = ['total' => $demographics[4]->textContent, 'position' => $demographics[5]->textContent];
            $response['demographics'][Str::slug($demographics[6]->textContent, '_')] = ['total' => $demographics[7]->textContent, 'position' => $demographics[8]->textContent];
            $response['demographics'][Str::slug($demographics[9]->textContent, '_')] = ['total' => $demographics[10]->textContent, 'position' => $demographics[11]->textContent];
            $response['demographics'][Str::slug($demographics[12]->textContent, '_')] = ['total' => $demographics[13]->textContent, 'position' => $demographics[14]->textContent];
            if (isset($demographics[15])) {
                $response['demographics'][Str::slug($demographics[15]->textContent, '_')] = ['total' => $demographics[16]->textContent, 'position' => $demographics[17]->textContent];
            }
        }
        if ('transportation' === $col) {
            $transportation = $xpath->query($this->xPath_transportation);
            $response['transportation'][Str::slug($transportation[0]->textContent, '_')] = ['total' => $transportation[1]->textContent, 'position' => $transportation[2]->textContent];
            $response['transportation'][Str::slug($transportation[3]->textContent, '_')] = ['total' => $transportation[4]->textContent, 'position' => $transportation[5]->textContent];
            $response['transportation'][Str::slug($transportation[6]->textContent, '_')] = ['total' => $transportation[7]->textContent, 'position' => $transportation[8]->textContent];
            $response['transportation'][Str::slug($transportation[9]->textContent, '_')] = ['total' => $transportation[10]->textContent, 'position' => $transportation[11]->textContent];
            $response['transportation'][Str::slug($transportation[12]->textContent, '_')] = ['total' => $transportation[13]->textContent, 'position' => $transportation[14]->textContent];
            if (isset($transportation[15])) {
                $response['transportation'][Str::slug($transportation[15]->textContent, '_')] = ['total' => $transportation[16]->textContent, 'position' => $transportation[17]->textContent];
            }
        }

        if ('economy' === $col) {
            $economy = $xpath->query($this->xPath_economy);
            $response['economy'][Str::slug($economy[3]->textContent, '_')] = ['total' => $economy[4]->textContent, 'position' => $economy[5]->textContent];
            $response['economy'][strtolower(str_ireplace('GDP per capita (', '', str_ireplace(')', '', $economy[6]->textContent)))] = ['total' => $economy[7]->textContent, 'position' => $economy[8]->textContent];
        }

        if ('coordinates' === $col) {
            $response['coordinates'] = ['latitude' => null, 'longitude' => null];
        }

        return $response;
    }

    private function plain($xpath, $col)
    {
        $response = [];

        $items = $xpath->query($this->xPath_core);
        $currency = explode(' ', $items[16]->textContent);
        if (count($currency) > 0) {
            $_cCode = str_ireplace('(', '', str_ireplace(')', '', $currency[1]));
            $_cName = $currency[0];
        }

        if ('currency_code' === $col) {
            $response['currency_code'] = $_cCode ?? $items[16]->textContent;
        }
        if ('currency_name' === $col) {
            $response['currency_name'] = $_cName ?? null;
        }

        if ('population_total' === $col) {
            $response['population_total'] = $items[22]->textContent;
        }
        if ('population_position' === $col) {
            $response['population_position'] = $items[23]->textContent;
        }

        $geography = $xpath->query($this->xPath_geography);
        if ('continent' === $col) {
            $response['continent'] = $geography[1]->textContent;
        }
        if ('location' === $col) {
            $response['location'] = $geography[4]->textContent;
        }
        if ('land_total' === $col) {
            $response['land_total'] = $geography[7]->textContent;
        }
        if ('land_position' === $col) {
            $response['land_position'] = $geography[8]->textContent;
        }
        if ('terrain' === $col) {
            $response['terrain'] = $geography[10]->textContent;
        }
        if ('climate' === $col) {
            $response['climate'] = $geography[13]->textContent;
        }
        if ('natural_hazards' === $col) {
            $response['natural_hazards'] = $geography[16]->textContent;
        }
        if ('geo_note' === $col) {
            $response['geo_note'] = isset($geography[18]) ? $geography[19]->textContent : null;
        }

        $demographics = $xpath->query($this->xPath_demographics);
        if ('life_expectancy_total' === $col) {
            $response['life_expectancy_total'] = $demographics[1]->textContent;
        }
        if ('life_expectancy_position' === $col) {
            $response['life_expectancy_position'] = $demographics[2]->textContent;
        }
        if ('median_age_total' === $col) {
            $response['median_age_total'] = $demographics[4]->textContent;
        }
        if ('median_age_position' === $col) {
            $response['median_age_position'] = $demographics[5]->textContent;
        }
        if ('birth_rate_total' === $col) {
            $response['birth_rate_total'] = $demographics[7]->textContent;
        }
        if ('birth_rate_position' === $col) {
            $response['birth_rate_position'] = $demographics[8]->textContent;
        }
        if ('death_rate_total' === $col) {
            $response['death_rate_total'] = $demographics[10]->textContent;
        }
        if ('death_rate_position' === $col) {
            $response['death_rate_position'] = $demographics[11]->textContent;
        }
        if ('sex_ratio_total' === $col) {
            $response['sex_ratio_total'] = $demographics[13]->textContent;
        }
        if ('sex_ratio_position' === $col) {
            $response['sex_ratio_position'] = $demographics[14]->textContent;
        }
        if ('literacy_total' === $col) {
            $response['literacy_total'] = isset($demographics[15]) ? $demographics[16]->textContent : null;
        }
        if ('literacy_position' === $col) {
            $response['literacy_position'] = isset($demographics[15]) ? $demographics[17]->textContent : null;
        }

        $transportation = $xpath->query($this->xPath_transportation);
        if ('roadways_total' === $col) {
            $response['roadways_total'] = $transportation[1]->textContent;
        }
        if ('roadways_position' === $col) {
            $response['roadways_position'] = $transportation[2]->textContent;
        }
        if ('railways_total' === $col) {
            $response['railways_total'] = $transportation[4]->textContent;
        }
        if ('railways_position' === $col) {
            $response['railways_position'] = $transportation[5]->textContent;
        }
        if ('airports_total' === $col) {
            $response['airports_total'] = $transportation[7]->textContent;
        }
        if ('airports_position' === $col) {
            $response['airports_position'] = $transportation[8]->textContent;
        }
        if ('waterways_total' === $col) {
            $response['waterways_total'] = $transportation[10]->textContent;
        }
        if ('waterways_position' === $col) {
            $response['waterways_position'] = $transportation[11]->textContent;
        }
        if ('heliports_total' === $col) {
            $response['heliports_total'] = $transportation[13]->textContent;
        }
        if ('heliports_position' === $col) {
            $response['heliports_position'] = $transportation[14]->textContent;
        }
        if ('airports_paved_total' === $col) {
            $response['airports_paved_total'] = isset($transportation[15]) ? $transportation[16]->textContent : null;
        }
        if ('airports_paved_position' === $col) {
            $response['airports_paved_position'] = isset($transportation[15]) ? $transportation[17]->textContent : null;
        }

        $economy = $xpath->query($this->xPath_economy);
        if ('gdp_total' === $col) {
            $response['gdp_total'] = $economy[4]->textContent;
        }
        if ('gdp_position' === $col) {
            $response['gdp_position'] = $economy[5]->textContent;
        }
        if ('ppp_total' === $col) {
            $response['ppp_total'] = $economy[7]->textContent;
        }
        if ('ppp_position' === $col) {
            $response['ppp_position'] = $economy[8]->textContent;
        }

        if ('latitude' === $col) {
            $response['latitude'] = null;
        }
        if ('longitude' === $col) {
            $response['longitude'] = null;
        }

        return $response;
    }

    protected function convertToHtml($link)
    {
        return str_ireplace('/', '', explode('.io/', $link)[1]) . '.html';
    }

    protected function getXPath($fileOrContents)
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->strictErrorChecking = false;
        $dom->recover = true;

        if ($this->isOffline()) {
            @$dom->loadHTMLFile($fileOrContents);
        } else {
            @$dom->loadHTML($fileOrContents);
        }

        return new \DOMXPath($dom);;
    }

    protected function isDB()
    {
        return $this->option('to') === 'db';
    }

    protected function isOffline()
    {
        return $this->option('offline');
    }
}
