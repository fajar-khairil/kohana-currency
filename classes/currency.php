<?php
class Currency_Exception extends Kohana_Exception {}
class Currency
{
	public static $instance = NULL;
	protected $_base_currency = 'USD';
	protected $_exchange_rates;

	public static function instance()
	{
		if ( ! Currency::$instance )
			Currency::$instance = new Currency();
		return Currency::$instance;
	}

	public function __construct()
	{
		// Get the exchange rates from $from_currency to any other available currency
		$cache_key = Kohana::$config->load('currency.cache_key');
		if ( $cache_key && ( $cache = Cache::instance()->get($cache_key) ))
		{
			$this->_exchange_rates = $cache['exchange_rates'];
			$this->_base_currency = $cache['base_currency'];
		}
		elseif ( ! $cache_key || ! $cache )
		{
			$this->_exchange_rates = json_decode(file_get_contents('http://openexchangerates.org/latest.json'), TRUE);
			$this->_base_currency = $this->_exchange_rates['base'];
			$this->_exchange_rates = $this->_exchange_rates['rates'];
			if ( $cache_key )
				Cache::instance()->set($cache_key, array('exchange_rates' => $this->_exchange_rates, 'base_currency' => $this->_base_currency), Kohana::$config->load('currency.cache_lifetime'));
		}
	}

	public function set_exchange_rate($currency, $amount)
	{
		$this->_exchange_rates[$currency] = $amount;
		return $this;
	}

	public function get_exchange_rate($from_currency, $to_currency)
	{
		$from_currency = strtoupper($from_currency); $to_currency = strtoupper($to_currency);

		if ( !isset($this->_exchange_rates[$from_currency]) || !isset($this->_exchange_rates[$to_currency]) )
		{
			$currency = ( isset($this->_exchange_rates[$from_currency]) ) ? $to_currency : $from_currency;
			throw new Currency_Exception('Invalid currency specified: :currency', array(':currency' => $currency));
		}

		if ( $from_currency == $this->_base_currency )
			return $this->_exchange_rates[$to_currency];
		elseif ( $to_currency == $this->_base_currency )
			return 1/ $this->_exchange_rates[$from_currency];
		else
			return $this->_exchange_rates[$to_currency] * ( 1 / $this->_exchange_rates[$from_currency] );
	}

	public function convert($from_currency, $to_currency, $amount)
	{
		return $this->get_exchange_rate($from_currency, $to_currency) * $amount;
	}
}
