<?php
require_once('/usr/local/onerain/bin/data-agents/GetUSGS/bin/GetUSGS.php');
require_once('/usr/local/onerain/bin/data-agents/phplib/bin/agentwrapper.php');
/**
 * GetUSGS wrapper agent
 *
 * @version    $Id: edit.php 1169 2011-10-05 19:59:43Z rick.jensen $   
 * @author stephan.ohlsson
 */
class USGS extends AgentWrapper {
    protected function init_config() {
        $this->config['conf'] = '/usr/local/onerain/bin/data-agents/GetUSGS/GetUSGS.ini';
        $this->config['data.app_name'] = 'GetUSGS';
        $this->config['data.basedir'] = '/usr/local/onerain/bin/data-agents/' . $this->config['data.app_name'];
        $this->config['exec.python'] = '/usr/bin/python';
        $this->config['exec.perl'] = '/usr/bin/perl';
        $this->config['data.generate_source_path'] = '/usr/local/onerain/bin/data-exchange/SystemSource/generateSystemSource.py';
        $this->config['data.poster'] = '/usr/local/onerain/bin/data-exchange/loadData/loadData.pl';
        $this->config['data.datadir'] = '/tmp/onerain-GetUSGS/';
        $this->config['data.output_filename'] = time() . '.dat';
        $this->config['logging.log_path'] = '/var/log/onerain/GetUSGS-php.log';
        $this->config['logging.level'] = LOG_DEBUG;
        $this->config['is_cli'] = php_sapi_name() === 'cli';
        $this->config['data.source'] = '';
        $this->config['data.times'] = '';
        $this->config['data.system_key'] = '';
        $this->config['data.email'] = 'operation.support@onerain.com';
        $this->config['data.output_type'] = 'source';
        $this->config['data.generate_source'] = TRUE;
        $this->config['data.backup_data'] = TRUE;
        $this->config['data.timeout'] = 300;
        $this->config['settings.start_delay'] = 300;
        $this->config['data.lock_dir'] = '/var/lock/subsys/';
        $this->needed_files = array(
            'exec.python',
            'data.poster',
            'data.generate_source_path',
            'data.basedir'
        );
    }
}
//actually run it.
$usgs = new USGS();
$usgs->run();
?>
