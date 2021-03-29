<?php

return [
	'table_name' => 'countryio',

	'model' => null,
	
	'cols_type' => 'plain',

	'has_slug' => true,
    'has_active' => true,
    'has_priority' => true,

	'cols' => [
		'name' => true,
		'code' => true,
		'iso2' => true,
		'iso3' => true,
		'capital' => true,
		'lang' => true,
		'neighbours' => false,
		'history' => false,
	],

	'cols_plain' => [
		'currency_code' => true,
		'currency_name' => true,
		
		'population_total' => false,
		'population_position' => false,
		
		'continent' => true,
		'location' => false,
		'land_total' => false,
		'land_position' => false,
		'terrain' => false,
		'climate' => false,
		'natural_hazards' => false,
		'geo_note' => false,
		
		'life_expectancy_total' => false,
		'life_expectancy_position' => false,
		'median_age_total' => false,
		'median_age_position' => false,
		'birth_rate_total' => false,
		'birth_rate_position' => false,
		'death_rate_total' => false,
		'death_rate_position' => false,
		'sex_ratio_total' => false,
		'sex_ratio_position' => false,
		'literacy_total' => false,
		'literacy_position' => false,

		'roadways_total' => false,
		'roadways_position' => false,
		'railways_total' => false,
		'railways_position' => false,
		'airports_total' => false,
		'airports_position' => false,
		'waterways_total' => false,
		'waterways_position' => false,
		'heliports_total' => false,
		'heliports_position' => false,
		'airports_paved_total' => false,
		'airports_paved_position' => false,

		'gdp_total' => false,
		'gdp_position' => false,
		'ppp_total' => false,
		'ppp_position' => false,

		'latitude' => false,
		'longitude' => false,
	],

	'cols_json' => [
		'currency' => true,
		'population' => false,
		'geography' => false,
		'demographics' => false,
		'transportation' => false,
		'economy' => false,
		'coordinates' => false,
	],

	'file' => storage_path('app/countryio.json'),
];