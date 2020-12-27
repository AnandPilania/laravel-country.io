<?php
namespace KSPEdu\CountryIO\Console;

trait CountryIOTrait {
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
}