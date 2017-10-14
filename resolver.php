<?php
/*
 * Copyright 2017 Shaun Cummiskey, <shaun@shaunc.com> <http://shaunc.com>
 * <https://github.com/parseword/php-multithreaded-resolver>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and 
 * limitations under the License.
 */

error_reporting(E_ALL);
define('DEBUG', true);
define('MAX_THREADS', 16);

//We need pthreads in order to proceed
if (!phpversion('pthreads')) {
    die("This script requires PHP compiled with the pthreads extension.\n");
}

//A function to write debug messages to the console
function debug($message) {
    echo DEBUG ? sprintf("%17.6f", microtime(true)) . ':: ' . $message . "\n" : '';
}

//A class for the worker threads
class ResolverThread extends Thread {
    
    private $id = null;
    protected $host = null;
    
    public function __construct($id, $host) {
        $this->id = $id;
        $this->host = $host;
    }
    
    public function run() {

        if (preg_match('|[a-z]|i', $this->host)) {
            //We got a hostname, resolve to an IP
            if ($result = @dns_get_record($this->host, DNS_A)) {
                echo $this->host . ':' . $result[0]['ip'] . "\n";
                $this->debug("threadId {$this->id}:: {$this->host}:" . $result[0]['ip']);
            }
            else {
                echo $this->host . ":SERVFAIL\n";
                $this->debug("threadId {$this->id}:: {$this->host}:SERVFAIL");
            }
        }
        
        else {
            //Construct in-addr.arpa address and resolve to PTR
            $in_addr = join('.', array_reverse(explode('.', $this->host))) . '.in-addr.arpa';
            if ($result = @dns_get_record($in_addr, DNS_PTR)) {
                echo $this->host . ':' . $result[0]['target'] . "\n";
                $this->debug("threadId {$this->id}:: {$this->host}:" . $in_addr . ':' . $result[0]['target']);
            }
            else {
                echo $this->host . ":SERVFAIL\n";
                $this->debug("threadId {$this->id}:: {$this->host}:" . $in_addr . ":SERVFAIL");
            }
        }
        usleep(100);
    }
    public function getId() {
        return $this->id;
    }
    private function debug($message) {
        echo DEBUG ? sprintf("%17.6f", microtime(true)) . ':: ' . $message . "\n" : '';
    }
}

//Open the list of hosts to resolve
$fp = fopen('hosts.txt', 'r');

$workers = array();
$timeStart = microtime(true);

//Load the workers queue with MAX_THREADS threads
while (@$i++ < MAX_THREADS && !feof($fp)) {
    if (!empty($line = trim(fgets($fp)))) {
        $threadId = substr(md5(microtime(true) . rand(0,999)), 0, 16);
        $workers[$threadId] = new ResolverThread($threadId, $line);
        $workers[$threadId]->start();
    }
}

//Manage the thread queue until there's no work left to do
while (1) {
    usleep(100000);
    $deadThreads = 0;
    foreach ($workers as $thread) {

        //If the thread is still working, skip it
        if ($thread->isRunning()) {
            continue;
        }
        
        //If the thread is completed, join and destroy it
        debug('Calling join() on ' . $thread->getId());
        if ($thread->join()) {
            unset($workers[$thread->getId()]);
            $deadThreads++;
        }
    }
    
    //If no threads were finished, go wait again
    if ($deadThreads == 0)
        continue;
    
    //Refill the thread queue
    debug("Starting $deadThreads threads");
    for ($i=0; $i < $deadThreads && $host = fgets($fp); $i++) {
        $threadId = substr(md5(microtime(true) . rand(0,999)), 0, 16);
        debug('memory_get_usage ' . memory_get_usage());
        debug('workers[] ' . count($workers) . ' deadThreads ' . $deadThreads 
            . " Creating new thread $threadId");
        $workers[$threadId] = new ResolverThread($threadId, trim($host));
        $workers[$threadId]->start();
    }
    
    //Bail when we're out of work to do
    if (feof($fp) && count($workers) == 0) {
        break;
    }
}

echo '#Script took ' . sprintf('%.02f', microtime(true) - $timeStart) . " seconds to execute\n";
