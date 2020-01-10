@echo off
if "%1"=="start" goto start
if "%1"=="stop" goto stop
if "%1"=="restart" goto stop
goto usage

REM Make sure local directory C:\web\VuFind-Plus\sites\default\solr\jetty\logs exists

:start
REM Setup solr configuration
set GC_LOG_OPTS=-verbose:gc -XX:+PrintHeapAtGC -XX:+PrintGCDetails -XX:+PrintGCDateStamps -XX:+PrintGCTimeStamps -XX:+PrintTenuringDistribution -XX:+PrintGCApplicationStoppedTime

set GC_TUNE=-XX:NewRatio=3 ^
 -XX:SurvivorRatio=4 ^
 -XX:TargetSurvivorRatio=90 ^
 -XX:MaxTenuringThreshold=8 ^
 -XX:+UseConcMarkSweepGC ^
 -XX:+UseParNewGC ^
 -XX:ConcGCThreads=4 -XX:ParallelGCThreads=4 ^
 -XX:+CMSScavengeBeforeRemark ^
 -XX:PretenureSizeThreshold=64m ^
 -XX:+UseCMSInitiatingOccupancyOnly ^
 -XX:CMSInitiatingOccupancyFraction=50 ^
 -XX:CMSMaxAbortablePrecleanTime=6000 ^
 -XX:+CMSParallelRemarkEnabled ^
 -XX:+ParallelRefProcEnabled

 set ENABLE_REMOTE_JMX_OPTS=false

REM Start Solr
  REM Start Indexing server
  call ..\default\solr\bin\solr.cmd start -p 8180 -m 4g -s "c:\data\vufind-plus\sacramento.localhost\solr_master" -d "c:\web\VuFind-Plus\sites\default\solr\jetty"
  REM Start Search Server
  call ..\default\solr\bin\solr.cmd start -p 8080 -m 2g -a "-Dsolr.masterport=8180" -s "c:\data\vufind-plus\sacramento.localhost\solr_searcher" -d "c:\web\VuFind-Plus\sites\default\solr\jetty"
goto done

:stop
rem Stop Solr
  REM Stop Master
  call ..\default\solr\bin\solr.cmd stop -p 8180 -s "c:\data\vufind-plus\sacramento.localhost\solr_master" -d "c:\web\VuFind-Plus\sites\default\solr\jetty"
  REM Stop Slave
  call ..\default\solr\bin\solr.cmd stop -p 8080 -s "c:\data\vufind-plus\sacramento.localhost\solr_searcher" -d "c:\web\VuFind-Plus\sites\default\solr\jetty"
if "%1"=="restart" goto start
goto done

:usage
echo Please provide a parameter start or stop to start/stop solr or restart to stop solr and then start it again

:done
