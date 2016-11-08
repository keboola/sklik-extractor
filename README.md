# sklik-extractor
KBC Docker app for extracting data from Sklik API (http://api.sklik.cz)

The Extractor gets list of accessible clients, list of their campaigns and campaign stats for previous day and saves the data to Storage API. Date of downloaded stats can be changed in configuration.

## Status

[![Build Status](https://travis-ci.org/keboola/sklik-extractor.svg)](https://travis-ci.org/keboola/sklik-extractor) [![Code Climate](https://codeclimate.com/github/keboola/sklik-extractor/badges/gpa.svg)](https://codeclimate.com/github/keboola/sklik-extractor) [![Test Coverage](https://codeclimate.com/github/keboola/sklik-extractor/badges/coverage.svg)](https://codeclimate.com/github/keboola/sklik-extractor/coverage)

## Configuration

- **parameters**:
    - **username** - Username to Sklik API
    - **password** - Password to Sklik API
    - **bucket** - Name of bucket where the data will be saved
    - **since** *(optional)* - start date of downloaded stats (default is "-1 day")
    - **until** *(optional)* - end date of downloaded stats (default is "-1 day")
    - **impressionShare** *(optional)* - boolean flag if impression share should be included in stats (default false)

## Output

Data are saved to three tables **incrementally**:


**accounts** - contains data of all Sklik accounts accessible from the main account, columns are:

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


**campaigns** - contains list of campaigns of all Sklik accounts accessible from the main account, columns are:

- **id**: campaign id (*primary key*)
- **name**: campaign name
- **deleted**: whether campaign is deleted
- **status**: campaign status; **active** or **suspend**
- **dayBudget**: campaign day budget (in halers)
- **exhaustedDayBudget**: how much of the day budget is already exhausted (in halers)
- **adSelection**: ad selection type; **weighted** or **random**
- **createDate**: campaign create date
- **totalBudget**: campaign total budget (if set; in halers)
- **exhaustedTotalBudget**: if campaign total budget is set, how much of it is exhausted (in halers)
- **totalClicks**: campaign total clicks
- **exhaustedTotalClicks**: if campaign total clicks is set, how much of them are exhausted
- **accountId**: account user id (foreign key to table **accounts**)


**stats** - contains stats of campaigns of all Sklik accounts accessible from the main account, columns are:

- **accountId**: account user id (foreign key to table **accounts**, *part of primary key*)
- **campaignId**: campaign id (foreign key to table **campaigns**, *part of primary key*)
- **date**: date of stats (*part of primary key*)
- **target**: campaign targetting; **context** or **fulltext** (each campaign has for each date both rows in the table, once with context and once with fulltext) (*part of primary key*)
- **impressions**: impression count
- **clicks**: click count
- **ctr**: click ratio - how much clicks per one impression (%)
- **cpc**: cost per click - average cost per one click, in halers
- **price**: total price paid for displaying ads (for clicks or impressions), in halers
- **avgPosition**: average position of ad in display format
- **conversions**: number of conversions (how many times user made an order)
- **conversionRatio**: how many conversions per one click (%)
- **conversionAvgPrice**: price of one conversion, in halers
- **conversionValue**: value of conversions
- **conversionAvgValue**: average value of conversion
- **conversionValueRatio**: value / price ratio (%)
- **transactions**: number of transactions
- **transactionAvgPrice**: price of one transaction, in halers
- **transactionAvgValue**: average value of one transaction
- **transactionAvgCount**: average number of transactions per conversion
- **impressionShare**: impression share and missed impressions (included optionally)


> **NOTICE!**

> - Main account used for access to API is queried for campaigns and stats too and is also saved to table accounts but has columns access, relationName,
relationStatus and relationType empty.
> - Prices are in halers so you need to divide by 100 to get prices in CZK.
> - Each campaign has two rows in stats table for each day, one with context target and one with fulltext. Even if one of them is without stats.


## Installation

If you want to run this app standalone:

1. Clone the repository: `git@github.com:keboola/sklik-extractor.git ex-sklik`
2. Go to the directory: `cd ex-sklik`
3. Install composer: `curl -s http://getcomposer.org/installer | php`
4. Install packages: `php composer.phar install`
5. Create folder `data`
6. Create file `data/config.yml` with configuration, e.g.:

    ```
    parameters:
      username:
      password:
      bucket: in.c-sklik
    ```
7. Run: `php src/run.php --data=./data`
8. Data tables will be saved to directory `data/out/tables`


## Contributing

Please contribute using TDD. Tests need ordinary Sklik account and can be run by command:

```
env EX_SK_USERNAME= EX_SK_PASSWORD= EX_SK_USER_ID= ./tests.sh
```
