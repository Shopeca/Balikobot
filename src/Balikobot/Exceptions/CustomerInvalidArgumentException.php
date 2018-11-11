<?php

namespace Shopeca\Balikobot\Exceptions;

class CustomerInvalidArgumentException extends \InvalidArgumentException
{

	private $argument;

	public function __construct($message, $code = 0, $argument = null)
	{
		parent::__construct($message, $code);

		$this->argument = $argument;
	}

	public function getArgument()
	{
		return $this->argument;
	}

}
