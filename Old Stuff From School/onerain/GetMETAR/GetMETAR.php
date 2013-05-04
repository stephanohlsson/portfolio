<?php
require_once '/usr/local/onerain/bin/data-agents/phplib/bin/agentgetter.php';
/**
* GetMETAR getter agent
*
* @version    $Id: edit.php 1169 2011-10-05 19:59:43Z rick.jensen $   
* @author stephan.ohlsson
*/
class GetMETAR extends AgentGetter {
	protected $config;
	protected $output_handle;
	protected $log;
	private $precip_found = 0;
	private $advanced_temp_found = 0;
	private $output_buffer = '';
	private $current_date;
	private $current_time;
	private $site_alias;
	private $temp;
	private $dew;
	protected function init_config() {
		$this->config['conf'] = '/usr/local/onerain/bin/data-agents/GetMETAR/perl_metar_onerain.ini';
		$this->config['data.datadir'] = '/tmp/onerain-GetMETAR/';
		$this->config['data.output_filename'] = time() . '.dat';
		$this->config['logging.log_path'] = '/var/log/onerain/GetMETAR.log';
		$this->config['logging.level'] = LOG_INFO;
		$this->config['is_cli'] = php_sapi_name() === 'cli';
		$this->config['data.app_name'] = 'GetMETAR';
		$this->config['data.source'] = '';
		$this->config['data.system_key'] = '';
		$this->config['data.email'] = 'operation.support@onerain.com';
		$this->config['get.unique_alias'] = TRUE;
		ini_set('user_agent', 'GetMETAR/php' . phpversion());
		$this->config['metar_host'] = 'http://weather.noaa.gov';
		$this->config['method'] = '/pub/data/observations/metar/stations/';
		$this->config['data.offset'] = 7200;
		// 2011-08-30 00:11:00 52118404 PC 47.87
		$this->config['dat_output_format'] = '%1$s %2$s:00 %3$s %4$s %5$s';
	}
	protected function process($station) {
		$this->site_alias = $station['site_alias'];
		$url = $this->config['metar_host'] . $this->config['method'] . $station['site_alias'] . '.TXT';
		$this->log->log(LOG_DEBUG, 'Connecting to URL ' . $url);
		$handle = fopen($url, 'r');
		if (!$handle) {
			$this->log->log(LOG_ERR, 'Could not connect to URL ' . $url);
			return FALSE;
		}
		while ($line = fgets($handle)) {
			//example: 2011/12/01 18:53
			//         KUAO 011853Z AUTO 36005KT 10SM CLR 07/01 A3067 RMK AO2 SLP386 T00670011
			$datum = explode(' ', $line);
			foreach($datum as $value) {
				if(preg_match('/^(VRB|\d\d\d)(\d\d)((G\d\d)?)KT$/', $value, $matches)) {
					// This is a wind field
					$this->decode_wind($matches);
				}	
				else if(preg_match('/^T(\d)(\d\d\d)(\d)(\d\d\d)/', $value, $matches)) {
					// this is an advanced (more accurate, but optional) temperature/dew point field
					$this->decode_advanced_temp($matches);
				}
				else if(preg_match('/^(M?(\d\d))\/(M?(\d\d))$/', $value, $matches)) {
					// simple temp / dew point field
					$this->decode_simple_temp($matches);
				}
				else if(preg_match('/^A(\d\d)(\d\d)$/', $value, $matches)) {
					// This is an altimeter field (in. Hg)
					$alt = $matches[1] . '.' . $matches[2];
					$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Alt $alt");
					$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Alt $alt\n";
				}
				else if(preg_match('/^SLP(\d\d\d)$/', $value, $matches)) {
					// this is a sea level pressure field, readings are in mmBar
					$this->decode_pressure($matches);
				}
				else if(preg_match('/^P([0-9]+)/', $value, $matches)) {
					// This is a precipitation field
					$precip = $matches[1] * 0.01;
					$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Rain $precip");
					$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Rain $precip\n";
					$this->precip_found = 1;
				}
				else if(preg_match('%(\d+)\/(\d+)/(\d+)%', $value, $matches)) {
					// This is a date header
					$this->current_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
					$this->log->log(LOG_DEBUG, "Date: $this->current_date");
				}
				else if(preg_match('/(\d\d:\d\d)/', $value, $matches)) {
					// This is a time header
					$this->current_time = $matches[1] . ':00';
					$this->log->log(LOG_DEBUG, "Time: $this->current_time");
				}
				else {
					$this->log->log(LOG_DEBUG, "Ignored field: $value");
				}
			}	
		}
		if($this->precip_found == 0) {
			$precip = 0.0;
			$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Rain $precip");
			$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Rain $precip\n";
		}
		if($this->advanced_temp_found == 0) {
			$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Temp $this->temp");
			$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Temp $this->temp\n";
			$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Dew $this->dew");
			$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Dew $this->dew\n";
		}
	
		fwrite($this->output_handle, $this->output_buffer);
		$this->log->log(LOG_INFO, 'Got station ' . $station['site_device']);
		fclose($handle);
		return TRUE;
	}
	// Helper functions
	private function decode_wind($matches) {
		$velocity = $matches[2];
		// Convert from MPH to knots
		$velocity = $velocity * 1.151;
		$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Vel $velocity");
		$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Vel $velocity\n";
		if(preg_match('/VRB/', $matches[0])) {
			// Wind is variable, we aren't going to report it
		}
		else {
			$this->log->log(LOG_DEBUG,  "$this->current_date $this->current_time $this->site_alias Dir $matches[1]");
			$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Dir $matches[1]\n";
		}
	}
	private function decode_advanced_temp($matches) {
		$this->advanced_temp_found = 1;
		// Determine sign of temperature (in C)
		$sign = 1;
		if($matches[1] != 0) {
			$sign = -1;
		}

		$this->temp = $sign * ($matches[2] * 1.8 / 10.0) + 32.0; # Convert to F
		$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Temp $this->temp");
		$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Temp $this->temp\n";
		
		// Dew point
		$sign = 1;
		if($matches[3] != 0) {
			$sign = -1;
		}
		$this->dew = $sign * ($matches[4] * 1.8 / 10.0) + 32.0;
		$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Dew $this->dew");
		$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Dew $this->dew\n";
	}
	private function decode_simple_temp($matches) {
		$this->temp = $matches[1];
		$temp_value = $matches[2];
		$this->dew = $matches[3];
		$dew_value = $matches[4];

		// M indicates negative value
		if(preg_match('/M/', $this->temp)) {
			$this->temp = -1 * $temp_value;
		}

		// Convert to F
		$this->temp = ($this->temp * 1.8) + 32.0;
		
		if(preg_match('/M/', $this->dew)) {
			$this->dew = -1 * $temp_value;
		}
		$this->dew = ($this->dew * 1.8) + 32.0;
	}
	private function decode_pressure($matches) {
		// Report format - SLPppp
		// If ppp greater than 500, it is normally necessary to put a 9 in front of ppp and divide
		// by 10 in order to get the sea level pressure in hectoPascals (mb).
		// If ppp is less than 500, it is normally necessary to put a 10 in front of ppp
		// and to divide by 10 to get the sea level pressure in hectoPascals (mb).
		// Source: http://geog-www.sbs.ohio-state.edu/courses/G620/hobgood/ASP620Lecture03.ppt
		
		$slp = $matches[1] / 10;
		if ($slp < 50) {
			$slp += 1000;
		}
		else {
			$slp += 900;
		}

		$this->log->log(LOG_DEBUG, "$this->current_date $this->current_time $this->site_alias Pressure $slp");
		$this->output_buffer = $this->output_buffer . "$this->current_date $this->current_time $this->site_alias Pressure $slp\n";
	}
}
?>
