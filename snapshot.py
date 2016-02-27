#!/usr/bin/python
import MySQLdb
import subprocess

db = MySQLdb.connect(host="localhost", # your host, usually localhost
                     user="root", # your username
                      passwd="changeme", # your password
                      db="pomf") # name of the data base

cur = db.cursor() 
cur.execute("SELECT filename FROM files")

outputs = []

for row in cur.fetchall():
	outputs.append(row[0].split('/')[0].strip())

output_hash = "QmUNLLsPACCz1vLxQVkXqqLX5R1X345qqfHbsf67hvA3Nn"

for curr_hash in outputs:
	output_hash = subprocess.check_output(['ipfs', 'object', 'patch', output_hash, 'add-link', curr_hash, curr_hash]).strip()
		
subprocess.call(['ipfs', 'name', 'publish', output_hash])


#hash_dict[entry.link] = subprocess.check_output(['transmission-show', '-m', '/tmp/rawtorrent']).split('&')[0]

