# sklik-extractor
KBC Docker app for extracting data from Sklik API (http://api.sklik.cz)

The extractor gets list of all accessible accounts if you don't restrict them explicitly. Then it downloads configured reports for all these specified accounts.

You can use API token or Sklik credentials for authorization.

[![Build Status](https://travis-ci.com/keboola/sklik-extractor.svg)](https://travis-ci.com/keboola/sklik-extractor) [![Code Climate](https://codeclimate.com/github/keboola/sklik-extractor/badges/gpa.svg)](https://codeclimate.com/github/keboola/sklik-extractor) [![Test Coverage](https://codeclimate.com/github/keboola/sklik-extractor/badges/coverage.svg)](https://codeclimate.com/github/keboola/sklik-extractor/coverage)

## Configuration

- **parameters**:
    - **#token** - Sklik API token (You will find it under the Account settings in Sklik)
    - **username** and **#password** - Sklik credentials is an alternative to authorization using API token
    - **accounts** *(optional)* - Comma separated list of accounts you want to download the data for. It downloads data for all accounts by default.
    - **reports** - Array of reports to download. Each item must contain:
        - **name** - Your name for the report, it will be used for name of the table in Storage. *Note that `accounts` is a reserved name, thus it cannot be used as report name.*
        - **resource** - Name of the resource on which you want the report to be created. Supported resources are all from https://api.sklik.cz/drak/ which support `createReport` and `readReport` methods (see https://blog.seznam.cz/2017/12/spravne-pouzivat-limit-offset-metodach-statisticke-reporty-api-drak/ for more information):
            - `ads`
            - `banners`
            - `campaigns`
            - `groups`
            - `intends`
            - `intends.negative`
            - etc.
        - **restrictionFilter** - Json object of the restriction filter configuration for `createReport` API call.
            - `dateFrom` and `dateTo` are required values. If omitted, yesterday's dates will be used.
            - the Extractor allows you to use relative days in [these supported formats](http://php.net/manual/en/datetime.formats.relative.php). 
        - **displayOptions** - Json object of the display options configuration for `createReport` API call.
        - **displayColumns** - Comma separated list of columns to get for `readReport` API call.
            - Column `id` as identifier of the resource is downloaded every time (except for resource `queries` which has `query` instead).
    
### API Limits
    
Current listing limit supported by Sklik API is 100. A problem appears when `statGranularity` is added to `displayOptions`. If you define granularity `daily`, the limit is divided by number of days in the specified interval. Ie. interval between `dateFrom` and `dateTo` must not be bigger then 100 days. 

## Output

### Table accounts

Table **accounts** is created by default and it contains data of all (or configured) Sklik accounts accessible from the main account, its columns are:

- **userId**: account user id (*primary key*)
- **username**: account username
- **access**: access type; **r** for read-only, **rw** for read-write, empty for main account
- **relationName**: name of relation of main account to this foreign account
- **relationStatus**: relation status; **live** means connection to the account is working, **offer** means an offer of the
    access by a foreign account was not yet accepted, **request** means requested access to this foreign account was not yet accepted
- **relationType**: type of the relation to this foreign account; **normal** or **agency** which means foreign account expenses are paid by main user
- **walletCredit**: account user's credit (in halers) or nil if not permitted to get this value or no wallet is assigned to this account
- **walletCreditWithVat**: account user's credit including VAT (in halers) or nil if not permitted to get this value or no wallet is assigned to this account
- **walletVerified**: user's Wallet is verified; can be nil if not permitted to get this value
- **accountLimit**: account monthly limit (in halers) or nil; account limit is valid only for agency client accounts
- **dayBudgetSum**: sum of day budgets of all campaigns (in cents)

> **NOTICE!**

> - Main account used for access to API is queried for reports by default too and is also saved to table accounts. But it has columns access, relationName, relationStatus and relationType empty.
> - Prices are in halers so you need to divide by 100 to get prices in CZK.

### Report tables

Each report creates two tables. One with metadata and one with actual stats by date. 

Metadata table named after the report has a primary key `id` (Column `id` is added to `displayColumns` automatically). Dots (`.`) in nested values will be replaced with underscores (`_`). The table is complemented with column `accountId` with id of the account.

Stats table is also named after the report with suffix `-stats` and has a primary key compounded from `id` and `date`. 

E.g. if you configure to download columns `name, clicks, impressions` from resource `campaigns` and call the report `report1`, you will get table `report1` with columns `id, name` and table `report1-stats` with columns `id, date, impressions, clicks`.


## Example

Let'say we want to download daily stats for campaigns. The report will look like this:
- name: `report1`
- resource: `campaigns`
- restrictionFilter: `{ "dateFrom": "2018-07-01", "dateTo": "2018-07-03" }`
- displayOptions: `{ "statGranularity": "daily" }`
- displayColumns: `id, name, clicks, impressions`

Extractor will create a table `report1` which will look like:

```
"id","name"
"15001","Keboola.com - content"
"15002","Keboola.com - search"
```

And table `report1-stats`:

```
"id","clicks","date","impressions"
"15001","0","","0"
"15002","5","20180701","26"
"15002","0","20180702","10"
"15002","0","20180703","2"
```

## Development
 
Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/sklik-extractor
cd sklik-extractor
docker-compose build
docker-compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker-compose run --rm dev composer tests
```
 
# Integration

For information about deployment and integration with KBC, please refer to the [deployment section of developers documentation](https://developers.keboola.com/extend/component/deployment/) 
