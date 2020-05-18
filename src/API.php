<?php

namespace GLS;

use nusoap_client;
use GuzzleHttp\Client;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;


class API {

	protected $urls = [
		'HU' => 'https://online.gls-hungary.com/webservices/soap_server.php?wsdl&ver=18.09.12.01',
        'HU-TEST' => 'https://test.gls-hungary.com/webservices/soap_server.php?wsdl&ver=15.04.18.01',
		'SK' => 'http://online.gls-slovakia.sk/webservices/soap_server.php?wsdl&ver=18.09.12.01',
		'CZ' => 'http://online.gls-czech.com/webservices/soap_server.php?wsdl&ver=18.09.12.01',
		'RO' => 'http://online.gls-romania.ro/webservices/soap_server.php?wsdl&ver=18.09.12.01',
		'SI' => 'http://connect.gls-slovenia.com/webservices/soap_server.php?wsdl&ver=18.09.12.01',
		'HR' => 'http://online.gls-croatia.com/webservices/soap_server.php?wsdl&ver=18.09.12.01',
	];

    protected $services = array("T12", "PSS", "PRS", "XS", "SZL", "INS", "SBS", "DDS", "SDS", "SAT", "AOS", "24H", "EXW", "SM1", "SM2", "CS1", "TGS", "FDS", "FSS", "PSD", "DPV");
    protected $label_size = array("A6", "A6_PP", "A6_ONA4", "A4_2x2", "A4_4x1", "T_85x85");
    protected $config;
    protected $logger;

    /**
     * Request constructor
     * @param $options
     */
    public function __construct($options)
    {
        $this->config = $this->resolveOptions($options);
        try {
            v::key('username', v::stringType()->notEmpty()->length(1,20))
                ->key('password', v::stringType()->notEmpty()->length(1,20))
                ->key('client_number', v::stringType()->notEmpty()->length(1,20))
                ->key('country_code', v::stringType()->notEmpty()->in(array_keys($this->urls)))
                ->key('label_paper_size', v::stringType()->notEmpty()->in(array_values($this->label_size)))
                ->assert($this->config);
        } catch(NestedValidationException $exception) {
            echo $exception->getFullMessage();
        }

        if (!empty($this->config['log_dir'])) {
            $this->logger = $this->getLogger();
        }
    }
    /**
     * Get required options for the GLS API to work
     * @param $opts
     * @return mixed
     */
    protected function resolveOptions($opts)
    {
        $resolver = new OptionsResolver();
        $resolver->setDefault('url', '');
        $resolver->setDefault('client_number', '');
        $resolver->setDefault('country_code', 'HU');
        $resolver->setDefault('label_paper_size', 'A4_2x2');
        $resolver->setDefault('log_dir', '');
		$resolver->setDefault('log_rotation_days', '7');
		$resolver->setDefault('log_syslog', false);
        $resolver->setDefault('log_msg_format', ['{method} {uri} HTTP/{version} {req_body}','RESPONSE: {code} - {res_body}',]);
        $resolver->setRequired(['url', 'username', 'password','client_number']);
        return $resolver->resolve($opts);
    }

    /**
     * Get api url based on country code
     *
     * @return string
     */
    protected function getApiUrl()
    {
        return $this->urls[strtoupper($this->config['country_code'])];
    }



    /**
     * Send parcels in batch
     *
     * @param array $parcel_data
     * @return array <pre> {
     *      [clientRef] => '123'
     *      [clientRef] => 'Error Description'
     * } </pre>
     * @throws Exception\ParcelGeneration
     */
    public function getParcelNumbers($parcel_data) {
        date_default_timezone_set("Europe/Budapest");
        $data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $data .="<DTU EmailAddress=\"" . $parcel_data[0]['SenderEmail'] . "\" Version=\"16.12.15.01\" Created =\"" . date(DATE_ATOM) . "\" RequestType=\"GlsApiRequest\" MethodName=\"prepareLabels\">";
        $data .= '<Shipments>';

        foreach ($parcel_data as $parcel) {
            // validate parcel data
            $this->validateParcelPrepare($parcel);

			$parcel['CodCurr'] = $parcel['CodCurr'] ?? 'HUF';
            // the smallest fraction is 5 for COD amount
            $parcel['CodAmount'] = round((float)$parcel['CodAmount'] / 5, 0) * 5;
            $data .= "<Shipment SenderID=\"" . $this->config["client_number"] . "\" ExpSenderID=\"\" PickupDate=\"" . (isset($parcel["PickupDate"]) ? date(DATE_ATOM, strtotime($parcel["PickupDate"])) : date(DATE_ATOM)) . "\" ClientRef=\"" . $parcel['ClientRef'] . "\" CODAmount=\"" . $parcel['CodAmount'] . "\" CODCurr=\"" . $parcel['CodCurr'] . "\" CODRef=\"" . $parcel['CodRef'] . "\" PCount=\"" . (isset($parcel["Pcount"]) ? $parcel["Pcount"] : "1") . "\" Info=\"".(isset($parcel['ConsigComment']) ? $parcel['ConsigComment'] : "" ) . "\">";
            $data .= "<From Name=\"" . $parcel['SenderName'] . "\" Address=\"" . $parcel['SenderAddress'] . "\" ZipCode=\"" . $parcel['SenderZipcode'] . "\" City=\"" . $parcel['SenderCity'] . "\" CtrCode=\"" . $parcel['SenderCountry'] . "\" ContactName=\"" . $parcel['SenderContact'] . "\" ContactPhone=\"" . $parcel['SenderPhone'] . "\" EmailAddress=\"" . $parcel['SenderEmail'] . "\" />";
            $data .= "<To Name=\"" . $parcel['ConsigName'] . "\" Address=\"" . $parcel['ConsigAddress'] . "\" ZipCode=\"" . $parcel['ConsigZipcode'] . "\" City=\"" . $parcel['ConsigCity'] . "\" CtrCode=\"" . $parcel['ConsigCountry'] . "\" ContactName=\"" . $parcel['ConsigContact'] . " #" . $parcel["ClientRef"] . "\" ContactPhone=\"" . $parcel["ConsigPhone"] . "\" EmailAddress=\"" . $parcel["ConsigEmail"] . "\" />";
            if (!empty($parcel['Services'])) {
                $data .= '<Services>';
                foreach ($parcel['Services'] as $service_code => $service_parameter) {
                    $data .= "<Service Code=\"" . $service_code . "\" >";
                        $data .= '<Info>';
                            $data .= "<ServiceInfo InfoType=\"INFO\" InfoData=\"" . $service_parameter . "\" />";
                        $data .= '</Info>';
                    $data .= '</Service>';
                }
                $data .= '</Services>';
            }
            $data .= "</Shipment>";
        }
        $data .= "</Shipments>";
        $data .= "</DTU>";

        $in = array(
            "username" => $this->config["username"],
            "password" => $this->config["password"],
            "senderid" => $this->config["client_number"],
            "data" => base64_encode(gzencode($data,9))
        );

        $this->log('Request body encoded: ' . $data);

        try {
            $return = $this->requestNuSOAP('preparelabels_gzipped_xml', $in);
        }
        catch (\Exception $e) {
            throw new Exception\ParcelGeneration($e->getMessage());
        }

        $return = gzdecode($return);
        $this->log('Response body encoded: ' . $return);

        $doc = new \DOMDocument;
        $doc->loadXML($return);
        if ($doc->getElementsByTagName("Status")->item(0)->nodeValue == "success") {
            $return_data = $doc->getElementsByTagName("Shipment");
            $parcel_result = array();
            for ($i = $return_data->length; --$i >= 0;) {
                if (isset($return_data->item($i)->getElementsByTagName("long")->item(0)->nodeValue)) {
                    $parcel_result[$return_data->item($i)->getAttribute("ClientRef")] = $return_data->item($i)->getElementsByTagName("long")->item(0)->nodeValue;
                } elseif ($return_data->item($i)->getElementsByTagName("Status")->item(0)->nodeValue == "failed") {
                    $parcel_result[$return_data->item($i)->getAttribute("ClientRef")] = $return_data->item($i)->getElementsByTagName("Status")->item(0)->getAttribute("ErrorDescription");
                } else {
                    $parcel_result[$return_data->item($i)->getAttribute("ClientRef")] = $return_data->item($i)->getElementsByTagName("Status")->item(0)->getAttribute("ErrorCode");
                }
            }
            return $parcel_result;
        }
    }

    /**
     * get parcel labels
     *
     * @param array $parcel_ids
     * @return array
     * $parcel_result = [
     *      'status'            => 'success'
     *      'error_description' => 'Error Description'
     *      'pdf'               => is streamed labels. Use echo and correct pdf header to display
     * ]
     * @throws Exception\ParcelLabels
     */
    public function getParcelLabels($parcel_ids) {

        date_default_timezone_set("Europe/Budapest");
        $data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $data .="<DTU EmailAddress=\"test@gls-hungary.com\" Version=\"16.12.15.01\" Created=\"" . date(DATE_ATOM) . "\" RequestType=\"GlsApiRequest\" MethodName=\"printLabels\">";
        $data .= '<Shipments>';
        foreach ($parcel_ids as $parcel_id) {
            $data .= "<Shipment><PclIDs><long>";
            $data .= $parcel_id;
            $data .= "</long></PclIDs></Shipment>";
        }
        $data .= "</Shipments>";
        $data .= "</DTU>";

        $in = array(
            "username" => $this->config["username"],
            "password" => $this->config["password"],
            "senderid" => $this->config["client_number"],
            "data" => base64_encode(gzencode($data,9)),
            "printertemplate"=>$this->config["label_paper_size"],
            "is_autoprint_pdfs"=>false
        );

        try {
            $return = $this->requestNuSOAP('getprintedlabels_gzipped_xml', $in);
        }
        catch (\Exception $e) {
            throw new Exception\ParcelLabels($e->getMessage());
        }

        $return = gzdecode($return);

        $doc = new \DOMDocument;
        $doc->loadXML($return);
        $return_data = $doc->getElementsByTagName("Parcels");

        $parcel_result = array();

        $parcel_result['status']            = $doc->getElementsByTagName("Status")->item(0)->nodeValue;
        $parcel_result['error_description'] = $doc->getElementsByTagName("Status")->item(0)->getAttribute("ErrorDescription");
        if (isset($return_data->item(0)->getElementsByTagName("Label")->item(0)->nodeValue)) {
            $parcel_result['pdf']           = base64_decode($return_data->item(0)->getElementsByTagName("Label")->item(0)->nodeValue);
        } else {
            $parcel_result['pdf'] = '';
        }

        // Logging
        $this->log('Request body encoded: ' . $data);
        $this->log('Response body status: ' . $parcel_result['status']);
        $this->log('Response body error description: ' . $parcel_result['error_description']);

        return $parcel_result;
    }

	/**
	 * Get parcel status
	 *
	 * @param $parcelNumber
	 * @return int
	 */
	public function getParcelStatus($parcelNumber) {
		$config_array = [
            'verify' => false,
            'debug' => false
        ];
        $client = new Client($config_array);
        $response = $client->request("GET", $this->getTrackingUrlXml($parcelNumber));
        $xml = simplexml_load_string($response->getBody());

		try {
            if ($xml !== FALSE) {
            	$delivery_code = $xml->Parcel->Statuses->Status[0]['StCode'];
            } else {
        		throw new Exception('Tracking code wasn`t registered or error occured!');
            }
		} catch (\Exception $e) {
                echo $e->getMessage();
		}

		return isset($delivery_code) ? (int)$delivery_code : false;
	}

	public function getTrackingUrl($parcelNumber, $language = 'en') {
		return "http://online.gls-hungary.com/tt_page.php?tt_value=$parcelNumber&lng=$language";
	}

	public function getTrackingUrlXml($parcelNumber) {
		return "http://online.gls-hungary.com/tt_page_xml.php?pclid=$parcelNumber";
	}

    /**
     * Delete parcels in batch
     *
     * @param array $parcel_ids
     * @return array
     * $parcel_result = [
     *      $parcel_id            => $result_msg
     * ]
     * @throws Exception\ParcelDeletion
     */
    public function deleteParcels($parcel_ids) {

        date_default_timezone_set("Europe/Budapest");
        $data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $data .="<DTU EmailAddress=\"test@gls-hungary.com\" Version=\"16.12.15.01\" Created=\"" . date(DATE_ATOM) . "\" RequestType=\"GlsApiRequest\" MethodName=\"deleteLabels\">";
        $data .= '<Shipments>';
        foreach ($parcel_ids as $parcel_id) {
            $data .= "<Shipment><PclIDs><long>";
            $data .= $parcel_id;
            $data .= "</long></PclIDs></Shipment>";
        }
        $data .= "</Shipments>";
        $data .= "</DTU>";

        $this->log('Request body encoded: ' . $data);

        $in = array(
            "username" => $this->config["username"],
            "password" => $this->config["password"],
            "senderid" => $this->config["client_number"],
            "data" => base64_encode(gzencode($data,9))
        );

        try {
            $return = $this->requestNuSOAP('deletelabels_gzipped_xml', $in);
        }
        catch (\Exception $e) {
            throw new Exception\ParcelDeletion($e->getMessage());
        }

        $return = gzdecode($return);
        $this->log('Response body encoded: ' . $return);

        $parcel_result = array();
        if (!empty($return)) {
            $doc = new \DOMDocument;
            $doc->loadXML($return);
            $return_data = $doc->getElementsByTagName("Shipment");
            for ($i = $return_data->length; --$i >= 0;) {
                if ($return_data->item($i)->getElementsByTagName("Status")->item(0)->nodeValue == "success") {
                    $parcel_result[$return_data->item($i)->getElementsByTagName("Parcel")->item(0)->getAttribute("PclId")] = $return_data->item($i)->getElementsByTagName("Status")->item(0)->nodeValue;
                } elseif ($return_data->item($i)->getElementsByTagName("Status")->item(0)->nodeValue == "failed") {
                    $parcel_result[$return_data->item($i)->getElementsByTagName("Parcel")->item(0)->getAttribute("PclId")] = $return_data->item($i)->getElementsByTagName("Status")->item(0)->getAttribute("ErrorDescription");
                } else {
                    $parcel_result[$return_data->item($i)->getElementsByTagName("Parcel")->item(0)->getAttribute("PclId")] = $return_data->item($i)->getElementsByTagName("Status")->item(0)->getAttribute("ErrorCode");
                }
            }
        }

        return $parcel_result;
    }


	/**
	 * @param string $method
	 * @param array $data
	 * @return mixed
	 */
	protected function requestNuSOAP($method, $data = array()) {
		$client = new nusoap_client($this->getApiUrl(), 'wsdl');

        $error  = $client->getError();
        if ($error) {
            $this->logger->error($error);
        }

        $result = $client->call($method, $data);

        // log soap request and response
//        $this->log('Request: ' . $client->request);
//        $this->log('Response: ' . $client->response);
        // log soap result
//        if ($client->fault) {
//            $this->log($result);
//        } else {
//            $error = $client->getError();
//            if ($error) {
//                $this->logger->error($error);
//            } else {
//                $this->log($result);
//            }
//        }


		return $result;
	}

    protected function request($uri, $data = array(), $method = 'GET', array $headers = array()) {
        $config_array = [
            'verify' => false,
            'debug' => false
        ];
        $client = new Client($config_array);
        $response = $client->request($method, $uri, ['query' => $data, 'headers' =>$headers]);

		return $response->getBody()->getContents();
	}

    /**
     *	Logger functionality: Creates a log file for each day with all requests and responses
     */
    private function getLogger()
    {
        if (empty($this->logger)) {
            $this->logger = new \Monolog\Logger('api-gls-consumer');
			$this->logger->pushHandler(
                new \Monolog\Handler\RotatingFileHandler( $this->config['log_dir'] . 'api-gls-consumer.log', $this->config['log_rotation_days'])
            );
        }
		if (!empty($this->config['log_syslog'])) {
			$this->logger->pushHandler(
				new \Monolog\Handler\SyslogHandler('api-gls-consumer')
			);
		}

        return $this->logger;
    }

    private function log($msg)
    {
        if ($this->logger) {
            $this->logger->info($msg);
        }
    }

    /**
     *	Validation functions
     */
    private function validateParcelPrepare ($data) {
        try {
            v::key('Services', v::ArrayType())
                // check date format for pickup date
                ->key('PickupDate', v::date('Y-m-d')->notEmpty())
                ->assert($data);
            v::optional(
                // check cod ref length
                v::key('CodRef', v::stringType()->length(0,512))
                //check phone number for receiver (international format)
                ->keyNested('Services.FSS',v::phone())
            )->assert($data);
        } catch(NestedValidationException $exception) {
            echo $exception->getFullMessage();
        }
    }
}