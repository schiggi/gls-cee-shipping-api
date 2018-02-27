gls-cee-shipping-api
================

GLS shipping API for Eastern Europa (HU, RO, SK, CZ, SI, HR) GLS webservice interface via SOAP-XML requests

## Installing

The easiest way to install the API is using Composer:

```
composer require schiggi/gls-cee-shipping-api
```

Then use your framework's autoload, or simply add:

```php
<?php
  require 'vendor/autoload.php';
```

## Getting started

You can start making requests to the GLS API just by creating a new `API` instance

```php
<?php
    $api = new GLS\API([
        'username'          => 'teszt',
        'password'          => 'teszt',
        'client_number'     => '100000004',
        'country_code'      => 'HU'
    ]);
```

The `API` class takes care of the communication between your app and the GLS servers via SOAP-XML requests.

#### Using Monolog to log requests and responses to a log file

Monolog is used optionally to log request/responses with DPD server.

You need to specific a log dir when creating a new `API` instance. Log files while have the name "api-gls-consumer-{date}.log". If omitted, no logs will be written. 

```php
<?php
  $api = new GLS\API([
    'username'          => 'teszt',
    'password'          => 'teszt',
    'client_number'     => '100000004',
    'country_code'      => 'HU',
    'log_dir'  => dirname(__FILE__).'/'
  ]);
 
```

## General usage

### Send parcel data to GLS

```php
<?php
// Parcel generation. Data will be validated before sending.
$parcel_generate[] = array(
    'SenderName' => 'Company Kft.',
    'SenderAddress' => 'Ady Endre ut 104',
    'SenderCity' => 'Budapest',
    'SenderZipcode' => '1072',
    'SenderCountry' => 'HU',
    // Not mandatory
    'SenderContact' => 'Contact Axanne',
    'SenderPhone' => '06202156156',
    'SenderEmail' => 'teszt@teszt.hu',
    //
    'ConsigName' => 'Alex Schikalow',
    'ConsigAddress' => 'Teszt ut 1',
    'ConsigCity' => 'Budapest',
    'ConsigZipcode' => '1025',
    'ConsigCountry' => 'HU',
    // Not mandatory
    'ConsigContact' => 'Alex Schikalow',
    'ConsigPhone' => '06301245879',
    'ConsigEmail' => 'alex@alex.hu',
    //

    'ClientRef' => '14050',
    'CodAmount' => 0,
    'CodRef' => '',
    'Pcount' => 1,
    'PickupDate' => '2018-02-26',
    'Services' => array(
        "24H" => "",
        "FDS" => "alex@alex.hu",
        "FSS" => "+362012345648",
    ),
);

// Will return the parcel numbers for each clientRef order from GLS or error message
$parcel_numbers = $api->getParcelNumbers($parcel_generate);
```

```php
var_dump($parcel_numbers);
Array
(
  [14050] => 00201084696
)
```

### Print labels for saved and sent parcels

```php
<?php
// Array of parcel numbers from GLS. Client / order ids optional. Can also be a numeric array.
$parcel_numbers = array(
    '14050' => 00201084696
);

// Returns array with success message and pdf stream
$printed_parcels = $api->getParcelLabels($prepared_parcels);
```
```php
var_dump($printed_parcels);
Array
(
    [status] => 'success'
    [error_description] => 'Error Description. Empty, if success'
    [pdf] => is streamed labels. Use echo and correct pdf header to display
)
```

You can set, which paper size and how many labels should be printed on pdf. For that, you need to specific a label paper size when creating a new `API` instance. Available options are "A6", "A6_PP", "A6_ONA4", "A4_2x2", "A4_4x1" and "T_85x85". Default is "A4_2x2".

```php
<?php
$api = new GLS\API([
    'username'          => 'teszt',
    'password'          => 'teszt',
    'client_number'     => '100000004',
    'country_code'      => 'HU-TEST',
    'label_paper_size'  => 'A4_2x2',
]);
```

### Delete parcel

```php
<?php
// Array of parcel numbers from GLS. Order references are optional. Can be numerical array.
$delete_parcels = Array
(
    '14050' => '00209053638',
    '14051' => '00209053637'
);

// Returns array with success message or error description
$deleted_parcels = $api->deleteParcels($delete_parcels);
```
```php
var_dump($deleted_parcels);
Array
(
    [00209053638] => 'success'
    [00209053637] => 'Parcel already deleted'
)
```
If one parcel has a problem, then no parcel will be deleted from the request array. 

### Retrieve parcel status

```php
<?php
// returns status message as string for parcel_number. One number at a time.
$status_msg = $api->getParcelStatus('123456789');

```

### Retrieve parcel status link

```php
<?php
// returns url link for parcel number. One number at a time.
$status_msg = $api->$api->getTrackingUrl('123456789');

```