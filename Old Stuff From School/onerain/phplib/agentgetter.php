<?php
/**
* Abstract data agent class
* Handles querying and parsing 
* @version    $Id: edit.php 1169 2011-10-05 19:59:43Z rick.jensen $   
* @author stephan.ohlsson
*/
abstract class AgentGetter {
	protected $config;
	protected $output_handle;
	protected $log;
	public function __construct($config, $logger, $datadir) {
		if (!isset($config)) {
			$this->config = array();
		}
		$this->config['conf'] = '/usr/local/onerain/bin/data-agents/GetHADS/GetHADS.ini';
		$this->config['data.datadir'] = $datadir;
		$this->config['data.output_filename'] = time() . '.dat';
		$this->config['logging.log_path'] = '/var/log/onerain/GetHADS.log';
		$this->config['logging.level'] = LOG_INFO;
		$this->config['is_cli'] = php_sapi_name() === 'cli';
		$this->config['data.app_name'] = 'GetHADS';
		$this->config['data.source'] = '';
		$this->config['data.system_key'] = '';
		$this->config['data.email'] = 'operation.support@onerain.com';
		$this->config['get.unique_alias'] = TRUE; // if this is TRUE, every site_alias only gets pulled once.
		ini_set('user_agent', 'GetHADS/php' . phpversion());
		$this->config['hads_host'] = 'http://amazon.nws.noaa.gov';
		$this->config['method'] = '/nexhads2/servlet/DecodedData?state=nil&hsa=nil&of=1&sinceday=0&data=Decoded+Data';
		// 52118404|GMFW1|PC|2011-08-30 11:11|47.87|  |
		$this->config['hads_output_format'] = '%u|%s|%s|%04d-%02d-%02d %02d:%02d|1.2f|%s|';
		// 2011-08-30 00:11:00 52118404 PC 47.87
		$this->config['dat_output_format'] = '%4$s:00 %1$u %3$s %5$1.2f' . PHP_EOL;
		$this->init_config();
		if (isset($config)) {
			foreach ($config as $key => $val) {
				$this->config[$key] = $val;
			}
		}
		ini_set('from', $this->config['data.email']);
		$this->log = $logger;
	}
	abstract protected function init_config();
	abstract protected function process($station);
	public function run() {
		$this->log->log(LOG_INFO, $this->config['data.app_name'] . ' starting.');
		//Now start.
		$this->log->log(LOG_DEBUG, 'Opening output file ' . $this->config['data.system_key'] . $this->config['data.output_filename']);
		$this->output_handle = fopen($this->config['data.datadir'] . '/' . $this->config['data.system_key'] . $this->config['data.output_filename'], 'w');
		$this->log->log(LOG_DEBUG, 'Reading source file');
		$sourcefile = fopen($this->config['data.source'], 'r');
		$stations = array();
		while (($line = fgets($sourcefile)) !== FALSE) { //loop over stations
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
			if (!preg_match('/^[A-Za-z0-9\-]+\|[A-Za-z0-9\-\s\(\)\+\/]+,\-?\d+,\-?\d+$/', $line)) {
				continue;
			}
			list($site_device, $offset, $use_dst) = explode(',', $line);
			list($site_alias, $device_alias) = explode('|', $site_device);
			$station = array(
				'site_device' => $site_device,
				'offset' => $offset,
				'use_dst' => $use_dst,
				'site_alias' => $site_alias,
				'device_alias' => $device_alias
			);
			if ($this->config['get.unique_alias']) { // Pull every site_alias only once
				if (array_key_exists($site_alias, $stations)) {
					continue;
				}
				$stations[$site_alias] = $station;
			} else {
				if (array_key_exists($site_device, $stations)) {
					continue;
				}
				$stations[$site_device] = $station;
			}
			$this->log->log(LOG_DEBUG, 'Loading station ' . $station['site_device']);
			$this->process($station) OR $this->log->log(LOG_ERR, 'Error while loading station ' . print_r($station, TRUE));
		}
		fclose($sourcefile);
		$this->log->log(LOG_INFO, 'Got ' . count($stations) . ' stations from source.');
		fclose($this->output_handle);
		$this->log->log(LOG_INFO, 'Fetched all stations, exiting...');
	}
}
?>
