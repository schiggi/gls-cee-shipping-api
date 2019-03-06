<?php

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

$api = new GLS\API([
    'username'          => 'teszt',
    'password'          => 'teszt',
    'client_number'     => '100000004',
    'country_code'      => 'HU-TEST',
    'label_paper_size'  => 'A4_2x2',
    'log_dir'           => dirname(__FILE__).'/'
]);

//******************** Prepare parcels *****************/
$parcel_prepare[] = array(
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

$parcel_prepare[] = array(
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
    'ConsigName' => 'Axanne Jelasity',
    'ConsigAddress' => 'Teszt ut 2',
    'ConsigCity' => 'Gődöllö',
    'ConsigZipcode' => '2100',
    'ConsigCountry' => 'HU',
    // Not mandatory
    'ConsigContact' => 'Axanne Jelasity',
    'ConsigPhone' => '06301245879',
    'ConsigEmail' => 'axanne@alex.hu',
    //
    'ClientRef' => '14051',
    'CodAmount' => 5999,
    'CodRef' => '2018-200546',
    'Pcount' => 1,
    'PickupDate' => '2018-02-26',
    'Services' => array(
        "24H" => "",
        "FDS" => "axanne@alex.hu",
        "FSS" => "+3620154678916",
    ),
);

$prepared_parcels = $api->getParcelNumbers($parcel_prepare);
echo '<pre>',print_r($prepared_parcels,1),'</pre>';


//******************** Print parcel labels *****************/
//$prepared_parcels = Array
//(
//    '14050' => '00209053509',
//    '14051' => '00209053508'
//);

$printed_parcels = $prepared_parcels;

//$printed_parcels = $api->getParcelLabels($prepared_parcels);
//header("Content-type:application/pdf");
//echo $printed_parcels['pdf'];


//******************** Delete parcels *****************/

$delete_parcels = Array
(
    '14050' => '00209053638',
    '14051' => '00209053637'
);

$delete_parcels = $prepared_parcels;

$deleted_parcels = $api->deleteParcels($delete_parcels);
echo '<pre>',print_r($deleted_parcels,1),'</pre>';


//******************** Parcel status test *****************/
// echo 'Status is: ' . $api->getParcelStatus('00209053637') . '<br>';

//******************** Print tracking url *****************/
//// Parcel tracking url
// echo 'Tracking URL is: ' . $api->getTrackingUrl('00209053509') . '<br>';