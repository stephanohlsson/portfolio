#!/usr/bin/python
import urllib
import time
import datetime

def openAndReadConf():
	conf = open('/home/steve/Dev/sites.conf', 'r')
	lines = [line.rstrip() for line in conf]
	conf.close()
	split = [line.split(',') for line in lines]
	nameToURL = {name: URL for (name, URL) in split}
	return nameToURL

nameToURL = openAndReadConf()

for name, URL in nameToURL.items():
	time = time.time()
	ts = datetime.datetime.fromtimestamp(time).strftime('%Y-%m-%d:%H:%M:%S')
	filename = "/home/steve/Dev/{0}/{1}.jpg".format(name, ts)
	f = open(filename, 'wb')
	url = urllib.urlopen(URL)
	f.write(url.read())
	f.close()

