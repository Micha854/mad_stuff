#!/usr/bin/env python3
__author__ = "GhostTalker"
__copyright__ = "Copyright 2019, The GhostTalker project"
__version__ = "0.2.0"
__status__ = "Dev"

# generic/built-in and other libs
import os
import sys
import time
import datetime
import pymysql.cursors
import pymysql
import json

with open('config.json') as file:
    data = json.load(file)
#print(json.dumps(data, indent=4))

statusOfflineTimeout = data["py-option"]["timeout"]
statusInterval = data["py-option"]["interval"]
cleanupDbEntryOlderThan = data["py-option"]["cleanup"]
instance_id = data["option"]["instance_id"]

def connect_sourcedb(): 
    connectionSourceDB = pymysql.connect(host=data["db"]["dbHost"],
                             port=data["py-option"]["dbPort"],
                             user=data["db"]["dbUsername"],
                             password=data["db"]["dbPassword"],
                             db=data["database"]["mapadroid"],
                             charset='utf8mb4',
                             cursorclass=pymysql.cursors.DictCursor)
    return connectionSourceDB

def connect_destdb(): 							 
    connectionDestDB = pymysql.connect(host=data["db"]["dbHost"],
                             port=data["py-option"]["dbPort"],
                             user=data["db"]["dbUsername"],
                             password=data["db"]["dbPassword"],
                             db=data["database"]["stats"],
                             charset='utf8',
                             cursorclass=pymysql.cursors.DictCursor)
    return connectionDestDB						 

def check_status_table_from_sourcedb():
    try:
        connectionSourceDB = connect_sourcedb()
        with connectionSourceDB.cursor() as cursor:
            # Read a single record
            sql = "SELECT `settings_device`.`name` AS `origin`, `trs_status`.`lastProtoDateTime`, `trs_status`.`currentSleepTime` FROM `trs_status` LEFT JOIN `settings_device` ON `trs_status`.`device_id` = `settings_device`.`device_id` WHERE trs_status.instance_id = " + str(instance_id)
            cursor.execute(sql)
            SourceStatusDict = cursor.fetchall()
            #print("Source:")
            #print("---------------------------------------------")        
            #print(SourceStatusDict)
            #print("---------------------------------------------")
    finally:
        connectionSourceDB.close()
        return SourceStatusDict

def calc_past_min_from_now(timedate):
    """ calculate time between now and given timedate """
    actual_time = time.time()
    if timedate == None or timedate == "":
        return 99999
    timedate = datetime.datetime.strptime(str(timedate), '%Y-%m-%d %H:%M:%S').timestamp()
    past_sec_from_now = actual_time - timedate
    past_min_from_now = past_sec_from_now / 60
    past_min_from_now = int(past_min_from_now)
    return past_min_from_now

def check_online_offline_status(lastProtoDateTime,currentSleepTime):
    currentSleepTimeMin = currentSleepTime / 60
    OfflineTimeout = statusOfflineTimeout + currentSleepTimeMin
    if calc_past_min_from_now(lastProtoDateTime) < OfflineTimeout:
        return 1
    else:
        return 0

try:
    while 1:
        now = datetime.datetime.now() # current date and time
        print(now.strftime("%m/%d/%Y, %H:%M:%S"))
        # Create new records
        try:
            connectionDestDB = connect_destdb()
            with connectionDestDB.cursor() as cursor:
                for entry in check_status_table_from_sourcedb():
                    save_data = "INSERT INTO `status`(`createdate`, `origin`, `status`, `time`) VALUES('{}','{}','{}','{}')".format(datetime.date.today(), entry["origin"], check_online_offline_status(entry["lastProtoDateTime"], entry["currentSleepTime"]), entry["lastProtoDateTime"])
                    cursor.execute(save_data)
                    # connection is not autocommit by default. So you must commit to save your changes.
                    connectionDestDB.commit()
        finally:
            print("new records done")
        
    	# delete old entrys
        try:
            with connectionDestDB.cursor() as cursor:
                del_data = "DELETE FROM `status` where `createdate` < CURDATE() - INTERVAL {} DAY;".format(cleanupDbEntryOlderThan)
                cursor.execute(del_data)
                # connection is not autocommit by default. So you must commit to save your changes.
                connectionDestDB.commit()
        finally:
            print("cleanup done")
            connectionDestDB.close()    
        time.sleep(statusInterval)
	
except KeyboardInterrupt:
    pass
    print('QUIT')