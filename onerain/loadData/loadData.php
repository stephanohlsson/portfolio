<?php
require_once '/usr/local/onerain/bin/data-agents/phplib/bin/logger.php';

/**
 * loadData - takes a .dat file and posts the contents into the data-exchange servlet
 * Converts all times to UTC before posting
 * Only data that is newer that the last post (kept track of in the .times file) is posted
 * This script is called by the data-agent wrapper
 *
 * Example usage: "php -f loadData.php -- --conf configfile.ini --input inputfile.dat"
 *
 * @author Stephan Ohlsson
 */
class loadData {
    protected $config;
    protected $conf_set =false;
    protected $input_set=false;
    protected $log;
    protected $sensor_tz;
    protected $sensor_use_dst;
    
    public function __construct() {
        $this->config = array();
        $this->config['conf'] = '/usr/local/onerain/bin/data-exchange/loadData/loadData.ini';
        $this->config['data.app_name'] = 'loadData';
        $this->config['data.basedir'] = '/usr/local/onerain/bin/data-agents/' . $this->config['data.app_name'];
        $this->config['data.email'] = 'operation.support@onerain.com';
        $this->config['data.delimiter'] = ' '; //space is default delimiter in .dat, but in future code it could change
        $this->config['data.source'] = '';
        $this->config['data.system_key'] = '';
        $this->config['data.input_file'] = '';
        $this->config['data.times'] = '';
        $this->config['is_cli'] = php_sapi_name() === 'cli';
        $this->config['logging.level'] = LOG_INFO;
        $this->config['logging.log_path'] = '/var/log/onerain/loadData.log';
        $this->config['exchange.host'] = '127.0.0.1';
        $this->config['exchange.port'] = 8080;
        $this->config['exchange.per_post'] = 20;
        $this->config['exchange.load_history'] = FALSE;
        
        $this->parse_args(); // first run to get config folder
        $this->read_config();
        $this->parse_args(); //second run to give cmd arguments priority
        
        $this->log = new Logger($this->config['logging.log_path'], $this->config['data.app_name'], $this->config['is_cli'], $this->config['logging.level']);
        
        if (!$this->conf_set || !$this->input_set) {
            $this->log->log(LOG_ERROR, 'ERROR: conf/input was not set. Please specify with "php -f loadData.php -- --conf configfile.ini --input inputfile.dat".');
        }
        $this->log->log(LOG_DEBUG, $this->config);
    }
    
    private function parse_args() {
        if ($this->config['is_cli']) {
            $this->parse_args_cli();
        } else {
            $this->parse_args_web();
        }
    }

    private function parse_args_cli() {
        global $argc, $argv;
        $i = 0;
        $this->conf_set = false;
        while ($i < $argc) {
            switch ($argv[$i]) {
                case '--conf':
                    $this->config['conf'] = $argv[++$i];
                    $this->conf_set = true;
                    break;
                case '--input':
                    $this->config['data.input_file'] = $argv[++$i];
                    $this->input_set=true;
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
    
	// reads the .times file and inits the sensor_times array - contains the time of the last post for each sensor
    protected function init_sensor_times() {
        $sensor_times = array();
        $thandle = fopen($this->config['data.times'],'r');
		$this->log->log(LOG_DEBUG, "Times file: " . $this->config['data.times']);
        if($thandle === false){
            $this->log->log(LOG_WARNING, 'WARNING: .times file '.$this->config['data.times'].' could not be opened. Assuming no data was loaded before.');
            return $sensor_times;
        }
        while ($line = fgets($thandle)) {
            $line=trim($line);
            list($name,$date)=explode(',',$line);
            $sensor_times[$name]=$date;
        }
        fclose($thandle);
        return $sensor_times;
    }
    
	// reads the .source file and inits the sensor_tz and sensor_use_dst arrays (keeps track of time zones for each sensor)
    protected function init_sensor_tz() {
        $this->sensor_tz=array();
        $this->sensor_use_dst=array();
        $thandle = fopen($this->config['data.source'],'r');
        if($thandle === false){
            $this->log->log(LOG_ERROR,'ERROR: .source file '.$this->config['data.source'].' could not be opened.');
            return false;
        }
        while ($line = fgets($thandle)) {
            $line=trim($line);
            list($name,$tz_offset,$use_dst)=explode(',',$line);
            $this->sensor_tz[$name]=$tz_offset;
            $this->sensor_use_dst[$name]=$use_dst;
        }
        fclose($thandle);
        return true;
    }
    
    /**
     * converts a given date/time to UTC time, substracting the tz offset and adding an hour for DST if needed
     */
    protected function to_utc($year, $month, $day, $hour, $min, $sec, $tz_offset, $name) {
		$time = gmmktime($hour, $min, $sec, $month, $day, $year);
		$this->log->log(LOG_DEBUG, "to_utc :" . gmdate("Y-m-d H:i:s", $time));
		$time = mktime($hour, $min, $sec, $month, $day, $year);
        $tz_sensor = $tz_offset;
        $tz_sensor = ($tz_sensor * -1) * 60 * 60; //convert from hours to seconds
        if ($this->sensor_use_dst[$name] > 0) {
			// Check if the time is in DST
			$dst_check = localtime($time, true);
            if (array_key_exists('tm_dst', $dst_check)) {
                //add one hour
				$tz_sensor += 60 * 60;
				$this->log->log(LOG_DEBUG, "Applied DST to data.");
            }
        }
		$time = gmmktime($hour, $min, $sec, $month, $day, $year);
		$time += $tz_sensor;
		return $time;
    }
	
	// posts $str to the data exchange
	protected function postData($str) {
		$this->log->log(LOG_DEBUG, 'Request =====');
		$this->log->log(LOG_DEBUG, $str);
		// Remove whitespace - the data exchange/xml parser WILL FAIL if whitespace/newlines are left in
		$replace = array("\n");
		$str = str_replace($replace, '', $str);
		
		$url = 'http://' .$this->config['exchange.host'] . ':' . $this->config['exchange.port'] . '/OneRain/GageXML';
		
		$ch = curl_init($url);
		//curl_setopt($ch, CURLOPT_MUTE, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		   'Content-Type: text/xml'
		));
		curl_setopt($ch, CURLOPT_POSTFIELDS, "$str");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		curl_close($ch);
		
		if($response) {
			$this->log->log(LOG_DEBUG, "Response =======");
			$this->log->log(LOG_DEBUG, $response);
		}
		else {
			$this->log->log(LOG_ERROR, "HTTP POST FAILURE");
			$this->log->log(LOB_DEBUG, $response);
		}
		
		if(strstr($response, 'update_success')) {
			$this->log->log(LOG_INFO, 'Update success');
			
		}
		else {
			$this->log->log(LOG_INFO, 'Post failure.');
			$this->log->log(LOG_INFO, $response);
		}
	}
	
	// Writes the .times file with the newest sensor times
	protected function saveSensorTimes($sensor_times) {
		$handle = fopen($this->config['data.times'], 'w');
		if(!$handle) {
			$this->log->log(LOG_ERROR, 'Could not open the times file for writing');
			return;
		}
		foreach($sensor_times as $key => $value) {
			fwrite($handle, "$key,$value\n");
		}
	}
    
    public function run(){
        if (!$this->conf_set || !$this->input_set) {
            return 3; //refuse to work.
        }
        $system_key = $this->config['data.system_key'];
        $infile = $this->config['data.input_file'];
        $delimiter = $this->config['data.delimiter'];
        
        $strhdr='<onerain><request>';
        $strftr='<poststatus><id>' . $system_key . '</id><skip_history>true</skip_history></poststatus>' . '</request></onerain>';
        $str; //the output string.
        
        $sensor_times=$this->init_sensor_times();
        
        if(!$this->init_sensor_tz()){ //returns false if source file couldn't be opened
            return 2;
        }
        
        $this->log->log(LOG_DEBUG, 'infile='.$infile);
        
        $inhandle = fopen($infile,'r');
        if($inhandle === false) {
            $this->log->log('ERROR: Could not open input file. exiting...');
            return 1;
        }
        $str= $strhdr; // first the header.
        $number_records = 0;
        while ($line = fgets($inhandle)) {
            $line=trim($line);
            @list($date,$time,$site_alias,$sensor_alias,$value,$raw) = explode($delimiter,$line);
            $name = $site_alias . '|' . $sensor_alias;
            list($year,$month,$day) = explode('-', $date);
            list($hour,$min,$sec) = explode(':',$time);
            
            //convert to UTC
            $tz='GMT';
            if(array_key_exists($name,$this->sensor_tz)){
                $tz = $this->sensor_tz[$name];
            } else {
                $this->log->log(LOG_NOTICE, 'NOTICE: No Timezone defined for sensor '.$name.', ignoring it.');
                continue;
            }
            $this->log->log(LOG_DEBUG, $name . ' TZ=' . $tz);
			// Convert .dat datetime to utc
            $utcdate = $this->to_utc($year, $month, $day, $hour, $min, $sec, $tz, $name);
			$utcdate_formatted = gmdate("Y-m-d H:i:s", $utcdate);
			$this->log->log(LOG_DEBUG, "UTC date: $utcdate_formatted");
			
			// Get last post from times file, convert to a utc timestamp
			if(array_key_exists($name, $sensor_times)) {
				$last_post = $sensor_times[$name];
				$this->log->log(LOG_DEBUG, "LAST POST: $last_post");
				list($last_post_date, $last_post_time) = explode(' ' ,$last_post);
				list($last_post_year, $last_post_month, $last_post_day) = explode('-', $last_post_date);
				list($last_post_hour, $last_post_min, $last_post_sec) = explode(':', $last_post_time);
				$last_post_utcdate = gmmktime($last_post_hour, $last_post_min, $last_post_sec, $last_post_month, $last_post_day, $last_post_year);
			}
			else {
				$this->log->log(LOG_DEBUG, "No old .times entry for $name.");
				$last_post_utcdate = 0;
			}
			// If the .dat file has newer data, post it
			if($utcdate > $last_post_utcdate) {
				$str = $str . "<postsensordata><sensor_data><idiom>$system_key</idiom><site_id>$site_alias</site_id>";
				$str = $str . "<sensor_id>$sensor_alias</sensor_id>";
				$str = $str . "<data_time>$utcdate_formatted</data_time>";
				$str = $str . "<data_value>$value</data_value>";
			
				if($raw) {
					$str = $str . "<raw_value>$raw</raw_value>";
				}
				
				$str = $str . "<source_quality>A</source_quality></sensor_data></postsensordata>";
				
				$number_records++;
				
				// Actually post it
				if($number_records % $this->config['exchange.per_post'] == 0) {
					$str = $str . $strftr;
					$this->postData($str);
					$str = $strhdr;
				}
				
				// Update the time of last post for the site
				$sensor_times[$name] = $utcdate_formatted;
			}
			else {
				$this->log->log(LOG_DEBUG, "Skipping data that has been already loaded");
			}
        }
        fclose($inhandle);
		$str = $str . $strftr;
		$this->postData($str);
		$this->saveSensorTimes($sensor_times);
    }
}

$load = new loadData();
$load->run();

?>
