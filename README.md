# sklik-extractor
KBC Docker app for extracting data from Sklik API (http://api.sklik.cz)

The Extractor gets list of accessible clients, list of their campaigns and campaign stats for previous day and saves the data to Storage API. Date of downloaded stats can be changed in configuration.

## Status

[![Build Status](https://travis-ci.org/keboola/sklik-extractor.svg)](https://travis-ci.org/keboola/sklik-extractor) [![Code Climate](https://codeclimate.com/github/keboola/sklik-extractor/badges/gpa.svg)](https://codeclimate.com/github/keboola/sklik-extractor) [![Test Coverage](https://codeclimate.com/github/keboola/sklik-extractor/badges/coverage.svg)](https://codeclimate.com/github/keboola/sklik-extractor/coverage)

## Configuration

- **username** - Username to Sklik API
- **password** - Password to Sklik API
- **bucket** - Name of bucket where the data will be saved
- **since** *(optional)* - start date of downloaded stats (default is "-1 day")
- **until** *(optional)* - end date of downloaded stats (default is "-1 day")