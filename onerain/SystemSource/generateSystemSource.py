#!/usr/bin/env python
#
# Generates source/tmzn files for a system
# Takes 3 arguments:
#	--path|p - output directory for writing source/tmzn files
#	--system_key|s - system_key/idiom
#	--output_type|o - either "tmzn" or "source" (default is source)
#
# Help: --help|h
#
# File formats:
#	source: "site|sensor,timezone,DST"
#	timezone is an integer (offset from GMT)
#	tmzn: "site|sensor,timezone,DST"
#	time is a string (MST,PST...)
#	DST is a boolean flag
#	All entries are line delimited (\n)	

__author__ = "Rick Jensen <rick.jensen@onerain.com>, Stephan Ohlsson <stephan.ohlsson@gmail.com>"
__version__ = "$Id: generateSystemSource.py 105 2010-08-20 20:27:58Z rick.jensen $"
__copyright__ = "2007-2010 OneRain Inc."

import logging
import MySQLdb
import MySQLdb.cursors
from MySQLdb.constants import FIELD_TYPE
import os
import sys
from optparse import OptionParser
import re
import ConfigParser

def executeQuery (query):
	''' execute SQl query and return dictionary of results '''
	try:
		conn = MySQLdb.connect (host=HOST,
								port=PORT,
								user=USERNAME,
								passwd=PASSWORD,
								db=DATABASE,
								conv=CONVERSION)

	except MySQLdb.Error, e:
		log.error("Error %d: %s" % (e.args[0], e.args[1]))
		sys.exit(1)

	try:
		cursor = conn.cursor(cursorclass=MySQLdb.cursors.DictCursor)
		cursor.execute(query)
		result = cursor.fetchall()
		cursor.close()

	except MySQLdb.Error, e:
		log.error("Error %d: %s" % (e.args[0], e.args[1]))
		log.error("SQL %s" % (query))
		sys.exit(1)

	conn.close()

	if (result == None):
		log.debug('Empty result, check for valid query')
		return None

	else:
		return result

def getSystemSQL (system_key):
	sql = ('''
		SELECT
			sys.`system_id`,
			sys.`system_key`,
			sys.`name` AS `system`,
			sys.`description`,
			sys.`system_type`,
			sys.`enabled`,
			st.`name` `type_name`,
			st.`interface`,
			sys.`client_id`,
			c.`name` AS `client`
		FROM
			system sys,
			system_type st, `client` c
		WHERE
			sys.`system_key` = '%s'
			AND st.system_type = sys.system_type
			AND c.client_id = sys.client_id;
		''' % system_key)
	return sql

def getSensorSQL (system_id):
	sql = ('''
		SELECT
			sa.site_alias,
			da.device_alias,
			d.utc_offset AS `offset`,
			d.using_dst AS `dst`,
			d.description
		FROM
			site_alias sa,
			device_alias da,
			device d
		WHERE
			sa.system_id = %d
			AND sa.system_id = da.system_id
			AND sa.site_id = da.site_id
			AND da.site_id = d.site_id
			AND da.device_id = d.device_id
			AND d.active = True
		ORDER BY
		sa.site_alias, da.device_alias ASC
		''' % system_id)
	return sql

def convertToTimeString (offset):
	time_string = 'GMT'
	if offset == -8:
		time_string = 'PST'
	elif offset == -7:
		time_string = 'MST'
	elif offset == -6:
		time_string = 'CST'
	elif offset == -5:
		time_string = 'EST'

	return time_string

def writeFile (path, system, sensors):
	if output_type == 'source':
		source_file = os.path.join (path, str(system['system_key'] + '.source'))
	else:
		source_file = os.path.join (path, str(system['system_key'] + '.tmzn'))
	try:
		source = open (source_file, 'w') # if the file already exists, then this removes it
	except:
		log.error('Could not open files')
		sys.exit(1)

	for sensor in sensors:
		try:
			if output_type == 'tmzn':
				offset = convertToTimeString(sensor['offset'])
			else:
				offset = sensor['offset']

			line = "%s|%s,%s,%s" % (sensor['site_alias'], sensor['device_alias'], offset, sensor['dst'])
			source.writelines(line + '\n')
		except:
			log.error('Could not write line to files')
			sys.exit(1)

	source.close()

if __name__ == "__main__":
	# parse config
	config = ConfigParser.RawConfigParser()
	config.read('/usr/local/onerain/bin/data-exchange/SystemSource/SystemSource.ini')
	HOST = config.get('database','HOST')
	PORT = config.getint('database','PORT')
	USERNAME = config.get('database','USERNAME')
	PASSWORD = config.get('database','PASSWORD')
	DATABASE = config.get('database','DATABASE')
	CONVERSION = { FIELD_TYPE.LONG: int }
	log_path = config.get('logging','path')
	# setup logger
	log = logging.getLogger('log')
	hdlr = logging.FileHandler(log_path)
	formatter = logging.Formatter('%(asctime)s - %(module)s - %(levelname)s - %(message)s')
	hdlr.setFormatter(formatter)
	log.addHandler(hdlr)
	log.setLevel(logging.INFO)

	path = '.'
	system_key = ''
	output_type = ''
	# command line (argument) parser
	parser = OptionParser()
	parser.add_option("-p","--path", action="store", type="string", dest="path", default=".", help="Path to save data to (default .)", metavar="PATH")
	parser.add_option("-s","--system_key", action="store", type="string", dest="system_key", help="System key - REQUIRED")
	parser.add_option("-o","--output_type", action="store", type="string", dest="output_type", default="source", help="Output type: \"source\" or \"tmzn\" (default source)")

	(options, args) = parser.parse_args()
	system_key = options.system_key
	output_type = options.output_type
	path = options.path

	if system_key == None:
		parser.print_help()
		sys.exit(1)

	query = getSystemSQL(system_key)
	systems = executeQuery(query)

for system in systems:
		if (system):
			log.info('System: %s' % system['system_key'])
			query = getSensorSQL(int(system['system_id']))
			sensors = executeQuery(query)

			if (sensors):
				writeFile (path, system, sensors)
			else:
				log.error('No sensor(s) avaliable in System: %s' % system)
				sys.exit(1)

		else:
			log.error('No System available.')
			sys.exit(1)
