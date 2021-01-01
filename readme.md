# kspedu/laravel-country.io

### UPDATE (v2.0.4)
  - Your own `migration` name,
  - Set model from config,
  - Performance improvement.

  - v2.0.3
    - KEEP needed columns only,
    - direct control via `config` file.


`KSPEdu/Laravel-Country.io` - `artisan` based package that can fetch countries list with details aka:

  - Code
  - Continent
  - Capital
  - GDP
  - Population
  - ISO2/3
  - Language
  - Population
  
and more directly from [Country.io](http://country.io) and store it directly into `file|db`.

### Installation

```sh
$ composer require kspedu/laravel-country.io
```


#### FIRST STEP

```sh
$ php artisan vendor:publish --tag=countryio-config
```
and configure `config` according to your need.


#### OPTIONAL STEP
```sh
$ php artisan vendor:publish --tag=countryio-migration
```


#### BEFORE USING
Configure/adjust `countryio` config file according to your need

```sh
  - table_name          - table name for storing in DB,
  - model               - Define your own `Model` class [\App\Models\Country::class],
  - cols_type           - `plain|json` [`plain` aka `continent`, `population_total` AND `json` aka `geography.continent`, `population.total`],
  - cols                - `cols_type` free columns,
  - cols_plain|json     - `cols_type` based columns,
  - file                - If not DB, than location of file (`--to=file|db` option of `kspedu:countryio` artisan command)
```

#### How to use
First take a look of supported `options`
```sh
$ php artisan help kspedu:countryio
```
````sh
--to      : file OR db
--offline : save fetched files to `storage/country` dir
--clean   : clean everything after setup
--fresh   : Fresh install (for `db` use only, truncates)
--silent  : silent work 
````

**NOTE: Default location is `storage/app/countryio.json`**

So, if you want to `file` list only (default):
First publish the `config` file
```sh
$ php artisan vendor:publish --tag="countryio-config"
```
then, change the location: `config/countryio.php`
```sh
'file' => storage_path('app/...');
```
and fire:
```sh
php artisan kspedu:countryio
```

##### FOR `db`:
`kspedu/laravel-country.io` contains Country `migration` & `model`
```sh
php artisan kspedu:countryio --to=db
```
will create `migration table` (based on `countryio` config) & `CountryIO` model (DEFAULT, if not set in config file) & update the database directly.

~~**Why not using `migration publish`: beacuse if your application already have migration/model for `country` then ...**~~


### Todos

 - App use `file` based `table`
 - Flags


#### Play with `artisan`:
Add to `routes/console.php`:

```sh
Artisan::command('countries', function () {
    $count = $this->ask('How many entries?');
    $model = config('countryio.model', \App\Models\CountryIO::class);

    if(!class_exists($model)) {
        $this->error($model . ' not exists!');
    }

    foreach ((new $model)->take($count ?? 10)->get() as $c) {
        $output = '';
        foreach (array_merge(config('countryio.cols', []), config('countryio.cols_' . config('countryio.cols_type', 'plain'), [])) as $col => $enabled) {
            if ($enabled) {
                $output .= $c->{$col} . ', ';
            }
        }

        $this->comment($output);
    }
})->purpose('Check CountryIO');
```

and `php artisan countries` :)

License
----

MIT


**Free Software, Hell Yeah!**
