import sys
sys.path.append('/usr/local/lib/python2.7/site-packages/')
from images2gif import writeGif
from PIL import Image
import os

def openAndReadConf():
        conf = open('/home/steve/Dev/sites.conf', 'r')
        lines = [line.rstrip() for line in conf]
        conf.close()
        names = [line.split(',')[0] for line in lines]
        return names

siteNames = openAndReadConf()

for site in siteNames:

	file_names = sorted(fn for fn in os.listdir(site))
	
	print file_names	

	images = [Image.open(site + '/' + fn) for fn in file_names]

	filename = site + '.gif'

	writeGif(filename, images, duration=0.2)

# Clean / backup directory
