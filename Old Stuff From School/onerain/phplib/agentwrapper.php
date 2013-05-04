<?php
require_once 'functions.php';
require_once 'logger.php';
set_time_limit(0);
/**
* Wrapper class for data agents
* Generates source files, and calls the getter agent
* Takes output of getter and feeds it to loadData, which handles posting to the data-exchange
* @author stephan.ohlsson
*/
abstract class AgentWrapper {
	protected $log;
	protected $config;
	protected $needed_files;
	protected $conf_set = false;
	public function __construct() {
		date_default_timezone_set('UTC');
		$this->config = array();
		$this->config['conf'] = '/usr/local/onerain/bin/data-agents/GetAgentWrapper/GetAgentWrapper.ini';
		$this->config['data.app_name'] = 'GetAgentWrapper';
		$this->config['data.backup_data'] = TRUE;
		$this->config['data.basedir'] = '/usr/local/onerain/bin/data-agents/' . $this->config['data.app_name'];
		$this->config['data.datadir'] = '/tmp/onerain-GetAgentWrapper/';
		$this->config['data.email'] = 'operation.support@onerain.com';
		$this->config['data.generate_source_path'] = '/usr/local/onerain/bin/data-exchange/SystemSource/generateSystemSource.py';
		$this->config['data.generate_source'] = TRUE;
		$this->config['data.output_filename'] = time() . '.dat';
		$this->config['data.output_type'] = 'source';
		$this->config['data.poster'] = '/usr/local/onerain/bin/data-exchange/loadData/loadData.pl';
		$this->config['data.poster_type'] = 'perl';
		$this->config['data.source'] = '';
		$this->config['data.system_key'] = '';
		$this->config['data.timeout'] = 300;
		$this->config['data.times'] = '';
		$this->config['exec.python'] = '/usr/bin/python';
		$this->config['is_cli'] = php_sapi_name() === 'cli';
		$this->config['logging.level'] = LOG_INFO;
		$this->config['logging.log_path'] = '/var/log/onerain/GetAgentWrapper.log';
		$this->config['settings.start_delay'] = 300;
		$this->config['data.lock_dir'] = '/var/lock/subsys/';
		$this->needed_files = array(
			'exec.python',
			'data.poster',
			'data.generate_source_path',
			'data.basedir'
		);
		$this->init_config();
		$this->parse_args(); // first run to get config folder
		$this->read_config();
		$this->parse_args(); //second run to give cmd arguments priority
		$this->config['logging.level'] = Logger::parse_level($this->config['logging.level']);
		$this->config['data.lock_file'] = $this->config['data.lock_dir'] . $this->config['data.system_key'];
		$this->log = new Logger($this->config['logging.log_path'], $this->config['data.app_name'], $this->config['is_cli'], $this->config['logging.level']);
		if (!$this->conf_set) {
			$this->log->log('ERROR: conf was not set. Please specify with "php -f *****.php -- --conf Get*****-1234.ini".', LOG_ERR);
		}
		if ($this->config['logging.level'] === LOG_DEBUG) {
			$this->log->log(LOG_DEBUG, $this->config);
		}
	}
	
	abstract protected function init_config();
	
	private function parse_args() {
		if ($this->config['is_cli']) {
			$this->parse_args_cli();
		} else {
			$this->parse_args_web();
		}
	}
	private function parse_args_cli() {
		global $argc, $argv;
		ini_set('register_argc_argv', 'on');
		$i = 0;
		$this->conf_set = false;
		while ($i < $argc) {
			switch ($argv[$i]) {
				case '--conf':
					$this->config['conf'] = $argv[++$i];
					$this->conf_set = true;
					break;
				case '--datadir':
					$this->config['data.datadir'] = $argv[++$i];
					break;
				case '--logpath':
					$this->config['logging.log_path'] = $argv[++$i];
					break;
				case '--loglevel':
					$l = $argv[++$i];
					$this->config['logging.level'] = $l;
					break;
				default:
					//die('unknown parameter.');
			}
			$i++;
		}
	}
	private function parse_args_web() {
		echo 'Sorry, this script can not parse HTTP POST or GET right now. Using defaults.';
	}
	private function read_config() {
		//Read the ini file.
		$sections = parse_ini_file($this->config['conf'], true);
		foreach ($sections as $sectionk => $sectionv) {
			foreach ($sectionv as $key => $var)
				$this->config[$sectionk . '.' . $key] = $var;
		}
	}
	public function run() {
		$startTime = time();
		if (!$this->conf_set) {
			return 3; //refuse to work.
		}
		echo $this->config['logging.level'];
		//check for files
		foreach ($this->needed_files as $file) {
			if (!file_exists($this->config[$file])) {
				$this->log->log(LOG_ERR, 'ERROR: ' . $file . ' not found in ' . $this->config[$file]);
				die($file . ' not found, exiting.' . PHP_EOL);
			}
		}
		$date = date('Y-m-d');
		$tar = '/bin/tar --create --gzip -P --file';
		
		//create / check for lock file
		if (file_exists($this->config['data.lock_file'])) {
			$this->log->log(LOG_WARNING, 'Service ' . $this->config['data.app_name'] . ' - ' . $this->config['data.system_key'] . ' already running. Exiting.');
			return 1;
		} else {
			touch($this->config['data.lock_file']);
			$this->log->log(LOG_INFO, 'Service ' . $this->config['data.app_name'] . ' - ' . $this->config['data.system_key'] . ' starting...');
		}
		
		//sleep until system has settled after booting
		$uptime = exec('/usr/bin/cut -f 1 -d "." < /proc/uptime');
		if ($uptime < $this->config['settings.start_delay']) {
			$pause = $this->config['settings.start_delay'] - $uptime;
			sleep($pause);
		}
		//run generate_source to generate tmzn/source files
		if ($this->config['data.generate_source']) {
			$this->log->log(LOG_DEBUG, $this->config['exec.python'] . ' "' . $this->config['data.generate_source_path'] . '" ' . '--path="' . $this->config['data.basedir'] . '" --system_key=' . $this->config['data.system_key'] . ' ' . '--output_type=' . $this->config['data.output_type']);
			exec($this->config['exec.python'] . ' "' . $this->config['data.generate_source_path'] . '" ' . '--path="' . $this->config['data.basedir'] . '" --system_key=' . $this->config['data.system_key'] . ' ' . '--output_type=' . $this->config['data.output_type'] . ' >> ' . $this->config['logging.log_path'] . ' 2>&1');
		} else {
			$this->log->log(LOG_WARNING, 'Did NOT create new source file, specified in config.');
		}
		$this->log->log(LOG_INFO, $this->config['data.system_key'] . ' Getting data...');
		//create temp dir
		$this->config['data.datadir'] = exec('mktemp -d');
		//run getter 
		$this->log->log(LOG_INFO, $this->config['data.app_name']);
		$getter = new $this->config['data.app_name']($this->config, $this->log, $this->config['data.datadir']);
		$getter->run();
		//read created files
		if ($handle = opendir($this->config['data.datadir'])) {
			$this->log->log(LOG_INFO, 'Posting data files...');
			while (false !== ($file = readdir($handle))) {
				if ($file != '.' && $file != '..') { //ignore .. and .
					switch ($this->config['data.output_type']) {
						case 'tmzn': //post tmzn files
							exec($this->config['exec.perl'] . ' ' . $this->config['data.poster'] . ' ' . $this->config['data.datadir'] . '/' . $file . ' ' . $this->config['data.system_key'] . ' >> ' . $this->config['logging.log_path'] . ' 2>&1');
							break;
						case 'source': //post source files
							if(strstr($this->config['data.poster'], '.pl')) {
								exec($this->config['exec.perl'] . ' ' . $this->config['data.poster'] . ' -i ' . $this->config['data.datadir'] . '/' . $file . ' -c ' . $this->config['conf'] . ' >> ' . $this->config['logging.log_path'] . ' 2>&1');
								echo $this->config['exec.perl'] . ' ' . $this->config['data.poster'] . ' -i ' . $this->config['data.datadir'] . '/' . $file . ' -c ' . $this->config['conf'] . PHP_EOL;
							}
							else if(strstr($this->config['data.poster'], '.php')) {
								exec('php -f ' . $this->config['data.poster'] . ' -- --input ' . $this->config['data.datadir'] . '/' . $file . ' --conf ' . $this->config['conf'] .' >> ' . $this->config['logging.log_path'] . ' 2>&1');
								echo 'php -f ' . $this->config['data.poster'] . ' -- --input ' . $this->config['data.datadir'] . '/' . $file . ' --conf ' . $this->config['conf'] . PHP_EOL;
							}
							break;
						default:
							$this->log->log('output type ' . $this->config['data.output_type'] . ' not recognized, exiting...');
							return 2;
					}
					if ($this->config['data.backup_data']) { //backup files...
						$outfolder = $this->config['data.basedir'] . '/' . $date;
						if (!file_exists($outfolder)) {
							mkdir($outfolder);
						}
						exec($tar . ' "' . $this->config['data.basedir'] . '/' . $date . '/' . $file . '.tgz" "' . $this->config['data.datadir'] . '/' . $file . '" >> ' . $this->config['logging.log_path'] . ' 2>&1');
					}
				}
			}
			closedir($handle);
			delete_directory($this->config['data.datadir']); //delete tmp
			unlink($this->config['data.lock_file']); //remove lock file
			$endTime = time();
			$totalTime = $endTime - $startTime;
			$seconds = $totalTime % 60;
			$minutes = (int) ($totalTime / 60);
			$this->log->log(LOG_INFO, 'Service ' . $this->config['data.app_name'] . ' - ' . $this->config['data.system_key'] . " finished -- ending... total time was $minutes minutes and $seconds seconds");
		}
	}
}
?>