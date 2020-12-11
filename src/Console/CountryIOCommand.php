<?php
namespace KSPEdu\CountryIO\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CountryIOCommand extends Command {
	protected $signature = 'kspedu:countryio
							{--T|to=file : Fetch and Save to file|db.}
							{--O|offline : Save for future use.}
							{--clean : Clean after complete}';

    protected $description = 'Fetch Countries';
	
	protected $files;
	
	protected $response;
	
	protected $url = 'http://country.io/';
	
	public function __construct(Filesystem $files) {
		parent::__construct();
		
		$this->files = $files;
	}
	
    public function handle()
    {
		if($this->isDB()) {
			$this->generateMigration();
			$this->generateModel();
			$this->call('migrate');
		}
		
		$this->getCountriesList();
		
		if($this->option('clean') && is_dir(storage_path().DIRECTORY_SEPARATOR.'country')) {
			Storage::deleteDirectory('country');
		}
    }
	
	protected function generateMigration() {
		$contain = false;
		foreach(glob($this->laravel->databasePath().DIRECTORY_SEPARATOR.'migrations/*') as $file) {
			if(Str::contains($file, '_create_countries_table')) {
				$contain = true;
				break;
			}
		}
		
		if(!$contain) {
			$path = $this->laravel->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.date('Y_m_d_His').'_create_countries_table.php';
			$this->files->put($path, $this->files->get(__DIR__.'/../../stubs/countries_table.stub'));
		}
	}
	
	protected function generateModel() {
		//copy(__DIR__.'/../../stubs/country_model.stub', app_path('Models/Country.php'));
		$path = app_path().DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.'Country.php';
		if(!$this->files->exists($path)) {
			$this->files->put($path, $this->files->get(__DIR__.'/../../stubs/country_model.stub'));
		}
	}
	
	protected function getCountriesList() {
		$link = $this->url . 'names.json';
		
		if(!Storage::exists('country/countries.json')) {
			$countries = file_get_contents($link);
			
			if($this->isOffline()) {
				Storage::put('country/countries.json', $countries);
			}
		} else {
			$countries = Storage::get('country/countries.json');
		}
		
		foreach(json_decode($countries) as $code => $name) {
			//$this->getFlags($code);
			$this->getCountry($name);
		}
		
		if(!$this->isDB()) {
			$file = config('countryio.file', storage_path('app/countries.json'));
			if($this->files->exists($file)) {
				$this->files->delete($file);
			}
			
			$this->files->put($file, json_encode($this->response, true));
		}
	}
	
	protected function getFlags($code) {
		if($this->isOffline() && !$this->option('no-flags')) {
			$link = 'http://country.io/static/flags/';
			$png = Str::lower($code).'.png';
				
			if(!$this->option('flag-24') && !$this->option('flag-48')) {
				$sFile = 'country/flag/24/' . $png;
				$xFile = 'country/flag/48/' . $png;
					
				if(!Storage::exists($sFile)) {
					Storage::put($sFile, file_get_contents($link.'24/'.$png));
				}
					
				if(!Storage::exists($xFile)) {
					Storage::put($xFile, file_get_contents($link.'48/'.$png));
				}
				
			}
		}
	}
	
	protected function getCountry($name) {
		$country = Str::slug($name);
		$link = $this->url . "{$country}/";
		$_name = $this->convertToHtml($link);
		
		if(!Storage::exists('country/'.$_name)) {
            $contents = file_get_contents($link);
			
			if($this->isOffline()) {
				Storage::put('country/'.$_name, $contents);
			}
        } else {
			$contents = Storage::get('country/'.$_name);
		}
		
		$response = [];
		
		$xpath = $this->getXPath($this->isOffline() ? storage_path('app/country/' . $country . '.html') : $contents);
        
		$name = $xpath->query("//a[@class='cc-country-toplink']");
        $response['name'] = $name[0]->textContent;
		
		$items = $xpath->query("//div[@class='col-sm-10 cc-home-block']/div/div/table/tr/td");
        $response['code'] = $items[1]->textContent;
        $response['iso2'] = $items[4]->textContent;
        $response['iso3'] = $items[7]->textContent;
        $response['capital'] = $items[10]->textContent;
        $response['lang'] = $items[13]->textContent;
        $currency = explode(' ', $items[16]->textContent);
        if(count($currency) > 0) {
            $response['currency'] = ['code' => str_ireplace('(', '', str_ireplace(')','', $currency[1])), 'name' => $currency[0]];
        } else{
            $response['currency'] = [$items[16]->textContent, null];
        }
        $response['population'] = ['total' => $items[22]->textContent, 'position' => $items[23]->textContent];

        $items = $xpath->query("//h2[@id='geography']/following-sibling::div/table/tr/td");
        $response['geography'][Str::slug($items[0]->textContent, '_')] = $items[1]->textContent;
        $response['geography'][Str::slug($items[3]->textContent, '_')] = $items[4]->textContent;
        $response['geography'][Str::slug($items[6]->textContent, '_')] = ['total' => $items[7]->textContent, 'position' => $items[8]->textContent];
        $response['geography'][Str::slug($items[9]->textContent, '_')] = $items[10]->textContent;
        $response['geography'][Str::slug($items[12]->textContent, '_')] = $items[13]->textContent;
        $response['geography'][Str::slug($items[15]->textContent, '_')] = $items[16]->textContent;
        if (isset($items[18])) {
            $response['geography'][Str::slug($items[18]->textContent, '_')] = $items[19]->textContent;
        }

        $items = $xpath->query("//div[@class='cc-neighbour']/p/span/a");
        foreach ($items as $index => $item) {
            $response['neighbours'][] = $item->textContent;
        }

        $items = $xpath->query("//h2[@id='history']/following-sibling::p");
        $response['history'] = str_ireplace(' Read more on Wikipedia', '', rtrim($items[0]->textContent));

        $items = $xpath->query("//h2[@id='demographics']/following-sibling::div/table/tr/td");
        $response['demographics'][Str::slug($items[0]->textContent, '_')] = ['total' => $items[1]->textContent, 'position' => $items[2]->textContent];
        $response['demographics'][Str::slug($items[3]->textContent, '_')] = ['total' => $items[4]->textContent, 'position' => $items[5]->textContent];
        $response['demographics'][Str::slug($items[6]->textContent, '_')] = ['total' => $items[7]->textContent, 'position' => $items[8]->textContent];
        $response['demographics'][Str::slug($items[9]->textContent, '_')] = ['total' => $items[10]->textContent, 'position' => $items[11]->textContent];
        $response['demographics'][Str::slug($items[12]->textContent, '_')] = ['total' => $items[13]->textContent, 'position' => $items[14]->textContent];
        if (isset($items[15])) {
            $response['demographics'][Str::slug($items[15]->textContent, '_')] = ['total' => $items[16]->textContent, 'position' => $items[17]->textContent];
        }

        $items = $xpath->query("//h2[@id='transportation']/following-sibling::div/table/tr/td");
        $response['transportation'][Str::slug($items[0]->textContent, '_')] = ['total' => $items[1]->textContent, 'position' => $items[2]->textContent];
        $response['transportation'][Str::slug($items[3]->textContent, '_')] = ['total' => $items[4]->textContent, 'position' => $items[5]->textContent];
        $response['transportation'][Str::slug($items[6]->textContent, '_')] = ['total' => $items[7]->textContent, 'position' => $items[8]->textContent];
        $response['transportation'][Str::slug($items[9]->textContent, '_')] = ['total' => $items[10]->textContent, 'position' => $items[11]->textContent];
        $response['transportation'][Str::slug($items[12]->textContent, '_')] = ['total' => $items[13]->textContent, 'position' => $items[14]->textContent];
        if (isset($items[15])) {
            $response['transportation'][Str::slug($items[15]->textContent, '_')] = ['total' => $items[16]->textContent, 'position' => $items[17]->textContent];
        }

        $items = $xpath->query("//h2[@id='economy']/following-sibling::div/table/tr/td");
        $response['economy'][Str::slug($items[3]->textContent, '_')] = ['total' => $items[4]->textContent, 'position' => $items[5]->textContent];
        $response['economy'][strtolower(str_ireplace('GDP per capita (', '', str_ireplace(')', '', $items[6]->textContent)))] = ['total' => $items[7]->textContent, 'position' => $items[8]->textContent];

        $response['slug'] = Str::slug($name[0]->textContent);

        if($name === 'Russia' || $name === 'Ukraine' || $name === 'Armenia' || $name === 'Kazakhstan' || $name === 'Philippines' || $name === 'Kyrgyzstan') {
            $response['active'] = true;
        }

        $response['coordinates'] = ['latitude' => null, 'longitude' => null];

        if($this->isDB()) {
			if($model = \App\Models\Country::where('slug', Str::slug($name[0]->textContent))->first()) {
				$model->update($response);
			} else {
				\App\Models\Country::create($response);
			}
		} else {
			$this->response[] = $response;
		}

        $response = [];

        echo $country . ' done' . PHP_EOL;
	}
	
	protected function convertToHtml($link) {
		return str_ireplace('/', '', explode('.io/', $link)[1]).'.html';
	}
	
	protected function getXPath($fileOrContents) {
		$dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->strictErrorChecking = false;
        $dom->recover = true;
		
		if($this->isOffline()) {
			@$dom->loadHTMLFile($fileOrContents);
		} else {
			@$dom->loadHTML($fileOrContents);
		}
		
		return new \DOMXPath($dom);;
	}
	
	protected function isDB() {
		return $this->option('to') === 'db';
	}
	
	protected function isOffline() {
		return $this->option('offline');
	}
}
