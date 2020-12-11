# kspedu/laravel-country.io

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

#### How to use
First take a look of supported `options`
```sh
$ php artisan help kspedu:countryio
```
````sh
--to : file OR db
--offline : save fetched files to `storage/country` dir
--clean : clean everything after setup
````

**NOTE: Default location is `storage/app/countries.json`**

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
will create [migration](https://github.com/AnandPilania/laravel-country.io/blob/master/stubs/countries_table.stub) `table` aka `countries` & `Country` model & update the database directly.

**Why not using `migration publish`: beacuse if your application already have migration/model for `country` then ...**


### Todos

 - App use `file` based `table`
 - Flags

License
----

MIT


**Free Software, Hell Yeah!**