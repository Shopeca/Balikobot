<?php

namespace Shopeca\Balikobot;

use Shopeca\Balikobot\Exceptions\CustomerInvalidArgumentException;

/**
 * @author Miroslav Merinsky <miroslav@merinsky.biz>
 * @version 1.0
 */
class Balikobot
{
	/** @var array
	 *
	 * each branch contains following params
	 * string $apiUser
	 * string $apiKey
	 * int $apiShopId Own identification of the shop; will be used to identify packages if you use one account for several shops
	 */
	protected $apiBranches;

	/** @var int */
	protected $activeApiBranch;

	/** @var string */
	protected $apiUrl = 'https://api.balikobot.cz';

	/** @var array */
	protected $data = [
		'isService' => false,
		'isCustomer' => false,
		'isCashOnDelivery' => false,
		'shipper' => null,
		'data' => [],
	];

	/** @var bool */
	protected $autoTrim = false;

	/**
	 * @param array $apiBranches
	 *
	 * each branch contains following params
	 * string $apiUser
	 * string $apiKey
	 * int $apiShopId Own identification of the shop; will be used to identify packages if you use one account for several shops
	 */
	public function __construct($apiBranches)
	{
		foreach ($apiBranches as $id => $branch) {
			if (empty($branch['apiUser']) || empty($branch['apiKey']) || empty($branch['apiShopId'])) {
				throw new \InvalidArgumentException('Invalid argument has been entered for branch ' . $id);
			}
			if (!is_int($branch['apiShopId'])) {
				throw new \InvalidArgumentException('Invalid apiShopId has been entered. Enter number for branch ' . $id);
			}
		}

		$this->apiBranches = $apiBranches;
	}

	/**
	 * @return int
	 * @throws \Exception
	 */
	public function getActiveApiBranch()
	{
		if (!array_key_exists($this->activeApiBranch, $this->apiBranches)) {
			throw new \Exception('No active api branch found. Please, select the branch.');
		}
		return $this->activeApiBranch;
	}

	/**
	 * @param int $activeApiBranch
	 */
	public function setActiveApiBranch($activeApiBranch)
	{
		$this->activeApiBranch = $activeApiBranch;
	}

	/**
	 * @return string
	 */
	public function getApiKey()
	{
		return $this->apiBranches[$this->getActiveApiBranch()]['apiKey'];
	}

	/**
	 * @return string
	 */
	public function getApiUser()
	{
		return $this->apiBranches[$this->getActiveApiBranch()]['apiUser'];
	}

	/**
	 * @return string
	 */
	public function getApiShopId()
	{
		return $this->apiBranches[$this->getActiveApiBranch()]['apiShopId'];
	}

	/**
	 * @return array
	 */
	public function getApiBranches()
	{
		return $this->apiBranches;
	}

	/**
	 * Sets service
	 *
	 * @param string $shipper
	 * @param string|int $service
	 * @param array $options
	 * @return $this
	 */
	public function service($shipper, $service, array $options = [])
	{
		$availableServices = $this->getServices($shipper);
		if (count($availableServices) > 0) {
			if (empty($shipper) || empty($service)) {
				throw new \InvalidArgumentException('Invalid argument has been entered.');
			}
			if (!isset($availableServices[$service])) {
				throw new \InvalidArgumentException("Invalid $service service for $shipper shipper.");
			}
		}
		if (empty($shipper)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}
		if (!in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException("Unknown $shipper shipper.");
		}


		// clean first
		$this->clean();

		// test if options are valid
		$validOptions = $this->getOptions($shipper);
		foreach ($options as $key => $v) {
			if (!in_array($key, $validOptions)) {
				throw new \InvalidArgumentException("Invalid $key option for $shipper shipper.");
			}
		}

		switch ($shipper) {
			case BalikobotEnum::SHIPPER_CP:
				if (!isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException("The price option is required for $shipper shipper.");
				}
				break;

			case BalikobotEnum::SHIPPER_DPD:
				if ($service == 3 /* pickup */) {
					if (empty($options[BalikobotEnum::OPTION_BRANCH])) {
						throw new \InvalidArgumentException('The branch option is required for pickup service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_PPL:
				if (($service == 15) || ($service == 19)) /* palette shipping */ {
					if (!isset($options[BalikobotEnum::OPTION_MU_TYPE])) {
						throw new \InvalidArgumentException('The mu type option is required for this service.');
					}
					if (!isset($options[BalikobotEnum::OPTION_WEIGHT])) {
						throw new \InvalidArgumentException('The weight option is required for this service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_ZASILKOVNA:
				if (!isset($options[BalikobotEnum::OPTION_BRANCH])) {
					throw new \InvalidArgumentException("The branch option is required for $shipper shipper.");
				}
				if (!isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException("The price option is required for $shipper shipper.");
				}
				break;

			case BalikobotEnum::SHIPPER_GEIS:
				if (isset($options[BalikobotEnum::OPTION_INSURANCE]) && !isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException('The price option is required for insurance option.');
				}
				if ($service == 6 /* pickup */) {
					if (empty($options[BalikobotEnum::OPTION_BRANCH])) {
						throw new \InvalidArgumentException('The branch option is required for pickup service.');
					}
				} elseif (($service == 4) || ($service == 5)) /* palette */ {
					if (empty($options[BalikobotEnum::OPTION_MU_TYPE])) {
						throw new \InvalidArgumentException('The mu type option is required for pickup service.');
					}
					if (empty($options[BalikobotEnum::OPTION_WEIGHT])) {
						throw new \InvalidArgumentException('The weight option is required for pickup service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_ULOZENKA:
				if (in_array($service, [1, 5, 7, 10, 11])) /* pickup */ {
					if (empty($options[BalikobotEnum::OPTION_BRANCH])) {
						throw new \InvalidArgumentException('The branch option is required for pickup service.');
					}
				}
				if ($service == 2) {
					if (!isset($options[BalikobotEnum::OPTION_PRICE])) {
						throw new \InvalidArgumentException("The price option is required for this service.");
					}
				}
				if (in_array($service, [2, 6, 7])) {
					if (empty($options[BalikobotEnum::OPTION_WEIGHT])) {
						throw new \InvalidArgumentException('The weight option is required for this service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_INTIME:
				if (isset($options[BalikobotEnum::OPTION_INSURANCE]) && !isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException('The price option is required for insurance option.');
				}
				if (($service == 4) || ($service == 5)) /* pickup */ {
					if (empty($options[BalikobotEnum::OPTION_BRANCH])) {
						throw new \InvalidArgumentException('The branch option is required for pickup service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_GLS:
				if (!isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException("The price option is required for $shipper shipper.");
				}
				if ($service == 2 /* pickup */) {
					if (empty($options[BalikobotEnum::OPTION_BRANCH])) {
						throw new \InvalidArgumentException('The branch option is required for pickup service.');
					}
				}
				break;

			case BalikobotEnum::SHIPPER_TOPTRANS:
				if (empty($options[BalikobotEnum::OPTION_MU_TYPE_ONE])) {
					throw new \InvalidArgumentException('The mu type option is required for this service.');
				}
				if (empty($options[BalikobotEnum::OPTION_WEIGHT])) {
					throw new \InvalidArgumentException('The weight option is required for this service.');
				}
				break;

			case BalikobotEnum::SHIPPER_PBH:
				if (!isset($options[BalikobotEnum::OPTION_PRICE])) {
					throw new \InvalidArgumentException("The price option is required for $shipper shipper.");
				}
				break;
		}

		// save options
		foreach ($options as $name => $value) {
			$this->saveOption($name, $value, $shipper);
		}
		$this->data['data']['service_type'] = $service;
		$this->data['shipper'] = $shipper;

		$this->data['isService'] = true;

		return $this;
	}

	/**
	 * Sets customer data
	 *
	 * @param string $name
	 * @param string $street
	 * @param string $city
	 * @param string $zip
	 * @param string $phone
	 * @param string $email
	 * @param string $company
	 * @param string $country
	 * @return $this
	 */
	public function customer(
		$name,
		$street,
		$city,
		$zip,
		$phone,
		$email,
		$company = null,
		$country = BalikobotEnum::COUNTRY_CZECHIA
	) {
		$failedArguments = [];
		if (empty($name)) {
			$failedArguments[] = 'name';
		}
		if (empty($street)) {
			$failedArguments[] = 'street';
		}
		if (empty($city)) {
			$failedArguments[] = 'city';
		}
		if (empty($zip)) {
			$failedArguments[] = 'zip';
		}
		if (empty($phone)) {
			$failedArguments[] = 'phone';
		}
		if (empty($email)) {
			$failedArguments[] = 'email';
		}
		if (count($failedArguments) > 0) {
			throw new CustomerInvalidArgumentException('Invalid argument has been entered.', 1, $failedArguments);
		}
		if (!in_array($country, BalikobotEnum::getCountryCodes())) {
			throw new CustomerInvalidArgumentException('Invalid country code has been entered.', 2, $country);
		}

		switch ($country) {
			case BalikobotEnum::COUNTRY_CZECHIA:
				if (!preg_match('/^\d{5}$/', $zip)) {
					throw new CustomerInvalidArgumentException('Invalid zip code has been entered. Match XXXXX pattern.', 3, $zip);
				}
				break;
			default:
				trigger_error("Validation method is not implemented for country {$country}.", E_USER_WARNING);
		}

		if (!preg_match('/^(\+|00)42[01]\d{9}$/', $phone)) {
			throw new CustomerInvalidArgumentException('Invalid phone has been entered. Match +420YYYYYYYYY pattern.', 4, $phone);
		}

		$this->data['data']['rec_name'] = $name;
		$this->data['data']['rec_street'] = $street;
		$this->data['data']['rec_city'] = $city;
		$this->data['data']['rec_zip'] = $zip;
		$this->data['data']['rec_phone'] = $phone;
		$this->data['data']['rec_email'] = $email;
		$this->data['data']['rec_country'] = $country;
		if (isset($company)) {
			$this->data['data']['rec_firm'] = $company;
		}

		$this->data['isCustomer'] = true;

		return $this;
	}

	/**
	 * Sets cash on delivery
	 *
	 * @param float $price
	 * @param string|int $variableSymbol
	 * @param string $currency
	 * @return $this
	 */
	public function cashOnDelivery($price, $variableSymbol, $currency = BalikobotEnum::CURRENCY_CZK)
	{
		if (empty($price) || empty($variableSymbol)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}
		if (!is_numeric($price)) {
			throw new \InvalidArgumentException('Invalid price has been entered.');
		}
		if (!is_numeric($variableSymbol)) {
			throw new \InvalidArgumentException('Invalid variable symbol has been entered.');
		}
		if (!in_array($currency, BalikobotEnum::getCurrencies())) {
			throw new \InvalidArgumentException('Invalid currency has been entered.');
		}

		$this->data['data'][BalikobotEnum::OPTION_COD_PRICE] = (float) $price;
		$this->data['data'][BalikobotEnum::OPTION_VS] = $variableSymbol;
		$this->data['data'][BalikobotEnum::OPTION_COD_CURRENCY] = $currency;

		$this->data['isCashOnDelivery'] = true;

		return $this;
	}

	/**
	 * Removes cash on delivery from package
	 *
	 * @return $this
	 */
	public function cleanCashOnDelivery()
	{
		$unsetValues = [BalikobotEnum::OPTION_COD_PRICE, BalikobotEnum::OPTION_VS, BalikobotEnum::OPTION_COD_CURRENCY];
		foreach ($unsetValues as $unsetValue) {
			if (array_key_exists($unsetValue, $this->data['data'])) {
				unset($this->data['data'][$unsetValue]);
			}
		}

		$this->data['isCashOnDelivery'] = false;

		return $this;
	}

	/**
	 * @return array(
	 *     'carrier_id' => track and trace package id,
	 *     'package_id' => identification used by API request,
	 *     'label_url' => url to the label,
	 *     'eid' => Generated EID of the package
	 * )
	 * @param string $eid MAX 40 alphanumeric characters. Will be generated if not supplied
	 * @param bool $test
	 * @param bool clean
	 */
	public function add($eid = null, $test = false, $clean = true)
	{
		if (!$this->data['isService'] || !$this->data['isCustomer']) {
			throw new \UnexpectedValueException('Call service and customer method before.');
		}

		$orderId = isset($this->data['data'][BalikobotEnum::OPTION_ORDER]) ? sprintf(
			'%\'010s',
			$this->data['data'][BalikobotEnum::OPTION_ORDER]
		) : '0000000000';
		if (isset($eid)) {
			$this->data['data']['eid'] = $eid;
		} else {
			$this->data['data']['eid'] = $this->getEid(null, $orderId);
		}
		$this->data['data']['return_full_errors'] = true;
		// add only one package
		$response = $this->call($test ? BalikobotEnum::REQUEST_CHECK : BalikobotEnum::REQUEST_ADD, $this->data['shipper'], [$this->data['data']]);
		$response[0]["eid"]=$this->data['data']['eid'];

		if ($clean) {
			$this->clean();
		}

		if (!isset($response[0]['package_id']) && ($test === false || !isset($response[0]['status']) || $response[0]['status'] != '200')) {
			$errorMsg = "";
			if (isset($response[0]['errors'])) {
				foreach ($response[0]['errors'] as $error) {
					$errorMsg .= $error['attribute'] . ": " . $error['message'] . " [" . $error['type'] . "]";
				}
			} else {
				$errorMsg = var_export($response[0], true);
			}
			throw new \InvalidArgumentException(
				'Invalid arguments. Errors: ' . $errorMsg,
				BalikobotEnum::EXCEPTION_INVALID_REQUEST
			);
		}

		return $response[0];
	}

	/**
	 * Returns available services for the given shipper
	 *
	 * @param string $shipper
	 * @return array
	 */
	public function getServices($shipper)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_SERVICES, $shipper);
		if (isset($response['status']) && ($response['status'] == 409)) {
			throw new \InvalidArgumentException("The $shipper shipper is not supported.", BalikobotEnum::EXCEPTION_NOT_SUPPORTED);
		}
		if (!isset($response['status']) || ($response['status'] != 200)) {
			$code = isset($response['status']) ? $response['status'] : 0;
			throw new \UnexpectedValueException("Unexpected server response, code = $code.", BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		return (!empty($response['service_types'])) ? $response['service_types'] : [];
	}

	/**
	 * Returns available manipulation units for the given shipper
	 *
	 * @param string $shipper
	 * @return array
	 */
	public function getManipulationUnits($shipper)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_MANIPULATIONUNITS, $shipper);

		if (isset($response['status']) && ($response['status'] == 409)) {
			throw new \InvalidArgumentException("The $shipper shipper is not supported.", BalikobotEnum::EXCEPTION_NOT_SUPPORTED);
		}
		if (!isset($response['status']) || ($response['status'] != 200)) {
			$code = isset($response['status']) ? $response['status'] : 0;
			throw new \UnexpectedValueException("Unexpected server response, code = $code.", BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		if ($response['units'] === null) {
			return [];
		}

		$units = [];

		foreach ($response['units'] as $item) {
			$units[$item['code']] = $item['name'];
		}

		return $units;
	}

	/**
	 * Returns available branches for the given shipper and its service
	 *
	 * @param string $shipper
	 * @param string $service
	 * @param bool $full Calls full branches instead branches request; currently available only for zasilkovna shipper
	 * @return array
	 */
	public function getBranches($shipper, $service = null, $full = false)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call($full ? BalikobotEnum::REQUEST_FULLBRANCHES : BalikobotEnum::REQUEST_BRANCHES, $shipper, [], $service);

		if (isset($response['status']) && ($response['status'] == 409)) {
			throw new \InvalidArgumentException("The $shipper shipper is not supported.", BalikobotEnum::EXCEPTION_NOT_SUPPORTED);
		}
		if (!isset($response['status']) || ($response['status'] != 200)) {
			$code = isset($response['status']) ? $response['status'] : 0;
			throw new \UnexpectedValueException("Unexpected server response, code = $code.", BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		if ($response['branches'] === null) {
			return [];
		}

		$branches = [];
		$id = 'id';

		if ($shipper == BalikobotEnum::SHIPPER_CP) {
			$id = 'zip';
		} elseif ($shipper == BalikobotEnum::SHIPPER_INTIME) {
			$id = 'name';
		}

		foreach ($response['branches'] as $item) {
			$branches[$item[$id]] = $item;
		}

		return $branches;
	}

	/**
	 * Returns list of countries where service is available in
	 *
	 * @param string $shipper
	 * @return array
	 */
	public function getCountriesForService($shipper)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_COUNTRIES4SERVICE, $shipper);

		if (isset($response['status']) && ($response['status'] == 409)) {
			throw new \InvalidArgumentException("The $shipper shipper is not supported.", BalikobotEnum::EXCEPTION_NOT_SUPPORTED);
		}
		if (!isset($response['status']) || ($response['status'] != 200)) {
			$code = isset($response['status']) ? $response['status'] : 0;
			throw new \UnexpectedValueException("Unexpected server response, code = $code.", BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		if ($response['service_types'] === null) {
			return [];
		}

		$services = [];

		foreach ($response['service_types'] as $item) {
			$services[$item['service_type']] = $item['countries'];
		}

		return $services;
	}

	/**
	 * Returns available branches for the given shipper and its service
	 *
	 * @param string $shipper
	 * @param string $service
	 * @return array
	 */
	public function getZipCodes($shipper, $service, $country = BalikobotEnum::COUNTRY_CZECHIA)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())
		    || empty($service) || !isset($this->getServices($shipper)[$service])
		    || !in_array($country, BalikobotEnum::getCountryCodes())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_ZIPCODES, $shipper, [], "$service/$country");

		if (isset($response['status']) && ($response['status'] == 409)) {
			throw new \InvalidArgumentException("The $shipper shipper is not supported.", BalikobotEnum::EXCEPTION_NOT_SUPPORTED);
		}
		if (!isset($response['status']) || ($response['status'] != 200)) {
			$code = isset($response['status']) ? $response['status'] : 0;
			throw new \UnexpectedValueException("Unexpected server response, code = $code.", BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		if ($response['zip_codes'] === null) {
			return [];
		}

		$zip = [];

		// type item indicates if structure is zip or zip codes, but for some shippers response structure is wrong
		// so we test if zip exist
		if (isset($response['zip_codes'][0]['zip'])) {
			foreach ($response['zip_codes'] as $item) {
				$zip[] = $item['zip'];
			}
		} elseif (isset($response['zip_codes'][0]['zip_start']) && isset($response['zip_codes'][0]['zip_end'])) {
			foreach ($response['zip_codes'] as $item) {
				$zip[] = [$item['zip_start'], $item['zip_end']];
			}
		}

		return $zip;
	}

	/**
	 * Drops a package from the front
	 * The package must not ordered
	 *
	 * @param string $shipper
	 * @param int $packageId
	 */
	public function dropPackage($shipper, $packageId)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers()) || empty($packageId)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_DROP, $shipper, ['id' => $packageId]);

		if (!isset($response['status'])) {
			throw new \UnexpectedValueException('Unexpected server response.', BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}
		if ($response['status'] == 404) {
			throw new \UnexpectedValueException(
				'The package does not exist or it was ordered.',
				BalikobotEnum::EXCEPTION_INVALID_REQUEST
			);
		}
		if ($response['status'] != 200) {
			throw new \UnexpectedValueException(
				"Unexpected server response, code={$response['status']}.",
				BalikobotEnum::EXCEPTION_SERVER_ERROR
			);
		}
	}

	/**
	 * Tracks a package
	 *
	 * @param string $shipper
	 * @param string $carrierId
	 * @return array
	 */
	public function trackPackage($shipper, $carrierId)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers()) || empty($carrierId)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_TRACK, $shipper, ['id' => $carrierId]);

		if (isset($response['status']) && ($response['status'] != 200)) {
			throw new \UnexpectedValueException(
				"Unexpected server response, code={$response['status']}.",
				BalikobotEnum::EXCEPTION_SERVER_ERROR
			);
		}
		if (empty($response[0])) {
			throw new \UnexpectedValueException('Unexpected server response.', BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		return $response[0];
	}

	/**
	 * Tracks a package statuses
	 *
	 * @param string $shipper
	 * @param string[] $carrierIds
	 * @return array Array of statuses in the same order as supplied $carrierIds, example: array( 0 => array(‚status_id‘ => 1, ‚status_text‘ => ‚Zásilka byla doručena příjemci.‘), 1 => array(‚status_id‘ => 2, ‚status_text‘ => ‚Zásilka je doručována příjemci.‘));
	 */
	public function trackStatus($shipper, $carrierIds)
	{
		if (empty($shipper)|| empty($carrierIds) || count($carrierIds)>4 || !in_array($shipper, $this->getShippers()) ) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}
		$requestData = [];
		foreach ($carrierIds as $carrierId){
		    $requestData[]=["id"=>$carrierId];
        }

		$response = $this->call(self::REQUEST_TRACKSTATUS, $shipper, $requestData);

		if (isset($response['status']) && ($response['status'] != 200)) {
			throw new \UnexpectedValueException(
				"Unexpected server response, code={$response['status']}.",
				self::EXCEPTION_SERVER_ERROR
			);
		}
		if (!isset($response[0]["status_id"]) || !isset($response[0]["status_text"])) {
			throw new \UnexpectedValueException('Unexpected server response.', self::EXCEPTION_SERVER_ERROR);
		}

		return $response;
	}

	/**
	 * Tracks a package, get the last info
	 *
	 * @param string $shipper
	 * @param string $carrierId
     * @see trackStatus for multiple packages at once
	 * @return array
	 */
	public function trackPackageLast($shipper, $carrierId)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers()) || empty($carrierId)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_TRACKSTATUS, $shipper, ['id' => $carrierId]);

		if (isset($response['status']) && ($response['status'] != 200)) {
			throw new \UnexpectedValueException(
				"Unexpected server response, code={$response['status']}.",
				BalikobotEnum::EXCEPTION_SERVER_ERROR
			);
		}
		if (empty($response[0])) {
			throw new \UnexpectedValueException('Unexpected server response.', BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}

		return $response[0];
	}

	/**
	 * Checks if there are packages in the front (not ordered)
	 *
	 * @param string $shipper
	 */
	public function overview($shipper)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_OVERVIEW, $shipper);

		if (isset($response['status']) && ($response['status'] == 404)) {
			throw new \UnexpectedValueException('No packages.', BalikobotEnum::EXCEPTION_INVALID_REQUEST);
		}

		return $response;
	}

	/**
	 * Gets labels
	 *
	 * @param string $shipper
	 * @param array $packages
	 * @return string
	 */
	public function getLabels($shipper, array $packages)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers()) || empty($packages)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_LABELS, $shipper, ['package_ids' => $packages]);

		if (isset($response['status']) && ($response['status'] != 200)) {
			throw new \UnexpectedValueException('Invalid data or invalid packages number.', BalikobotEnum::EXCEPTION_INVALID_REQUEST);
		}

		return $response['labels_url'];
	}

	/**
	 * Gets complete information about a package
	 *
	 * @param string $shipper
	 * @param int $packageId
	 * @return array
	 */
	public function getPackageInfo($shipper, $packageId)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers()) || empty($packageId)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_PACKAGE, $shipper, null, $packageId);

		if (isset($response['status']) && ($response['status'] == 404)) {
			throw new \UnexpectedValueException('Invalid package number.', BalikobotEnum::EXCEPTION_INVALID_REQUEST);
		}

		return $response;
	}

	/**
	 * Order packages' collection
	 *
	 * @param string $shipper
	 * @param array $packages
	 * @return array
	 */
	public function order($shipper, array $packages = [])
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$response = $this->call(BalikobotEnum::REQUEST_ORDER, $shipper, empty($packages) ? [] : ['package_ids' => $packages]);

		if (!isset($response['status'])) {
			throw new \UnexpectedValueException('Unexpected server response.', BalikobotEnum::EXCEPTION_SERVER_ERROR);
		}
		if ($response['status'] == 406) {
			throw new \UnexpectedValueException('Invalid package numbers.', BalikobotEnum::EXCEPTION_INVALID_REQUEST);
		}
		if ($response['status'] != 200) {
			throw new \UnexpectedValueException(
				"Unexpected server response, code={$response['status']}.",
				BalikobotEnum::EXCEPTION_SERVER_ERROR
			);
		}

		return $response;
	}

	// helpers ---------------------------------------------------------------------------------------------------------

	/**
	 * Returns available options for the given shipper
	 *
	 * @param string $shipper
	 * @return array
	 */
	public function getOptions($shipper)
	{
		if (empty($shipper) || !in_array($shipper, BalikobotEnum::getShippers())) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		switch ($shipper) {
			case BalikobotEnum::SHIPPER_CP:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_SERVICES,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_DPD:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_SMS_NOTIFICATION,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_INSURANCE,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_GEIS:
				return [
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_INSURANCE,
					BalikobotEnum::OPTION_PAY_BY_CUSTOMER,
					BalikobotEnum::OPTION_NOTE,
					// palette
					BalikobotEnum::OPTION_MU_TYPE,
					BalikobotEnum::OPTION_PIECES,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_PAY_BY_CUSTOMER,
					BalikobotEnum::OPTION_SMS_NOTIFICATION,
					BalikobotEnum::OPTION_PHONE_NOTIFICATION,
					BalikobotEnum::OPTION_B2C,
					BalikobotEnum::OPTION_NOTE_DRIVER,
					BalikobotEnum::OPTION_NOTE_CUSTOMER,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_GLS:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_ORDER_NUMBER,
					BalikobotEnum::OPTION_NOTE,
				];

			case BalikobotEnum::SHIPPER_INTIME:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_INSURANCE,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_PBH:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
				];

			case BalikobotEnum::SHIPPER_PPL:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_INSURANCE,
					// palette
					BalikobotEnum::OPTION_MU_TYPE,
					BalikobotEnum::OPTION_PIECES,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_PAY_BY_CUSTOMER,
					BalikobotEnum::OPTION_COMFORT,
					BalikobotEnum::OPTION_RETURN_OLD_HA,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_TOPTRANS:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_TT_MU_TYPE,
					BalikobotEnum::OPTION_TT_PIECES,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_COMFORT,
					BalikobotEnum::OPTION_ORDER_NUMBER,
					BalikobotEnum::OPTION_MU_TYPE_ONE,
					BalikobotEnum::OPTION_PIECES_ONE,
					BalikobotEnum::OPTION_MU_TYPE_TWO,
					BalikobotEnum::OPTION_PIECES_TWO,
					BalikobotEnum::OPTION_MU_TYPE_THREE,
					BalikobotEnum::OPTION_PIECES_THREE,
				];

			case BalikobotEnum::SHIPPER_ULOZENKA:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_NOTE,
					BalikobotEnum::OPTION_AGE,
					BalikobotEnum::OPTION_PASSWORD,
					BalikobotEnum::OPTION_ORDER_NUMBER,
				];

			case BalikobotEnum::SHIPPER_ZASILKOVNA:
				return [
					BalikobotEnum::OPTION_PRICE,
					BalikobotEnum::OPTION_ORDER,
					BalikobotEnum::OPTION_BRANCH,
					BalikobotEnum::OPTION_WEIGHT,
					BalikobotEnum::OPTION_ORDER_NUMBER,
					BalikobotEnum::OPTION_COD_CURRENCY,
				];
		}

		return [];
	}

	/**
	 * @param bool $option
	 */
	public function setAutoTrim($option)
	{
		$this->autoTrim = $option === true;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @param string $shipper
	 */
	public function saveOption($name, $value, $shipper = null)
	{
		if (empty($name)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		switch ($name) {
			case BalikobotEnum::OPTION_BRANCH:
				// do nothing
				break;

			case BalikobotEnum::OPTION_MU_TYPE:
			case BalikobotEnum::OPTION_TT_MU_TYPE:
				// do nothing
				break;

			case BalikobotEnum::OPTION_SERVICES:
				if (!is_array($value)) {
					throw new \InvalidArgumentException('Invalid value of services option has been entered.');
				}

				$cpServices = BalikobotEnum::getOptionServices();

				foreach ($value as $serviceItem) {
					if (!in_array($serviceItem, $cpServices)) {
						throw new \InvalidArgumentException("Invalid $serviceItem value of services option has been entered.");
					}
				}

				$value = implode('+', $value);
				break;

			case BalikobotEnum::OPTION_SMS_NOTIFICATION:
			case BalikobotEnum::OPTION_INSURANCE:
			case BalikobotEnum::OPTION_PAY_BY_CUSTOMER:
			case BalikobotEnum::OPTION_COMFORT:
			case BalikobotEnum::OPTION_RETURN_OLD_HA:
			case BalikobotEnum::OPTION_PHONE_NOTIFICATION:
			case BalikobotEnum::OPTION_B2C:
			case BalikobotEnum::OPTION_AGE:
				if (!is_bool($value)) {
					throw new \InvalidArgumentException("Invalid value of $name option has been entered. Enter boolean.");
				}

				$value = (bool) $value;
				break;

			case BalikobotEnum::OPTION_PRICE:
			case BalikobotEnum::OPTION_WEIGHT:
				if (!is_numeric($value)) {
					throw new \InvalidArgumentException("Invalid value of $name option has been entered. Enter float.");
				}

				$value = (float) $value;
				break;

			case BalikobotEnum::OPTION_NOTE:
			case BalikobotEnum::OPTION_NOTE_DRIVER:
			case BalikobotEnum::OPTION_NOTE_CUSTOMER:
			case BalikobotEnum::OPTION_PASSWORD:
				if (!is_string($value)) {
					throw new \InvalidArgumentException('Invalid value of note option has been entered. Enter string.');
				}

				$limit = 64;

				if ($shipper == BalikobotEnum::SHIPPER_DPD) {
					$limit = 70;
				} elseif ($shipper == BalikobotEnum::SHIPPER_PPL) {
					$limit = 350;
				} elseif ($shipper == BalikobotEnum::SHIPPER_CP) {
					$limit = 50;
				} elseif ($shipper == BalikobotEnum::SHIPPER_GEIS) {
					$limit = ($name == BalikobotEnum::OPTION_NOTE) ? 57 : 62;
				} elseif ($shipper == BalikobotEnum::SHIPPER_ULOZENKA) {
					$limit = ($name == BalikobotEnum::OPTION_PASSWORD) ? 99 : 75;
				} elseif ($shipper == BalikobotEnum::SHIPPER_INTIME) {
					$limit = 75;
				} elseif ($shipper == BalikobotEnum::SHIPPER_TOPTRANS) {
					$limit = 50;
				}

				if (strlen($value) > $limit) {
					if ($this->autoTrim) {
						$value = substr($value, 0, $limit);
					} else {
						throw new \InvalidArgumentException(
							"Invalid value of note option has been entered. Maximum length is $limit characters."
						);
					}
				}
				break;

			case BalikobotEnum::OPTION_PIECES:
			case BalikobotEnum::OPTION_TT_PIECES:
				if (!is_int($value) || ($value < 1)) {
					throw new \InvalidArgumentException('Invalid value of pieces has been entered. Enter positive integer.');
				}
				break;

			case BalikobotEnum::OPTION_ORDER:
				if (!is_numeric($value) || (strlen($value) > 10)) {
					throw new \InvalidArgumentException(
						"Invalid value of order option has been entered. Enter number, max 10 characters length."
					);
				}
				break;
		}

		$this->data['data'][$name] = $value;
	}

	// protected ---------------------------------------------------------------------------------------------------------

	/**
	 * @param string $shipper
	 * @param string $orderId
	 * @return string
	 */
	protected function getEid($shipper = null, $orderId = null)
	{
		$time = time();
		$delimeter = '';

		if (isset($shipper) && isset($orderId)) {
			return implode($delimeter, [$this->getApiShopId(), $shipper, $orderId, $time]);
		} elseif (isset($shipper)) {
			return implode($delimeter, [$this->getApiShopId(), $shipper, $time]);
		} elseif (isset($orderId)) {
			return implode($delimeter, [$this->getApiShopId(), $orderId, $time]);
		} else {
			return implode($delimeter, [$this->getApiShopId(), $time]);
		}
	}

	/**
	 * @param string $request
	 * @param string $shipper
	 * @param array $data
	 * @param string $url
	 * @return array
	 */
	protected function call($request, $shipper, array $data = [], $url = null)
	{
		if (empty($request) || empty ($shipper)) {
			throw new \InvalidArgumentException('Invalid argument has been entered.');
		}

		$r = curl_init();
		curl_setopt($r, CURLOPT_URL, $url ? "$this->apiUrl/$shipper/$request/$url" : "$this->apiUrl/$shipper/$request");
		curl_setopt($r, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($r, CURLOPT_HEADER, false);
		if (!empty($data)) {
			curl_setopt($r, CURLOPT_POST, true);
			curl_setopt($r, CURLOPT_POSTFIELDS, json_encode($data));
		}
		curl_setopt(
			$r,
			CURLOPT_HTTPHEADER,
			[
				'Authorization: Basic ' . base64_encode($this->getApiUser() . ":" . $this->getApiKey()),
				'Content-Type: application/json',
			]
		);
		$response = curl_exec($r);
		curl_close($r);

		return json_decode($response, true);
	}

	/**
	 * Cleans temporary data about created package
	 */
	protected function clean()
	{
		$this->data = [
			'isService' => false,
			'isCustomer' => false,
			'isCashOnDelivery' => false,
			'shipper' => null,
			'data' => [],
		];
	}

}
