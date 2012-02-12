<?php
require_once '/usr/local/onerain/bin/data-agents/phplib/bin/agentgetter.php';
/**
* GetUSGS getter agent
*
* @version    $Id: edit.php 1169 2011-10-05 19:59:43Z rick.jensen $   
* @author stephan.ohlsson
*/
class GetUSGS extends AgentGetter {
	protected $config;
	protected $output_handle;
	protected $log;
	private $output_buffer = '';
	protected function init_config() {
		$this->config['conf'] = '/usr/local/onerain/bin/data-agents/GetCDEC/perl_cdec_onerain.ini';
		$this->config['data.datadir'] = '/tmp/onerain-GetMETAR/';
		$this->config['data.output_filename'] = time() . '.dat';
		$this->config['logging.log_path'] = '/var/log/onerain/GetMETAR-php.log';
		$this->config['logging.level'] = LOG_DEBUG;
		$this->config['is_cli'] = php_sapi_name() === 'cli';
		$this->config['data.app_name'] = 'GetMETAR';
		$this->config['data.source'] = '';
		$this->config['data.system_key'] = '';
		$this->config['data.email'] = 'operation.support@onerain.com';
		$this->config['get.unique_alias'] = FALSE;
		ini_set('user_agent', 'GetUSGS/php' . phpversion());
		$this->config['usgs_host'] = 'http://waterdata.usgs.gov';
		$this->config['method'] = '/nwis/uv?format=rdb';
		$this->config['site_args'] = '&site_no=';
		$this->config['parameter_args'] = '&PARAmeter_cd=';
		$this->config['data.offset'] = 7200;
		// 2011-08-30 00:11:00 52118404 PC 47.87
		$this->config['dat_output_format'] = '%1$s %2$s:00 %3$s %4$s %5$s';
		
		// Specific settings for usgs api
		$this->config['api.max_site_no'] = 20;
		$this->config['api.period'] = '8h';
		$this->config['api.result_md_minutes'] = 30;
	}
	public function run() {
		$this->log->log(LOG_INFO, $this->config['data.app_name'] . ' starting.');
		$this->log->log(LOG_DEBUG, 'Opening output file ' . $this->config['data.system_key'] . $this->config['data.output_filename']);
		$this->output_handle = fopen($this->config['data.datadir'] . '/' . $this->config['data.system_key'] . $this->config['data.output_filename'], 'w');
		$this->log->log(LOG_DEBUG, 'Reading source file');
		
		// Read in source file of stations to get
		$sourcehandle = fopen($this->config['data.source'], 'r');
		$stations = array();
		$device_types = array();
		while (($line = fgets($sourcehandle)) !== FALSE) { //loop over stations
			//schema to load:
			// 52118404|PC,0,0
			// or: SRA|26-H,-8,1
			// 52118404|PC == site_device
			// 52118404 == site_alias
			// PC == device_alias
			// first number == offset
			// second number == use_dst?
			$station = array();
			$line = trim($line);
			list($site_device, $offset, $use_dst) = explode(',', $line);
			list($site_alias, $device_alias) = explode('|', $site_device);
			if(!preg_match('/\d+_(\d+)/', $device_alias, $matches)) {
				continue;
			}
			
			// Keep track of site aliases and device aliases needed for api call
			$device_alias = $matches[1];
			$stations[$site_alias] = 1;
			$device_types[$device_alias] = 1;
			if(count($stations) >= $this->config['api.max_site_no']) {
				$arg_container['stations'] = $stations;
				$arg_container['device_types'] = $device_types;
				$this->log->log(LOG_DEBUG, 'Loading stations ' . (array_keys($stations)));
				$this->process($arg_container);
				unset($stations);
				unset($device_types);
			}
		}
		if(count($stations) > 0) {
			$arg_container['stations'] = $stations;
			$arg_container['device_types'] = $device_types;
			$this->process($arg_container);
			$this->log->log(LOG_DEBUG, 'Loading stations ' . (array_keys($stations)));
		}
		fclose($sourcehandle);
		$this->log->log(LOG_INFO, 'Got ' . count($stations) . ' stations from source.');
		fclose($this->output_handle);
		$this->log->log(LOG_INFO, 'Fetched all stations, exiting...');
	}
	protected function process($arg_container) {
		$header;
		$stations = $arg_container['stations'];
		$device_types = $arg_container['device_types'];
		$site_list = implode(',' , array_keys($stations));
		$device_list = implode(',' , array_keys($device_types));
		$interval_arguments = '&period=' . $this->config['api.period'] . '&result_md_minutes=' . $this->config['api.result_md_minutes'];
		$url = $this->config['usgs_host'] . $this->config['method'] . $interval_arguments . $this->config['site_args'] . $site_list . $this->config['parameter_args'] . $device_list;
		$this->log->log(LOG_DEBUG, 'Connecting to URL ' . $url);
		$handle = fopen($url, 'r');
		if (!$handle) {
			$this->log->log(LOG_ERR, 'Could not connect to URL ' . $url);
			return FALSE;
		}
		while ($line = fgets($handle)) {
			$line = trim($line);
			if(preg_match('/agency_cd\tsite_no\tdatetime\ttz_cd/', $line)) {
				// This is a header line
				$this->log->log(LOG_DEBUG, 'Matched header line: ' . $line);
				$header = explode("\t", $line);
			}
			else if(preg_match('/^USGS\t[0-9]+\t[0-9]*-[0-9]*-[0-9]* [0-9]*:[0-9]*\t.*/', $line)) {
				$this->log->log(LOG_DEBUG, 'Matched data line: ' . $line);
				// This is a data line
				// Looks something like this (tab delimited):
				// USGS	01547500	2011-12-05 07:45	EST	926	P	4.55	P
				$datum = explode("\t", $line);
				$site_id = $datum[1];
				$date_time = $datum[2] . ':00';
				// Match header to data (header contains the device aliases)
				for($i = 0; $i < count($header); $i++) {
					if(preg_match('/^\d\d_\d\d\d\d\d$/', $header[$i])) {
						if(isset($datum[$i]) && preg_match('/-?\d+\.?\d*/', $datum[$i])) {
							$this->log->log(LOG_DEBUG, "out: $date_time $site_id $header[$i] $datum[$i]");
							$this->output_buffer = $this->output_buffer . "$date_time $site_id $header[$i] $datum[$i]\n";
						}
					}
				}
			}
			else {
				// Ignored field
			}
		}
		fwrite($this->output_handle, $this->output_buffer);
		$this->log->log(LOG_INFO, 'Got station ');
		fclose($handle);
		return TRUE;
	}
}
?>
